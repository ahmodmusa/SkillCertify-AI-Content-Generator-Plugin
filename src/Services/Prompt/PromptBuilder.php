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
You are a senior technical writer and SEO specialist at SkillCertify,
a certification exam practice platform. Your content appears on
individual exam question pages and must:
1. Rank on Google for the question as a search query
2. Genuinely help students understand the concept and pass the exam
3. Read like it was written by a human subject-matter expert{$context}
===== INPUT DATA =====
EXAM:           {$exam_name}
CATEGORY:       {$category_name}
DIFFICULTY:     {$difficulty}
QUESTION:       {$title}
OPTIONS:
{$options_list}
CORRECT ANSWER: {$correct_answer}
WRONG OPTIONS:  {$wrong_list}
EXPLANATION:    {$explanation}
======================

{$code_instruction}

===== OUTPUT FORMAT =====
Return ONLY the tagged sections below.
No text before [DESCRIPTION]. No commentary. No markdown.
Use only <h2>, <h3>, <pre>, <code> tags inside [DESCRIPTION].
Every section must be completely unique — zero repeated sentences.

CRITICAL TAG RULES:
- Each tag must appear EXACTLY ONCE in your response
- [FAQ_N_QUESTION] is followed by the question text
- [FAQ_N_ANSWER] is followed by the answer text
- Never repeat a tag
- Never use [FAQ_N_QUESTION] where [FAQ_N_ANSWER] should be

Example of CORRECT format:
[FAQ_4_QUESTION]
How is {$topic} used in real-world practice?

[FAQ_4_ANSWER]
First sentence of answer. Second sentence. Third sentence.

[DESCRIPTION]
Target: 650-780 words total across all 5 sections.
Keep each section focused and concise.
Do NOT pad with filler to reach word count.
After [DESCRIPTION], you MUST write all FAQ sections.
The FAQ sections are required — do not skip them.

Write in flowing paragraphs. Varied sentence length.
Active voice. Sound like a human expert.
Never use: "By leveraging", "By understanding",
"It is important to note", "In conclusion",
"This allows developers to", "This is crucial".

<h2>What is {$topic} in {$category_name}?</h2>
150-180 words.
Open with a sentence that naturally contains the exam question
as phrasing — this is the SEO anchor sentence.
Define the concept precisely. Explain what it does, what
problem it solves, and why it exists.
Avoid restating the definition twice.
{$code_instruction}

<h2>How {$topic} Works</h2>
180-200 words.
Explain the internal mechanism or process step by step.
Give a concrete real-world scenario.
{$code_instruction}
If technical: show input → process → output clearly.
If non-technical: use an analogy that makes it click.

<h2>Why "{$short_correct}" is the Correct Answer</h2>
120-150 words.
Start by confirming the correct answer directly.
Explain the technical/conceptual reason it is correct.
Reference the explanation provided.
Connect to actual usage — when does this matter in practice?

<h2>Why {$wrong_list} Are Incorrect</h2>
120-150 words.
Address each wrong option by name.
Explain what each wrong option actually does (or doesn't do).
Focus on the subtle differences that trip students up.
This is where most students lose marks — be precise.

<h2>How to Answer {$category_name} Questions Like This on Exam Day</h2>
80-100 words.
Give one specific elimination strategy for this question type.
Give one memory trick tied to this exact concept.
Mention what the difficulty level ({$difficulty}) means
for how this question is typically worded.
End with a confidence-building sentence.

[FAQ_1_QUESTION]
{$title}

[FAQ_1_ANSWER]
4-5 sentences. Open with the correct answer stated directly.
Explain the mechanism. Give one practical use case.
End with why understanding this matters for the exam.

[FAQ_2_QUESTION]
Why is "{$short_correct}" the correct answer for this {$category_name} question?

[FAQ_2_ANSWER]
4-5 sentences. Technical explanation of correctness.
Name each wrong option ({$wrong_list}) and state precisely
why each fails. Use contrast language (whereas, unlike, instead).

[FAQ_3_QUESTION]
What is the difference between {$topic} and {$confusing}?

[FAQ_3_ANSWER]
4-5 sentences. Clear head-to-head comparison.
What does each one actually do?
Give one concrete example or analogy that makes the
distinction memorable. End with which to use when.

[FAQ_4_QUESTION]
How is {$topic} used in real-world {$category_name} practice?

[FAQ_4_ANSWER]
4-5 sentences. Realistic professional scenario.
Specific — not "it is used in many situations."
Describe the before (problem) and after (solution with this concept).

[FAQ_5_QUESTION]
What mistakes do students commonly make on {$exam_name} questions about {$topic}?

[FAQ_5_ANSWER]
4-5 sentences. Name 2-3 specific mistakes.
Explain why each wrong option ({$wrong_list}) is tempting.
Give one actionable exam-day tip to avoid each mistake.

[EXAM_TIP]
2-3 sentences maximum.
One specific, actionable strategy for this exact question.
Not generic advice — tie it to {$topic} specifically.
If the question has a common trick or trap, name it.

WORD COUNT ENFORCEMENT:
[DESCRIPTION] target: 650-780 words total.
Keep each section focused and concise.
Do NOT pad with filler to reach word count.
After [DESCRIPTION], you MUST write all FAQ sections.
The FAQ sections are required — do not skip them.

===== QUALITY CHECKLIST =====
Before finalizing, verify:
□ [DESCRIPTION] is 1200-1500 words
□ Each FAQ answer is 4-5 sentences
□ No sentence appears in more than one section
□ No filler phrases used
□ Wrong options named explicitly in FAQ_2 and section 4
□ {$category_name} terminology used throughout
□ Reads naturally — varied rhythm, not robotic
PROMPT;
    }
}
