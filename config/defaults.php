<?php

defined( 'ABSPATH' ) || exit;

return [
    // API Settings
    'primary_provider' => 'groq',
    'fallback_provider' => 'openrouter',
    'batch_provider' => 'groq',
    
    // API Keys
    'groq_key' => '',
    'openrouter_key' => '',
    
    // Model Settings
    'groq_model' => 'llama-3.3-70b-versatile',
    'groq_max_tokens' => 4000,
    'groq_batch_model' => 'llama-3.1-8b-instant',
    'openrouter_model' => 'openai/gpt-3.5-turbo',
    'openrouter_max_tokens' => 500,
    
    // Queue Settings
    'final_batch_size' => 20,
    'manual_batch_size' => 5,
    'final_cron_time' => '04:00',
    'enable_cron' => '1',
    
    // Retry Settings
    'max_attempts' => 3,
    'retry_delay' => 60, // seconds
    
    // Rate Limit Settings
    'rate_limit_groq' => 2,
    'rate_limit_openrouter' => 4,
];
