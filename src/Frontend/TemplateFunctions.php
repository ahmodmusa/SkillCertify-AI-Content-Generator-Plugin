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
        $cache_key = 'scp_content_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $content = get_post_meta( $post_id, '_scp_ai_description', true ) ?: '';
        set_transient( $cache_key, $content, HOUR_IN_SECONDS );
        return $content;
    }

    public static function getAiFaqs( int $post_id ): array {
        $cache_key = 'scp_faqs_final_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $faqs_raw = get_post_meta( $post_id, '_scp_ai_faqs', true );

        if ( empty( $faqs_raw ) ) {
            return [];
        }

        $faqs = json_decode( $faqs_raw, true );
        if ( ! is_array( $faqs ) ) {
            $faqs = [];
        }

        $result = is_array( $faqs ) ? $faqs : [];
        set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        return $result;
    }

    public static function hasAiContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_ai_description', true );
        $faqs = get_post_meta( $post_id, '_scp_ai_faqs', true );
        $tip = get_post_meta( $post_id, '_scp_ai_exam_tip', true );
        return ! empty( $description ) || ! empty( $faqs ) || ! empty( $tip );
    }

    public static function hasContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_ai_description', true );
        $faqs = get_post_meta( $post_id, '_scp_ai_faqs', true );
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

        // Try new content first
        $desc = get_post_meta( $post_id, '_scp_ai_description', true );
        if ( ! empty( $desc ) ) {
            $result['description'] = $desc;
            $result['faqs'] = self::getAiFaqs( $post_id );
            $result['exam_tip'] = get_post_meta( $post_id, '_scp_ai_exam_tip', true ) ?: '';
            $result['source'] = 'ai';
            set_transient( $cache_key, $result, SC_AI_CACHE_TIME_CONTENT );
            return $result;
        }

        // Try legacy content
        $legacy_desc = get_post_meta( $post_id, '_scp_description', true );
        if ( ! empty( $legacy_desc ) ) {
            $result['description'] = $legacy_desc;
            $result['faqs'] = get_post_meta( $post_id, '_scp_faqs', true );
            if ( is_string( $result['faqs'] ) ) {
                $result['faqs'] = json_decode( $result['faqs'], true );
            }
            $result['faqs'] = is_array( $result['faqs'] ) ? $result['faqs'] : [];
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
