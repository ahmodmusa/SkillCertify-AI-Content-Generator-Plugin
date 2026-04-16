<?php

namespace SC_AI\ContentGenerator\Admin;

defined( 'ABSPATH' ) || exit;

class DashboardController {
    private object $progress_repository;
    private object $generator_service;

    public function __construct( object $progress_repository, object $generator_service ) {
        $this->progress_repository = $progress_repository;
        $this->generator_service = $generator_service;
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'addMenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
    }

    public function enqueueAssets( string $hook ): void {
        if ( $hook !== 'toplevel_page_sc-ai-dashboard' ) {
            return;
        }

        wp_enqueue_style(
            'sc-ai-admin',
            SC_AI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SC_AI_VERSION
        );

        wp_enqueue_script(
            'sc-ai-admin',
            SC_AI_PLUGIN_URL . 'assets/js/admin.js',
            [],
            SC_AI_VERSION,
            true
        );

        wp_localize_script( 'sc-ai-admin', 'scAiData', [
            'nonce' => wp_create_nonce( 'sc_ai_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function addMenu(): void {
        // Add AI Dashboard as top-level menu
        add_menu_page(
            'AI Content Dashboard',
            'AI Dashboard',
            'manage_options',
            'sc-ai-dashboard',
            [ $this, 'renderDashboard' ],
            '',
            90
        );

        // First submenu with same slug as parent
        add_submenu_page(
            'sc-ai-dashboard',
            'AI Content Dashboard',
            'Dashboard',
            'manage_options',
            'sc-ai-dashboard',
            [ $this, 'renderDashboard' ]
        );

        // Add AI Settings as submenu
        add_submenu_page(
            'sc-ai-dashboard',
            'AI Content Settings',
            '⚙️ AI Settings',
            'manage_options',
            'sc-ai-generator',
            [ $this, 'renderSettings' ]
        );
    }

    public function renderDashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        $stats = $this->progress_repository->getDashboardStats();
        $activities = $this->progress_repository->getRecentActivities( 20 );

        require SC_AI_PLUGIN_DIR . 'views/dashboard.php';
    }

    public function renderSettings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        require SC_AI_PLUGIN_DIR . 'views/settings.php';
    }
}
