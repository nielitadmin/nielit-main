<?php
/**
 * Copy to portal_assistant.local.php and set your Gemini or OpenAI key.
 * portal_assistant.local.php is not committed — keep API keys off git.
 * You can also configure keys in Master Admin -> API & Automation.
 */
return [
    // 'auto' checks DB/n8n/Gemini/OpenAI automatically, or force 'gemini' | 'openai' | 'n8n' | 'local'
    'ai_provider' => 'auto',

    // Google Gemini API (recommended)
    'use_gemini' => false,
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-2.0-flash',

    // OpenAI API (optional)
    'use_openai' => false,
    'openai_api_key' => '',
    'openai_model' => 'gpt-4o-mini',
];
