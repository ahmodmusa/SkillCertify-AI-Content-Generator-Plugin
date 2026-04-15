<?php
/**
 * Content Saver - Template Integration Functions
 *
 * Add these functions to single-question.php to display AI-generated content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output AI-generated FAQ Schema
 * Call this in single-question.php after the FAQ section
 * Uses unified content getter (final → draft → legacy)
 */
function scp_output_ai_faq_schema( int $post_id ): void {

    $cache_key = 'scp_schema_' . $post_id;
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        echo $cached;
        return;
    }

    $faqs = scp_get_unified_faqs( $post_id );

    if ( empty( $faqs ) ) {
        return;
    }

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map( fn( $f ) => [
            '@type'          => 'Question',
            'name'           => $f['q'] ?? $f['question'] ?? '',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $f['a'] ?? $f['answer'] ?? '',
            ],
        ], $faqs ),
    ];

    $output = '<script type="application/ld+json">'
       . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
       . '</script>';

    set_transient( $cache_key, $output, HOUR_IN_SECONDS );
    echo $output;
}

/**
 * Get AI-generated exam tip
 * Returns the tip string or empty string if not available
 */
function scp_get_ai_exam_tip( int $post_id ): string {
    return get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
}

/**
 * Get AI-generated description
 * Returns the description string or empty string if not available
 */
function scp_get_ai_description( int $post_id ): string {
    return get_post_meta( $post_id, '_scp_description', true ) ?: '';
}

/**
 * Get AI-generated draft description
 * Returns the draft description string or empty string if not available
 */
function scp_get_ai_description_draft( int $post_id ): string {
    return get_post_meta( $post_id, '_scp_description_draft', true ) ?: '';
}

/**
 * Get AI-generated final description
 * Returns the final description string or empty string if not available
 */
function scp_get_ai_description_final( int $post_id ): string {
    $cache_key = 'scp_content_' . $post_id;
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $content = get_post_meta( $post_id, '_scp_description_final', true ) ?: '';
    set_transient( $cache_key, $content, HOUR_IN_SECONDS );
    return $content;
}

/**
 * Get AI-generated FAQs
 * Returns array of FAQs or empty array
 */
function scp_get_ai_faqs( int $post_id ): array {
    $faqs = get_post_meta( $post_id, '_scp_faqs', true );

    if ( empty( $faqs ) ) {
        return array();
    }

    // Decode if stored as JSON
    if ( is_string( $faqs ) ) {
        $faqs = json_decode( $faqs, true );
    }

    return is_array( $faqs ) ? $faqs : array();
}

/**
 * Get AI-generated draft FAQs
 * Returns array of draft FAQs or empty array
 */
function scp_get_ai_faqs_draft( int $post_id ): array {
    $faqs = get_post_meta( $post_id, '_scp_faqs_draft', true );

    if ( empty( $faqs ) ) {
        return array();
    }

    // Decode if stored as JSON
    if ( is_string( $faqs ) ) {
        $faqs = json_decode( $faqs, true );
    }

    return is_array( $faqs ) ? $faqs : array();
}

/**
 * Get AI-generated final FAQs
 * Returns array of final FAQs or empty array
 */
function scp_get_ai_faqs_final( int $post_id ): array {
    $cache_key = 'scp_faqs_final_' . $post_id;
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $faqs = get_post_meta( $post_id, '_scp_faqs_final', true );

    if ( empty( $faqs ) ) {
        return array();
    }

    // Decode if stored as JSON
    if ( is_string( $faqs ) ) {
        $faqs = json_decode( $faqs, true );
    }

    $result = is_array( $faqs ) ? $faqs : array();
    set_transient( $cache_key, $result, HOUR_IN_SECONDS );
    return $result;
}

/**
 * Check if AI content exists for a question
 */
function scp_has_ai_content( int $post_id ): bool {
    $description = get_post_meta( $post_id, '_scp_description', true );
    $faqs = get_post_meta( $post_id, '_scp_faqs', true );
    $tip  = get_post_meta( $post_id, '_scp_ai_exam_tip', true );
    return ! empty( $description ) || ! empty( $faqs ) || ! empty( $tip );
}

/**
 * Check if draft content exists for a question
 */
function scp_has_draft_content( int $post_id ): bool {
    $description = get_post_meta( $post_id, '_scp_description_draft', true );
    $faqs = get_post_meta( $post_id, '_scp_faqs_draft', true );
    return ! empty( $description ) || ! empty( $faqs );
}

/**
 * Check if final content exists for a question
 */
