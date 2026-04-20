<?php

namespace SC_AI\ContentGenerator\Services\Prompt;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {
    public function build( array $question_data ): string {
        $title          = $question_data['title'] ?? '';
        $correct_answer = $question_data['correct_answer'] ?? '';
        $explanation    = $question_data['explanation'] ?? '';
        $options        = $question_data['options'] ?? [];
        $category_name  = $question_data['category_name'] ?? '';
        $exam_name      = $question_data['exam_name'] ?? '';
        $difficulty     = $question_data['difficulty'] ?? '';
        $existing       = $question_data['existing_content'] ?? '';

        // Detect if technical/coding topic
        $tech_keywords = [
            'javascript', 'python', 'java', 'php', 'css', 'html',
            'sql', 'react', 'node', 'aws', 'api', 'code', 'function',
            'method', 'class', 'object', 'array', 'database', 'query',
            'programming', 'developer', 'web', 'http', 'rest', 'json',
            'algorithm', 'loop', 'variable', 'syntax', 'framework',
            'library', 'git', 'linux', 'docker', 'kubernetes',
        ];
        $text_to_check  = strtolower( $title . ' ' . $category_name );
        $is_technical   = false;
        foreach ( $tech_keywords as $kw ) {
            if ( strpos( $text_to_check, $kw ) !== false ) {
                $is_technical = true;
                break;
            }
        }

        // Build options list
        $options_list = '';
        foreach ( $options as $k => $v ) {
            if ( ! empty( trim( $v ) ) ) {
                $marker = ( strtolower( trim( $v ) )
                         === strtolower( trim( $correct_answer ) ) )
                         ? ' ← CORRECT' : '';
                $options_list .= strtoupper( $k ) . ') ' . $v
                               . $marker . "\n";
            }
        }

        // Build wrong options
        $wrong = array_filter(
            $options,
            fn( $v ) => ! empty( trim( $v ) )
                     && strtolower( trim( $v ) )
                     !== strtolower( trim( $correct_answer ) )
        );
        $wrong_list = implode( ', ', array_map(
            fn( $k, $v ) => strtoupper( $k ) . ') ' . $v,
            array_keys( $wrong ), $wrong
        ) );

        // For FAQ 3: find the most educationally meaningful
        // comparison concept — prefer real related terms
        // over exam distractors

        // Check if any wrong option looks like a real method/concept
        // (longer than 15 chars = likely a real description,
        //  shorter = likely a distractor label)
        $wrong_vals = array_values( $wrong );
        $confusing = '';
        foreach ( $wrong_vals as $w ) {
            if ( strlen( $w ) > 15 ) {
                $confusing = $w;
                break;
            }
        }

        // If no substantial wrong option found,
        // derive a related concept from the correct answer
        // or category
        if ( empty( $confusing ) ) {
            // Map common categories to their most confused sibling
            $related_map = [
                'javascript' => 'Object.keys() and Object.values()',
                'python'     => 'similar built-in methods',
                'aws'        => 'similar AWS services',
                'css'        => 'similar CSS properties',
                'html'       => 'similar HTML elements',
                'sql'        => 'similar SQL clauses',
                'react'      => 'similar React hooks or methods',
                'java'       => 'similar Java methods',
                'python'     => 'similar Python functions',
                'php'        => 'similar PHP functions',
                'networking' => 'similar networking protocols',
                'security'   => 'similar security concepts',
                'agile'      => 'similar Agile concepts',
                'pmp'        => 'similar project management concepts',
            ];

            $cat_lower  = strtolower( $category_name );
            $confusing  = 'similar alternatives';
            foreach ( $related_map as $key => $related ) {
                if ( strpos( $cat_lower, $key ) !== false ) {
                    $confusing = $related;
                    break;
                }
            }
        }

        // Extract core topic from title
        $topic = $title;
        foreach ( [
            'what is the purpose of the ',
            'what is the purpose of ',
            'what is the ',
            'what are the ',
            'how does the ',
            'how do you ',
            'which of the following ',
            'what does the ',
            'when should you ',
            'why is ',
            'what ',
            'how ',
            'why ',
            'which ',
        ] as $prefix ) {
            if ( stripos( $topic, $prefix ) === 0 ) {
                $topic = substr( $topic, strlen( $prefix ) );
                break;
            }
        }
        $topic = ucfirst( rtrim( $topic, '?' ) );

        // Short correct answer
        $short_correct = strlen( $correct_answer ) > 60
            ? substr( $correct_answer, 0, 57 ) . '...'
            : $correct_answer;

        // Code example instruction
        $code_instruction = $is_technical
            ? 'Include 1-2 short code examples (use <pre><code> tags). '
            . 'Code must be correct and directly relevant to the answer.'
            : 'No code examples needed — use real-world scenarios '
            . 'and analogies instead.';

        // Context note
        $context = '';
        if ( ! empty( $existing ) ) {
            $context = "\nIMPORTANT: Fresh content required. "
                     . "Do NOT reuse these phrases: \""
                     . wp_trim_words(
                         wp_strip_all_tags( $existing ), 15 )
                     . "...\"\n";
        }

        return <<<PROMPT
You are an expert exam content writer.

Generate a SHORT, unique explanation for a multiple-choice question.

Context:
- Topic: {$category_name}
- Difficulty: {$difficulty}

Question:
{$title}

Options:
A) {$options['a']}
B) {$options['b']}
C) {$options['c']}
D) {$options['d']}

Correct Answer:
{$correct_answer}

---

INSTRUCTIONS:

- Make the explanation SPECIFIC to this question
- Do NOT use generic phrases like:
  "This is an important concept" or "In real-world applications"
- Mention the actual concept from the question
- Briefly explain why the correct answer is right
- Briefly hint why others are wrong

- Keep explanation between 40–100 words

- Vary sentence structure across outputs
- Avoid repeating same wording across different questions

---

OUTPUT FORMAT:

[EXPLANATION]
(2–4 sentences, question-specific, no fluff)

[KEY_POINTS]
- Use different wording each time
- Avoid repeating same phrases across questions
- Focus on distinctions, not definitions
- Write 3–5 unique points

[COMMON_MISTAKE]
(1 short sentence, specific confusion)

[EXAM_TIP]
(1 short actionable tip)
PROMPT;
    }
}
