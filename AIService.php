<?php

use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;
use Google\Client as Google_Client;
use Google\Service\CustomSearchAPI as CustomSearch;

class AIService
{
    private $apiKey;
    private $searchEngineId;
    private $customSearchService;
    private $emulatorEnabled=false;
    public $geminiApiUrl;

    public function __construct()
    {
        $this->loadApiKeys();
        //$this->geminiApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->apiKey;
        $this->geminiApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $this->apiKey;
        $this->emulatorEnabled = is_development();
    }

    private function loadApiKeys()
    {
        // Logic to load real API keys, remains unchanged...
        $secretData = [];
        $serviceAccountJson = null;

        try {
            $secretClient = new SecretManagerServiceClient();
            $projectId = getenv('GOOGLE_CLOUD_PROJECT') ?: 'mediabrain';

            // Fetch Gemini API Key JSON from Secret Manager
            $geminiSecretName = 'gemini-api-key';
            $geminiSecretVersion = 'latest';
            $geminiName = "projects/$projectId/secrets/$geminiSecretName/versions/$geminiSecretVersion";
            $geminiRequest = new AccessSecretVersionRequest(['name' => $geminiName]);
            $geminiResponse = $secretClient->accessSecretVersion($geminiRequest);
            $payload = $geminiResponse->getPayload()->getData();
            $secretData = json_decode($payload, true);
            $this->log("AIService: Successfully loaded gemini-api-key from Secret Manager.");

            // Fetch Service Account JSON from Secret Manager for Google Search
            $saSecretName = 'service-account-key';
            $saSecretVersion = 'latest';
            $saName = "projects/$projectId/secrets/$saSecretName/versions/$saSecretVersion";
            $saRequest = new AccessSecretVersionRequest(['name' => $saName]);
            $saResponse = $secretClient->accessSecretVersion($saRequest);
            $serviceAccountJson = $saResponse->getPayload()->getData();
            $this->log("AIService: Successfully loaded service account from Secret Manager.");

        } catch (\Exception $e) {
            $this->log("AIService: WARNING - Could not fetch secrets from Secret Manager: " . $e->getMessage() . ". Falling back to environment variables.");
        }

        $this->apiKey = $secretData['api_key'] ?? getenv('GEMINI_API_KEY') ?: 'YOUR_API_KEY';
        $searchApiKey = $secretData['search_api_key'] ?? getenv('SEARCH_API_KEY') ?: 'YOUR_SEARCH_API_KEY';
        $this->searchEngineId = $secretData['search_engine_id'] ?? getenv('SEARCH_ENGINE_ID') ?: 'YOUR_SEARCH_ENGINE_ID';

        $googleClient = new Google_Client();
        $googleClient->setApplicationName("MB App Client");

        if ($serviceAccountJson) {
            $authConfig = json_decode($serviceAccountJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $googleClient->setAuthConfig($authConfig);
                $googleClient->addScope('https://www.googleapis.com/auth/cse');
                $this->log("AIService: Google Client initialized with Service Account JSON for Custom Search.");
            } else {
                $this->log("AIService: WARNING: Failed to decode service account JSON. Falling back to API Key.");
                $googleClient->setDeveloperKey($searchApiKey);
            }
        } else {
            $this->log("AIService: WARNING: Service Account key not found. Falling back to API Key for search.");
            $googleClient->setDeveloperKey($searchApiKey);
        }
        
        $this->customSearchService = new CustomSearch($googleClient);
    }

