<?php

namespace SC_AI\ContentGenerator\Services\Parser;

defined( 'ABSPATH' ) || exit;

class StructuredParser {
    public function parse( string $output ): ?array {
        $description = '';
        $faq1_q = '';
        $faq1_a = '';
        $faq2_q = '';
        $faq2_a = '';
        $faq3_q = '';
        $faq3_a = '';
        $exam_tip = '';

        // Remove all tags from output first
        $clean_output = preg_replace( '/\[DESCRIPTION\]|\[FAQ_\d_QUESTION\]|\[FAQ_\d_ANSWER\]|\[EXAM_TIP\]/', '', $output );

        // Try structured format first
        if ( preg_match( '/\[DESCRIPTION\](.*?)\[FAQ_1_QUESTION\]/s', $output, $matches ) ) {
            $description = trim( $matches[1] );
            $description = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $description );
        } else {
            // Fallback: Use entire output as description if no tags found
            $description = trim( $clean_output );
            // Convert markdown headers to HTML
            $description = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $description );
            $description = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $description );
            $description = preg_replace( '/^\*\*(.+?)\*\*$/m', '<strong>$1</strong>', $description );
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
            error_log( '[SC AI] Parse failed - description empty. Raw output: ' . substr( $output, 0, 500 ) );
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
}
