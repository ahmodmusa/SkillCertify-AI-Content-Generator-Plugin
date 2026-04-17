<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractGenerator - Base class for content generators
 * Implements template method pattern for content generation workflow
 */
abstract class AbstractGenerator {
    protected object $api_pool;
    protected object $prompt_builder;
    protected object $parser;
    protected object $content_storage;
    protected object $progress_tracker;
    protected object $question_repository;

    public function __construct(
        object $api_pool,
        object $prompt_builder,
        object $parser,
        object $content_storage,
        object $progress_tracker,
        object $question_repository
    ) {
        $this->api_pool = $api_pool;
        $this->prompt_builder = $prompt_builder;
        $this->parser = $parser;
        $this->content_storage = $content_storage;
        $this->progress_tracker = $progress_tracker;
        $this->question_repository = $question_repository;
    }

    /**
     * Generate content for a question
     *
     * @param int $question_id The question ID
     * @return array Result with 'success' and 'error' keys
     */
    public function generate( int $question_id ): array {
        $result = [
            'success' => false,
            'error' => '',
            'provider_used' => '',
        ];

        // Check if content already exists
        if ( $this->contentExists( $question_id ) ) {
            $result['error'] = $this->getAlreadyExistsError();
            return $result;
        }

        // Pre-generation check
        $pre_check_result = $this->preGenerationCheck( $question_id );
        if ( is_string( $pre_check_result ) ) {
            return [
                'success' => false,
                'message' => $pre_check_result,
            ];
        }

        // Mark as processing
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_PROCESSING, $this->getStage() );

        // Get question data
        $question_data = $this->question_repository->getQuestionData( $question_id );
        if ( ! $question_data ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, $this->getStage(), 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        // Build prompt
        $prompt = $this->buildPrompt( $question_data, $pre_check_result );

        // Generate content
        $generated = $this->api_pool->generate( $prompt );
        if ( $generated === false ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, $this->getStage(), 'AI API call failed' );
            $result['error'] = 'AI API call failed';
            return $result;
        }

        // Capture which provider was used
        $result['provider_used'] = $generated['provider_used'];

        // Parse output
        $parsed = $this->parser->parse( $generated['content'] );
        if ( ! $parsed ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, $this->getStage(), 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        // Save content
        $saved = $this->saveContent( $question_id, $parsed );
        if ( ! $saved ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, $this->getStage(), 'Save failed' );
            $result['error'] = 'Failed to save content';
            return $result;
        }

        // Mark as done
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_DONE, $this->getStage() );
        $result['success'] = true;

        return $result;
    }

    public function getProvider(): object {
        return $this->api_pool;
    }

    /**
     * Get the content stage
     *
     * @return string The stage identifier
     */
    abstract protected function getStage(): string;

    /**
     * Check if content already exists for this stage
     *
     * @param int $question_id The question ID
     * @return bool True if content exists
     */
    abstract protected function contentExists( int $question_id ): bool;

    /**
     * Get error message when content already exists
     *
     * @return string Error message
     */
    abstract protected function getAlreadyExistsError(): string;

    /**
     * Pre-generation check
     *
     * @param int $question_id The question ID
     * @return array|string|false Return array of data if needed, or error string if check fails, or false if no check needed
     */
    abstract protected function preGenerationCheck( int $question_id );

    /**
     * Build the prompt for AI generation
     *
     * @param array $question_data The question data
     * @param array|false $pre_check_data Data from pre-generation check
     * @return string The generated prompt
     */
    abstract protected function buildPrompt( array $question_data, $pre_check_data ): string;

    /**
     * Save the generated content
     *
     * @param int $question_id The question ID
     * @param array $parsed The parsed AI output
     * @return bool Success status
     */
    abstract protected function saveContent( int $question_id, array $parsed ): bool;
}
