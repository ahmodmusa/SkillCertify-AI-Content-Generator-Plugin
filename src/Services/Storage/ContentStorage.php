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
        'scp_explanation_',
        'scp_keypoints_',
        'scp_mistake_',
        'scp_tip_',
    ];

    public function save( int $post_id, array $data ): bool {
        // Debug: Log data being saved
        error_log( '[SC AI] Saving data for post ' . $post_id . ': ' . print_r( $data, true ) );

        // Guard: prevent saving if explanation is empty
        if ( empty( $data['explanation'] ) ) {
            error_log( '[SC AI] ERROR: Explanation missing for post ' . $post_id );
            return false;
        }

        $saved = true;

        // Save explanation to post_content (40-100 words) - use wp_kses_post for HTML
        $clean_explanation = wp_kses_post( $data['explanation'] );
        $saved = wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $clean_explanation,
        ], false ) && $saved;

        // Save keypoints (JSON array)
        if ( ! empty( $data['keypoints'] ) && is_array( $data['keypoints'] ) ) {
            $clean = array_map( 'sanitize_text_field', $data['keypoints'] );
            $json = json_encode( $clean, JSON_UNESCAPED_UNICODE );
            $saved = update_post_meta( $post_id, '_scp_ai_keypoints', $json ) && $saved;
        }

        // Save mistake (1 line)
        if ( ! empty( $data['mistake'] ) ) {
            $saved = update_post_meta( $post_id, '_scp_ai_mistake', sanitize_text_field( $data['mistake'] ) ) && $saved;
        }

        // Save tip (1 line)
        if ( ! empty( $data['tip'] ) ) {
            $saved = update_post_meta( $post_id, '_scp_ai_tip', sanitize_text_field( $data['tip'] ) ) && $saved;
        }

        $this->clearCache( $post_id );
        return $saved;
    }

    public function hasContent( int $post_id ): bool {
        $post = get_post( $post_id );
        $explanation = $post ? $post->post_content : '';
        return ! empty( $explanation );
    }

    public function getContent( int $post_id ): ?array {
        $post = get_post( $post_id );
        $explanation = $post ? $post->post_content : '';
        if ( empty( $explanation ) ) {
            return null;
        }

        $keypoints_raw = get_post_meta( $post_id, '_scp_ai_keypoints', true );
        $keypoints = is_string( $keypoints_raw ) ? json_decode( $keypoints_raw, true ) : $keypoints_raw;
        $keypoints = is_array( $keypoints ) ? $keypoints : [];

        return [
            'explanation' => $explanation,
            'keypoints' => $keypoints,
            'mistake' => get_post_meta( $post_id, '_scp_ai_mistake', true ),
            'tip' => get_post_meta( $post_id, '_scp_ai_tip', true ),
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
