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

    public function saveDraft( int $post_id, array $parsed ): bool {
        update_post_meta( $post_id, '_scp_description_draft', wp_kses_post( $parsed['description'] ) );

        $faqs = [
            [ 'question' => $parsed['faq1_q'], 'answer' => $parsed['faq1_a'] ],
            [ 'question' => $parsed['faq2_q'], 'answer' => $parsed['faq2_a'] ],
            [ 'question' => $parsed['faq3_q'], 'answer' => $parsed['faq3_a'] ],
        ];

        $faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['question'] ) && ! empty( $f['answer'] ) );
        update_post_meta( $post_id, '_scp_faqs_draft', json_encode( array_values( $faqs ) ) );
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $parsed['exam_tip'] ) );

        $this->clearCache( $post_id );
        return true;
    }

    public function saveFinal( int $post_id, array $parsed ): bool {
        update_post_meta( $post_id, '_scp_description_final', wp_kses_post( $parsed['description'] ) );

        $faqs = [
            [ 'question' => $parsed['faq1_q'], 'answer' => $parsed['faq1_a'] ],
            [ 'question' => $parsed['faq2_q'], 'answer' => $parsed['faq2_a'] ],
            [ 'question' => $parsed['faq3_q'], 'answer' => $parsed['faq3_a'] ],
        ];

        $faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['question'] ) && ! empty( $f['answer'] ) );
        update_post_meta( $post_id, '_scp_faqs_final', json_encode( array_values( $faqs ) ) );
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $parsed['exam_tip'] ) );

        $this->clearCache( $post_id );
        return true;
    }

    public function hasDraft( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_description_draft', true );
        return ! empty( $description );
    }

    public function hasFinal( int $post_id ): bool {
        $description = get_post_meta( $post_id, '_scp_description_final', true );
        return ! empty( $description );
    }

    public function getDraft( int $post_id ): ?array {
        $description = get_post_meta( $post_id, '_scp_description_draft', true );
        if ( empty( $description ) ) {
            return null;
        }

        $faqs_raw = get_post_meta( $post_id, '_scp_faqs_draft', true );
        $faqs = is_string( $faqs_raw ) ? json_decode( $faqs_raw, true ) : $faqs_raw;
        $faqs = is_array( $faqs ) ? $faqs : [];

        return [
            'description' => $description,
            'faqs' => $faqs,
        ];
    }

    public function getFinal( int $post_id ): ?array {
        $description = get_post_meta( $post_id, '_scp_description_final', true );
        if ( empty( $description ) ) {
            return null;
        }

        $faqs_raw = get_post_meta( $post_id, '_scp_faqs_final', true );
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
}
