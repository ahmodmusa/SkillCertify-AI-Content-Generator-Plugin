<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

class GeneratorService {
    private object $final_generator;
    private object $final_queue;
    private object $retry_queue;

    public function __construct(
        object $final_generator,
        object $final_queue,
        object $retry_queue
    ) {
        $this->final_generator = $final_generator;
        $this->final_queue = $final_queue;
        $this->retry_queue = $retry_queue;
    }

    public function generate( int $question_id ): array {
        return $this->final_generator->generate( $question_id );
    }

    public function enqueueFinalBatch( array $question_ids ): array {
        return $this->final_queue->enqueueBatch( $question_ids );
    }

    public function processFinalBatch( int $batch_size ): array {
        return $this->final_queue->process( $batch_size );
    }

    public function processRetryBatch(): array {
        return $this->retry_queue->process();
    }
}
