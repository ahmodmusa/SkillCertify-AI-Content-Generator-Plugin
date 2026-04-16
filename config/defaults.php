<?php

defined( 'ABSPATH' ) || exit;

return [
    // API Settings
    'primary_provider' => 'groq',
    'fallback_provider' => 'openrouter',
    
    // API Keys
    'groq_key' => '',
    'openrouter_key' => '',
    
    // Model Settings
    'groq_model' => 'llama-3.1-8b-instant',
    'openrouter_model' => 'openai/gpt-3.5-turbo',
    
    // Queue Settings
    'enable_draft_queue' => '0', // 1 = use draft queue, 0 = direct publish (default changed to 0)
    'draft_batch_size' => 25,
    'final_batch_size' => 20,
    'draft_cron_interval' => 'hourly',
    'final_cron_time' => '04:00',
    'enable_cron' => '1',
    
    // Retry Settings
    'max_attempts' => 3,
    'retry_delay' => 60, // seconds
    
    // Rate Limit Settings
    'rate_limit_groq' => 2,
    'rate_limit_openrouter' => 4,
];
