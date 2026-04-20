<?php

namespace SC_AI\ContentGenerator\Services;

defined( 'ABSPATH' ) || exit;

class UsageTracker {
    private const OPTION_PREFIX = 'sc_ai_usage_';
    private const GROQ_RATE_LIMIT = 30; // requests per minute
    private const WINDOW_SECONDS = 60;

    /**
     * Record an API request for a provider
     */
    public function recordRequest( string $provider, int $code = 200 ): void {
        error_log( "[SC AI USAGE] Recording request for {$provider}, code: {$code}" );
        $key = self::OPTION_PREFIX . $provider;
        $data = get_option( $key, [
            'requests' => [],
            'total_requests' => 0,
            'last_reset' => time(),
        ] );

        // Clean old requests outside the window
        $now = time();
        $data['requests'] = array_filter( $data['requests'], function( $timestamp ) use ( $now ) {
            return ( $now - $timestamp ) < self::WINDOW_SECONDS;
        } );

        // Add new request
        $data['requests'][] = $now;
        $data['total_requests']++;
        $data['last_request'] = $now;
        $data['last_code'] = $code;

        update_option( $key, $data );
        error_log( "[SC AI USAGE] Saved {$provider} usage - total: {$data['total_requests']}, in window: " . count( $data['requests'] ) );
    }

    /**
     * Get usage data for a provider
     */
    public function getUsage( string $provider ): array {
        $key = self::OPTION_PREFIX . $provider;
        $data = get_option( $key, [
            'requests' => [],
            'total_requests' => 0,
            'last_reset' => time(),
        ] );

        // Clean old requests
        $now = time();
        $data['requests'] = array_filter( $data['requests'], function( $timestamp ) use ( $now ) {
            return ( $now - $timestamp ) < self::WINDOW_SECONDS;
        } );

        $request_count = count( $data['requests'] );
        $remaining = max( 0, self::GROQ_RATE_LIMIT - $request_count );
        $reset_time = $data['requests'] ? min( $data['requests'] ) + self::WINDOW_SECONDS : $now;
        $seconds_until_reset = max( 0, $reset_time - $now );

        return [
            'requests_in_window' => $request_count,
            'remaining_requests' => $remaining,
            'total_requests' => $data['total_requests'],
            'last_request' => $data['last_request'] ?? null,
            'last_code' => $data['last_code'] ?? null,
            'reset_in_seconds' => $seconds_until_reset,
            'reset_time' => date( 'H:i:s', $reset_time ),
        ];
    }

    /**
     * Get all provider usage data
     */
    public function getAllUsage( $providers = [] ): array {
        $usage = [
            'groq' => $this->getUsage( 'groq' ),
            'openrouter' => $this->getUsage( 'openrouter' ),
        ];

        // Fetch real quota from providers if available
        foreach ( $providers as $name => $provider ) {
            if ( method_exists( $provider, 'getQuota' ) ) {
                $quota = $provider->getQuota();
                if ( ! isset( $quota['error'] ) ) {
                    $usage[ $name ]['quota'] = $quota;
                }
            }
        }

        return $usage;
    }

    /**
     * Reset usage data for a provider
     */
    public function resetUsage( string $provider ): void {
        $key = self::OPTION_PREFIX . $provider;
        delete_option( $key );
    }

    /**
     * Check if provider is rate limited based on usage
     */
    public function isRateLimited( string $provider ): bool {
        $usage = $this->getUsage( $provider );
        return $usage['remaining_requests'] <= 0;
    }
}
