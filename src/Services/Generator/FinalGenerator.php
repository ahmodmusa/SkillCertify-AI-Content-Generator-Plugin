<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

class FinalGenerator extends AbstractGenerator {
    protected function getStage(): string {
        return 'done';
    }

    protected function contentExists( int $question_id ): bool {
        return $this->content_storage->hasContent( $question_id );
    }

    protected function getAlreadyExistsError(): string {
        return 'Content already exists';
    }

    protected function preGenerationCheck( int $question_id ) {
        // No pre-generation checks required
        return false;
    }

    protected function buildPrompt( array $question_data, $pre_check_data ): string {
        return $this->prompt_builder->build( $question_data );
    }

    protected function saveContent( int $question_id, array $parsed ): bool {
        return $this->content_storage->save( $question_id, $parsed );
    }
}
