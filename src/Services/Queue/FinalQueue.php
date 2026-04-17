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

    public function process( int $batch_size = 20 ): array {
        global $wpdb;
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'generated' => [],
        ];

        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        // Use batch model for queue processing
        $batch_model = get_option( 'sc_ai_groq_batch_model', 'llama-3.1-8b-instant' );
        $original_model = get_option( 'sc_ai_groq_model' );
        update_option( 'sc_ai_groq_model', $batch_model );

        // Get questions without AI content
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

        if ( empty( $questions ) ) {
            // Restore original model even if no questions to process
            update_option( 'sc_ai_groq_model', $original_model );
            return $results;
        }

        foreach ( $questions as $question ) {
            $results['processed']++;

            try {
                $result = $this->final_generator->generate( $question->question_id );

                if ( $result['success'] ) {
                    $results['success']++;
                    $results['generated'][] = $question->question_id;
                    error_log( "[SC AI] Final generated for question #{$question->question_id}" );
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Q#{$question->question_id}: {$result['error']}";
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

        // Restore original model after processing
        update_option( 'sc_ai_groq_model', $original_model );

        return $results;
    }
}
