<?php

namespace SC_AI\ContentGenerator\Services\Generator;

defined( 'ABSPATH' ) || exit;

class FinalGenerator {
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

        // Check if final already exists
        if ( $this->content_storage->hasFinal( $question_id ) ) {
            $result['error'] = 'Final already exists';
            return $result;
        }

        // Check if draft exists
        $draft_data = $this->content_storage->getDraft( $question_id );
        if ( ! $draft_data ) {
            $result['error'] = 'No draft found';
            return $result;
        }

        // Mark as processing
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_PROCESSING, SC_AI_STAGE_FINAL );

        // Get question data
        $question_data = $this->getQuestionData( $question_id );
        if ( ! $question_data ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_FINAL, 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        // Build polish prompt
        $prompt = $this->prompt_builder->build( $question_data, $draft_data['description'], $draft_data['faqs'] );

        // Generate content
        $generated = $this->api_pool->generate( $prompt );
        if ( $generated === false ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_FINAL, 'AI API call failed' );
            $result['error'] = 'AI API call failed';
            return $result;
        }

        // Parse output
        $parsed = $this->parser->parse( $generated );
        if ( ! $parsed ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_FINAL, 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        // Save final content
        $saved = $this->content_storage->saveFinal( $question_id, $parsed );
        if ( ! $saved ) {
            $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_FAILED, SC_AI_STAGE_FINAL, 'Save failed' );
            $result['error'] = 'Failed to save content';
            return $result;
        }

        // Mark as done
        $this->progress_tracker->updateStatus( $question_id, SC_AI_STATUS_DONE, SC_AI_STAGE_FINAL );
        $result['success'] = true;

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

        return [
            'title' => $post->post_title,
            'correct_answer' => $correct_answer,
            'explanation' => $explanation,
            'options' => $options,
            'category_name' => $category_name,
            'exam_name' => $exam_name,
        ];
    }

    public function getProvider(): object {
        return $this->api_pool;
    }
}
