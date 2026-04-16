<?php

namespace SC_AI\ContentGenerator\Models;

defined( 'ABSPATH' ) || exit;

class ApiConfig {
    public string $primary_provider;
    public string $fallback_provider;
    public string $groq_key;
    public string $openrouter_key;
    public string $groq_model;
    public string $openrouter_model;
    public bool $enable_draft_queue;
    public int $draft_batch_size;
    public int $final_batch_size;
    public string $draft_cron_interval;
    public string $final_cron_time;
    public bool $enable_cron;

    public function __construct( array $config = [] ) {
        $this->primary_provider = $config['primary_provider'] ?? 'groq';
        $this->fallback_provider = $config['fallback_provider'] ?? 'openrouter';
        $this->groq_key = $config['groq_key'] ?? '';
        $this->openrouter_key = $config['openrouter_key'] ?? '';
        $this->groq_model = $config['groq_model'] ?? 'llama-3.1-8b-instant';
        $this->openrouter_model = $config['openrouter_model'] ?? 'openai/gpt-3.5-turbo';
        $this->enable_draft_queue = ($config['enable_draft_queue'] ?? '1') === '1';
        $this->draft_batch_size = (int) ($config['draft_batch_size'] ?? 25);
        $this->final_batch_size = (int) ($config['final_batch_size'] ?? 20);
        $this->draft_cron_interval = $config['draft_cron_interval'] ?? 'hourly';
        $this->final_cron_time = $config['final_cron_time'] ?? '04:00';
        $this->enable_cron = ($config['enable_cron'] ?? '1') === '1';
    }

    public static function fromOptions(): self {
        return new self( [
            'primary_provider' => get_option( 'sc_ai_primary_provider', 'groq' ),
            'fallback_provider' => get_option( 'sc_ai_fallback_provider', 'openrouter' ),
            'groq_key' => get_option( 'sc_ai_groq_key', '' ),
            'openrouter_key' => get_option( 'sc_ai_openrouter_key', '' ),
            'groq_model' => get_option( 'sc_ai_groq_model', 'llama-3.1-8b-instant' ),
            'openrouter_model' => get_option( 'sc_ai_openrouter_model', 'openai/gpt-3.5-turbo' ),
            'enable_draft_queue' => get_option( 'sc_ai_enable_draft_queue', '1' ),
            'draft_batch_size' => get_option( 'sc_ai_draft_batch_size', 25 ),
            'final_batch_size' => get_option( 'sc_ai_final_batch_size', 20 ),
            'draft_cron_interval' => get_option( 'sc_ai_draft_cron_interval', 'hourly' ),
            'final_cron_time' => get_option( 'sc_ai_final_cron_time', '04:00' ),
            'enable_cron' => get_option( 'sc_ai_enable_cron', '1' ),
        ] );
    }

    public function toArray(): array {
        return [
            'primary_provider' => $this->primary_provider,
            'fallback_provider' => $this->fallback_provider,
            'groq_key' => $this->groq_key,
            'openrouter_key' => $this->openrouter_key,
            'groq_model' => $this->groq_model,
            'openrouter_model' => $this->openrouter_model,
            'enable_draft_queue' => $this->enable_draft_queue ? '1' : '0',
            'draft_batch_size' => $this->draft_batch_size,
            'final_batch_size' => $this->final_batch_size,
            'draft_cron_interval' => $this->draft_cron_interval,
            'final_cron_time' => $this->final_cron_time,
            'enable_cron' => $this->enable_cron ? '1' : '0',
        ];
    }
}
