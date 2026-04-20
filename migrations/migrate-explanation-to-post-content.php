<?php
/**
 * Migration Script: Copy _scp_ai_explanation to post_content
 * 
 * This script migrates all existing explanation data from meta field
 * _scp_ai_explanation to the WordPress post_content field.
 * 
 * Usage:
 * 1. Place this file in WordPress root
 * 2. Access via browser: http://yoursite.com/migrate-explanation-to-post-content.php
 * 3. Delete the file after successful migration
 * 
 * Or run via WP-CLI:
 * wp eval-file migrations/migrate-explanation-to-post-content.php
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/wp-load.php';

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. You must be an administrator.' );
}

echo '<h1>Migration: _scp_ai_explanation → post_content</h1>';
echo '<p>Starting migration...</p>';

// Get all scp_question posts
$args = [
    'post_type'      => 'scp_question',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
];

$question_ids = get_posts( $args );

$total = count( $question_ids );
$migrated = 0;
$skipped = 0;
$failed = 0;

echo "<p>Total questions found: {$total}</p>";

foreach ( $question_ids as $post_id ) {
    // Get explanation from meta
    $explanation = get_post_meta( $post_id, '_scp_ai_explanation', true );
    
    if ( empty( $explanation ) ) {
        $skipped++;
        continue;
    }
    
    // Check if post_content already has content
    $current_content = get_post_field( 'post_content', $post_id );
    if ( ! empty( $current_content ) ) {
        $skipped++;
        echo "<p>Skipped post {$post_id}: post_content already has content</p>";
        continue;
    }
    
    // Update post_content
    $result = wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $explanation,
    ], false );
    
    if ( is_wp_error( $result ) ) {
        $failed++;
        echo "<p>Failed to update post {$post_id}: " . $result->get_error_message() . '</p>';
    } else {
        $migrated++;
        echo "<p>Migrated post {$post_id}</p>";
    }
    
    // Optional: Delete old meta after successful migration
    // delete_post_meta( $post_id, '_scp_ai_explanation' );
}

echo '<h2>Migration Complete</h2>';
echo "<p>Total: {$total}</p>";
echo "<p>Migrated: {$migrated}</p>";
echo "<p>Skipped: {$skipped}</p>";
echo "<p>Failed: {$failed}</p>";

if ( $failed === 0 ) {
    echo '<p style="color: green;">✓ Migration successful!</p>';
    echo '<p><strong>IMPORTANT:</strong> Delete this file after verifying the migration.</p>';
} else {
    echo '<p style="color: red;">✗ Migration completed with errors. Review the failed items above.</p>';
}
