<?php

namespace SC_AI\ContentGenerator\Core;

defined( 'ABSPATH' ) || exit;

class Plugin {
    private static ?Plugin $instance = null;
    private ServiceProvider $service_provider;
    private string $version;
    private string $plugin_file;

    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->version = SC_AI_VERSION;
        $this->service_provider = new ServiceProvider();
    }

    public static function getInstance( string $plugin_file ): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self( $plugin_file );
        }
        return self::$instance;
    }

    public function run(): void {
        $this->service_provider->register();
        $this->service_provider->boot();
        
        register_activation_hook( $this->plugin_file, [ $this, 'activate' ] );
        register_deactivation_hook( $this->plugin_file, [ $this, 'deactivate' ] );
    }

    public function activate(): void {
        $this->service_provider->get( 'database.migration' )->up();
        $this->service_provider->get( 'database.seeder' )->seed();
        
        // Schedule queue hooks
        if ( ! wp_next_scheduled( SC_AI_FINAL_QUEUE_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 04:00' ), 'daily', SC_AI_FINAL_QUEUE_HOOK );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( SC_AI_DRAFT_QUEUE_HOOK );
        wp_clear_scheduled_hook( SC_AI_FINAL_QUEUE_HOOK );
        wp_clear_scheduled_hook( SC_AI_RETRY_QUEUE_HOOK );
    }

    public function getVersion(): string {
        return $this->version;
    }

    public function getPluginFile(): string {
        return $this->plugin_file;
    }

    public function getServiceProvider(): ServiceProvider {
        return $this->service_provider;
    }
}
