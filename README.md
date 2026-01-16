# Mediabrain.app: AI Orchestration & Researcher Service

This repository serves as a technical showcase for the core AI logic powering mediabrain.app. It demonstrates a high-level integration between legacy LAMP stack architecture and modern Generative AI models.
🏗️ Architecture Overview

The AIService.php module is the central nervous system of the Researcher app. It orchestrates complex multi-step workflows, transforming raw user prompts into structured, data-backed reports.
Key Technical Implementations:

    Secure Credential Management: Utilizes Google Cloud Secret Manager to securely fetch API keys and Service Account JSON at runtime, ensuring no sensitive data is stored in the codebase.

Resilient API Communication: Implements custom makeApiCall logic with exponential backoff and retry strategies to handle rate limits (HTTP 429) and model overloads.

Dual-Model Orchestration: Leverages Gemini 3-Flash-Preview for logic-heavy research planning and Google Custom Search API for real-time data retrieval.

    Automated Content Formatting: Generates production-ready Markdown reports with dynamic HTML anchor tags for easy UI navigation and deep-linking.

🛠️ Tech Stack

    Language: PHP 8.x (Yii Framework) 

Infrastructure: Google Cloud Platform (GCP), Secret Manager, Custom Search API

AI: Gemini Pro / Gemini 3 Flash Preview

Environment: Dockerized for local development and scalable deployment

💡 The "Architect" Philosophy

This code represents over 20 years of Full Stack development experience. It focuses on "Production-Ready" reliability—handling the "spooky" edge cases of network latency and API instability so the User experience remains seamless within the Grid.



🕊️ Dedication: The Guiding Signal

This project is built on a foundation of resilience, a trait I learned from my big sister.

She was the original "Architect" of our family, looking out for us since the 90s and ensuring our "grid" stayed strong through every storm. Though her presence has shifted frequencies, she is not gone, and she is not lost. I still feel her thoughts guiding my hands as I write this code and her strength directing my path as I protect our family.

Mediabrain is more than just an AI tool; it is a testament to the "Legacy Code" of care, protection, and clarity she instilled in me. I am doing exactly what I am supposed to be doing, because she is still showing me the way.