function scp_has_final_content( int $post_id ): bool {
    $description = get_post_meta( $post_id, '_scp_description_final', true );
    $faqs = get_post_meta( $post_id, '_scp_faqs_final', true );
    return ! empty( $description ) || ! empty( $faqs );
}

/**
 * Save draft content for a question
 */
function scp_save_draft_content( int $post_id, string $description, array $faqs ): bool {
    update_post_meta( $post_id, '_scp_description_draft', wp_kses_post( $description ) );
    update_post_meta( $post_id, '_scp_faqs_draft', json_encode( $faqs ) );
    return true;
}

/**
 * Save final content for a question
 */
function scp_save_final_content( int $post_id, string $description, array $faqs ): bool {
    update_post_meta( $post_id, '_scp_description_final', wp_kses_post( $description ) );
    update_post_meta( $post_id, '_scp_faqs_final', json_encode( $faqs ) );
    return true;
}

/**
 * Output AI-generated exam tip with styling
 * Call this in single-question.php where you want to show the tip
 */
function scp_output_ai_exam_tip( int $post_id ): void {
    $tip = scp_get_ai_exam_tip( $post_id );
    if ( $tip ) {
        echo '<div class="scp-ai-exam-tip" style="background:#e7f3ff;padding:15px;border-radius:8px;border-left:4px solid #0073aa;margin:20px 0;">';
        echo '<strong>💡 Exam Tip:</strong> ' . esc_html( $tip );
        echo '</div>';
    }
}

/**
 * Get unified AI content with fallback hierarchy
 * Priority: Final → Draft → Legacy → Fallback (empty)
 *
 * @param int $post_id The question post ID
 * @return array {
 *   'description' => string,
 *   'faqs' => array,
 *   'exam_tip' => string,
 *   'source' => string ('final'|'draft'|'legacy'|'none')
 * }
 */
function scp_get_unified_content( int $post_id ): array {
    $cache_key = 'scp_unified_content_' . $post_id;
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $result = [
        'description' => '',
        'faqs' => [],
        'exam_tip' => '',
        'source' => 'none',
    ];

    // Try final content first
    $final_desc = get_post_meta( $post_id, '_scp_description_final', true );
    if ( ! empty( $final_desc ) ) {
        $result['description'] = $final_desc;
        $result['faqs'] = scp_get_ai_faqs_final( $post_id );
        $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
        $result['source'] = 'final';
        set_transient( $cache_key, $result, DAY_IN_SECONDS );
        return $result;
    }

    // Try draft content
    $draft_desc = get_post_meta( $post_id, '_scp_description_draft', true );
    if ( ! empty( $draft_desc ) ) {
        $result['description'] = $draft_desc;
        $result['faqs'] = scp_get_ai_faqs_draft( $post_id );
        $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
        $result['source'] = 'draft';
        set_transient( $cache_key, $result, DAY_IN_SECONDS );
        return $result;
    }

    // Try legacy content
    $legacy_desc = get_post_meta( $post_id, '_scp_description', true );
    if ( ! empty( $legacy_desc ) ) {
        $result['description'] = $legacy_desc;
        $result['faqs'] = scp_get_ai_faqs( $post_id );
        $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
        $result['source'] = 'legacy';
        set_transient( $cache_key, $result, DAY_IN_SECONDS );
        return $result;
    }

    // No content found - cache empty result for 24 hours to avoid repeated checks
    set_transient( $cache_key, $result, DAY_IN_SECONDS );
    return $result;
}

/**
 * Get unified description with fallback hierarchy
 * Priority: Final → Draft → Legacy → empty string
 */
function scp_get_unified_description( int $post_id ): string {
    $content = scp_get_unified_content( $post_id );
    return $content['description'];
}

/**
 * Get unified FAQs with fallback hierarchy
 * Priority: Final → Draft → Legacy → empty array
 */
function scp_get_unified_faqs( int $post_id ): array {
    $content = scp_get_unified_content( $post_id );
    return $content['faqs'];
}

/**
 * Get content source (where the content came from)
 * Returns: 'final', 'draft', 'legacy', or 'none'
 */
function scp_get_content_source( int $post_id ): string {
    $content = scp_get_unified_content( $post_id );
    return $content['source'];
}

/**
 * Check if any AI content exists (unified check)
 * Checks final, draft, and legacy content
 */
function scp_has_unified_content( int $post_id ): bool {
    $content = scp_get_unified_content( $post_id );
    return $content['source'] !== 'none';
}