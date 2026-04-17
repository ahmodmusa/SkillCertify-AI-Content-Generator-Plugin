<?php

namespace SC_AI\ContentGenerator\Services\API;

defined( 'ABSPATH' ) || exit;

class ProviderPool {
    private array $providers = [];
    private CircuitBreaker $circuit_breaker;
    private string $primary_provider;
    private ?string $fallback_provider;

    public function __construct(
        array $providers,
        CircuitBreaker $circuit_breaker,
        string $primary_provider = 'groq',
        ?string $fallback_provider = null
    ) {
        $this->providers = $providers;
        $this->circuit_breaker = $circuit_breaker;
        $this->primary_provider = $primary_provider;
        $this->fallback_provider = $fallback_provider;
    }

    /**
     * Generate content using available providers
     *
     * @param string $prompt The prompt to send to the AI
     * @return array|false Array with 'content' and 'provider_used' keys, or false on failure
     */
    public function generate( string $prompt ): array|false {
        // Try primary provider first
        if ( isset( $this->providers[ $this->primary_provider ] ) ) {
            $provider = $this->providers[ $this->primary_provider ];

            if ( ! $this->circuit_breaker->isOpen( $provider->getName() ) ) {
                error_log( "[SC AI] Trying primary provider: {$provider->getName()}" );
                $result = $provider->generate( $prompt );

                if ( $result !== false && $result !== 'RATE_LIMITED' ) {
                    $this->circuit_breaker->recordSuccess( $provider->getName() );
                    return [
                        'content' => $result,
                        'provider_used' => $provider->getName(),
                    ];
                }

                if ( $result === 'RATE_LIMITED' ) {
                    error_log( "[SC AI] Primary provider rate limited" );
                } else {
                    $this->circuit_breaker->recordFailure( $provider->getName() );
                }
            } else {
                error_log( "[SC AI] Primary provider circuit open, skipping" );
            }
        }

        // Try fallback provider
        if ( $this->fallback_provider && isset( $this->providers[ $this->fallback_provider ] ) ) {
            $provider = $this->providers[ $this->fallback_provider ];

            if ( ! $this->circuit_breaker->isOpen( $provider->getName() ) ) {
                error_log( "[SC AI] Trying fallback provider: {$provider->getName()}" );
                $result = $provider->generate( $prompt );

                if ( $result !== false && $result !== 'RATE_LIMITED' ) {
                    $this->circuit_breaker->recordSuccess( $provider->getName() );
                    return [
                        'content' => $result,
                        'provider_used' => $provider->getName(),
                    ];
                }

                if ( $result === 'RATE_LIMITED' ) {
                    error_log( "[SC AI] Fallback provider rate limited" );
                } else {
                    $this->circuit_breaker->recordFailure( $provider->getName() );
                }
            } else {
                error_log( "[SC AI] Fallback provider circuit open, skipping" );
            }
        }

        // Try other available providers
        foreach ( $this->providers as $name => $provider ) {
            if ( $name === $this->primary_provider || $name === $this->fallback_provider ) {
                continue;
            }

            if ( ! $this->circuit_breaker->isOpen( $provider->getName() ) ) {
                error_log( "[SC AI] Trying alternative provider: {$provider->getName()}" );
                $result = $provider->generate( $prompt );

                if ( $result !== false && $result !== 'RATE_LIMITED' ) {
                    $this->circuit_breaker->recordSuccess( $provider->getName() );
                    return [
                        'content' => $result,
                        'provider_used' => $provider->getName(),
                    ];
                }

                $this->circuit_breaker->recordFailure( $provider->getName() );
            }
        }

        error_log( '[SC AI] All providers failed' );
        return false;
    }

    public function testAll(): array {
        $results = [];
        
        foreach ( $this->providers as $name => $provider ) {
            $results[ $name ] = $provider->testConnection();
        }
        
        return $results;
    }

    public function getAvailableProviders(): array {
        $available = [];
        
        foreach ( $this->providers as $name => $provider ) {
            if ( $provider->isEnabled() && ! $this->circuit_breaker->isOpen( $name ) ) {
                $available[ $name ] = $provider;
            }
        }
        
        return $available;
    }

    public function resetCircuitBreaker( string $provider_name ): void {
        $this->circuit_breaker->reset( $provider_name );
    }
}
