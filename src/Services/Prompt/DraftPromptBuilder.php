<?php

namespace SC_AI\ContentGenerator\Services\Prompt;

defined( 'ABSPATH' ) || exit;

class DraftPromptBuilder {
    public function build( array $question_data ): string {
        $title = $question_data['title'] ?? '';
        $correct_answer = $question_data['correct_answer'] ?? '';
        $explanation = $question_data['explanation'] ?? '';
        $options = $question_data['options'] ?? [];
        $category_name = $question_data['category_name'] ?? '';
        $exam_name = $question_data['exam_name'] ?? '';
        $existing_content = $question_data['existing_content'] ?? '';

        $wrong_options = array_filter(
            $options,
            fn( $v ) => strtolower( trim( $v ) ) !== strtolower( trim( $correct_answer ) )
        );
        $wrong_list = implode( ', ', array_map(
            fn( $k, $v ) => "{$k}) {$v}",
            array_keys( $wrong_options ),
            $wrong_options
        ) );

        $context_note = '';
        if ( ! empty( $existing_content ) ) {
            $context_note = "\n\nNOTE: This question already has some content. "
                          . "Improve and expand it without repeating the same sentences. "
                          . "Existing content summary: "
                          . wp_trim_words( wp_strip_all_tags( $existing_content ), 30 );
        }

        return <<<PROMPT
You are an expert content writer for a certification exam practice website called SkillCertify.

TASK: Write SEO-friendly educational content for the following exam question.
The content must be unique, factual, and helpful for students preparing for the exam.
Do NOT use generic filler. Every sentence must add real value.{$context_note}

---
EXAM: {$exam_name}
CATEGORY: {$category_name}
QUESTION: {$title}
CORRECT ANSWER: {$correct_answer}
WRONG OPTIONS: {$wrong_list}
EXPLANATION: {$explanation}
---

OUTPUT FORMAT (return exactly this structure, no extra text):

[DESCRIPTION]
## Understanding [Topic]

## Why Other Options Are Incorrect

## Real-World Application in {$exam_name}

[FAQ_1_QUESTION]
Write the exam question exactly as-is

[FAQ_1_ANSWER]
Write a 2-3 sentence answer using the correct answer and explanation

[FAQ_2_QUESTION]
Write: "Why is '{$correct_answer}' the correct answer for this {$category_name} question?"

[FAQ_2_ANSWER]
Write 2-3 sentences explaining why this is correct

[FAQ_3_QUESTION]
Write a "how to remember" or "common mistake" question about this topic

[FAQ_3_ANSWER]
Write 2-3 sentences with a practical memory tip or mistake warning

[EXAM_TIP]
Write 1-2 sentences: a specific exam strategy tip for this exact question

---
Rules:
- No markdown, no asterisks, no bullet symbols in output (except ## headings in description)
- Plain text only inside each tag
- Each section must be unique content, not repetitions
- Mention the specific wrong options by name when explaining mistakes
- Description section: Write 200-250 words under each heading
- Keep total output 600-800 words (description is 600-800 words, FAQs and tips add more)
PROMPT;
    }
}
