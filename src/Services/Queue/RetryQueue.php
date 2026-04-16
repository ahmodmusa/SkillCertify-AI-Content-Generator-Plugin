<?php

namespace SC_AI\ContentGenerator\Services\Queue;

defined( 'ABSPATH' ) || exit;

class RetryQueue {
    private QueueManager $queue_manager;

    public function __construct( QueueManager $queue_manager ) {
        $this->queue_manager = $queue_manager;
    }

    public function process(): array {
        global $wpdb;
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        
        // Get failed questions with attempts < max
        $questions = $wpdb->get_results( $wpdb->prepare( "
            SELECT question_id, content_stage
            FROM {$progress_table}
            WHERE status = 'failed'
            AND attempts < %d
            ORDER BY generated_at ASC
            LIMIT 50
        ", get_option( 'sc_ai_max_attempts', 3 ) ) );

        if ( empty( $questions ) ) {
            return $results;
        }

        foreach ( $questions as $question ) {
            $results['processed']++;
            
            // Re-enqueue based on stage
            $hook = $question->content_stage === 'final' ? SC_AI_FINAL_QUEUE_HOOK : SC_AI_DRAFT_QUEUE_HOOK;
            $this->queue_manager->enqueue( $hook, [ 'question_id' => $question->question_id ] );
            
            error_log( "[SC AI] Re-enqueued question #{$question->question_id} for {$question->content_stage} stage" );
        }

        $results['success'] = $results['processed'];
        return $results;
    }
}
