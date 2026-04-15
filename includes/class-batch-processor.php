<?php

class SC_Batch_Processor {

    private SC_AI_Client    $ai;
    private SC_Prompt_Builder $prompt_builder;

    public function __construct() {
        $this->ai             = new SC_AI_Client();
        $this->prompt_builder = new SC_Prompt_Builder();
    }

    /**
     * প্রতিদিন চলে — pending questions থেকে $limit টা নেয়
     * LEGACY FUNCTION - DISABLED to prevent overwriting draft/final content
     */
    public function run( int $limit = 100, bool $skip_generated = true ): array {

        // GUARD: Check if dual pipeline is active (content_stage column exists)
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_ai_progress';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'content_stage'",
            DB_NAME, $table_name
        ) );

        if ( $column_exists ) {
            error_log( '[SC AI LEGACY] Dual pipeline detected, legacy run() disabled to prevent overwriting draft/final content' );
            return [
                'processed' => 0,
                'success'   => 0,
                'failed'    => 0,
                'skipped'   => $limit,
                'errors'    => ['Legacy function disabled - use generate_draft_content() or generate_final_content()'],
            ];
        }

        $results = [
            'processed' => 0,
            'success'   => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'errors'    => [],
        ];

        // Pending questions নাও
        $questions = $this->get_pending_questions( $limit, $skip_generated );

        if ( empty( $questions ) ) {
            $results['skipped'] = $limit;
            return $results;
        }

        foreach ( $questions as $q ) {

            $results['processed']++;

            // Mark as processing
            $this->update_status( $q->question_id, 'processing' );

            // Question data নাও
            $q_data = $this->get_question_data( $q->question_id );

            if ( ! $q_data ) {
                $this->update_status( $q->question_id, 'failed', 'No question data found' );
                $results['failed']++;
                $results['errors'][] = "Q#{$q->question_id}: No question data found";
                error_log( '[SC AI] Q#' . $q->question_id . ' failed: No question data found' );
                continue;
            }

            // আগের content নাও (context হিসেবে)
            $existing = get_post_field( 'post_content', $q->question_id );

            // Prompt তৈরি করো
            $prompt = $this->prompt_builder->build(
                title:            $q_data['title'],
                correct_answer:   $q_data['correct_answer'],
                explanation:      $q_data['explanation'],
                options:          $q_data['options'],
                category_name:    $q_data['category_name'],
                exam_name:        $q_data['exam_name'],
                existing_content: $existing
            );

            // AI call
            error_log( '[SC AI] Q#' . $q->question_id . ' calling AI API...' );
            $generated = $this->ai->generate( $prompt );

            if ( $generated === false ) {
                $this->update_status( $q->question_id, 'failed', 'AI API returned false' );
                $results['failed']++;
                $results['errors'][] = "Q#{$q->question_id}: AI call failed";
                error_log( '[SC AI] Q#' . $q->question_id . ' failed: AI API returned false' );
                continue;
            }

            // Parse করো
            $parsed = $this->parse_output( $generated );

            if ( ! $parsed ) {
                $this->update_status( $q->question_id, 'failed', 'Parse failed: ' . substr( $generated, 0, 100 ) );
                $results['failed']++;
                $results['errors'][] = "Q#{$q->question_id}: Parse failed";
                error_log( '[SC AI] Q#' . $q->question_id . ' failed: Parse failed' );
                continue;
            }

            // Save করো
            $saved = $this->save_content( $q->question_id, $parsed );

            if ( $saved ) {
                $this->update_status( $q->question_id, 'done' );
                $results['success']++;
                error_log( '[SC AI] Q#' . $q->question_id . ' success: Content saved' );
            } else {
                $this->update_status( $q->question_id, 'failed', 'Save failed' );
                $results['failed']++;
                $results['errors'][] = "Q#{$q->question_id}: Save failed";
                error_log( '[SC AI] Q#' . $q->question_id . ' failed: Save failed' );
            }
        }

        return $results;
    }

    /**
     * Generate draft content for a single question
     */
    public function generate_draft_content( int $post_id ): array {
        $result = [
            'success' => false,
            'error'   => '',
        ];

        // Check if draft already exists
        $existing_draft = get_post_meta( $post_id, '_scp_description_draft', true );
        if ( ! empty( $existing_draft ) ) {
            error_log( '[SCP DRAFT] Draft already exists for question: ' . $post_id . ', skipping' );
            $result['error'] = 'Draft already exists';
            return $result;
        }

        // Mark as processing
        $this->update_status_with_stage( $post_id, 'processing', 'draft' );

        // Question data নাও
        $q_data = $this->get_question_data( $post_id );

        if ( ! $q_data ) {
            $this->update_status_with_stage( $post_id, 'failed', 'draft', 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        // আগের content নাও (context হিসেবে)
        $existing = get_post_field( 'post_content', $post_id );

        // Prompt তৈরি করো
        $prompt = $this->prompt_builder->build(
            title:            $q_data['title'],
            correct_answer:   $q_data['correct_answer'],
            explanation:      $q_data['explanation'],
            options:          $q_data['options'],
            category_name:    $q_data['category_name'],
            exam_name:        $q_data['exam_name'],
            existing_content: $existing
        );

        // AI call
        error_log( '[SCP DRAFT] Calling AI API for question: ' . $post_id );
        $generated = $this->ai->generate( $prompt );

        if ( $generated === false ) {
            $this->update_status_with_stage( $post_id, 'failed', 'draft', 'AI API returned false' );
            $result['error'] = 'AI API call failed';
            return $result;
        }

        // Parse করো
        $parsed = $this->parse_output( $generated );

        if ( ! $parsed ) {
            $this->update_status_with_stage( $post_id, 'failed', 'draft', 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        // Save draft content
        $saved = $this->save_draft_content( $post_id, $parsed );

        if ( $saved ) {
            $this->update_status_with_stage( $post_id, 'done', 'draft' );
            $result['success'] = true;
            error_log( '[SCP DRAFT] Generated for question: ' . $post_id );
        } else {
            $this->update_status_with_stage( $post_id, 'failed', 'draft', 'Save failed' );
            $result['error'] = 'Failed to save content';
        }

        return $result;
    }

    /**
     * Generate final content by polishing draft using Gemini API
     */
    public function generate_final_content( int $post_id ): array {
        $result = [
            'success' => false,
            'error'   => '',
        ];

        // Check if final already exists
        $existing_final = get_post_meta( $post_id, '_scp_description_final', true );
        if ( ! empty( $existing_final ) ) {
            error_log( '[SCP FINAL] Final already exists for question: ' . $post_id . ', skipping' );
            $result['error'] = 'Final already exists';
            return $result;
        }

        // Check if draft exists
        $draft_description = get_post_meta( $post_id, '_scp_description_draft', true );
        if ( empty( $draft_description ) ) {
            error_log( '[SCP FINAL] No draft found for question: ' . $post_id );
            $result['error'] = 'No draft found';
            return $result;
        }

        // Mark as processing
        $this->update_status_with_stage( $post_id, 'processing', 'final' );

        // Question data নাও
        $q_data = $this->get_question_data( $post_id );

        if ( ! $q_data ) {
            $this->update_status_with_stage( $post_id, 'failed', 'final', 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        // Get draft FAQs
        $draft_faqs_raw = get_post_meta( $post_id, '_scp_faqs_draft', true );
        $draft_faqs = is_string( $draft_faqs_raw ) ? json_decode( $draft_faqs_raw, true ) : $draft_faqs_raw;
        $draft_faqs = is_array( $draft_faqs ) ? $draft_faqs : [];

        // Build polish prompt using draft content
        $prompt = $this->build_polish_prompt(
            title:            $q_data['title'],
            correct_answer:   $q_data['correct_answer'],
            explanation:      $q_data['explanation'],
            options:          $q_data['options'],
            category_name:    $q_data['category_name'],
            exam_name:        $q_data['exam_name'],
            draft_description: $draft_description,
            draft_faqs:       $draft_faqs
        );

        // AI call using Gemini (force Gemini for polish)
        error_log( '[SCP FINAL] Calling Gemini API for question: ' . $post_id );
        $generated = $this->ai->generate_with_gemini( $prompt );

        if ( $generated === false ) {
            $this->update_status_with_stage( $post_id, 'failed', 'final', 'Gemini API returned false' );
            $result['error'] = 'Gemini API call failed';
            return $result;
        }

        // Parse করো
        $parsed = $this->parse_output( $generated );

        if ( ! $parsed ) {
            $this->update_status_with_stage( $post_id, 'failed', 'final', 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        // Save final content
        $saved = $this->save_final_content( $post_id, $parsed );

        if ( $saved ) {
            $this->update_status_with_stage( $post_id, 'done', 'final' );
            $result['success'] = true;
            error_log( '[SCP FINAL] Generated for question: ' . $post_id );
        } else {
            $this->update_status_with_stage( $post_id, 'failed', 'final', 'Save failed' );
            $result['error'] = 'Failed to save content';
        }

        return $result;
    }

    /**
     * Build polish prompt for improving draft content
     */
    private function build_polish_prompt(
        string $title,
        string $correct_answer,
        string $explanation,
        array  $options,
        string $category_name,
        string $exam_name,
        string $draft_description,
        array  $draft_faqs
    ): string {

        $wrong_options = array_filter(
            $options,
            fn( $v ) => strtolower( trim( $v ) ) !== strtolower( trim( $correct_answer ) )
        );
        $wrong_list = implode( ', ', array_map(
            fn( $k, $v ) => "{$k}) {$v}",
            array_keys( $wrong_options ),
            $wrong_options
        ) );

        $draft_faqs_text = '';
        foreach ( $draft_faqs as $faq ) {
            $q = $faq['question'] ?? '';
            $a = $faq['answer'] ?? '';
            if ( $q && $a ) {
                $draft_faqs_text .= "Q: {$q}\nA: {$a}\n\n";
            }
        }

        return <<<PROMPT
You are an expert content editor for a certification exam practice website called SkillCertify.

TASK: Rewrite and improve the following draft content to make it more professional, engaging, and accurate.
The draft is already good, but enhance it with better flow, clarity, and educational value.

---
EXAM: {$exam_name}
CATEGORY: {$category_name}
QUESTION: {$title}
CORRECT ANSWER: {$correct_answer}
WRONG OPTIONS: {$wrong_list}
EXPLANATION: {$explanation}
---

DRAFT CONTENT TO IMPROVE:

[DESCRIPTION]
{$draft_description}

[FAQS]
{$draft_faqs_text}
---

OUTPUT FORMAT (return exactly this structure, no extra text):

[DESCRIPTION]
## Understanding [Topic]

## Why Other Options Are Incorrect

## Real-World Application in {$exam_name}

[FAQ_1_QUESTION]
Write the exam question exactly as-is

[FAQ_1_ANSWER]
Write a polished 2-3 sentence answer using the correct answer and explanation

[FAQ_2_QUESTION]
Write: "Why is '{$correct_answer}' the correct answer for this {$category_name} question?"

[FAQ_2_ANSWER]
Write 2-3 polished sentences explaining why this is correct

[FAQ_3_QUESTION]
Write a "how to remember" or "common mistake" question about this topic

[FAQ_3_ANSWER]
Write 2-3 polished sentences with a practical memory tip or mistake warning

[EXAM_TIP]
Write 1-2 polished sentences: a specific exam strategy tip for this exact question

---
Rules:
- No markdown, no asterisks, no bullet symbols in output (except ## headings in description)
- Plain text only inside each tag
- Make it more engaging and professional than the draft
- Keep the core information from the draft but improve flow and clarity
- Mention the specific wrong options by name when explaining mistakes
- Description section: Write 200-250 words under each heading
- Keep total output 600-800 words (description is 600-800 words, FAQs and tips add more)
PROMPT;
    }

    /**
     * Save final content to meta fields
     */
    private function save_final_content( int $post_id, array $parsed ): bool {
        update_post_meta( $post_id, '_scp_description_final', wp_kses_post( $parsed['description'] ) );

        $faqs = [
            [ 'question' => $parsed['faq1_q'], 'answer' => $parsed['faq1_a'] ],
            [ 'question' => $parsed['faq2_q'], 'answer' => $parsed['faq2_a'] ],
            [ 'question' => $parsed['faq3_q'], 'answer' => $parsed['faq3_a'] ],
        ];

        $faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['question'] ) && ! empty( $f['answer'] ) );
        update_post_meta( $post_id, '_scp_faqs_final', json_encode( array_values( $faqs ) ) );
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $parsed['exam_tip'] ) );

        // Clear cache
        delete_transient( 'scp_fallback_desc_' . $post_id );
        delete_transient( 'scp_fallback_faq_' . $post_id );
        delete_transient( 'sc_ai_dashboard_stats' );
        delete_transient( 'sc_ai_activities_15' );
        delete_transient( 'sc_ai_stats_cache' );
        delete_transient( 'scp_q_data_' . $post_id );
        delete_transient( 'scp_content_' . $post_id );
        delete_transient( 'scp_faqs_final_' . $post_id );
        delete_transient( 'scp_schema_' . $post_id );
        delete_transient( 'scp_unified_content_' . $post_id );

        return true;
    }

    /**
     * Save draft content to meta fields
     */
    private function save_draft_content( int $post_id, array $parsed ): bool {
        update_post_meta( $post_id, '_scp_description_draft', wp_kses_post( $parsed['description'] ) );

        $faqs = [
            [ 'question' => $parsed['faq1_q'], 'answer' => $parsed['faq1_a'] ],
            [ 'question' => $parsed['faq2_q'], 'answer' => $parsed['faq2_a'] ],
            [ 'question' => $parsed['faq3_q'], 'answer' => $parsed['faq3_a'] ],
        ];

        $faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['question'] ) && ! empty( $f['answer'] ) );
        update_post_meta( $post_id, '_scp_faqs_draft', json_encode( array_values( $faqs ) ) );
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $parsed['exam_tip'] ) );

        // Clear cache
        delete_transient( 'scp_fallback_desc_' . $post_id );
        delete_transient( 'scp_fallback_faq_' . $post_id );
        delete_transient( 'sc_ai_dashboard_stats' );
        delete_transient( 'sc_ai_activities_15' );
        delete_transient( 'sc_ai_stats_cache' );
        delete_transient( 'scp_q_data_' . $post_id );
        delete_transient( 'scp_content_' . $post_id );
        delete_transient( 'scp_faqs_final_' . $post_id );
        delete_transient( 'scp_schema_' . $post_id );
        delete_transient( 'scp_unified_content_' . $post_id );

        return true;
    }

    /**
     * Update status with content stage
     */
    private function update_status_with_stage( int $question_id, string $status, string $stage, string $error = '' ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "
            INSERT INTO {$wpdb->prefix}sc_ai_progress
                (question_id, status, content_stage, attempts, generated_at, error_msg)
            VALUES
                (%d, %s, %s, 1, %s, %s)
            ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                content_stage = VALUES(content_stage),
                attempts     = attempts + 1,
                generated_at = VALUES(generated_at),
                error_msg    = VALUES(error_msg)
        ", $question_id, $status, $stage, current_time( 'mysql' ), $error ) );
    }

    /**
     * Generate content for a single question (manual trigger)
     * LEGACY FUNCTION - DISABLED to prevent overwriting draft/final content
     */
    public function generate_single( int $post_id ): array {
        $result = [
            'success' => false,
            'error'   => '',
        ];

        // GUARD: Check if dual pipeline is active (content_stage column exists)
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_ai_progress';
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'content_stage'",
            DB_NAME, $table_name
        ) );

        if ( $column_exists ) {
            error_log( '[SC AI LEGACY] Dual pipeline detected, legacy generate_single() disabled for question: ' . $post_id );
            $result['error'] = 'Legacy function disabled - use generate_draft_content() or generate_final_content()';
            return $result;
        }

        $this->update_status( $post_id, 'processing' );
        $q_data = $this->get_question_data( $post_id );

        if ( ! $q_data ) {
            $this->update_status( $post_id, 'failed', 'No question data found' );
            $result['error'] = 'No question data found';
            return $result;
        }

        $existing = get_post_field( 'post_content', $post_id );
        $prompt = $this->prompt_builder->build(
            title:            $q_data['title'],
            correct_answer:   $q_data['correct_answer'],
            explanation:      $q_data['explanation'],
            options:          $q_data['options'],
            category_name:    $q_data['category_name'],
            exam_name:        $q_data['exam_name'],
            existing_content: $existing
        );

        $generated = $this->ai->generate( $prompt );

        if ( $generated === false ) {
            $this->update_status( $post_id, 'failed', 'AI API returned false' );
            $result['error'] = 'AI API call failed';
            return $result;
        }

        $parsed = $this->parse_output( $generated );

        if ( ! $parsed ) {
            $this->update_status( $post_id, 'failed', 'Parse failed' );
            $result['error'] = 'Failed to parse AI output';
            return $result;
        }

        $saved = $this->save_content( $post_id, $parsed );

        if ( $saved ) {
            $this->update_status( $post_id, 'done' );
            $result['success'] = true;
        } else {
            $this->update_status( $post_id, 'failed', 'Save failed' );
            $result['error'] = 'Failed to save content';
        }

        return $result;
    }

    // ========== HELPER METHODS ==========

    private function get_pending_questions( int $limit, bool $skip_generated ): array {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'sc_ai_progress';
        $posts_table = $wpdb->posts;

        if ( $skip_generated ) {
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT p.ID as question_id
                FROM {$posts_table} p
                LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
                WHERE p.post_type = 'scp_question' 
                AND p.post_status = 'publish'
                AND (pr.status IS NULL OR pr.status != 'done')
                ORDER BY p.ID ASC
                LIMIT %d
            ", $limit ) );
        }

        return $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID as question_id
            FROM {$posts_table} p
            WHERE p.post_type = 'scp_question' 
            AND p.post_status = 'publish'
            ORDER BY p.ID ASC
            LIMIT %d
        ", $limit ) );
    }

    private function get_question_data( int $post_id ): ?array {
        // Check cache first
        $cache_key = 'scp_q_data_' . $post_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'scp_question' ) {
            return null;
        }

        // Single meta query instead of 5 separate calls
        $all_meta = get_post_meta( $post_id );
        $correct_answer = $all_meta['correct_answer'][0] ?? '';
        $explanation = $all_meta['explanation'][0] ?? '';
        $options = [
            'a' => $all_meta['option_a'][0] ?? '',
            'b' => $all_meta['option_b'][0] ?? '',
            'c' => $all_meta['option_c'][0] ?? '',
            'd' => $all_meta['option_d'][0] ?? '',
        ];

        $terms = get_the_terms( $post_id, 'scp_category' );
        $category_name = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';

        $exam_terms = get_the_terms( $post_id, 'scp_exam' );
        $exam_name = $exam_terms && ! is_wp_error( $exam_terms ) ? $exam_terms[0]->name : '';

        $data = [
            'title'          => $post->post_title,
            'correct_answer' => $correct_answer,
            'explanation'    => $explanation,
            'options'        => $options,
            'category_name'  => $category_name,
            'exam_name'      => $exam_name,
        ];

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );

        return $data;
    }

    private function parse_output( string $output ): ?array {
        $description = '';
        $faq1_q = '';
        $faq1_a = '';
        $faq2_q = '';
        $faq2_a = '';
        $faq3_q = '';
        $faq3_a = '';
        $exam_tip = '';

        // Extract description
        if ( preg_match( '/\[DESCRIPTION\](.*?)\[FAQ_1_QUESTION\]/s', $output, $matches ) ) {
            $description = trim( $matches[1] );
            // Convert markdown headings to HTML
            $description = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $description );
        }

        // Extract FAQ 1
        if ( preg_match( '/\[FAQ_1_QUESTION\](.*?)\[FAQ_1_ANSWER\]/s', $output, $matches ) ) {
            $faq1_q = trim( $matches[1] );
        }
        if ( preg_match( '/\[FAQ_1_ANSWER\](.*?)\[FAQ_2_QUESTION\]/s', $output, $matches ) ) {
            $faq1_a = trim( $matches[1] );
        }

        // Extract FAQ 2
        if ( preg_match( '/\[FAQ_2_QUESTION\](.*?)\[FAQ_2_ANSWER\]/s', $output, $matches ) ) {
            $faq2_q = trim( $matches[1] );
        }
        if ( preg_match( '/\[FAQ_2_ANSWER\](.*?)\[FAQ_3_QUESTION\]/s', $output, $matches ) ) {
            $faq2_a = trim( $matches[1] );
        }

        // Extract FAQ 3
        if ( preg_match( '/\[FAQ_3_QUESTION\](.*?)\[FAQ_3_ANSWER\]/s', $output, $matches ) ) {
            $faq3_q = trim( $matches[1] );
        }
        if ( preg_match( '/\[FAQ_3_ANSWER\](.*?)\[EXAM_TIP\]/s', $output, $matches ) ) {
            $faq3_a = trim( $matches[1] );
        }

        // Extract exam tip
        if ( preg_match( '/\[EXAM_TIP\](.*)$/s', $output, $matches ) ) {
            $exam_tip = trim( $matches[1] );
        }

        if ( empty( $description ) ) {
            error_log( '[AI DEBUG] Parse failed - description empty. Raw output: ' . substr( $output, 0, 500 ) );
            return null;
        }

        return [
            'description' => $description,
            'faq1_q'      => $faq1_q,
            'faq1_a'      => $faq1_a,
            'faq2_q'      => $faq2_q,
            'faq2_a'      => $faq2_a,
            'faq3_q'      => $faq3_q,
            'faq3_a'      => $faq3_a,
            'exam_tip'    => $exam_tip,
        ];
    }

    private function save_content( int $post_id, array $parsed ): bool {
        update_post_meta( $post_id, '_scp_description', wp_kses_post( $parsed['description'] ) );

        $faqs = [
            [ 'question' => $parsed['faq1_q'], 'answer' => $parsed['faq1_a'] ],
            [ 'question' => $parsed['faq2_q'], 'answer' => $parsed['faq2_a'] ],
            [ 'question' => $parsed['faq3_q'], 'answer' => $parsed['faq3_a'] ],
        ];

        $faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['question'] ) && ! empty( $f['answer'] ) );
        update_post_meta( $post_id, '_scp_faqs', json_encode( array_values( $faqs ) ) );
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_text_field( $parsed['exam_tip'] ) );

        delete_transient( 'scp_fallback_desc_' . $post_id );
        delete_transient( 'scp_fallback_faq_' . $post_id );

        return true;
    }

    private function update_status( int $question_id, string $status, string $error = '' ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "
            INSERT INTO {$wpdb->prefix}sc_ai_progress
                (question_id, status, attempts, generated_at, error_msg)
            VALUES
                (%d, %s, 1, %s, %s)
            ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                attempts     = attempts + 1,
                generated_at = VALUES(generated_at),
                error_msg    = VALUES(error_msg)
        ", $question_id, $status, current_time( 'mysql' ), $error ) );
    }
}

// Log integration readiness
error_log('[SC AI] Plugin integrated with Skill Certify Pro meta fields (_scp_description, _scp_faqs)');
error_log('[SC AI] Performance optimization: Fallback cache clearing enabled');
