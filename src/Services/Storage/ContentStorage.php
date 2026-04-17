<?php

namespace SC_AI\ContentGenerator\Services\Storage;

defined( 'ABSPATH' ) || exit;

class ContentStorage {
    private array $cache_keys = [
        'scp_fallback_desc_',
        'scp_fallback_faq_',
        'sc_ai_dashboard_stats',
        'sc_ai_activities_',
        'sc_ai_stats_cache',
        'scp_q_data_',
        'scp_content_',
        'scp_faqs_final_',
        'scp_schema_',
        'scp_unified_content_',
    ];

    public function save( int $post_id, array $data ): bool {
        $saved = true;

        if ( ! empty( $data['description'] ) ) {
            $clean_desc = wp_kses_post( $data['description'] );
            // Decode any HTML-encoded quotes back to real chars
            $clean_desc = html_entity_decode( $clean_desc, ENT_QUOTES, 'UTF-8' );
            $saved = update_post_meta( $post_id, '_scp_ai_description', $clean_desc ) && $saved;
            // Sync to parent plugin key
            $saved = update_post_meta( $post_id, '_scp_description_final', $clean_desc ) && $saved;
        }

        if ( ! empty( $data['exam_tip'] ) ) {
            $saved = update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $data['exam_tip'] ) ) && $saved;
        }

        // Build FAQ array for storage
        $faqs = [];
        for ( $i = 1; $i <= 5; $i++ ) {
            $q = $data["faq{$i}_q"] ?? '';
            $a = $data["faq{$i}_a"] ?? '';
            if ( ! empty( $q ) && ! empty( $a ) ) {
                $faqs[] = [
                    'question' => wp_strip_all_tags( $q ),
                    'answer'   => wp_strip_all_tags( $a ),
                ];
            }
        }

        if ( ! empty( $faqs ) ) {
            // Use JSON_HEX_QUOT to escape all double quotes as \u0022
            // This prevents inner quotes from breaking JSON structure
            $json = json_encode( $faqs, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT );
            $saved = update_post_meta( $post_id, '_scp_ai_faqs', $json ) && $saved;
        }

        $this->clearCache( $post_id );
        return $saved;
    }

    public function hasContent( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_ai_description', true );
        return ! empty( $description );
    }

    public function getContent( int $post_id ): ?array {
        $description = get_post_meta( $post_id, '_scp_ai_description', true );
        if ( empty( $description ) ) {
            return null;
        }

        $faqs_raw = get_post_meta( $post_id, '_scp_ai_faqs', true );
        $faqs = is_string( $faqs_raw ) ? json_decode( $faqs_raw, true ) : $faqs_raw;
        $faqs = is_array( $faqs ) ? $faqs : [];

        return [
            'description' => $description,
            'faqs' => $faqs,
        ];
    }

    private function clearCache( int $post_id ): void {
        foreach ( $this->cache_keys as $key ) {
            delete_transient( $key . $post_id );
        }

        // Clear global caches
        delete_transient( SC_AI_CACHE_STATS );
        delete_transient( SC_AI_CACHE_ACTIVITIES . '15' );
    }

    private function sanitizeText( string $text ): string {
        return str_replace(
            [ "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" ],
            [ '"',        '"',        "'",         "'"        ],
            $text
        );
    }
}
