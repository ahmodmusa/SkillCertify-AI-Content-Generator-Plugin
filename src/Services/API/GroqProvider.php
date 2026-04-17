<?php

namespace SC_AI\ContentGenerator\Services\API;

defined( 'ABSPATH' ) || exit;

class GroqProvider implements ApiProviderInterface {
    private string $api_key;
    private string $model;
    private int $timeout;

    public function __construct( string $api_key, string $model = 'llama-3.1-8b-instant' ) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->timeout = 30;
    }

    public function generate( string $prompt ): string|false {
        if ( empty( $this->api_key ) ) {
            error_log( '[SC AI] Groq API key not set' );
            return false;
        }

        $start_time = microtime( true );
        $timestamp = current_time( 'mysql' );

        $max_tokens = intval( get_option( 'sc_ai_groq_max_tokens', 4000 ) );

        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => wp_json_encode( [
                'model'       => $this->model,
                'messages'    => [ [
                    'role'    => 'user',
                    'content' => $prompt,
                ] ],
                'temperature' => 0.3,
                'max_tokens'  => $max_tokens,
            ] ),
            'timeout' => $this->timeout,
        ] );

        $duration = round( microtime( true ) - $start_time, 2 );

        if ( is_wp_error( $response ) ) {
            error_log( '[SC AI] Groq error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_headers = wp_remote_retrieve_headers( $response );

        error_log( '[SC AI PERF] Groq API | Time: ' . $timestamp . ' | Code: ' . $code . ' | Duration: ' . $duration . 's' );

        if ( $code === 429 ) {
            error_log( '[SC AI] Groq rate limit hit' );
            return 'RATE_LIMITED';
        }

        if ( $code !== 200 ) {
            error_log( '[SC AI] Groq HTTP ' . $code . ': ' . $response_body );
            return false;
        }

        $data = json_decode( $response_body, true );
        return $data['choices'][0]['message']['content'] ?? false;
    }

    public function testConnection(): array {
        if ( empty( $this->api_key ) ) {
            return [ 'status' => 'not_configured', 'error' => 'API key not set' ];
        }

        if ( ! preg_match( '/^gsk_[A-Za-z0-9]{40,60}$/', $this->api_key ) ) {
            return [ 'status' => 'failed', 'error' => 'Invalid key format' ];
        }

        $result = $this->generate( 'Test' );
        
        if ( $result === 'RATE_LIMITED' ) {
            return [ 'status' => 'rate_limited', 'error' => 'Rate limit exceeded' ];
        }

        if ( $result !== false ) {
            return [ 'status' => 'success', 'error' => '' ];
        }

        return [ 'status' => 'failed', 'error' => 'Connection failed' ];
    }

    public function getName(): string {
        return 'groq';
    }

    public function getRateLimit(): int {
        return SC_AI_RATE_LIMIT_GROQ;
    }

    public function isEnabled(): bool {
        return ! empty( $this->api_key );
    }
}
