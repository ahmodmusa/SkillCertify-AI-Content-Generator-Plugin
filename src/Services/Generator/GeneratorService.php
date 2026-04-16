<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

class GeneratorService {
    private object $draft_generator;
    private object $final_generator;
    private ?object $draft_queue;
    private object $final_queue;
    private object $retry_queue;

    public function __construct(
        object $draft_generator,
        object $final_generator,
        ?object $draft_queue,
        object $final_queue,
        object $retry_queue
    ) {
        $this->draft_generator = $draft_generator;
        $this->final_generator = $final_generator;
        $this->draft_queue = $draft_queue;
        $this->final_queue = $final_queue;
        $this->retry_queue = $retry_queue;
    }

    public function generateDraft( int $question_id ): array {
        return $this->draft_generator->generate( $question_id );
    }

    public function generateFinal( int $question_id ): array {
        return $this->final_generator->generate( $question_id );
    }

    public function generateDirect( int $question_id ): array {
        // Generate draft first
        $draft_result = $this->draft_generator->generate( $question_id );
        if ( ! $draft_result['success'] ) {
            return $draft_result;
        }

        // Immediately generate final (skip queue)
        return $this->final_generator->generate( $question_id );
    }

    public function enqueueDraftBatch( array $question_ids ): array {
        // Draft queue removed, return empty array
        return ['enqueued' => 0];
    }

    public function enqueueFinalBatch( array $question_ids ): array {
        return $this->final_queue->enqueueBatch( $question_ids );
    }

    public function processDraftBatch( int $batch_size ): array {
        // Draft queue removed, return empty result
        return ['processed' => 0, 'success' => 0, 'failed' => 0, 'generated' => []];
    }

    public function processFinalBatch( int $batch_size ): array {
        return $this->final_queue->process( $batch_size );
    }

    public function processRetryBatch(): array {
        return $this->retry_queue->process();
    }
}
