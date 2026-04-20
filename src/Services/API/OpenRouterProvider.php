<?php

namespace SC_AI\ContentGenerator\Services\API;

defined( 'ABSPATH' ) || exit;

class OpenRouterProvider implements ApiProviderInterface {
    private string $api_key;
    private string $model;
    private int $timeout;
    private int $max_tokens;
    private $usage_tracker;

    public function __construct( string $api_key, string $model = 'openai/gpt-3.5-turbo', int $max_tokens = 4000, $usage_tracker = null ) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->timeout = 30;
        $this->max_tokens = $max_tokens;
        $this->usage_tracker = $usage_tracker;
    }

    public function generate( string $prompt ): string|false {
        if ( empty( $this->api_key ) ) {
            error_log( '[SC AI] OpenRouter API key not set' );
            return false;
        }

        $start_time = microtime( true );
        $timestamp = current_time( 'mysql' );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'HTTP-Referer'  => site_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ],
            'body'    => wp_json_encode( [
                'model'       => $this->model,
                'messages'    => [ [
                    'role'    => 'user',
                    'content' => $prompt,
                ] ],
                'temperature' => 0.3,
                'max_tokens'  => $this->max_tokens,
            ] ),
            'timeout' => $this->timeout,
        ] );

        $duration = round( microtime( true ) - $start_time, 2 );

        if ( is_wp_error( $response ) ) {
            error_log( '[SC AI] OpenRouter error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_headers = wp_remote_retrieve_headers( $response );

        error_log( '[SC AI PERF] OpenRouter API | Time: ' . $timestamp . ' | Code: ' . $code . ' | Duration: ' . $duration . 's' );

        // Track usage
        if ( $this->usage_tracker ) {
            $this->usage_tracker->recordRequest( 'openrouter', $code );
        }

        if ( $code === 429 ) {
            error_log( '[SC AI] OpenRouter rate limit hit' );
            return 'RATE_LIMITED';
        }

        if ( $code === 402 ) {
            error_log( '[SC AI] OpenRouter insufficient credits' );
            return 'INSUFFICIENT_CREDITS';
        }

        if ( $code !== 200 ) {
            error_log( '[SC AI] OpenRouter HTTP ' . $code . ': ' . $response_body );
            return false;
        }

        $data = json_decode( $response_body, true );
        return $data['choices'][0]['message']['content'] ?? false;
    }

    public function testConnection(): array {
        if ( empty( $this->api_key ) ) {
            return [ 'status' => 'not_configured', 'error' => 'API key not set' ];
        }

        // Skip regex validation - test the actual connection instead
        $result = $this->generate( 'Test' );

        if ( $result === 'RATE_LIMITED' ) {
            return [ 'status' => 'rate_limited', 'error' => 'Rate limit exceeded' ];
        }

        if ( $result === 'INSUFFICIENT_CREDITS' ) {
            return [ 'status' => 'insufficient_credits', 'error' => 'Insufficient credits - Add credits at https://openrouter.ai/settings/credits' ];
        }

        if ( $result !== false ) {
            return [ 'status' => 'success', 'error' => '' ];
        }

        return [ 'status' => 'failed', 'error' => 'Connection failed' ];
    }

    public function getName(): string {
        return 'openrouter';
    }

    public function getRateLimit(): int {
        return SC_AI_RATE_LIMIT_OPENROUTER;
    }

    public function isEnabled(): bool {
        return ! empty( $this->api_key );
    }

    public function getQuota(): array {
        error_log( '[SC AI QUOTA] Fetching OpenRouter quota...' );
        if ( empty( $this->api_key ) ) {
            error_log( '[SC AI QUOTA] API key not set' );
            return [ 'error' => 'API key not set' ];
        }

        $response = wp_remote_get( 'https://openrouter.ai/api/v1/auth/key', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[SC AI QUOTA] WP Error: ' . $response->get_error_message() );
            return [ 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        error_log( "[SC AI QUOTA] Response code: {$code}, body: {$body}" );

        if ( $code !== 200 ) {
            error_log( "[SC AI QUOTA] HTTP {$code}: {$body}" );
            return [ 'error' => "HTTP {$code}: {$body}" ];
        }

        $data = json_decode( $body, true );
        $quota = [
            'usage' => $data['data']['usage'] ?? null,
            'usage_monthly' => $data['data']['usage_monthly'] ?? null,
            'is_free_tier' => $data['data']['is_free_tier'] ?? true,
        ];
        error_log( '[SC AI QUOTA] Success: ' . json_encode( $quota ) );
        return $quota;
    }
}
