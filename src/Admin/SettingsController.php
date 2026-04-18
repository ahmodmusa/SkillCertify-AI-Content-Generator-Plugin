<?php

namespace SC_AI\ContentGenerator\Admin;

defined( 'ABSPATH' ) || exit;

class SettingsController {
    private object $api_pool;

    public function __construct( object $api_pool ) {
        $this->api_pool = $api_pool;
    }

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'handleSettingsSave' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
    }

    public function enqueueAssets( string $hook ): void {
        if ( $hook !== 'ai-dashboard_page_sc-ai-generator' ) {
            return;
        }

        wp_enqueue_script(
            'sc-ai-admin-settings',
            SC_AI_PLUGIN_URL . 'assets/js/admin-settings.js',
            [],
            SC_AI_VERSION,
            true
        );

        wp_localize_script( 'sc-ai-admin-settings', 'scAiSettings', [
            'nonce' => wp_create_nonce( 'sc_ai_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function handleSettingsSave(): void {
        if ( ! isset( $_POST['sc_ai_save'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'sc_ai_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // API Settings - only update if fields are present
        if ( isset( $_POST['primary_provider'] ) ) {
            update_option( 'sc_ai_primary_provider', sanitize_text_field( $_POST['primary_provider'] ) );
        }
        if ( isset( $_POST['fallback_provider'] ) ) {
            update_option( 'sc_ai_fallback_provider', sanitize_text_field( $_POST['fallback_provider'] ) );
        }
        if ( isset( $_POST['groq_key'] ) ) {
            update_option( 'sc_ai_groq_key', sanitize_text_field( $_POST['groq_key'] ) );
        }
        if ( isset( $_POST['openrouter_key'] ) ) {
            update_option( 'sc_ai_openrouter_key', sanitize_text_field( $_POST['openrouter_key'] ) );
        }
        if ( isset( $_POST['groq_model'] ) ) {
            update_option( 'sc_ai_groq_model', sanitize_text_field( $_POST['groq_model'] ) );
        }
        if ( isset( $_POST['groq_max_tokens'] ) ) {
            update_option( 'sc_ai_groq_max_tokens', intval( $_POST['groq_max_tokens'] ?? 4000 ) );
        }
        if ( isset( $_POST['groq_batch_model'] ) ) {
            update_option( 'sc_ai_groq_batch_model', sanitize_text_field( $_POST['groq_batch_model'] ?? 'llama-3.1-8b-instant' ) );
        }
        if ( isset( $_POST['openrouter_model'] ) ) {
            update_option( 'sc_ai_openrouter_model', sanitize_text_field( $_POST['openrouter_model'] ) );
        }

        // Queue Settings
        update_option( 'sc_ai_final_batch_size', absint( $_POST['final_batch_size'] ?? 20 ) );
        update_option( 'sc_ai_final_cron_time', sanitize_text_field( $_POST['final_cron_time'] ?? '04:00' ) );
        update_option( 'sc_ai_enable_cron', isset( $_POST['enable_cron'] ) ? '1' : '0' );

        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        } );
    }
}
