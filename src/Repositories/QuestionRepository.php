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

        // Get meta values with correct _scp_ prefix
        $correct_answer = get_post_meta( $question_id, '_scp_correct_answer', true );
        $explanation = get_post_meta( $question_id, '_scp_explanation', true );
        // Fallback to post_content if explanation meta is empty
        if ( empty( $explanation ) ) {
            $explanation = get_post_field( 'post_content', $question_id );
        }

        $options = [
            'a' => get_post_meta( $question_id, '_scp_option_a', true ),
            'b' => get_post_meta( $question_id, '_scp_option_b', true ),
            'c' => get_post_meta( $question_id, '_scp_option_c', true ),
            'd' => get_post_meta( $question_id, '_scp_option_d', true ),
        ];

        $category_name = $this->getPrimaryTerm( $question_id, 'scp_category' );
        $difficulty = get_post_meta( $question_id, '_scp_difficulty', true );
        $exam_name = $this->buildExamName( $question_id );
        $existing_content = get_post_meta( $question_id, '_scp_ai_description', true );

        $data = [
            'title' => get_the_title( $question_id ),
            'correct_answer' => $correct_answer,
            'explanation' => $explanation,
            'options' => $options,
            'category_name' => $category_name,
            'difficulty' => $difficulty,
            'exam_name' => $exam_name,
            'existing_content' => $existing_content,
        ];

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );

        return $data;
    }

    private function getPrimaryTerm( int $post_id, string $taxonomy ): string {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( is_array( $terms ) && ! empty( $terms ) ) {
            return $terms[0]->name;
        }
        return '';
    }

    private function buildExamName( int $post_id ): string {
        $category = $this->getPrimaryTerm( $post_id, 'scp_category' );
        $difficulty = get_post_meta( $post_id, '_scp_difficulty', true );

        if ( $category && $difficulty ) {
            return $category . ' — ' . ucfirst( $difficulty ) . ' Level';
        }
        if ( $category ) {
            return $category;
        }
        return 'Certification Exam';
    }

    public function clearCache( int $post_id ): void {
        delete_transient( 'scp_q_data_' . $post_id );
    }

    public function getPendingQuestions( int $limit, string $stage = 'none' ): array {
        global $wpdb;
        $posts_table = $wpdb->posts;

        // Get questions without AI content
        return $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID as question_id
            FROM {$posts_table} p
            LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_ai_description'
            WHERE p.post_type = 'scp_question' 
            AND p.post_status = 'publish'
            AND (m.meta_id IS NULL OR m.meta_value = '')
            ORDER BY p.ID ASC
            LIMIT %d
        ", $limit ) );
    }

    public function getTotalQuestionsCount(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'scp_question' AND post_status = 'publish'
        " );
    }
}
