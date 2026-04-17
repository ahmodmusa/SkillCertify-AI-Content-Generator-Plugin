<?php

namespace SC_AI\ContentGenerator\Database\Migrations;

defined( 'ABSPATH' ) || exit;

class Migration {
    public function up(): void {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            generated_at DATETIME NULL,
            error_msg TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_question_id (question_id),
            KEY idx_status (status),
            KEY idx_question_id (question_id),
            KEY idx_generated_at (generated_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Drop content_stage column for existing installs
        $column_exists = $wpdb->get_results( $wpdb->prepare( "
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s
            AND COLUMN_NAME = 'content_stage'
        ", $table ) );

        if ( ! empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table} DROP COLUMN content_stage" );
            error_log( '[SC AI] Dropped content_stage column from progress table' );
        }

        error_log( '[SC AI] Progress table created/updated' );
    }

    public function down(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        error_log( '[SC AI] Progress table dropped' );
    }
}
