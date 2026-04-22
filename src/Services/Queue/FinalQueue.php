<?php

namespace SC_AI\ContentGenerator\Services\Queue;

defined( 'ABSPATH' ) || exit;

class FinalQueue {
    private QueueManager $queue_manager;
    private object $final_generator;

    public function __construct( QueueManager $queue_manager, object $final_generator ) {
        $this->queue_manager = $queue_manager;
        $this->final_generator = $final_generator;
    }

    public function enqueue( int $question_id ): int {
        return $this->queue_manager->enqueue( SC_AI_FINAL_QUEUE_HOOK, [ 'question_id' => $question_id ] );
    }

    public function enqueueBatch( array $question_ids ): array {
        return $this->queue_manager->enqueueBatch( SC_AI_FINAL_QUEUE_HOOK, 
            array_map( fn( $id ) => [ 'question_id' => $id ], $question_ids )
        );
    }

    public function process( int $batch_size = 20, array $specific_ids = [], bool $force_regenerate = false ): array {
        global $wpdb;
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'generated' => [],
        ];

        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        // Use batch provider setting
        $batch_provider = get_option( 'sc_ai_batch_provider', 'groq' );
        $original_provider = get_option( 'sc_ai_primary_provider', 'groq' );
        update_option( 'sc_ai_primary_provider', $batch_provider );

        // Get questions without AI content
        if ( ! empty( $specific_ids ) ) {
            // Process specific IDs from bulk selection
            $placeholders = implode( ',', array_fill( 0, count( $specific_ids ), '%d' ) );
            $questions = $wpdb->get_results( $wpdb->prepare( "
                SELECT p.ID as question_id
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'scp_question'
                AND p.post_status = 'publish'
                AND p.ID IN ( $placeholders )
            ", $specific_ids ) );
        } else {
            // Process next N pending questions
            $questions = $wpdb->get_results( $wpdb->prepare( "
                SELECT p.ID as question_id
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_ai_description'
                WHERE p.post_type = 'scp_question'
                AND p.post_status = 'publish'
                AND (m.meta_id IS NULL OR m.meta_value = '')
                ORDER BY p.ID ASC
                LIMIT %d
            ", $batch_size ) );
        }

        if ( empty( $questions ) ) {
            // Restore original provider even if no questions to process
            update_option( 'sc_ai_primary_provider', $original_provider );
            return $results;
        }

        foreach ( $questions as $question ) {
            $results['processed']++;

            // Delete existing content if force_regenerate is true
            if ( $force_regenerate ) {
                delete_post_meta( $question->question_id, '_scp_ai_description' );
                delete_post_meta( $question->question_id, '_scp_ai_faqs' );
                delete_post_meta( $question->question_id, '_scp_ai_exam_tip' );
                delete_post_meta( $question->question_id, '_scp_description_final' );
                delete_post_meta( $question->question_id, '_scp_faqs_final' );
                delete_post_meta( $question->question_id, '_scp_ai_keypoints' );
                delete_post_meta( $question->question_id, '_scp_ai_mistake' );
                delete_post_meta( $question->question_id, '_scp_ai_tip' );
                wp_update_post( [
                    'ID'           => $question->question_id,
                    'post_content' => '',
                ], false );
            }

            try {
                $result = $this->final_generator->generate( $question->question_id );

                if ( $result['success'] ) {
                    $results['success']++;
                    $results['generated'][] = $question->question_id;
                    error_log( "[SC AI] Final generated for question #{$question->question_id}" );
                } else {
                    // Check if skipped due to existing content
                    if ( strpos( $result['error'], 'Content already exists' ) !== false ) {
                        $results['skipped']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Q#{$question->question_id}: {$result['error']}";
                    }
                    error_log( "[SC AI] Final failed for question #{$question->question_id}: {$result['error']}" );
                }

                // Rate limit delay based on provider used
                $provider_used = $result['provider_used'] ?? 'openrouter';
                $rate_limit = ( $provider_used === 'groq' ) ? SC_AI_RATE_LIMIT_GROQ : SC_AI_RATE_LIMIT_OPENROUTER;
                sleep( $rate_limit );

            } catch ( \Exception $e ) {
                $results['failed']++;
                $results['errors'][] = "Q#{$question->question_id}: " . $e->getMessage();
                error_log( "[SC AI] Final exception for question #{$question->question_id}: " . $e->getMessage() );
            }
        }

        // Restore original provider after processing
        update_option( 'sc_ai_primary_provider', $original_provider );

        return $results;
    }
}
