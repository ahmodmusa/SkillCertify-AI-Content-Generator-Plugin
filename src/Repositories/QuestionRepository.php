<?php

namespace SC_AI\ContentGenerator\Repositories;

defined( 'ABSPATH' ) || exit;

class QuestionRepository {
    public function getQuestionData( int $question_id ): ?array {
        $post = get_post( $question_id );
        if ( ! $post || $post->post_type !== 'scp_question' ) {
            return null;
        }

        // Check cache first
        $cache_key = 'scp_q_data_' . $question_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        // Single meta query instead of multiple calls
        $all_meta = get_post_meta( $question_id );
        $correct_answer = $all_meta['correct_answer'][0] ?? '';
        $explanation = $all_meta['explanation'][0] ?? '';
        $options = [
            'a' => $all_meta['option_a'][0] ?? '',
            'b' => $all_meta['option_b'][0] ?? '',
            'c' => $all_meta['option_c'][0] ?? '',
            'd' => $all_meta['option_d'][0] ?? '',
        ];

        $terms = get_the_terms( $question_id, 'scp_category' );
        $category_name = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';

        $exam_terms = get_the_terms( $question_id, 'scp_exam' );
        $exam_name = $exam_terms && ! is_wp_error( $exam_terms ) ? $exam_terms[0]->name : '';

        $existing_content = get_post_field( 'post_content', $question_id );

        $data = [
            'title' => $post->post_title,
            'correct_answer' => $correct_answer,
            'explanation' => $explanation,
            'options' => $options,
            'category_name' => $category_name,
            'exam_name' => $exam_name,
            'existing_content' => $existing_content,
        ];

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );

        return $data;
    }

    public function getPendingQuestions( int $limit, string $stage = 'draft' ): array {
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;

        if ( $stage === 'draft' ) {
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT p.ID as question_id
                FROM {$posts_table} p
                LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
                LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_description_draft'
                WHERE p.post_type = 'scp_question' 
                AND p.post_status = 'publish'
                AND (pr.content_stage = 'none' OR pr.content_stage IS NULL OR pr.content_stage = 'draft')
                AND (m.meta_id IS NULL OR m.meta_value = '' OR (pr.status = 'failed' AND pr.attempts < 3))
                ORDER BY p.ID ASC
                LIMIT %d
            ", $limit ) );
        } elseif ( $stage === 'final' ) {
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT p.ID as question_id
                FROM {$posts_table} p
                LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
                LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_description_draft'
                LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_scp_description_final'
                WHERE p.post_type = 'scp_question' 
                AND p.post_status = 'publish'
                AND pr.content_stage = 'draft'
                AND pr.status = 'done'
                AND m.meta_value IS NOT NULL 
                AND m.meta_value != ''
                AND (m2.meta_id IS NULL OR m2.meta_value = '' OR (pr.status = 'failed' AND pr.attempts < 3))
                ORDER BY p.ID ASC
                LIMIT %d
            ", $limit ) );
        }

        return [];
    }

    public function getTotalQuestionsCount(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'scp_question' AND post_status = 'publish'
        " );
    }
}
