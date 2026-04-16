<?php

namespace SC_AI\ContentGenerator\Services\Queue;

defined( 'ABSPATH' ) || exit;

class QueueManager {
    public function enqueue( string $hook, array $args = [] ): int {
        return as_schedule_single_action( time(), $hook, $args );
    }

    public function enqueueBatch( string $hook, array $args_list = [] ): array {
        $scheduled = [];
        
        foreach ( $args_list as $args ) {
            $scheduled[] = $this->enqueue( $hook, $args );
        }
        
        return $scheduled;
    }

    public function isScheduled( string $hook, array $args = [] ): bool {
        return as_next_scheduled_action( $hook, $args ) !== false;
    }

    public function cancel( string $hook, array $args = [] ): bool {
        $action_id = as_next_scheduled_action( $hook, $args );
        if ( $action_id ) {
            return as_unschedule_action( $hook, $args );
        }
        return false;
    }

    public function getQueueCount( string $hook ): int {
        return as_count_scheduled_actions( $hook );
    }
}
