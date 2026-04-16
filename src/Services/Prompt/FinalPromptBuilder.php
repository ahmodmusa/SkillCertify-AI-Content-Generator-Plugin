<?php

namespace SC_AI\ContentGenerator\Services\Prompt;

defined( 'ABSPATH' ) || exit;

class FinalPromptBuilder {
    public function build( array $question_data, string $draft_description, array $draft_faqs ): string {
        $title = $question_data['title'] ?? '';
        $correct_answer = $question_data['correct_answer'] ?? '';
        $explanation = $question_data['explanation'] ?? '';
        $options = $question_data['options'] ?? [];
        $category_name = $question_data['category_name'] ?? '';
        $exam_name = $question_data['exam_name'] ?? '';

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
}
