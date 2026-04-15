<?php
/**
 * Plugin Name: SC AI Content Generator
 * Description: Generates SEO content for questions using free AI APIs
 * Version:     1.0.0
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

defined( 'ABSPATH' ) || exit;

// Check if Skill Certify Pro is active
include_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'skill-certify-pro/skill-certify-pro.php' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>SC AI Content Generator:</strong> Skill Certify Pro plugin must be active for this plugin to work.</p></div>';
    } );
    return;
}

define( 'SC_AI_VERSION',   '1.0.0' );
define( 'SC_AI_BATCH',     100 );        // প্রতিদিন কতটা process করবে
define( 'SC_AI_CRON_HOOK', 'sc_ai_daily_batch' );
define( 'SC_AI_DRAFT_CRON_HOOK', 'sc_ai_draft_batch' );
define( 'SC_AI_POLISH_CRON_HOOK', 'sc_ai_polish_batch' );

class SC_AI_Generator {

    public static function init(): void {
        // Include template functions
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-content-saver.php';

        // Admin menu
        add_action( 'admin_menu',       [ __CLASS__, 'add_menu' ] );
        // Daily cron (legacy) - DISABLED to prevent overwriting draft/final content
        // add_action( SC_AI_CRON_HOOK,    [ __CLASS__, 'run_daily_batch' ] );
        // Draft batch cron (every 2 hours)
        add_action( SC_AI_DRAFT_CRON_HOOK, [ __CLASS__, 'run_draft_batch' ] );
        // Polish batch cron (daily)
        add_action( SC_AI_POLISH_CRON_HOOK, [ __CLASS__, 'run_polish_batch' ] );
        // Activation
        register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );
        register_deactivation_hook( __FILE__, [ __CLASS__, 'deactivate' ] );
        // Manual trigger (AJAX)
        add_action( 'wp_ajax_sc_ai_run_now',        [ __CLASS__, 'ajax_run_now' ] );
        add_action( 'wp_ajax_sc_ai_get_stats',      [ __CLASS__, 'ajax_get_stats' ] );
        add_action( 'wp_ajax_sc_ai_status',         [ __CLASS__, 'ajax_status' ] );
        add_action( 'wp_ajax_sc_ai_test_api',       [ __CLASS__, 'ajax_test_api' ] );
        add_action( 'wp_ajax_sc_ai_test_api_debug', [ __CLASS__, 'ajax_test_api_debug' ] );
        add_action( 'wp_ajax_sc_ai_ping',           [ __CLASS__, 'ajax_ping' ] );
        add_action( 'wp_ajax_sc_ai_reset_stuck',   [ __CLASS__, 'ajax_reset_stuck' ] );
        add_action( 'wp_ajax_sc_ai_simple_test',    [ __CLASS__, 'ajax_simple_test' ] );
        // Single question generation
        add_action( 'wp_ajax_sc_ai_generate_single', [ __CLASS__, 'ajax_generate_single' ] );
        // Draft generation
        add_action( 'wp_ajax_sc_ai_generate_draft', [ __CLASS__, 'ajax_generate_draft' ] );
        // Final generation
        add_action( 'wp_ajax_sc_ai_generate_final', [ __CLASS__, 'ajax_generate_final' ] );
        // Manual batch handlers
        add_action( 'wp_ajax_sc_ai_draft_batch_manual', [ __CLASS__, 'ajax_draft_batch_manual' ] );
        add_action( 'wp_ajax_sc_ai_final_batch_manual', [ __CLASS__, 'ajax_final_batch_manual' ] );
        // Status table handler
        add_action( 'wp_ajax_sc_ai_get_status_table', [ __CLASS__, 'ajax_get_status_table' ] );
    }

    public static function activate(): void {
        // Legacy daily cron - DISABLED to prevent overwriting draft/final content
        // if ( ! wp_next_scheduled( SC_AI_CRON_HOOK ) ) {
        //     wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', SC_AI_CRON_HOOK );
        // }

        // Draft batch cron (every 2 hours)
        if ( ! wp_next_scheduled( SC_AI_DRAFT_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', SC_AI_DRAFT_CRON_HOOK );
        }
        // Polish batch cron (daily at 4 AM)
        if ( ! wp_next_scheduled( SC_AI_POLISH_CRON_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 4:00 AM' ), 'daily', SC_AI_POLISH_CRON_HOOK );
        }

        // Progress tracking table
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_ai_progress';
        
        // Create table if not exists
        $wpdb->query( "
            CREATE TABLE IF NOT EXISTS {$table_name} (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                question_id  BIGINT UNSIGNED NOT NULL UNIQUE,
                status       VARCHAR(20) DEFAULT 'pending',
                attempts     TINYINT DEFAULT 0,
                generated_at DATETIME NULL,
                error_msg    TEXT NULL,
                INDEX idx_status (status)
            )
        " );
        
        // Add content_stage column if not exists (for dual content pipeline)
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'content_stage'",
            DB_NAME, $table_name
        ) );
        
        if ( ! $column_exists ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN content_stage ENUM('none','draft','final') DEFAULT 'none' AFTER status" );
            error_log( '[SC AI] Added content_stage column to progress table' );
            
            // Migrate existing content to final stage
            $wpdb->query( "
                UPDATE {$table_name} p
                INNER JOIN {$wpdb->postmeta} m1 ON p.question_id = m1.post_id AND m1.meta_key = '_scp_description'
                SET p.content_stage = 'final'
                WHERE m1.meta_value != '' AND m1.meta_value IS NOT NULL
            " );
            error_log( '[SC AI] Migrated existing AI content to final stage' );
        }

        // Add index for content_stage if not exists
        $content_stage_index = $wpdb->get_var( $wpdb->prepare(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_content_stage'",
            DB_NAME, $table_name
        ) );
        if ( ! $content_stage_index ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD INDEX idx_content_stage (content_stage)" );
            error_log( '[SC AI] Added index for content_stage' );
        }

        // Add index for generated_at if not exists
        $generated_at_index = $wpdb->get_var( $wpdb->prepare(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_generated_at'",
            DB_NAME, $table_name
        ) );
        if ( ! $generated_at_index ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD INDEX idx_generated_at (generated_at)" );
            error_log( '[SC AI] Added index for generated_at' );
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( SC_AI_CRON_HOOK );
        wp_clear_scheduled_hook( SC_AI_DRAFT_CRON_HOOK );
        wp_clear_scheduled_hook( SC_AI_POLISH_CRON_HOOK );
    }

    public static function add_menu(): void {
        // Add AI Dashboard as top-level menu
        add_menu_page(
            'AI Content Dashboard',
            'AI Dashboard',
            'manage_options',
            'sc-ai-dashboard',
            [ __CLASS__, 'render_dashboard' ],
            '',
            90
        );

        // First submenu with same slug as parent (prevents duplicate)
        add_submenu_page(
            'sc-ai-dashboard',
            'AI Content Dashboard',
            'Dashboard',
            'manage_options',
            'sc-ai-dashboard',
            [ __CLASS__, 'render_dashboard' ]
        );

        // Add AI Settings as submenu
        add_submenu_page(
            'sc-ai-dashboard',
            'AI Content Settings',
            '⚙️ AI Settings',
            'manage_options',
            'sc-ai-generator',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Daily batch — প্রতিদিন pending থেকে ১০০টা নেয়
     */
    public static function run_daily_batch(): void {
        $processor = new SC_Batch_Processor();
        $processor->run( SC_AI_BATCH );
    }

    /**
     * Draft batch — uses settings for batch size and interval
     */
    public static function run_draft_batch(): void {
        // Global lock check - prevent any AI batch from running
        if ( get_transient( 'sc_ai_global_lock' ) ) {
            error_log( '[SC AI DRAFT] Global lock active, skipping batch' );
            return;
        }

        // Check if cron is enabled
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI DRAFT] Cron disabled, skipping batch' );
            return;
        }

        // Lock check
        $lock_key = 'sc_ai_draft_batch_lock';
        if ( get_transient( $lock_key ) ) {
            error_log( '[SC AI DRAFT] Batch already running, skipping' );
            return;
        }
        set_transient( $lock_key, true, 15 * MINUTE_IN_SECONDS );
        set_transient( 'sc_ai_global_lock', true, 5 * MINUTE_IN_SECONDS );

        $batch_size = absint( get_option( 'sc_ai_draft_batch_size', 25 ) );
        $batch_start_time = microtime( true );
        $initial_queries = $wpdb->num_queries;
        error_log( '[SC AI DRAFT] Starting draft batch with ' . $batch_size . ' questions' );

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $questions = self::get_questions_for_stage( 'draft', $batch_size );

            $processed = 0;
            $success = 0;
            $failed = 0;
            $consecutive_failures = 0;

            foreach ( $questions as $q ) {
                $processed++;
                $result = $processor->generate_draft_content( $q->question_id );

                if ( $result['success'] ) {
                    $success++;
                    $consecutive_failures = 0; // Reset on success
                } else {
                    $failed++;
                    $consecutive_failures++;
                    error_log( '[SC AI DRAFT] Failed for Q#' . $q->question_id . ': ' . $result['error'] );

                    // Early break after 3 consecutive failures
                    if ( $consecutive_failures >= 3 ) {
                        error_log( '[SC AI DRAFT] Stopping batch after 3 consecutive failures' );
                        break;
                    }
                }

                // Memory safety
                unset( $result );

                // Sleep 2s between requests (Groq rate limit)
                sleep( 2 );
            }

            $batch_duration = round( microtime( true ) - $batch_start_time, 2 );
            $db_queries = $wpdb->num_queries - $initial_queries;
            error_log( "[SC AI DRAFT] Batch complete: {$processed} processed, {$success} success, {$failed} failed | Duration: {$batch_duration}s | DB Queries: {$db_queries}" );
        } catch ( Exception $e ) {
            error_log( '[SC AI DRAFT] Exception: ' . $e->getMessage() );
        } finally {
            delete_transient( $lock_key );
            delete_transient( 'sc_ai_global_lock' );
        }
    }

    public static function run_polish_batch(): void {
        // Global lock check - prevent any AI batch from running
        if ( get_transient( 'sc_ai_global_lock' ) ) {
            error_log( '[SC AI POLISH] Global lock active, skipping batch' );
            return;
        }

        // Check if cron is enabled
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI POLISH] Cron disabled, skipping batch' );
            return;
        }

        // Lock check
        $lock_key = 'sc_ai_polish_batch_lock';
        if ( get_transient( $lock_key ) ) {
            error_log( '[SC AI POLISH] Batch already running, skipping' );
            return;
        }
        set_transient( $lock_key, true, 30 * MINUTE_IN_SECONDS );
        set_transient( 'sc_ai_global_lock', true, 5 * MINUTE_IN_SECONDS );

        $batch_size = absint( get_option( 'sc_ai_final_batch_size', 20 ) );
        $batch_start_time = microtime( true );
        $initial_queries = $wpdb->num_queries;
        error_log( '[SC AI POLISH] Starting polish batch with ' . $batch_size . ' questions' );

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $questions = self::get_questions_for_stage( 'final', $batch_size );

            $processed = 0;
            $success = 0;
            $failed = 0;
            $consecutive_failures = 0;

            foreach ( $questions as $q ) {
                $processed++;
                $result = $processor->generate_final_content( $q->question_id );

                if ( $result['success'] ) {
                    $success++;
                    $consecutive_failures = 0; // Reset on success
                } else {
                    $failed++;
                    $consecutive_failures++;
                    error_log( '[SC AI POLISH] Failed for Q#' . $q->question_id . ': ' . $result['error'] );

                    // Early break after 3 consecutive failures
                    if ( $consecutive_failures >= 3 ) {
                        error_log( '[SC AI POLISH] Stopping batch after 3 consecutive failures' );
                        break;
                    }
                }

                // Memory safety
                unset( $result );

                // Sleep 6s between requests (Gemini rate limit - safe margin)
                sleep( 6 );
            }

            $batch_duration = round( microtime( true ) - $batch_start_time, 2 );
            $db_queries = $wpdb->num_queries - $initial_queries;
            error_log( "[SC AI POLISH] Batch complete: {$processed} processed, {$success} success, {$failed} failed | Duration: {$batch_duration}s | DB Queries: {$db_queries}" );
        } catch ( Exception $e ) {
            error_log( '[SC AI POLISH] Exception: ' . $e->getMessage() );
        } finally {
            delete_transient( $lock_key );
            delete_transient( 'sc_ai_global_lock' );
        }
    }

    /**
     * Get questions for a specific stage
     */
    private static function get_questions_for_stage( string $target_stage, int $limit ): array {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'sc_ai_progress';

        if ( $target_stage === 'draft' ) {
            // Get questions with no content or failed draft
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT p.question_id
                FROM {$progress_table} p
                LEFT JOIN {$wpdb->postmeta} m1 ON p.question_id = m1.post_id AND m1.meta_key = '_scp_description_draft'
                WHERE (p.content_stage = 'none' OR p.content_stage = 'draft')
                AND (m1.meta_id IS NULL OR m1.meta_value = '' OR (p.status = 'failed' AND p.attempts < 3))
                ORDER BY p.question_id ASC
                LIMIT %d
            ", $limit ) );
        } elseif ( $target_stage === 'final' ) {
            // Get questions with draft but no final
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT p.question_id
                FROM {$progress_table} p
                LEFT JOIN {$wpdb->postmeta} m1 ON p.question_id = m1.post_id AND m1.meta_key = '_scp_description_final'
                WHERE p.content_stage = 'draft'
                AND p.status = 'done'
                AND (m1.meta_id IS NULL OR m1.meta_value = '' OR (p.status = 'failed' AND p.attempts < 3))
                ORDER BY p.question_id ASC
                LIMIT %d
            ", $limit ) );
        }

        return [];
    }

    /**
     * AJAX handler for generating draft
     */
    public static function ajax_generate_draft(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
        }

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $result = $processor->generate_draft_content( $post_id );

            wp_send_json_success( $result );
        } catch ( Exception $e ) {
            error_log( '[SC AI] Draft generation exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX handler for generating final
     */
    public static function ajax_generate_final(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
        }

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $result = $processor->generate_final_content( $post_id );

            wp_send_json_success( $result );
        } catch ( Exception $e ) {
            error_log( '[SC AI] Final generation exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX handler for manual draft batch
     */
    public static function ajax_draft_batch_manual(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        // Global lock check
        if ( get_transient( 'sc_ai_global_lock' ) ) {
            wp_send_json_error( [ 'message' => 'Another batch is currently running. Please wait.' ] );
        }

        $batch = absint( $_POST['batch'] ?? 5 );
        if ( $batch < 1 || $batch > 10 ) {
            $batch = 5;
        }

        error_log( '[SC AI] Manual draft batch triggered: ' . $batch . ' questions' );
        set_transient( 'sc_ai_global_lock', true, 5 * MINUTE_IN_SECONDS );

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $questions = self::get_questions_for_stage( 'draft', $batch );

            $success = 0;
            $failed = 0;

            foreach ( $questions as $q ) {
                $result = $processor->generate_draft_content( $q->question_id );

                if ( $result['success'] ) {
                    $success++;
                } else {
                    $failed++;
                }

                sleep( 2 );
            }

            wp_send_json_success( [
                'success' => $success,
                'failed'  => $failed,
                'total'   => count( $questions ),
            ] );
        } catch ( Exception $e ) {
            error_log( '[SC AI] Manual draft batch exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        } finally {
            delete_transient( 'sc_ai_global_lock' );
        }
    }

    /**
     * AJAX handler for manual final batch
     */
    public static function ajax_final_batch_manual(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        // Global lock check
        if ( get_transient( 'sc_ai_global_lock' ) ) {
            wp_send_json_error( [ 'message' => 'Another batch is currently running. Please wait.' ] );
        }

        $batch = absint( $_POST['batch'] ?? 5 );
        if ( $batch < 1 || $batch > 10 ) {
            $batch = 5;
        }

        error_log( '[SC AI] Manual final batch triggered: ' . $batch . ' questions' );
        set_transient( 'sc_ai_global_lock', true, 5 * MINUTE_IN_SECONDS );

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $questions = self::get_questions_for_stage( 'final', $batch );

            $success = 0;
            $failed = 0;

            foreach ( $questions as $q ) {
                $result = $processor->generate_final_content( $q->question_id );

                if ( $result['success'] ) {
                    $success++;
                } else {
                    $failed++;
                }

                sleep( 6 );
            }

            wp_send_json_success( [
                'success' => $success,
                'failed'  => $failed,
                'total'   => count( $questions ),
            ] );
        } catch ( Exception $e ) {
            error_log( '[SC AI] Manual final batch exception: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        } finally {
            delete_transient( 'sc_ai_global_lock' );
        }
    }

    /**
     * AJAX handler for status table data
     */
    public static function ajax_get_status_table(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $page = absint( $_POST['page'] ?? 1 );
        $per_page = absint( $_POST['per_page'] ?? 20 );
        $filter = sanitize_text_field( $_POST['filter'] ?? 'all' );
        $offset = ( $page - 1 ) * $per_page;

        global $wpdb;
        $progress_table = $wpdb->prefix . 'sc_ai_progress';
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        // Build WHERE clause based on filter
        $where = "WHERE p.post_type = 'scp_question' AND p.post_status = 'publish'";
        $filter_clause = '';

        if ( $filter === 'draft' ) {
            $filter_clause = "AND pr.content_stage = 'draft' AND pr.status = 'done'";
        } elseif ( $filter === 'final' ) {
            $filter_clause = "AND pr.content_stage = 'final' AND pr.status = 'done'";
        } elseif ( $filter === 'pending' ) {
            $filter_clause = "AND (pr.content_stage = 'none' OR pr.content_stage IS NULL)";
        }

        // Get total count
        $total_query = "
            SELECT COUNT(*)
            FROM {$posts_table} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            {$where} {$filter_clause}
        ";
        $total = $wpdb->get_var( $total_query );

        // Get paginated questions
        $query = $wpdb->prepare( "
            SELECT
                p.ID as id,
                p.post_title as title,
                COALESCE(pr.content_stage, 'none') as status,
                pr.generated_at
            FROM {$posts_table} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            {$where} {$filter_clause}
            ORDER BY p.ID DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset );

        $results = $wpdb->get_results( $query );

        $questions = [];
        foreach ( $results as $row ) {
            $draft_time = '';
            $final_time = '';

            // Get draft time from meta
            $draft_meta = get_post_meta( $row->id, '_scp_description_draft', true );
            if ( ! empty( $draft_meta ) ) {
                $draft_progress = $wpdb->get_var( $wpdb->prepare( "
                    SELECT generated_at FROM {$progress_table}
                    WHERE question_id = %d AND content_stage = 'draft' AND status = 'done'
                    ORDER BY generated_at DESC LIMIT 1
                ", $row->id ) );
                $draft_time = $draft_progress ? date( 'M j, g:i A', strtotime( $draft_progress ) ) : '';
            }

            // Get final time from meta
            $final_meta = get_post_meta( $row->id, '_scp_description_final', true );
            if ( ! empty( $final_meta ) ) {
                $final_progress = $wpdb->get_var( $wpdb->prepare( "
                    SELECT generated_at FROM {$progress_table}
                    WHERE question_id = %d AND content_stage = 'final' AND status = 'done'
                    ORDER BY generated_at DESC LIMIT 1
                ", $row->id ) );
                $final_time = $final_progress ? date( 'M j, g:i A', strtotime( $final_progress ) ) : '';
            }

            $questions[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'status' => $row->status,
                'draft_time' => $draft_time,
                'final_time' => $final_time,
                'edit_link' => get_edit_post_link( $row->id ),
            ];
        }

        wp_send_json_success( [
            'questions' => $questions,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
        ] );
    }

    /**
     * AJAX handler for manual run (legacy) - DISABLED
     */
    public static function ajax_run_now(): void {
        // GUARD: Check if dual pipeline is active
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_ai_progress';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'content_stage'",
            DB_NAME, $table_name
        ) );

        if ( $column_exists ) {
            error_log( '[SC AI LEGACY] Dual pipeline detected, legacy ajax_run_now() disabled' );
            wp_send_json_error( [ 'message' => 'Legacy function disabled - use draft/final batch buttons instead' ] );
            return;
        }

        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }

        $batch = absint( $_POST['batch'] ?? 100 );
        $skip_generated = isset( $_POST['skip_generated'] ) && $_POST['skip_generated'] === 'true';

        error_log( '[AI DEBUG] Manual batch run triggered: ' . $batch . ' questions (skip_generated=' . ($skip_generated ? 'yes' : 'no') . ')' );

        try {
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

            $processor = new SC_Batch_Processor();
            $results = $processor->run( $batch, $skip_generated );

            wp_send_json_success( $results );
        } catch ( Exception $e ) {
            error_log( '[AI ERROR] RUN NOW EXCEPTION: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function ajax_get_stats(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        // Check cache first (5 minutes)
        $cache_key = 'sc_ai_stats_cache';
        $cached_stats = get_transient( $cache_key );
        if ( $cached_stats !== false ) {
            wp_send_json_success( $cached_stats );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sc_ai_progress';

        $stats = $wpdb->get_row( "
            SELECT
                COUNT(*) as total,
                SUM( status = 'done'       ) as done,
                SUM( status = 'pending'    ) as pending,
                SUM( status = 'failed'     ) as failed,
                SUM( status = 'processing' ) as processing
            FROM {$table}
        " );

        // Cache for 5 minutes
        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

        wp_send_json_success( $stats );
    }

    public static function ajax_status(): void {
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sc_ai_progress';

        $stats = $wpdb->get_row( "
            SELECT
                COUNT(*) as total,
                SUM( status = 'done'    ) as done,
                SUM( status = 'pending' ) as pending,
                SUM( status = 'failed'  ) as failed
            FROM {$table}
        " );

        wp_send_json_success( $stats );
    }

    public static function ajax_test_api(): void {
        error_log( '[AI DEBUG] Handler triggered: sc_ai_test_api' );
        error_log( '[AI DEBUG] ENTERED HANDLER: sc_ai_test_api' );

        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[AI ERROR] Permission denied' );
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }

        try {
            error_log( '[AI DEBUG] Including AI client...' );
            $file = plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            if ( ! file_exists( $file ) ) {
                error_log( '[AI ERROR] File missing: ' . $file );
                wp_send_json_error( [ 'message' => 'AI client file missing' ] );
                return;
            }
            require_once $file;

            error_log( '[AI DEBUG] Creating AI client...' );
            $ai = new SC_AI_Client();
            error_log( '[AI DEBUG] Calling test_connection...' );
            $results = $ai->test_connection();
            error_log( '[AI DEBUG] test_connection returned' );

            error_log( '[AI DEBUG] API Test Results: ' . wp_json_encode( $results ) );
            error_log( '[AI DEBUG] Sending JSON response...' );

            wp_send_json_success( $results );
        } catch ( Exception $e ) {
            error_log( '[AI ERROR] API TEST EXCEPTION: ' . $e->getMessage() );
            error_log( '[AI ERROR] Exception trace: ' . $e->getTraceAsString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function ajax_test_api_debug(): void {
        error_log( '[AI DEBUG] Handler triggered: sc_ai_test_api_debug' );
        error_log( '[AI DEBUG] ENTERED HANDLER: sc_ai_test_api_debug' );

        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }

        try {
            // First test basic connectivity
            error_log( '[AI DEBUG] Testing internet connectivity...' );
            $connectivity = wp_remote_get( 'https://www.google.com', [ 'timeout' => 5 ] );
            $can_reach_internet = ! is_wp_error( $connectivity );

            if ( is_wp_error( $connectivity ) ) {
                error_log( '[AI DEBUG] Connectivity error: ' . $connectivity->get_error_message() );
            } else {
                error_log( '[AI DEBUG] Connectivity response code: ' . wp_remote_retrieve_response_code( $connectivity ) );
            }

            error_log( '[AI DEBUG] Internet connectivity test: ' . ( $can_reach_internet ? 'OK' : 'FAILED' ) );

            // Include required classes
            error_log( '[AI DEBUG] Including AI client...' );
            $file = plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
            if ( ! file_exists( $file ) ) {
                error_log( '[AI ERROR] File missing: ' . $file );
                wp_send_json_error( [ 'message' => 'AI client file missing' ] );
                return;
            }
            require_once $file;

            error_log( '[AI DEBUG] Creating AI client...' );
            $ai = new SC_AI_Client();
            error_log( '[AI DEBUG] Calling test_connection...' );
            $results = $ai->test_connection();
            error_log( '[AI DEBUG] test_connection returned' );

            $results['connectivity'] = $can_reach_internet ? 'OK' : 'FAILED';

            error_log( '[AI DEBUG] API Test Results: ' . wp_json_encode( $results ) );
            error_log( '[AI DEBUG] Sending JSON response...' );

            wp_send_json_success( $results );
        } catch ( Exception $e ) {
            error_log( '[AI ERROR] DEBUG HANDLER EXCEPTION: ' . $e->getMessage() );
            error_log( '[AI ERROR] Exception trace: ' . $e->getTraceAsString() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function ajax_ping(): void {
        error_log( '[AI DEBUG] Handler triggered: sc_ai_ping' );
        error_log( '[AI DEBUG] ENTERED HANDLER: sc_ai_ping' );
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }
        wp_send_json_success( [ 'message' => 'Pong!', 'time' => current_time( 'mysql' ) ] );
    }

    public static function ajax_simple_test(): void {
        error_log( '[AI DEBUG] Handler triggered: sc_ai_simple_test' );
        error_log( '[AI DEBUG] ENTERED HANDLER: sc_ai_simple_test' );
        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }
        wp_send_json_success( [ 'message' => 'Simple test works!', 'time' => current_time( 'mysql' ) ] );
    }

    public static function ajax_reset_stuck(): void {
        error_log( '[AI DEBUG] Handler triggered: sc_ai_reset_stuck' );
        error_log( '[AI DEBUG] ENTERED HANDLER: sc_ai_reset_stuck' );

        check_ajax_referer( 'sc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[AI ERROR] Permission denied' );
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
            return;
        }

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'sc_ai_progress';

            // Clear global lock
            delete_transient( 'sc_ai_global_lock' );
            delete_transient( 'sc_ai_draft_batch_lock' );
            delete_transient( 'sc_ai_polish_batch_lock' );
            error_log( '[AI DEBUG] Cleared all batch locks' );

            // Reset stuck processing questions to pending
            $updated = $wpdb->query( $wpdb->prepare( "
                UPDATE {$table}
                SET status = 'pending',
                    error_msg = 'Reset from stuck processing'
                WHERE status = 'processing'
            " ) );

            error_log( '[AI DEBUG] Reset ' . $updated . ' stuck processing questions to pending' );

            wp_send_json_success( [ 'reset_count' => $updated, 'locks_cleared' => true ] );
        } catch ( Exception $e ) {
            error_log( '[AI ERROR] RESET STUCK EXCEPTION: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function ajax_generate_single(): void {
        // GUARD: Check if dual pipeline is active
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_ai_progress';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'content_stage'",
            DB_NAME, $table_name
        ) );

        if ( $column_exists ) {
            error_log( '[SC AI LEGACY] Dual pipeline detected, legacy ajax_generate_single() disabled' );
            wp_send_json_error( [ 'message' => 'Legacy function disabled - use draft/final generation buttons instead' ] );
            return;
        }

        check_ajax_referer( 'sc_ai_single', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $post_id = intval( $_POST['post_id'] ?? 0 );

        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID' );
        }

        // Include required classes
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';

        $processor = new SC_Batch_Processor();
        $result = $processor->generate_single( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    public static function render_dashboard(): void {
        // Get stats from cache or database
        $stats = self::get_dashboard_stats();
        
        ?>
        <div class="wrap">
            <h1>🤖 AI Content Dashboard</h1>
            <p>Overview of AI-generated content status</p>
            
            <div class="sc-ai-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <!-- Total Questions -->
                <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Total Questions</h3>
                    <div style="font-size: 48px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $stats['total'] ); ?></div>
                </div>
                
                <!-- Draft Generated -->
                <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Draft Generated</h3>
                    <div style="font-size: 48px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $stats['draft'] ); ?></div>
                    <?php if ( $stats['last_draft'] ) : ?>
                    <div style="margin-top: 10px; font-size: 12px; color: #646970;">Last: <?php echo esc_html( $stats['last_draft'] ); ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Final Generated -->
                <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Final Generated</h3>
                    <div style="font-size: 48px; font-weight: bold; color: #d63638;"><?php echo esc_html( $stats['final'] ); ?></div>
                    <?php if ( $stats['last_final'] ) : ?>
                    <div style="margin-top: 10px; font-size: 12px; color: #646970;">Last: <?php echo esc_html( $stats['last_final'] ); ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Pending -->
                <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Pending</h3>
                    <div style="font-size: 48px; font-weight: bold; color: #646970;"><?php echo esc_html( $stats['pending'] ); ?></div>
                </div>
                
            </div>
            
            <!-- Progress Bar -->
            <div style="margin-top: 30px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Content Generation Progress</h3>
                <?php 
                $progress = $stats['total'] > 0 ? ( ( $stats['draft'] + $stats['final'] ) / $stats['total'] * 100 ) : 0;
                ?>
                <div style="background: #e5e5e5; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div style="background: linear-gradient(90deg, #2271b1 0%, #00a32a 100%); height: 100%; width: <?php echo esc_attr( $progress ); ?>%; transition: width 0.3s;"></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: #646970;">
                    <strong><?php echo number_format( $progress, 1 ); ?>%</strong> complete (<?php echo esc_html( $stats['draft'] + $stats['final'] ); ?> of <?php echo esc_html( $stats['total'] ); ?> questions)
                </div>
            </div>
            
            <!-- Cron Status -->
            <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Cron Status</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e5e5;"><strong>Draft Batch</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e5e5;">Every hour (25 questions)</td>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e5e5;"><?php echo wp_next_scheduled( 'sc_ai_draft_batch' ) ? '<span style="color: #00a32a;">● Active</span>' : '<span style="color: #d63638;">● Inactive</span>'; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong>Polish Batch</strong></td>
                        <td style="padding: 10px;">Daily at 4 AM (20 questions)</td>
                        <td style="padding: 10px;"><?php echo wp_next_scheduled( 'sc_ai_polish_batch' ) ? '<span style="color: #00a32a;">● Active</span>' : '<span style="color: #d63638;">● Inactive</span>'; ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Activity Timeline -->
            <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Recent Activity (Last 15)</h3>
                <?php 
                $activities = self::get_recent_activities( 15 );
                if ( empty( $activities ) ) :
                ?>
                <p style="color: #646970; font-style: italic;">No recent activity recorded.</p>
                <?php else : ?>
                <div style="position: relative; padding-left: 20px;">
                    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: #e5e5e5;"></div>
                    <?php foreach ( $activities as $activity ) : ?>
                    <div style="position: relative; margin-bottom: 20px; padding-left: 20px;">
                        <div style="position: absolute; left: -24px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $activity['type'] === 'draft' ? '#00a32a' : ( $activity['type'] === 'final' ? '#d63638' : '#2271b1' ); ?>;"></div>
                        <div style="background: #f6f7f7; padding: 12px; border-radius: 6px; border-left: 3px solid <?php echo $activity['type'] === 'draft' ? '#00a32a' : ( $activity['type'] === 'final' ? '#d63638' : '#2271b1' ); ?>;">
                            <div style="font-size: 13px; color: #646970; margin-bottom: 5px;"><?php echo esc_html( $activity['time'] ); ?></div>
                            <div style="font-weight: 500; color: #1d2327;"><?php echo esc_html( $activity['message'] ); ?></div>
                            <?php if ( ! empty( $activity['question_title'] ) ) : ?>
                            <div style="margin-top: 5px; font-size: 12px; color: #646970;">
                                <a href="<?php echo esc_url( get_edit_post_link( $activity['question_id'] ) ); ?>" style="color: #2271b1; text-decoration: none;"><?php echo esc_html( $activity['question_title'] ); ?></a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Question Status Table -->
            <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Question Status</h3>

                <!-- Filters -->
                <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="sc-ai-filter button button-primary" data-filter="all">All</button>
                    <button class="sc-ai-filter button" data-filter="draft">Draft Only</button>
                    <button class="sc-ai-filter button" data-filter="final">Final Only</button>
                    <button class="sc-ai-filter button" data-filter="pending">Pending</button>
                </div>

                <!-- Table -->
                <div class="sc-ai-table-container" style="overflow-x: auto;">
                    <table class="sc-ai-status-table wp-list-table widefat fixed striped" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Question Title</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Status</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Draft Time</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Final Time</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sc-ai-table-body">
                            <tr>
                                <td colspan="5" style="padding: 20px; text-align: center;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="sc-ai-pagination" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span id="sc-ai-page-info">Page 1</span>
                    <div id="sc-ai-page-controls"></div>
                </div>
            </div>

        </div>

        <script>
        const scAiAjaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
        let scAiCurrentPage = 1;
        let scAiCurrentFilter = 'all';
        const scAiPerPage = 20;

        function scAiLoadTable( page = 1, filter = 'all' ) {
            scAiCurrentPage = page;
            scAiCurrentFilter = filter;

            const tbody = document.getElementById( 'sc-ai-table-body' );
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">Loading...</td></tr>';

            fetch( scAiAjaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_get_status_table',
                    nonce: '<?php echo wp_create_nonce( 'sc_ai_nonce' ); ?>',
                    page: page,
                    per_page: scAiPerPage,
                    filter: filter,
                }),
            })
            .then( r => r.json() )
            .then( response => {
                if ( response.success ) {
                    scAiRenderTable( response.data );
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #d63638;">Error: ' + response.data + '</td></tr>';
                }
            })
            .catch( err => {
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #d63638;">Error: ' + err.message + '</td></tr>';
            });
        }

        function scAiRenderTable( data ) {
            const tbody = document.getElementById( 'sc-ai-table-body' );

            if ( ! data.questions || data.questions.length === 0 ) {
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">No questions found.</td></tr>';
                document.getElementById( 'sc-ai-page-info' ).textContent = 'No results';
                document.getElementById( 'sc-ai-page-controls' ).innerHTML = '';
                return;
            }

            let html = '';
            data.questions.forEach( q => {
                let statusBadge = '';
                if ( q.status === 'final' ) {
                    statusBadge = '<span style="background: #d63638; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">FINAL</span>';
                } else if ( q.status === 'draft' ) {
                    statusBadge = '<span style="background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">DRAFT</span>';
                } else {
                    statusBadge = '<span style="background: #646970; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">PENDING</span>';
                }

                html += '<tr>';
                html += '<td style="padding: 10px; border-bottom: 1px solid #e5e5e5;"><a href="' + q.edit_link + '" style="color: #2271b1; text-decoration: none;">' + q.title + '</a></td>';
                html += '<td style="padding: 10px; border-bottom: 1px solid #e5e5e5;">' + statusBadge + '</td>';
                html += '<td style="padding: 10px; border-bottom: 1px solid #e5e5e5; font-size: 12px; color: #646970;">' + ( q.draft_time || '—' ) + '</td>';
                html += '<td style="padding: 10px; border-bottom: 1px solid #e5e5e5; font-size: 12px; color: #646970;">' + ( q.final_time || '—' ) + '</td>';
                html += '<td style="padding: 10px; border-bottom: 1px solid #e5e5e5;">';
                html += '<button class="button button-small sc-ai-gen-draft" data-id="' + q.id + '" style="font-size: 11px; padding: 2px 8px;">Draft</button> ';
                html += '<button class="button button-small sc-ai-gen-final" data-id="' + q.id + '" style="font-size: 11px; padding: 2px 8px;">Final</button>';
                html += '</td>';
                html += '</tr>';
            });

            tbody.innerHTML = html;

            // Update pagination
            const totalPages = Math.ceil( data.total / scAiPerPage );
            document.getElementById( 'sc-ai-page-info' ).textContent = 'Page ' + scAiCurrentPage + ' of ' + totalPages + ' (' + data.total + ' total)';

            let paginationHtml = '';
            if ( scAiCurrentPage > 1 ) {
                paginationHtml += '<button class="button button-small sc-ai-prev-page">← Prev</button> ';
            }
            if ( scAiCurrentPage < totalPages ) {
                paginationHtml += '<button class="button button-small sc-ai-next-page">Next →</button>';
            }
            document.getElementById( 'sc-ai-page-controls' ).innerHTML = paginationHtml;

            // Add action listeners
            document.querySelectorAll( '.sc-ai-gen-draft' ).forEach( btn => {
                btn.addEventListener( 'click', function() {
                    scAiGenerateDraft( this.dataset.id );
                });
            });

            document.querySelectorAll( '.sc-ai-gen-final' ).forEach( btn => {
                btn.addEventListener( 'click', function() {
                    scAiGenerateFinal( this.dataset.id );
                });
            });

            document.querySelector( '.sc-ai-prev-page' )?.addEventListener( 'click', () => scAiLoadTable( scAiCurrentPage - 1, scAiCurrentFilter ) );
            document.querySelector( '.sc-ai-next-page' )?.addEventListener( 'click', () => scAiLoadTable( scAiCurrentPage + 1, scAiCurrentFilter ) );
        }

        function scAiGenerateDraft( postId ) {
            if ( ! confirm( 'Generate draft for this question?' ) ) return;

            fetch( scAiAjaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_generate_draft',
                    nonce: '<?php echo wp_create_nonce( 'sc_ai_nonce' ); ?>',
                    post_id: postId,
                }),
            })
            .then( r => r.json() )
            .then( response => {
                if ( response.success ) {
                    alert( 'Draft generated successfully!' );
                    scAiLoadTable( scAiCurrentPage, scAiCurrentFilter );
                } else {
                    alert( 'Error: ' + response.data );
                }
            })
            .catch( err => alert( 'Error: ' + err.message ) );
        }

        function scAiGenerateFinal( postId ) {
            if ( ! confirm( 'Generate final for this question?' ) ) return;

            fetch( scAiAjaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_generate_final',
                    nonce: '<?php echo wp_create_nonce( 'sc_ai_nonce' ); ?>',
                    post_id: postId,
                }),
            })
            .then( r => r.json() )
            .then( response => {
                if ( response.success ) {
                    alert( 'Final generated successfully!' );
                    scAiLoadTable( scAiCurrentPage, scAiCurrentFilter );
                } else {
                    alert( 'Error: ' + response.data );
                }
            })
            .catch( err => alert( 'Error: ' + err.message ) );
        }

        // Filter buttons
        document.querySelectorAll( '.sc-ai-filter' ).forEach( btn => {
            btn.addEventListener( 'click', function() {
                document.querySelectorAll( '.sc-ai-filter' ).forEach( b => b.classList.remove( 'button-primary' ) );
                this.classList.add( 'button-primary' );
                scAiLoadTable( 1, this.dataset.filter );
            });
        });

        // Load table on page load
        scAiLoadTable();
        </script>
        <?php
    }
    
    /**
     * Get dashboard stats with caching
     */
    private static function get_dashboard_stats(): array {
        $cache_key = 'sc_ai_dashboard_stats';
        $stats = get_transient( $cache_key );
        
        if ( $stats !== false ) {
            return $stats;
        }
        
        global $wpdb;
        $progress_table = $wpdb->prefix . 'sc_ai_progress';
        $posts_table = $wpdb->posts;
        
        // Get counts
        $total = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$posts_table}
            WHERE post_type = 'scp_question' AND post_status = 'publish'
        " );
        
        $draft = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$progress_table}
            WHERE content_stage = 'draft' AND status = 'done'
        " );
        
        $final = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$progress_table}
            WHERE content_stage = 'final' AND status = 'done'
        " );
        
        $pending = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$progress_table}
            WHERE content_stage = 'none'
        " );
        
        // Get last generation times
        $last_draft = $wpdb->get_var( "
            SELECT generated_at FROM {$progress_table}
            WHERE content_stage = 'draft' AND status = 'done' AND generated_at IS NOT NULL
            ORDER BY generated_at DESC LIMIT 1
        " );
        
        $last_final = $wpdb->get_var( "
            SELECT generated_at FROM {$progress_table}
            WHERE content_stage = 'final' AND status = 'done' AND generated_at IS NOT NULL
            ORDER BY generated_at DESC LIMIT 1
        " );
        
        $stats = [
            'total' => (int) $total,
            'draft' => (int) $draft,
            'final' => (int) $final,
            'pending' => (int) $pending,
            'last_draft' => $last_draft ? date( 'M j, g:i A', strtotime( $last_draft ) ) : '',
            'last_final' => $last_final ? date( 'M j, g:i A', strtotime( $last_final ) ) : '',
        ];
        
        // Cache for 5 minutes
        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        
        return $stats;
    }
    
    /**
     * Get recent activities for timeline
     */
    private static function get_recent_activities( int $limit = 15 ): array {
        $cache_key = 'sc_ai_activities_' . $limit;
        $activities = get_transient( $cache_key );
        
        if ( $activities !== false ) {
            return $activities;
        }
        
        global $wpdb;
        $progress_table = $wpdb->prefix . 'sc_ai_progress';
        $posts_table = $wpdb->posts;
        
        // Get recent successful generations from progress table
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT 
                p.question_id,
                p.content_stage,
                p.generated_at,
                p.status,
                pt.post_title as question_title
            FROM {$progress_table} p
            LEFT JOIN {$posts_table} pt ON p.question_id = pt.ID
            WHERE p.status = 'done' 
            AND p.generated_at IS NOT NULL
            AND p.content_stage IN ('draft', 'final')
            ORDER BY p.generated_at DESC
            LIMIT %d
        ", $limit ) );
        
        $activities = [];
        
        foreach ( $results as $row ) {
            $type = $row->content_stage === 'draft' ? 'draft' : 'final';
            $message = $type === 'draft' 
                ? 'Draft content generated' 
                : 'Final content generated';
            
            $activities[] = [
                'type' => $type,
                'message' => $message,
                'time' => self::format_time_ago( $row->generated_at ),
                'question_id' => (int) $row->question_id,
                'question_title' => $row->question_title ?? '',
            ];
        }
        
        // Cache for 2 minutes (more frequent than stats)
        set_transient( $cache_key, $activities, 2 * MINUTE_IN_SECONDS );
        
        return $activities;
    }
    
    /**
     * Format time as "X minutes ago" or "X hours ago"
     */
    private static function format_time_ago( string $datetime ): string {
        $time = strtotime( $datetime );
        $now = current_time( 'timestamp' );
        $diff = $now - $time;
        
        if ( $diff < MINUTE_IN_SECONDS ) {
            return 'Just now';
        } elseif ( $diff < HOUR_IN_SECONDS ) {
            $minutes = floor( $diff / MINUTE_IN_SECONDS );
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        } elseif ( $diff < DAY_IN_SECONDS ) {
            $hours = floor( $diff / HOUR_IN_SECONDS );
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        } else {
            $days = floor( $diff / DAY_IN_SECONDS );
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }
    }

    public static function render_page(): void {
        require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';
    }
}

SC_AI_Generator::init();