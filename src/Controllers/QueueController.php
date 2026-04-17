<?php

namespace SC_AI\ContentGenerator\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * QueueController - Handles queue processing via cron hooks
 */
class QueueController {
    private object $container;

    public function __construct( object $container ) {
        $this->container = $container;
    }

    public function boot(): void {
        // Register queue hooks
        add_action( SC_AI_FINAL_QUEUE_HOOK, [ $this, 'handleFinalQueue' ] );
        add_action( SC_AI_RETRY_QUEUE_HOOK, [ $this, 'handleRetryQueue' ] );
    }

    public function handleFinalQueue(): void {
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI] Final cron disabled, skipping' );
            return;
        }

        $batch_size = absint( get_option( 'sc_ai_final_batch_size', 20 ) );
        $final_queue = $this->container->get( 'queue.final' );
        $results = $final_queue->process( $batch_size );

        error_log( sprintf(
            '[SC AI] Final cron: %d processed, %d success, %d failed',
            $results['processed'],
            $results['success'],
            $results['failed']
        ) );

        // Log to cron history
        $this->logCronHistory( 'Final', $results );
    }

    public function handleRetryQueue(): void {
        if ( get_option( 'sc_ai_enable_cron', '1' ) !== '1' ) {
            error_log( '[SC AI] Retry cron disabled, skipping' );
            return;
        }

        $retry_queue = $this->container->get( 'queue.retry' );
        $results = $retry_queue->process( 10 );

        error_log( sprintf(
            '[SC AI] Retry cron: %d processed, %d success, %d failed',
            $results['processed'],
            $results['success'],
            $results['failed']
        ) );

        // Log to cron history
        $this->logCronHistory( 'Retry', $results );
    }

    private function logCronHistory( string $type, array $results ): void {
        $history = get_option( 'sc_ai_cron_history', [] );
        $status = $results['failed'] > 0 ? 'failed' : 'success';

        $entry = [
            'type' => $type,
            'status' => $status,
            'processed' => $results['processed'],
            'success' => $results['success'],
            'failed' => $results['failed'],
            'time' => date( 'M j, g:i A' ),
        ];

        // Add to beginning and keep only last 50
        array_unshift( $history, $entry );
        $history = array_slice( $history, 0, 50 );

        update_option( 'sc_ai_cron_history', $history );
    }
}
