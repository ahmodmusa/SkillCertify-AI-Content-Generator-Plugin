<?php

namespace SC_AI\ContentGenerator\Frontend;

defined( 'ABSPATH' ) || exit;

class TemplateFunctions {
    public static function outputAiFaqSchema( int $post_id ): void {
        $cache_key = 'scp_schema_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            echo $cached;
            return;
        }

        $faqs = self::getUnifiedFaqs( $post_id );

        if ( empty( $faqs ) ) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map( fn( $f ) => [
                '@type' => 'Question',
                'name' => $f['q'] ?? $f['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $f['a'] ?? $f['answer'] ?? '',
                ],
            ], $faqs ),
        ];

        $output = '<script type="application/ld+json">'
           . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
           . '</script>';

        set_transient( $cache_key, $output, HOUR_IN_SECONDS );
        echo $output;
    }

    public static function getAiExamTip( int $post_id ): string {
        return get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
    }

    public static function getAiDescription( int $post_id ): string {
        return get_post_meta( $post_id, '_scp_description', true ) ?: '';
    }

    public static function getAiDescriptionDraft( int $post_id ): string {
        return get_post_meta( $post_id, '_scp_description_draft', true ) ?: '';
    }

    public static function getAiDescriptionFinal( int $post_id ): string {
        $cache_key = 'scp_content_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $content = get_post_meta( $post_id, '_scp_description_final', true ) ?: '';
        set_transient( $cache_key, $content, HOUR_IN_SECONDS );
        return $content;
    }

    public static function getAiFaqs( int $post_id ): array {
        $faqs = get_post_meta( $post_id, '_scp_faqs', true );

        if ( empty( $faqs ) ) {
            return [];
        }

        if ( is_string( $faqs ) ) {
            $faqs = json_decode( $faqs, true );
        }

        return is_array( $faqs ) ? $faqs : [];
    }

    public static function getAiFaqsDraft( int $post_id ): array {
        $faqs = get_post_meta( $post_id, '_scp_faqs_draft', true );

        if ( empty( $faqs ) ) {
            return [];
        }

        if ( is_string( $faqs ) ) {
            $faqs = json_decode( $faqs, true );
        }

        return is_array( $faqs ) ? $faqs : [];
    }

    public static function getAiFaqsFinal( int $post_id ): array {
        $cache_key = 'scp_faqs_final_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $faqs = get_post_meta( $post_id, '_scp_faqs_final', true );

        if ( empty( $faqs ) ) {
            return [];
        }

        if ( is_string( $faqs ) ) {
            $faqs = json_decode( $faqs, true );
        }

        $result = is_array( $faqs ) ? $faqs : [];
        set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        return $result;
    }

    public static function hasAiContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_description', true );
        $faqs = get_post_meta( $post_id, '_scp_faqs', true );
        $tip = get_post_meta( $post_id, '_scp_ai_exam_tip', true );
        return ! empty( $description ) || ! empty( $faqs ) || ! empty( $tip );
    }

    public static function hasDraftContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_description_draft', true );
        $faqs = get_post_meta( $post_id, '_scp_faqs_draft', true );
        return ! empty( $description ) || ! empty( $faqs );
    }

    public static function hasFinalContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_description_final', true );
        $faqs = get_post_meta( $post_id, '_scp_faqs_final', true );
        return ! empty( $description ) || ! empty( $faqs );
    }

    public static function getUnifiedContent( int $post_id ): array {
        $cache_key = SC_AI_CACHE_UNIFIED_CONTENT . $post_id;
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
            $result['faqs'] = self::getAiFaqsFinal( $post_id );
            $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
            $result['source'] = 'final';
            set_transient( $cache_key, $result, SC_AI_CACHE_TIME_CONTENT );
            return $result;
        }

        // Try draft content
        $draft_desc = get_post_meta( $post_id, '_scp_description_draft', true );
        if ( ! empty( $draft_desc ) ) {
            $result['description'] = $draft_desc;
            $result['faqs'] = self::getAiFaqsDraft( $post_id );
            $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
            $result['source'] = 'draft';
            set_transient( $cache_key, $result, SC_AI_CACHE_TIME_CONTENT );
            return $result;
        }

        // Try legacy content
        $legacy_desc = get_post_meta( $post_id, '_scp_description', true );
        if ( ! empty( $legacy_desc ) ) {
            $result['description'] = $legacy_desc;
            $result['faqs'] = self::getAiFaqs( $post_id );
            $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
            $result['source'] = 'legacy';
            set_transient( $cache_key, $result, SC_AI_CACHE_TIME_CONTENT );
            return $result;
        }

        set_transient( $cache_key, $result, SC_AI_CACHE_TIME_CONTENT );
        return $result;
    }

    public static function getUnifiedDescription( int $post_id ): string {
        $content = self::getUnifiedContent( $post_id );
        return $content['description'];
    }

    public static function getUnifiedFaqs( int $post_id ): array {
        $content = self::getUnifiedContent( $post_id );
        return $content['faqs'];
    }

    public static function getContentSource( int $post_id ): string {
        $content = self::getUnifiedContent( $post_id );
        return $content['source'];
    }

    public static function hasUnifiedContent( int $post_id ): bool {
        $content = self::getUnifiedContent( $post_id );
        return $content['source'] !== 'none';
    }

    public static function outputAiExamTip( int $post_id ): void {
        $tip = self::getAiExamTip( $post_id );
        if ( $tip ) {
            echo '<div class="scp-ai-exam-tip" style="background:#e7f3ff;padding:15px;border-radius:8px;border-left:4px solid #0073aa;margin:20px 0;">';
            echo '<strong>💡 Exam Tip:</strong> ' . esc_html( $tip );
            echo '</div>';
        }
    }
}
