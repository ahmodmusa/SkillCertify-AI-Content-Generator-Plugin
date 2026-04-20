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
        $this->registerControllers();
        $this->registerSeoServices();
    }

    private function registerCoreServices(): void {
        $this->bind( 'config', function() {
            $defaults = require SC_AI_PLUGIN_DIR . 'config/defaults.php';
            return array_merge( $defaults, [
                'groq_key' => get_option( 'sc_ai_groq_key', '' ),
                'openrouter_key' => get_option( 'sc_ai_openrouter_key', '' ),
                'primary_provider' => get_option( 'sc_ai_primary_provider', 'groq' ),
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
                $config['groq_model'],
                $this->get( 'usage.tracker' )
            );
        });

        $this->bind( 'api.openrouter', function() {
            $config = $this->get( 'config' );
            return new \SC_AI\ContentGenerator\Services\API\OpenRouterProvider(
                $config['openrouter_key'],
                $config['openrouter_model'],
                $config['openrouter_max_tokens'],
                $this->get( 'usage.tracker' )
            );
        });

        $this->singleton( 'api.circuit_breaker', function() {
            return new \SC_AI\ContentGenerator\Services\API\CircuitBreaker(
                SC_AI_CIRCUIT_FAILURE_THRESHOLD,
                SC_AI_CIRCUIT_TIMEOUT
            );
        });

        $this->singleton( 'usage.tracker', function() {
            return new \SC_AI\ContentGenerator\Services\UsageTracker();
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

        $this->singleton( 'queue.final', function() {
            return new \SC_AI\ContentGenerator\Services\Queue\FinalQueue(
                $this->get( 'queue.manager' ),
                $this->get( 'generator' )
            );
        });

        $this->singleton( 'queue.retry', function() {
            return new \SC_AI\ContentGenerator\Services\Queue\RetryQueue(
                $this->get( 'queue.manager' ),
                $this->get( 'generator' )
            );
        });
    }

    private function registerGeneratorServices(): void {
        $this->bind( 'prompt', function() {
            return new \SC_AI\ContentGenerator\Services\Prompt\PromptBuilder();
        });

        $this->bind( 'parser', function() {
            return new \SC_AI\ContentGenerator\Services\Parser\StructuredParser();
        });

        $this->singleton( 'storage.content', function() {
            return new \SC_AI\ContentGenerator\Services\Storage\ContentStorage();
        });

        $this->singleton( 'storage.progress', function() {
            return new \SC_AI\ContentGenerator\Services\Storage\ProgressTracker(
                $this->get( 'repository.progress' )
            );
        });

        $this->singleton( 'generator', function() {
            return new \SC_AI\ContentGenerator\Services\Generator\FinalGenerator(
                $this->get( 'api.pool' ),
                $this->get( 'prompt' ),
                $this->get( 'parser' ),
                $this->get( 'storage.content' ),
                $this->get( 'storage.progress' ),
                $this->get( 'repository.question' )
            );
        });

        $this->singleton( 'generator.service', function() {
            return new \SC_AI\ContentGenerator\Services\Generator\GeneratorService(
                $this->get( 'generator' ),
                $this->get( 'queue.final' ),
                $this->get( 'queue.retry' )
            );
        });
    }

    private function registerStorageServices(): void {
        $this->singleton( 'storage.progress', function() {
            return new \SC_AI\ContentGenerator\Services\Storage\ProgressTracker(
                $this->get( 'repository.progress' )
            );
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

        $this->singleton( 'admin.ai_content_metabox', function() {
            return new \SC_AI\ContentGenerator\Admin\AIContentMetaBox();
        });
    }

    private function registerControllers(): void {
        $this->singleton( 'controller.queue', function() {
            return new \SC_AI\ContentGenerator\Controllers\QueueController( $this );
        });

        $this->singleton( 'controller.question_column', function() {
            return new \SC_AI\ContentGenerator\Controllers\QuestionColumnController(
                $this->get( 'repository.progress' )
            );
        });
    }

    private function registerSeoServices(): void {
        $this->singleton( 'seo.rankmath', function() {
            return new \SC_AI\ContentGenerator\Services\SEO\RankMathService();
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
        $this->get( 'admin.ai_content_metabox' )->boot();

        // Boot queue controller
        $this->get( 'controller.queue' )->boot();

        // Boot question column controller
        $this->get( 'controller.question_column' )->boot();

        // Boot SEO services
        $this->get( 'seo.rankmath' )->boot();

        $this->booted = true;
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
