<?php

namespace SC_AI\ContentGenerator\Admin;

defined( 'ABSPATH' ) || exit;

class AjaxController {
    private object $generator_service;
    private object $progress_repository;
    private object $api_pool;
    private object $service_provider;

    public function __construct(
        object $generator_service,
        object $progress_repository,
        object $api_pool,
        object $service_provider
    ) {
        $this->generator_service = $generator_service;
        $this->progress_repository = $progress_repository;
        $this->api_pool = $api_pool;
        $this->service_provider = $service_provider;
    }

    public function boot(): void {
        add_action( 'wp_ajax_sc_ai_generate', [ $this, 'handleGenerate' ] );
        add_action( 'wp_ajax_sc_ai_final_batch_manual', [ $this, 'handleFinalBatchManual' ] );
        add_action( 'wp_ajax_sc_ai_get_stats', [ $this, 'handleGetStats' ] );
        add_action( 'wp_ajax_sc_ai_get_status_table', [ $this, 'handleGetStatusTable' ] );
        add_action( 'wp_ajax_sc_ai_test_api', [ $this, 'handleTestApi' ] );
        add_action( 'wp_ajax_sc_ai_reset_stuck', [ $this, 'handleResetStuck' ] );
        add_action( 'wp_ajax_sc_ai_delete_question', [ $this, 'handleDeleteQuestion' ] );
        add_action( 'wp_ajax_sc_ai_manual_cron', [ $this, 'handleManualCron' ] );
    }

    public function handleGenerate(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
        }

        try {
            // Delete existing meta so content is fully replaced
            delete_post_meta($post_id, '_scp_ai_description');
            delete_post_meta($post_id, '_scp_ai_faqs');
            delete_post_meta($post_id, '_scp_ai_exam_tip');
            delete_post_meta($post_id, '_scp_description_final');
            delete_post_meta($post_id, '_scp_faqs_final');
            // Delete new meta keys
            delete_post_meta($post_id, '_scp_ai_keypoints');
            delete_post_meta($post_id, '_scp_ai_mistake');
            delete_post_meta($post_id, '_scp_ai_tip');
            // Clear post_content (explanation is now stored here)
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => '',
            ], false);

            // Reset progress so generator runs fresh
            $progress_repo = $this->service_provider->get('repository.progress');
            $progress_repo->upsertProgress($post_id, 'pending', 'none', '');

            $result = $this->generator_service->generate( $post_id );
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            error_log( '[SC AI] Generation exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'An error occurred' ] );
        }
    }

    public function handleFinalBatchManual(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $batch = absint( $_POST['batch'] ?? 5 );
        if ( $batch < 1 || $batch > 10 ) {
            $batch = 5;
        }

        try {
            $result = $this->generator_service->processFinalBatch( $batch );
            
            // Log to manual history
            if ( isset( $result['generated'] ) && is_array( $result['generated'] ) ) {
                foreach ( $result['generated'] as $question_id ) {
                    $post = get_post( $question_id );
                    if ( $post ) {
                        $this->logManualHistory( $post->post_title, 'final', $question_id );
                    }
                }
            }
            
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            error_log( '[SC AI] Manual final batch exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'An error occurred' ] );
        }
    }

    private function logManualHistory( string $title, string $type, int $post_id ): void {
        $history = get_option( 'sc_ai_manual_history', [] );
        
        $entry = [
            'title' => $title,
            'type' => $type,
            'post_id' => $post_id,
            'time' => date( 'M j, g:i A' ),
        ];

        // Add to beginning and keep only last 50
        array_unshift( $history, $entry );
        $history = array_slice( $history, 0, 50 );
        
        update_option( 'sc_ai_manual_history', $history );
    }

    public function handleGetStats(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }

        $stats = $this->progress_repository->getStats();
        wp_send_json_success( $stats );
    }

    public function handleGetStatusTable(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $page = absint( $_POST['page'] ?? 1 );
        $per_page = absint( $_POST['per_page'] ?? 20 );
        $filter = sanitize_text_field( $_POST['filter'] ?? 'all' );

        $data = $this->progress_repository->getStatusTableData( $page, $per_page, $filter );
        wp_send_json_success( $data );
    }

    public function handleTestApi(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        try {
            $results = $this->api_pool->testAll();
            wp_send_json_success( $results );
        } catch ( \Exception $e ) {
            error_log( '[SC AI] API test exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Test failed' ] );
        }
    }

    public function handleResetStuck(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        try {
            $reset_count = $this->progress_repository->resetStuckProcessing();
            wp_send_json_success( [ 'reset_count' => $reset_count, 'locks_cleared' => true ] );
        } catch ( \Exception $e ) {
            error_log( '[SC AI] Reset stuck exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Reset failed' ] );
        }
    }

    public function handleDeleteQuestion(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
        }

        try {
            $result = wp_delete_post( $post_id, true );
            if ( $result ) {
                // Clean up progress tracking
                $this->progress_repository->deleteProgress( $post_id );
                wp_send_json_success( [ 'message' => 'Question deleted successfully' ] );
            } else {
                wp_send_json_error( [ 'message' => 'Failed to delete question' ] );
            }
        } catch ( \Exception $e ) {
            error_log( '[SC AI] Delete question exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'An error occurred' ] );
        }
    }

    public function handleManualCron(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        try {
            // Trigger the final cron job handler
            $batch_size = absint( get_option( 'sc_ai_final_batch_size', 20 ) );
            $final_queue = $this->service_provider->get( 'queue.final' );
            $results = $final_queue->process( $batch_size );

            wp_send_json_success( [
                'processed' => $results['processed'],
                'success' => $results['success'],
                'failed' => $results['failed'],
            ] );
        } catch ( \Exception $e ) {
            error_log( '[SC AI] Manual cron exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'An error occurred: ' . $e->getMessage() ] );
        }
    }
}
