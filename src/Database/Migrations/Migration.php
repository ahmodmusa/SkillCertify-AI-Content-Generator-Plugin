<?php

namespace SC_AI\ContentGenerator\Database\Migrations;

defined( 'ABSPATH' ) || exit;

class Migration {
    public function up(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_id  BIGINT UNSIGNED NOT NULL UNIQUE,
            status       VARCHAR(20) DEFAULT 'pending',
            content_stage ENUM('none','draft','final') DEFAULT 'none',
            attempts     TINYINT DEFAULT 0,
            generated_at DATETIME NULL,
            error_msg    TEXT NULL,
            INDEX idx_status (status),
            INDEX idx_content_stage (content_stage),
            INDEX idx_generated_at (generated_at),
            INDEX idx_stage_status_time (content_stage, status, generated_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        error_log( '[SC AI] Progress table created/updated' );
    }

    public function down(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        error_log( '[SC AI] Progress table dropped' );
    }
}
