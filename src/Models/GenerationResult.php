<?php

namespace SC_AI\ContentGenerator\Models;

defined( 'ABSPATH' ) || exit;

class GenerationResult {
    public bool $success;
    public string $error;
    public int $question_id;
    public string $stage;
    public ?array $data;

    public function __construct(
        bool $success = false,
        string $error = '',
        int $question_id = 0,
        string $stage = '',
        ?array $data = null
    ) {
        $this->success = $success;
        $this->error = $error;
        $this->question_id = $question_id;
        $this->stage = $stage;
        $this->data = $data;
    }

    public static function success( int $question_id, string $stage, ?array $data = null ): self {
        return new self( true, '', $question_id, $stage, $data );
    }

    public static function failure( int $question_id, string $stage, string $error ): self {
        return new self( false, $error, $question_id, $stage );
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'question_id' => $this->question_id,
            'stage' => $this->stage,
            'data' => $this->data,
        ];
    }
}
