<?php

class SC_AI_Client {

    private string $gemini_key;
    private string $groq_key;
    private int    $timeout = 30;

    public function __construct() {
        $this->gemini_key = get_option( 'sc_ai_gemini_key', '' );
        $this->groq_key   = get_option( 'sc_ai_groq_key',   '' );
    }

    /**
     * Generate text — Uses selected primary API first, then fallback
     */
    public function generate( string $prompt ): string|false {

        $primary_model = get_option( 'sc_ai_primary_model', 'groq' ); // Default to Groq

        error_log( '[SC AI] Starting generation. Primary: ' . $primary_model . ', Gemini key: ' . (empty($this->gemini_key) ? 'NOT SET' : 'SET') . ', Groq key: ' . (empty($this->groq_key) ? 'NOT SET' : 'SET'));

        // Try primary API first
        if ( $primary_model === 'groq' ) {
            // Primary: Groq
            if ( $this->groq_key ) {
                error_log( '[SC AI] Trying Groq API (primary)...');
                $result = $this->call_groq( $prompt );
                if ( $result !== false ) {
                    error_log( '[SC AI] Groq API succeeded. Output length: ' . strlen($result));
                    return $result;
                }
                error_log( '[SC AI] Groq API failed, trying fallback');
            } else {
                error_log( '[SC AI] Groq key not set, skipping to fallback');
            }

            // Fallback: Gemini
            if ( $this->gemini_key ) {
                error_log( '[SC AI] Trying Gemini API (fallback)...');
                $result = $this->call_gemini( $prompt );
                if ( $result !== false ) {
                    error_log( '[SC AI] Gemini API succeeded. Output length: ' . strlen($result));
                    return $result;
                }
                error_log( '[SC AI] Gemini API failed');
            } else {
                error_log( '[SC AI] Gemini key not set, no fallback available');
            }
        } else {
            // Primary: Gemini
            if ( $this->gemini_key ) {
                error_log( '[SC AI] Trying Gemini API (primary)...');
                $result = $this->call_gemini( $prompt );
                if ( $result !== false ) {
                    error_log( '[SC AI] Gemini API succeeded. Output length: ' . strlen($result));
                    return $result;
                }
                error_log( '[SC AI] Gemini API failed, trying fallback');
            } else {
                error_log( '[SC AI] Gemini key not set, skipping to fallback');
            }

            // Fallback: Groq
            if ( $this->groq_key ) {
                error_log( '[SC AI] Trying Groq API (fallback)...');
                $result = $this->call_groq( $prompt );
                if ( $result !== false ) {
                    error_log( '[SC AI] Groq API succeeded. Output length: ' . strlen($result));
                    return $result;
                }
                error_log( '[SC AI] Groq API failed');
            } else {
                error_log( '[SC AI] Groq key not set, no fallback available');
            }
        }

        error_log( '[SC AI] All APIs failed, returning false');
        return false;
    }

    /**
     * Force generation with Gemini API (for final/polished content)
     */
    public function generate_with_gemini( string $prompt ): string|false {
        if ( ! $this->gemini_key ) {
            error_log( '[SC AI] Gemini key not set, cannot force Gemini generation' );
            return false;
        }

        error_log( '[SC AI] Forcing Gemini API generation' );
        return $this->call_gemini( $prompt );
    }

