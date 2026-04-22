<?php

namespace SC_AI\ContentGenerator\Controllers;

defined( 'ABSPATH' ) || exit;

class QuestionColumnController {
    private object $progress_repository;
    private object $generator_service;
    private array $status_cache = [];

    public function __construct( object $progress_repository, object $generator_service ) {
        $this->progress_repository = $progress_repository;
        $this->generator_service = $generator_service;
    }

    /**
     * Register all hooks
     */
    public function boot(): void {
        add_filter( 'manage_scp_question_posts_columns', [ $this, 'addColumn' ] );
        add_filter( 'the_posts', [ $this, 'prefetchStatus' ], 10, 2 );
        add_action( 'manage_scp_question_posts_custom_column', [ $this, 'renderColumn' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
        add_filter( 'bulk_actions-edit-scp_question', [ $this, 'addBulkAction' ] );
        add_filter( 'handle_bulk_actions-edit-scp_question', [ $this, 'handleBulkAction' ], 10, 3 );
        add_action( 'admin_notices', [ $this, 'showBulkActionNotice' ] );
    }

    /**
     * Add AI status column to scp_question list table
     */
    public function addColumn( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['sc_ai_status'] = 'AI Content';
            }
        }
        return $new_columns;
    }

    /**
     * Prefetch all statuses to avoid N+1 queries
     */
    public function prefetchStatus( array $posts, \WP_Query $query ): array {
        global $pagenow;

        // Only run on scp_question list screen
        if ( $pagenow !== 'edit.php' || ( $_GET['post_type'] ?? '' ) !== 'scp_question' ) {
            return $posts;
        }

        if ( empty( $posts ) ) {
            return $posts;
        }

        // Collect all post IDs
        $post_ids = array_map( fn( $post ) => $post->ID, $posts );

        // Single query to fetch all statuses
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT question_id, status, generated_at
            FROM {$progress_table}
            WHERE question_id IN ({$placeholders})
        ", $post_ids ) );

        // Cache results keyed by question_id
        $this->status_cache = [];
        foreach ( $results as $row ) {
            $this->status_cache[ $row->question_id ] = [
                'status' => $row->status,
                'generated_at' => $row->generated_at,
            ];
        }

        return $posts;
    }

    /**
     * Render AI status column
     */
    public function renderColumn( string $column_name, int $post_id ): void {
        if ( $column_name !== 'sc_ai_status' ) {
            return;
        }

        $status_info = $this->status_cache[ $post_id ] ?? null;
        $status = $status_info['status'] ?? 'not_started';
        $generated_at = $status_info['generated_at'] ?? null;

        $nonce = wp_create_nonce( 'sc_ai_nonce' );

        switch ( $status ) {
            case 'not_started':
                echo '<span class="sc-ai-badge not-started">— Not Started</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            case 'pending':
                echo '<span class="sc-ai-badge pending">⏳ Pending</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            case 'processing':
                echo '<span class="sc-ai-badge processing">🔄 Processing</span>';
                break;

            case 'done':
                echo '<span class="sc-ai-badge done">✅ Done</span>';
                if ( $generated_at ) {
                    $time = \SC_AI\ContentGenerator\Helpers\DateHelper::formatTimeAgo( $generated_at );
                    echo '<small class="sc-ai-time">' . esc_html( $time ) . '</small>';
                }
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( wp_create_nonce('sc_ai_generate') ) . '" style="margin-top:4px;display:block">🔄 Regenerate</button>';
                break;

            case 'failed':
                echo '<span class="sc-ai-badge failed">❌ Failed</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            default:
                echo '<span class="sc-ai-badge not-started">— Not Started</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;
        }
    }

    /**
     * Enqueue assets on scp_question pages only
     */
    public function enqueueAssets(): void {
        $screen = get_current_screen();

        // List page
        if ( $screen && $screen->post_type === 'scp_question' && $screen->base === 'edit' ) {
            wp_enqueue_script(
                'sc-ai-question-column',
                SC_AI_PLUGIN_URL . 'assets/js/question-column.js',
                [ 'jquery' ],
                SC_AI_VERSION,
                true
            );

            wp_localize_script( 'sc-ai-question-column', 'scAiCol', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_ai_nonce' ),
            ] );

            wp_add_inline_style( 'dashicons', '
                .sc-ai-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    margin-bottom: 4px;
                }
                .sc-ai-badge.not-started { background:#e2e3e5; color:#383d41; }
                .sc-ai-badge.pending { background:#fff3cd; color:#856404; }
                .sc-ai-badge.processing { background:#cce5ff; color:#004085; }
                .sc-ai-badge.done { background:#d4edda; color:#155724; }
                .sc-ai-badge.failed { background:#f8d7da; color:#721c24; }
                .sc-ai-time { display:block; color:#999; font-size:11px; }
                .column-sc_ai_status { width:160px; }
            ' );
        }
    }

    /**
     * Add bulk action to Actions dropdown
     */
    public function addBulkAction( array $bulk_actions ): array {
        $bulk_actions['sc_ai_generate'] = 'Generate AI Content';
        return $bulk_actions;
    }

    /**
     * Handle bulk action
     */
    public function handleBulkAction( string $redirect_to, string $doaction, array $post_ids ): string {
        if ( $doaction !== 'sc_ai_generate' ) {
            return $redirect_to;
        }

        $processed = 0;
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ( $post_ids as $post_id ) {
            $processed++;
            error_log( "[SC AI Bulk] Processing post #{$post_id}" );
            $result = $this->generator_service->generate( $post_id );
            if ( $result['success'] ) {
                $success++;
                error_log( "[SC AI Bulk] Success for post #{$post_id}" );
            } else {
                // Check if error is "Content already exists" - skip instead of fail
                if ( isset( $result['error'] ) && strpos( $result['error'], 'Content already exists' ) !== false ) {
                    $skipped++;
                    error_log( "[SC AI Bulk] Skipped post #{$post_id}: Content already exists" );
                } else {
                    $failed++;
                    error_log( "[SC AI Bulk] Failed for post #{$post_id}: " . ( $result['error'] ?? 'Unknown error' ) );
                }
            }
        }

        $redirect_to = add_query_arg( [
            'sc_ai_bulk_processed' => $processed,
            'sc_ai_bulk_success' => $success,
            'sc_ai_bulk_failed' => $failed,
            'sc_ai_bulk_skipped' => $skipped,
        ], $redirect_to );

        return $redirect_to;
    }

    /**
     * Show admin notice after bulk action
     */
    public function showBulkActionNotice(): void {
        if ( isset( $_GET['sc_ai_bulk_processed'] ) ) {
            $processed = intval( $_GET['sc_ai_bulk_processed'] );
            $success = intval( $_GET['sc_ai_bulk_success'] );
            $failed = intval( $_GET['sc_ai_bulk_failed'] );
            $skipped = isset( $_GET['sc_ai_bulk_skipped'] ) ? intval( $_GET['sc_ai_bulk_skipped'] ) : 0;

            $message = 'Processed ' . $processed . ' questions - ' . $success . ' successful';
            if ( $failed > 0 ) {
                $message .= ', ' . $failed . ' failed';
            }
            if ( $skipped > 0 ) {
                $message .= ', ' . $skipped . ' skipped (content already exists)';
            }
            $message .= '.';

            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>AI Content Generation:</strong> ' . $message . '</p>';
            echo '</div>';
        }
    }
}
