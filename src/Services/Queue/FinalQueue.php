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
        
        // Get questions with draft but no final
        $questions = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID as question_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = '_scp_description_draft'
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_scp_description_final'
            WHERE p.post_type = 'scp_question' 
            AND p.post_status = 'publish'
            AND pr.content_stage = 'draft'
            AND pr.status = 'done'
            AND m1.meta_value IS NOT NULL 
            AND m1.meta_value != ''
            AND (m2.meta_id IS NULL OR m2.meta_value = '' OR (pr.status = 'failed' AND pr.attempts < 3))
            ORDER BY p.ID ASC
            LIMIT %d
        ", $batch_size ) );

        if ( empty( $questions ) ) {
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
                
                // Rate limit delay
                sleep( SC_AI_RATE_LIMIT_OPENROUTER );
                
            } catch ( \Exception $e ) {
                $results['failed']++;
                $results['errors'][] = "Q#{$question->question_id}: " . $e->getMessage();
                error_log( "[SC AI] Final exception for question #{$question->question_id}: " . $e->getMessage() );
            }
        }

        return $results;
    }
}