    /**
     * Test API connection
     */
    public function test_connection(): array {
        error_log( '[SC AI] Starting API connection test...' );

        $results = [
            'gemini' => ['status' => 'not_configured', 'error' => ''],
            'groq'   => ['status' => 'not_configured', 'error' => ''],
        ];

        // Test Groq
        if ( $this->groq_key ) {
            error_log( '[SC AI] Groq key set: ' . (empty($this->groq_key) ? 'NO' : 'YES'));
            error_log( '[SC AI] Groq key length: ' . strlen($this->groq_key));
            error_log( '[SC AI] Testing Groq API...');
            // Check if key format is valid (more flexible - starts with gsk_ and is reasonable length)
            if ( ! preg_match( '/^gsk_[A-Za-z0-9]{40,60}$/', $this->groq_key ) ) {
                $results['groq']['status'] = 'failed';
                $results['groq']['error'] = 'Invalid key format (must start with gsk_)';
                error_log( '[SC AI] Groq key format invalid');
            } else {
                $test_prompt = 'Test';
                try {
                    $result = $this->call_groq( $test_prompt, 10, true ); // skip retry during test
                    if ( $result === 'RATE_LIMITED' ) {
                        $results['groq']['status'] = 'rate_limited';
                        $results['groq']['error'] = 'Rate limit exceeded (wait 2-3 min)';
                        error_log( '[SC AI] Groq API test: rate limited');
                    } elseif ( $result !== false ) {
                        $results['groq']['status'] = 'success';
                        error_log( '[SC AI] Groq API test succeeded');
                    } else {
                        $results['groq']['status'] = 'failed';
                        $results['groq']['error'] = 'API call timed out or failed';
                        error_log( '[SC AI] Groq API test failed');
                    }
                } catch ( Exception $e ) {
                    $results['groq']['status'] = 'failed';
                    $results['groq']['error'] = $e->getMessage();
                    error_log( '[SC AI] Groq API test exception: ' . $e->getMessage() );
                }
            }
        } else {
            error_log( '[SC AI] Groq key not set, skipping test');
        }

        // Test Gemini
        if ( $this->gemini_key ) {
            error_log( '[SC AI] Gemini key set: ' . (empty($this->gemini_key) ? 'NO' : 'YES'));
            error_log( '[SC AI] Gemini key length: ' . strlen($this->gemini_key));
            error_log( '[SC AI] Testing Gemini API...');
            // First check if key format is valid
            if ( ! preg_match( '/^AIza[A-Za-z0-9_-]{35}$/', $this->gemini_key ) ) {
                $results['gemini']['status'] = 'failed';
                $results['gemini']['error'] = 'Invalid key format';
                error_log( '[SC AI] Gemini key format invalid');
            } else {
                $test_prompt = 'Test';
                error_log( '[SC AI] Calling call_gemini with 3s timeout...');
                try {
                    $result = $this->call_gemini( $test_prompt, 3, true ); // skip retry during test
                    error_log( '[SC AI] call_gemini returned');
                    if ( $result === 'RATE_LIMITED' ) {
                        $results['gemini']['status'] = 'rate_limited';
                        $results['gemini']['error'] = 'Rate limit exceeded (wait 2-3 min)';
                        error_log( '[SC AI] Gemini API test: rate limited');
                    } elseif ( $result !== false ) {
                        $results['gemini']['status'] = 'success';
                        error_log( '[SC AI] Gemini API test succeeded');
                    } else {
                        $results['gemini']['status'] = 'failed';
                        $results['gemini']['error'] = 'API call timed out or failed (3s timeout)';
                        error_log( '[SC AI] Gemini API test failed');
                    }
                } catch ( Exception $e ) {
                    $results['gemini']['status'] = 'failed';
                    $results['gemini']['error'] = $e->getMessage();
                    error_log( '[SC AI] Gemini API test exception: ' . $e->getMessage() );
                }
            }
        } else {
            error_log( '[SC AI] Gemini key not set, skipping test');
        }

        error_log( '[SC AI] API connection test complete' );
        return $results;
    }