    public function createResearchPlan($prompt)
    {
        if ($this->emulatorEnabled) {
            return $this->_getEmulatedResponse('create_plan', $prompt);
        }

        if ($this->apiKey === 'YOUR_API_KEY') {
            return ['error' => "Gemini API key not configured."];
        }
        $this->log("Creating research plan for prompt: - {$prompt}");

        $planPrompt = "Create a research plan for the query: '{$prompt}'. The plan should be a JSON object with a single key 'sections', which is an array of objects. Each object in the array should have two keys: 'name' (a string for the section title) and 'search_query' (a string for a concise search engine query). Respond with ONLY the JSON object, without any surrounding text or markdown formatting.";
        $data = ['contents' => [['parts' => [['text' => $planPrompt]]]]];
        $response = $this->makeApiCall($this->geminiApiUrl, $data);

        // ... existing response handling
        if (isset($response['error'])) {
            return [
                'error' => "Error creating research plan: " . $response['error'],
                'planPrompt' => $planPrompt
            ];
        }
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $responseText = $response['candidates'][0]['content']['parts'][0]['text'];
            
            // Find the JSON block in the response
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $responseText, $matches)) {
                $jsonText = $matches[1];
            } else {
                $jsonText = $responseText;
            }

            $plan = json_decode(trim($jsonText), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($plan['sections'])) 
            {
                return [
                    'plan' => $plan, 
                    'planPrompt' => $planPrompt, 
                    'usage' => $response['usageMetadata'] ?? ['totalTokenCount' => 0]
                ];
            }
            $this->log("Failed to parse research plan. Response text: " . $responseText);
            return ['error' => "Could not parse research plan from Gemini response."];
        }
        return [
            'error' => "Could not generate research plan from Gemini.",
            'planPrompt' => $planPrompt
        ];
    }

    public function generateReportFromSearchResults($prompt, $plan) {
        if ($this->emulatorEnabled) {
            return $this->_getEmulatedResponse('generate_report', $prompt);
        }

        if ($this->apiKey === 'YOUR_API_KEY') {
            return ['error' => "Gemini API key not configured."];
        }
        $this->log("Generating report from search results for prompt: - {$prompt}");

        $searchContext = "";
        $searchSummaryForUI = [];
        foreach ($plan['sections'] as $section) {
            $query = $section['search_query'];
            $searchResults = $this->performWebSearch($query);
            if (is_array($searchResults)) {
                $searchContext .= "Research for section '{$section['name']}':\n";
                foreach(array_slice($searchResults, 0, 3) as $item) {
                    $searchContext .= "Title: " . ($item['title'] ?? '') . "\nSnippet: " . ($item['snippet'] ?? '') . "\nSource: " . ($item['link'] ?? '') . "\n\n";
                }
                $searchSummaryForUI[] = "Finished searching for '{$query}'";
            }
        }

        $sectionNames = array_map(function($section) {
            return $section['name'];
        }, $plan['sections']);

        $reportPrompt = "You are a senior research assistant tasked with generating a comprehensive report in Markdown format based on web search snippets. The topic is '{$prompt}'.

The report MUST be structured into the following sections, using the exact titles provided:
- " . implode("\n- ", $sectionNames) . "

Crucially, before each Markdown heading for a section, you MUST insert an HTML anchor tag. The 'name' attribute of the anchor must be a 'slug' of the section title (all lowercase, spaces replaced with hyphens, and special characters removed).

For example, for a section titled 'Key Aspects of Topic', the final output in the report must be formatted exactly like this:
<a name=\"key-aspects-of-topic\"></a>
## Key Aspects of Topic
... content for the section ...

Follow this instruction for all sections.

--- WEB SEARCH SNIPPETS ---
" . $searchContext . "
--- END OF SNIPPETS ---

Now, generate the full report following these instructions precisely.";

        $data = ['contents' => [['parts' => [['text' => $reportPrompt]]]]];

        $response = $this->makeApiCall($this->geminiApiUrl, $data);

        if (isset($response['error'])) {
            return ['error' => "Error generating final report: " . $response['error']];
        }
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'report' => $response['candidates'][0]['content']['parts'][0]['text'],
                'usage' => $response['usageMetadata'] ?? ['totalTokenCount' => 0],
                'intermediate_summary' => implode("\n", $searchSummaryForUI)
            ];
        }
        return ['error' => "Could not generate final report from search results."];
    }

    private function _getEmulatedResponse($dev_action, $prompt) {
        $this->log("Generating emulated response for action '{$dev_action}'");
        sleep(rand(1, 2)); // Simulate network latency

        if ($dev_action === 'create_plan') {
            return [
                'plan' => [
                    'sections' => [
                        ['name' => "Emulated Intro to {$prompt}", 'search_query' => "intro to {$prompt}"],
                        ['name' => "Emulated Key Aspects of {$prompt}", 'search_query' => "key aspects of {$prompt}"],
                        ['name' => "Emulated Future of {$prompt}", 'search_query' => "future of {$prompt}"],
                    ]
                ],
                'usage' => ['totalTokenCount' => 123]
            ];
        }
        
        if ($dev_action === 'generate_report') {
            $report_content = "# Emulated Report for: The significance of digital literacy in the 21st century.\n\n<h1>HTML Ipsum Presents</h1><p><strong>Pellentesque habitant morbi tristique</strong> senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. <em>Aenean ultricies mi vitae est.</em> Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed, <code>commodo vitae</code>, ornare sit amet, wisi. Aenean fermentum, elit eget tincidunt condimentum, eros ipsum rutrum orci, sagittis tempus lacus enim ac dui. <a href=\"#\" class=\"underline\">Donec non enim</a> in turpis pulvinar facilisis. Ut felis.</p><h2>Header Level 2</h2><ol><li>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li>Aliquam tincidunt mauris eu risus.</li></ol><blockquote><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus magna. Cras in mi at felis aliquet congue. Ut a est eget ligula molestie gravida. Curabitur massa. Donec eleifend, libero at sagittis mollis, tellus est malesuada tellus, at luctus turpis elit sit amet quam. Vivamus pretium ornare est.</p></blockquote><h3>Header Level 3</h3><ul><li>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</li><li>Aliquam tincidunt mauris eu risus.</li></ul><pre><code>#header h1 a {display: block;width: 300px;height: 80px;}</code></pre>";
            return [
                'report' => $report_content,
                'usage' => ['totalTokenCount' => 456],
                'intermediate_summary' => "Finished searching for 'intro to {$prompt}'\nFinished searching for 'key aspects of {$prompt}'"
            ];
        }

        return ['error' => 'Unknown dev_action specified for emulator'];
    }
    
    private function performWebSearch($query)
    {
        // ... existing implementation
        if (empty($this->searchEngineId) || $this->searchEngineId === 'YOUR_SEARCH_ENGINE_ID') {
            return "Web search is not configured.";
        }
        try {
            $optParams = ['cx' => $this->searchEngineId, 'q' => $query];
            $results = $this->customSearchService->cse->listCse($optParams);
            return $results->getItems() ?? [];
        } catch (\Exception $e) {
            $this->log("Web search Exception: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    private function makeApiCall($url, $data) {
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Insecure, but useful for local dev

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['error' => $error]; // Hard curl error, don't retry
            }

            $result = json_decode($response, true);

            // Check for retriable errors
            $isOverloaded = isset($result['error']['message']) && strpos($result['error']['message'], 'overloaded') !== false;
            $isRateLimited = $httpCode === 429;
            $isServiceUnavailable = $httpCode === 503;

            if (($isOverloaded || $isRateLimited || $isServiceUnavailable) && $attempt < $maxRetries) {
                $this->log("API is busy (HTTP {$httpCode}). Retrying in {$retryDelay} seconds... (Attempt {$attempt}/{$maxRetries})");
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
                continue;
            }

            // If not a retriable error, or if max retries are exhausted, return the result.
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Failed to decode JSON response from API.'];
            }
            
            if (isset($result['error']['message'])) {
                $errorMessage = $result['error']['message'];
                // Check for specific quota error
                if (strpos($errorMessage, 'quota') !== false) {
                    return ['error' => 'QUOTA_EXCEEDED'];
                }

                // After all retries, return the final error
                if ($isOverloaded) {
                    return ['error' => 'The model is overloaded. Please try again later.'];
                }
                return ['error' => $errorMessage];
            }
            
            return $result; // Success
        }
        
        return ['error' => 'API call failed after multiple retries.']; // Should not be reached, but as a fallback
    }

    private function log($message)
    {
        error_log("[AIService] " . $message);
    }
}
