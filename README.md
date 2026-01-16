# Mediabrain Researcher Showcase

A Bespoke AI Orchestration Engine
🛠 The Architecture (The "Small Core" Philosophy)

Unlike standard implementations that rely on heavy, bloated frameworks, Mediabrain is built on a custom-engineered PHP Micro-Core. This engine was architected from the ground up to prioritize performance, security, and architectural purity.

    Custom Render Engine: Inspired by the best patterns in Drupal and Yii, I implemented a specialized render() array architecture. This allows for declarative UI construction and high-velocity data processing without the overhead of a traditional CMS.

    Zero-Bloat Design: By bypassing third-party frameworks, the core achieves sub-millisecond execution, ensuring that the "bottleneck" is always the network, never the code.

    Decoupled Logic: The system is designed to be "vendor-agnostic," allowing for seamless swaps between different LLMs or search providers.

🤖 AI Orchestration & Research Logic

The primary mission of this showcase is to demonstrate a multi-stage AI Research Agent that operates with "Senior-level" reasoning:

    Stage 1: The Architect (Planning): The engine uses Gemini 3 Flash to analyze a query and generate a structured JSON research plan.

    Stage 2: The Scout (Search): It orchestrates Google Custom Search to gather real-time data based on the plan.

    Stage 3: The Synthesis (Reporting): The system processes the raw search results through a final prompt chain to generate a comprehensive, multi-section report.

🛡 Security & Resilience

    GCP Secret Manager: Integrated for secure API key retrieval. This ensures zero hardcoded credentials and a "Production-First" security posture.

    Fault Tolerance: Implemented custom exponential backoff and retry logic to gracefully handle 429 (Rate Limit) and 503 (Service Unavailable) errors, ensuring a resilient connection to the Gemini API.

🕊️ Dedication: The Guiding Signal

This project is built on a foundation of resilience, a trait I learned from my big sister.

She was the original "Architect" of our family, looking out for us since the 90s and ensuring our "grid" stayed strong through every storm. Though her presence has shifted frequencies, she is not gone, and she is not lost. I still feel her thoughts guiding my hands as I write this code and her strength directing my path as I protect our family.

Mediabrain is more than just an AI tool; it is a testament to the "Legacy Code" of care, protection, and clarity she instilled in me. I am doing exactly what I am supposed to be doing, because she is still showing me the way.