    /**
     * Google Gemini API — Free tier: 15 req/min, 1500/day
     */
    private function call_gemini( string $prompt, int $timeout = null, bool $skip_retry = false ): string|false {

        $start_time = microtime( true );
        $timestamp = current_time( 'mysql' );
        error_log( '[SC AI] call_gemini: Starting with timeout=' . ($timeout ?? $this->timeout) . ', skip_retry=' . ($skip_retry ? 'yes' : 'no'));

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='
             . $this->gemini_key;

        error_log( '[SC AI] call_gemini: URL built');

        $body = wp_json_encode( [
            'contents' => [ [
                'parts' => [ [ 'text' => $prompt ] ],
            ] ],
            'generationConfig' => [
                'temperature'     => 0.3,  // Low = consistent, factual
                'maxOutputTokens' => 1024,
                'topP'            => 0.8,
            ],
            'safetySettings' => [ [
                'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE',
            ] ],
        ] );

        error_log( '[SC AI] call_gemini: Body encoded, calling wp_remote_post...');

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => $timeout ?? $this->timeout,
        ] );

        $duration = round( microtime( true ) - $start_time, 2 );
        error_log( '[SC AI] call_gemini: wp_remote_post returned');

        if ( is_wp_error( $response ) ) {
            error_log( '[SC AI] Gemini error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_headers = wp_remote_retrieve_headers( $response );

        error_log( '[SC AI] call_gemini: Response code=' . $code);

        // Performance logging: timestamp, code, duration
        error_log( '[SC AI PERF] Gemini API | Time: ' . $timestamp . ' | Code: ' . $code . ' | Duration: ' . $duration . 's' );

        // Rate limit hit - log full response and retry once
        if ( $code === 429 ) {
            error_log( '[GEMINI DEBUG] Full 429 response: ' . print_r([
                'code' => $code,
                'body' => $response_body,
                'headers' => $response_headers,
            ], true));

            if ( $skip_retry ) {
                error_log( '[SC AI] call_gemini: Rate limit hit (test mode, skipping retry)' );
                return 'RATE_LIMITED'; // Return special value for test mode
            }

            // Check for Retry-After header
            $retry_after = is_array( $response_headers ) && isset( $response_headers['retry-after'] )
                ? (int) $response_headers['retry-after']
                : 60; // Default to 60 seconds

            error_log( '[SC AI] call_gemini: Rate limit hit, waiting ' . $retry_after . 's before retry...' );
            sleep( $retry_after );

            // Retry once
            error_log( '[SC AI] call_gemini: Retrying now...' );
            return $this->call_gemini( $prompt, $timeout, true ); // skip_retry = true to prevent infinite loop
        }

        if ( $code !== 200 ) {
            error_log( '[SC AI] Gemini HTTP ' . $code . ': ' . $response_body );
            return false;
        }

        $data = json_decode( $response_body, true );
        error_log( '[SC AI] call_gemini: Response decoded');

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? false;
    }

    /**
     * Groq API — Free tier: 30 req/min, ultra-fast
     * Model: llama-3.1-8b-instant (fastest free)
     */
    private function call_groq( string $prompt, int $timeout = null, bool $skip_retry = false ): string|false {

        $start_time = microtime( true );
        $timestamp = current_time( 'mysql' );

        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->groq_key,
            ],
            'body'    => wp_json_encode( [
                'model'       => 'llama-3.1-8b-instant',
                'messages'    => [ [
                    'role'    => 'user',
                    'content' => $prompt,
                ] ],
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ] ),
            'timeout' => $timeout ?? $this->timeout,
        ] );

        $duration = round( microtime( true ) - $start_time, 2 );

        if ( is_wp_error( $response ) ) {
            error_log( 'Groq error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_headers = wp_remote_retrieve_headers( $response );

        // Performance logging: timestamp, code, duration
        error_log( '[SC AI PERF] Groq API | Time: ' . $timestamp . ' | Code: ' . $code . ' | Duration: ' . $duration . 's' );

        // Rate limit hit - log full response and retry once
        if ( $code === 429 ) {
            error_log( '[GROQ DEBUG] Full 429 response: ' . print_r([
                'code' => $code,
                'body' => $response_body,
                'headers' => $response_headers,
            ], true));

            if ( $skip_retry ) {
                error_log( 'Groq: Rate limit hit (test mode, skipping retry)' );
                return 'RATE_LIMITED'; // Return special value for test mode
            }

            // Check for Retry-After header
            $retry_after = is_array( $response_headers ) && isset( $response_headers['retry-after'] )
                ? (int) $response_headers['retry-after']
                : 60; // Default to 60 seconds

            error_log( 'Groq: Rate limit hit, waiting ' . $retry_after . 's before retry...' );
            sleep( $retry_after );

            // Retry once
            error_log( 'Groq: Retrying now...' );
            return $this->call_groq( $prompt, $timeout, true ); // skip_retry = true to prevent infinite loop
        }

        if ( $code !== 200 ) {
            error_log( 'Groq HTTP ' . $code . ': ' . $response_body );
            return false;
        }

        $data = json_decode( $response_body, true );

        return $data['choices'][0]['message']['content'] ?? false;
    }
}