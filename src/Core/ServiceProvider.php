<?php

namespace SC_AI\ContentGenerator\Core;

defined( 'ABSPATH' ) || exit;

class ServiceProvider {
    private array $services = [];
    private array $instances = [];
    private bool $booted = false;

    public function register(): void {
        // Register services
        $this->registerCoreServices();
        $this->registerApiServices();
        $this->registerQueueServices();
        $this->registerGeneratorServices();
        $this->registerStorageServices();
        $this->registerRepositories();
        $this->registerAdminServices();
    }

    private function registerCoreServices(): void {
        $this->bind( 'config', function() {
            $defaults = require SC_AI_PLUGIN_DIR . 'config/defaults.php';
            return array_merge( $defaults, [
                'groq_key' => get_option( 'sc_ai_groq_key', '' ),
                'openrouter_key' => get_option( 'sc_ai_openrouter_key', '' ),
                'primary_provider' => get_option( 'sc_ai_primary_provider', 'groq' ),
                'enable_draft_queue' => get_option( 'sc_ai_enable_draft_queue', '1' ),
                'draft_batch_size' => get_option( 'sc_ai_draft_batch_size', 25 ),
                'final_batch_size' => get_option( 'sc_ai_final_batch_size', 20 ),
                'enable_cron' => get_option( 'sc_ai_enable_cron', '1' ),
            ] );
        });

        $this->singleton( 'database.migration', function() {
            return new \SC_AI\ContentGenerator\Database\Migrations\Migration();
        });

        $this->singleton( 'database.seeder', function() {
            return new \SC_AI\ContentGenerator\Database\Seeds\Seeder();
        });
    }

    private function registerApiServices(): void {
        $this->bind( 'api.groq', function() {
            $config = $this->get( 'config' );
            return new \SC_AI\ContentGenerator\Services\API\GroqProvider(
                $config['groq_key'],
                $config['groq_model']
            );
        });

        $this->bind( 'api.openrouter', function() {
            $config = $this->get( 'config' );
            return new \SC_AI\ContentGenerator\Services\API\OpenRouterProvider(
                $config['openrouter_key'],
                $config['openrouter_model']
            );
        });

        $this->singleton( 'api.circuit_breaker', function() {
            return new \SC_AI\ContentGenerator\Services\API\CircuitBreaker(
                SC_AI_CIRCUIT_FAILURE_THRESHOLD,
                SC_AI_CIRCUIT_TIMEOUT
            );
        });

        $this->singleton( 'api.pool', function() {
            $config = $this->get( 'config' );
            $providers = [];
            
            if ( ! empty( $config['groq_key'] ) ) {
                $providers['groq'] = $this->get( 'api.groq' );
            }
            if ( ! empty( $config['openrouter_key'] ) ) {
                $providers['openrouter'] = $this->get( 'api.openrouter' );
            }
            
            return new \SC_AI\ContentGenerator\Services\API\ProviderPool(
                $providers,
                $this->get( 'api.circuit_breaker' ),
                $config['primary_provider'],
                $config['fallback_provider'] ?? null
            );
        });
    }

    private function registerQueueServices(): void {
        $this->singleton( 'queue.manager', function() {
            return new \SC_AI\ContentGenerator\Services\Queue\QueueManager();
        });

        // Draft queue removed - using direct generation instead

        $this->singleton( 'queue.final', function() {
            return new \SC_AI\ContentGenerator\Services\Queue\FinalQueue(
                $this->get( 'queue.manager' ),
                $this->get( 'generator.final' )
            );
        });

        $this->singleton( 'queue.retry', function() {
            return new \SC_AI\ContentGenerator\Services\Queue\RetryQueue(
                $this->get( 'queue.manager' ),
                $this->get( 'generator.final' )
            );
        });
    }

    private function registerGeneratorServices(): void {
        $this->bind( 'prompt.draft', function() {
            return new \SC_AI\ContentGenerator\Services\Prompt\DraftPromptBuilder();
        });

        $this->bind( 'prompt.final', function() {
            return new \SC_AI\ContentGenerator\Services\Prompt\FinalPromptBuilder();
        });

        $this->singleton( 'parser', function() {
            return new \SC_AI\ContentGenerator\Services\Parser\StructuredParser();
        });

        $this->bind( 'generator.draft', function() {
            return new \SC_AI\ContentGenerator\Services\Generator\DraftGenerator(
                $this->get( 'api.pool' ),
                $this->get( 'prompt.draft' ),
                $this->get( 'parser' ),
                $this->get( 'storage.content' ),
                $this->get( 'storage.progress' )
            );
        });

        $this->bind( 'generator.final', function() {
            return new \SC_AI\ContentGenerator\Services\Generator\FinalGenerator(
                $this->get( 'api.pool' ),
                $this->get( 'prompt.final' ),
                $this->get( 'parser' ),
                $this->get( 'storage.content' ),
                $this->get( 'storage.progress' )
            );
        });

        $this->singleton( 'generator.service', function() {
            return new \SC_AI\ContentGenerator\Services\Generator\GeneratorService(
                $this->get( 'generator.draft' ),
                $this->get( 'generator.final' ),
                null, // queue.draft removed
                $this->get( 'queue.final' ),
                $this->get( 'queue.retry' )
            );
        });
    }

