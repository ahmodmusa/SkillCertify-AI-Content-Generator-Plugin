<?php

namespace SC_AI\ContentGenerator\Services\Queue;

defined( 'ABSPATH' ) || exit;

class DraftQueue {
    private QueueManager $queue_manager;
    private object $draft_generator;

    public function __construct( QueueManager $queue_manager, object $draft_generator ) {
        $this->queue_manager = $queue_manager;
        $this->draft_generator = $draft_generator;
    }

    public function enqueue( int $question_id ): int {
        return $this->queue_manager->enqueue( SC_AI_DRAFT_QUEUE_HOOK, [ 'question_id' => $question_id ] );
    }

    public function enqueueBatch( array $question_ids ): array {
        return $this->queue_manager->enqueueBatch( SC_AI_DRAFT_QUEUE_HOOK, 
            array_map( fn( $id ) => [ 'question_id' => $id ], $question_ids )
        );
    }

    public function process( int $batch_size = 25 ): array {
        global $wpdb;
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'generated' => [],
        ];

        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        
        // Get questions needing draft
        $questions = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID as question_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            WHERE p.post_type = 'scp_question' 
            AND p.post_status = 'publish'
            AND (pr.content_stage = 'none' OR pr.content_stage IS NULL OR pr.content_stage = 'draft')
            AND (pr.status IS NULL OR pr.status != 'done' OR (pr.status = 'failed' AND pr.attempts < 3))
            ORDER BY p.ID ASC
            LIMIT %d
        ", $batch_size ) );

        if ( empty( $questions ) ) {
            return $results;
        }

        foreach ( $questions as $question ) {
            $results['processed']++;
            
            try {
                $result = $this->draft_generator->generate( $question->question_id );
                
                if ( $result['success'] ) {
                    $results['success']++;
                    $results['generated'][] = $question->question_id;
                    error_log( "[SC AI] Draft generated for question #{$question->question_id}" );
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Q#{$question->question_id}: {$result['error']}";
                    error_log( "[SC AI] Draft failed for question #{$question->question_id}: {$result['error']}" );
                }
                
                // Rate limit delay
                sleep( SC_AI_RATE_LIMIT_GROQ );
                
            } catch ( \Exception $e ) {
                $results['failed']++;
                $results['errors'][] = "Q#{$question->question_id}: " . $e->getMessage();
                error_log( "[SC AI] Draft exception for question #{$question->question_id}: " . $e->getMessage() );
            }
        }

        return $results;
    }
}
