<?php
/**
 * Plugin Name: SC AI Content Generator
 * Description: Generates SEO content for questions using free AI APIs (Groq, OpenRouter)
 * Version:     1.0.5
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

// Load config (defines all constants including SC_AI_PLUGIN_DIR and SC_AI_PLUGIN_URL)
require_once plugin_dir_path( __FILE__ ) . 'config/constants.php';

// Load composer autoloader if available
if ( file_exists( SC_AI_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once SC_AI_PLUGIN_DIR . 'vendor/autoload.php';
}

// Bootstrap the plugin
require_once SC_AI_PLUGIN_DIR . 'src/Core/Bootstrap.php';
\SC_AI\ContentGenerator\Core\Bootstrap::run( __FILE__ );

// Register template functions for backward compatibility
if ( ! function_exists( 'scp_output_ai_faq_schema' ) ) {
    function scp_output_ai_faq_schema( int $post_id ): void {
        \SC_AI\ContentGenerator\Frontend\TemplateFunctions::outputAiFaqSchema( $post_id );
    }
}

if ( ! function_exists( 'scp_get_ai_exam_tip' ) ) {
    function scp_get_ai_exam_tip( int $post_id ): string {
        return \SC_AI\ContentGenerator\Frontend\TemplateFunctions::getAiExamTip( $post_id );
    }
}

if ( ! function_exists( 'scp_get_ai_description' ) ) {
    function scp_get_ai_description( int $post_id ): string {
        return \SC_AI\ContentGenerator\Frontend\TemplateFunctions::getAiDescription( $post_id );
    }
}

if ( ! function_exists( 'scp_get_unified_content' ) ) {
    function scp_get_unified_content( int $post_id ): array {
        return \SC_AI\ContentGenerator\Frontend\TemplateFunctions::getUnifiedContent( $post_id );
    }
}

// Register shortcodes
\SC_AI\ContentGenerator\Frontend\Shortcodes::register();