    private function registerStorageServices(): void {
        $this->singleton( 'storage.content', function() {
            return new \SC_AI\ContentGenerator\Services\Storage\ContentStorage();
        });

        $this->singleton( 'storage.progress', function() {
            return new \SC_AI\ContentGenerator\Services\Storage\ProgressTracker();
        });
    }

    private function registerRepositories(): void {
        $this->singleton( 'repository.question', function() {
            return new \SC_AI\ContentGenerator\Repositories\QuestionRepository();
        });

        $this->singleton( 'repository.progress', function() {
            return new \SC_AI\ContentGenerator\Repositories\ProgressRepository();
        });
    }

    private function registerAdminServices(): void {
        $this->singleton( 'admin.dashboard', function() {
            return new \SC_AI\ContentGenerator\Admin\DashboardController(
                $this->get( 'repository.progress' ),
                $this->get( 'generator.service' )
            );
        });

        $this->singleton( 'admin.settings', function() {
            return new \SC_AI\ContentGenerator\Admin\SettingsController(
                $this->get( 'api.pool' )
            );
        });

        $this->singleton( 'admin.ajax', function() {
            return new \SC_AI\ContentGenerator\Admin\AjaxController(
                $this->get( 'generator.service' ),
                $this->get( 'repository.progress' ),
                $this->get( 'api.pool' ),
                $this
            );
        });
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        // Boot admin services
        $this->get( 'admin.dashboard' )->boot();
        $this->get( 'admin.settings' )->boot();
        $this->get( 'admin.ajax' )->boot();

        // Register queue hooks
        add_action( SC_AI_FINAL_QUEUE_HOOK, [ $this, 'handleFinalQueue' ] );
        add_action( SC_AI_RETRY_QUEUE_HOOK, [ $this, 'handleRetryQueue' ] );

        $this->booted = true;
    }

    public function handleFinalQueue(): void {
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI] Final cron disabled, skipping' );
            return;
        }

        $batch_size = absint( get_option( 'sc_ai_final_batch_size', 20 ) );
        $final_queue = $this->get( 'queue.final' );
        $results = $final_queue->process( $batch_size );

        error_log( sprintf(
            '[SC AI] Final cron: %d processed, %d success, %d failed',
            $results['processed'],
            $results['success'],
            $results['failed']
        ) );

        // Log to cron history
        $this->logCronHistory( 'Final', $results );
    }

    public function handleRetryQueue(): void {
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI] Retry cron disabled, skipping' );
            return;
        }

        $retry_queue = $this->get( 'queue.retry' );
        $results = $retry_queue->process( 10 );

        error_log( sprintf(
            '[SC AI] Retry cron: %d processed, %d success, %d failed',
            $results['processed'],
            $results['success'],
            $results['failed']
        ) );

        // Log to cron history
        $this->logCronHistory( 'Retry', $results );
    }

    private function logCronHistory( string $type, array $results ): void {
        $history = get_option( 'sc_ai_cron_history', [] );
        $status = $results['failed'] > 0 ? 'failed' : 'success';
        
        $entry = [
            'type' => $type,
            'status' => $status,
            'processed' => $results['processed'],
            'success' => $results['success'],
            'failed' => $results['failed'],
            'time' => date( 'M j, g:i A' ),
        ];

        // Add to beginning and keep only last 50
        array_unshift( $history, $entry );
        $history = array_slice( $history, 0, 50 );
        
        update_option( 'sc_ai_cron_history', $history );
    }

    public function bind( string $key, callable $concrete ): void {
        $this->services[ $key ] = $concrete;
        unset( $this->instances[ $key ] );
    }

    public function singleton( string $key, callable $concrete ): void {
        $this->services[ $key ] = $concrete;
    }

    public function get( string $key ) {
        if ( isset( $this->instances[ $key ] ) ) {
            return $this->instances[ $key ];
        }

        if ( ! isset( $this->services[ $key ] ) ) {
            throw new \Exception( "Service '{$key}' not registered" );
        }

        $concrete = $this->services[ $key ];
        $instance = $concrete();

        // Check if this was registered as a singleton by checking if it's still in services
        if ( isset( $this->services[ $key ] ) && $this->services[ $key ] === $concrete ) {
            $this->instances[ $key ] = $instance;
        }

        return $instance;
    }
}
