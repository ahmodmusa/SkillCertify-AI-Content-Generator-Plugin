<?php

namespace SC_AI\ContentGenerator\Services\API;

defined( 'ABSPATH' ) || exit;

class CircuitBreaker {
    private int $failure_threshold;
    private int $timeout;
    private array $failures = [];
    private array $last_failure_time = [];

    public function __construct( int $failure_threshold = 3, int $timeout = 300 ) {
        $this->failure_threshold = $failure_threshold;
        $this->timeout = $timeout;
    }

    public function recordFailure( string $provider_name ): void {
        if ( ! isset( $this->failures[ $provider_name ] ) ) {
            $this->failures[ $provider_name ] = 0;
        }
        
        $this->failures[ $provider_name ]++;
        $this->last_failure_time[ $provider_name ] = time();
        
        error_log( "[SC AI] Circuit Breaker: Failure recorded for {$provider_name} ({$this->failures[$provider_name]}/{$this->failure_threshold})" );
    }

    public function recordSuccess( string $provider_name ): void {
        $this->failures[ $provider_name ] = 0;
        unset( $this->last_failure_time[ $provider_name ] );
        
        error_log( "[SC AI] Circuit Breaker: Success recorded for {$provider_name}, failures reset" );
    }

    public function isOpen( string $provider_name ): bool {
        if ( ! isset( $this->failures[ $provider_name ] ) ) {
            return false;
        }

        if ( $this->failures[ $provider_name ] < $this->failure_threshold ) {
            return false;
        }

        // Check if timeout has passed
        if ( isset( $this->last_failure_time[ $provider_name ] ) ) {
            $time_since_failure = time() - $this->last_failure_time[ $provider_name ];
            if ( $time_since_failure > $this->timeout ) {
                // Reset after timeout
                $this->failures[ $provider_name ] = 0;
                unset( $this->last_failure_time[ $provider_name ] );
                error_log( "[SC AI] Circuit Breaker: Timeout passed for {$provider_name}, circuit closed" );
                return false;
            }
        }

        return true;
    }

    public function getFailureCount( string $provider_name ): int {
        return $this->failures[ $provider_name ] ?? 0;
    }

    public function reset( string $provider_name ): void {
        $this->failures[ $provider_name ] = 0;
        unset( $this->last_failure_time[ $provider_name ] );
        error_log( "[SC AI] Circuit Breaker: Manually reset for {$provider_name}" );
    }
}
