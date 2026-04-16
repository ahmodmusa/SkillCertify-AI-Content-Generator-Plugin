<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

class DraftGenerator {
    private object $api_pool;
    private object $prompt_builder;
    private object $parser;
    private object $content_storage;
    private object $progress_tracker;

    public function __construct(
        object $api_pool,
        object $prompt_builder,
        object $parser,
        object $content_storage,
        object $progress_tracker
    ) {
        $this->api_pool = $api_pool;
        $this->prompt_builder = $prompt_builder;
        $this->parser = $parser;
        $this->content_storage = $content_storage;
        $this->progress_tracker = $progress_tracker;
    }

    public function generate( int $question_id ): array {
        $result = [
            'success' => false,
            'error' => '',
        ];

        // Check if draft already exists
        if ( $this->content_storage->hasDraft( $question_id ) ) {
            $result['error'] = 'Draft already exists';
            return $result;
        }

        // Mark as processing
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_PROCESSING, SC_AI_STAGE_DRAFT );

        // Get question data
        $question_data = $this->getQuestionData( $question_id );
        if ( ! $question_data ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_DRAFT, 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        // Build prompt
        $prompt = $this->prompt_builder->build( $question_data );

        // Generate content
        $generated = $this->api_pool->generate( $prompt );
        if ( $generated === false ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_DRAFT, 'AI API call failed' );
            $result['error'] = 'AI API call failed';
            return $result;
        }

        // Parse output
        $parsed = $this->parser->parse( $generated );
        if ( ! $parsed ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_DRAFT, 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        // Save draft content
        $saved = $this->content_storage->saveDraft( $question_id, $parsed );
        if ( ! $saved ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_DRAFT, 'Save failed' );
            $result['error'] = 'Failed to save content';
            return $result;
        }

        // Mark as done
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_DONE, SC_AI_STAGE_DRAFT );
        $result['success'] = true;

        // Check if draft queue is disabled - if so, directly generate final
        if ( get_option( 'sc_ai_enable_draft_queue', '1' ) !== '1' ) {
            // Skip draft stage, go directly to final
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_PROCESSING, SC_AI_STAGE_FINAL );
            // This will be handled by the final generator
        }

        return $result;
    }

    private function getQuestionData( int $question_id ): ?array {
        $post = get_post( $question_id );
        if ( ! $post || $post->post_type !== 'scp_question' ) {
            return null;
        }

        $all_meta = get_post_meta( $question_id );
        $correct_answer = $all_meta['correct_answer'][0] ?? '';
        $explanation = $all_meta['explanation'][0] ?? '';
        $options = [
            'a' => $all_meta['option_a'][0] ?? '',
            'b' => $all_meta['option_b'][0] ?? '',
            'c' => $all_meta['option_c'][0] ?? '',
            'd' => $all_meta['option_d'][0] ?? '',
        ];

        $terms = get_the_terms( $question_id, 'scp_category' );
        $category_name = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';

        $exam_terms = get_the_terms( $question_id, 'scp_exam' );
        $exam_name = $exam_terms && ! is_wp_error( $exam_terms ) ? $exam_terms[0]->name : '';

        $existing_content = get_post_field( 'post_content', $question_id );

        return [
            'title' => $post->post_title,
            'correct_answer' => $correct_answer,
            'explanation' => $explanation,
            'options' => $options,
            'category_name' => $category_name,
            'exam_name' => $exam_name,
            'existing_content' => $existing_content,
        ];
    }

    public function getProvider(): object {
        return $this->api_pool;
    }
}
