<?php

namespace SC_AI\ContentGenerator\Services\Storage;

defined( 'ABSPATH' ) || exit;

class ProgressTracker {
    private object $progress_repository;

    public function __construct( object $progress_repository ) {
        $this->progress_repository = $progress_repository;
    }

    public function updateStatus( int $question_id, string $status, string $stage, string $error = '' ): void {
        // Business logic: clear cache before update to show real-time updates
        delete_transient( SC_AI_CACHE_STATS );
        delete_transient( SC_AI_CACHE_ACTIVITIES );

        // Delegate DB operation to repository
        $this->progress_repository->upsertProgress( $question_id, $status, $stage, $error );
    }

    public function getStatus( int $question_id ): ?array {
        return $this->progress_repository->getProgress( $question_id );
    }

    public function resetStuck(): int {
        return $this->progress_repository->resetStuckProcessing();
    }
}
