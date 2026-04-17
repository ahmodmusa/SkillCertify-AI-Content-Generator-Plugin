<?php

namespace SC_AI\ContentGenerator\Services\Parser;

defined( 'ABSPATH' ) || exit;

class StructuredParser {
    public function parse( string $output ): ?array {
        // Fix escaped unicode that lost backslash (e.g., u2014 → \u2014)
        $output = preg_replace(
            '/(?<!\\\\)u([0-9a-fA-F]{4})/',
            '\\u$1',
            $output
        );

        // Normalize curly quotes to straight quotes
        $output = $this->normalizeQuotes( $output );

        $description = $this->extractSection( $output, 'DESCRIPTION' );
        $faq1_q      = $this->cleanText( $this->extractSection( $output, 'FAQ_1_QUESTION' ) );
        $faq1_a      = $this->cleanText( $this->extractFaqAnswerFallback( $output, 1 ) );
        $faq2_q      = $this->cleanText( $this->extractSection( $output, 'FAQ_2_QUESTION' ) );
        $faq2_a      = $this->cleanText( $this->extractFaqAnswerFallback( $output, 2 ) );
        $faq3_q      = $this->cleanText( $this->extractSection( $output, 'FAQ_3_QUESTION' ) );
        $faq3_a      = $this->cleanText( $this->extractFaqAnswerFallback( $output, 3 ) );
        $faq4_q      = $this->cleanText( $this->extractSection( $output, 'FAQ_4_QUESTION' ) );
        $faq4_a      = $this->cleanText( $this->extractFaqAnswerFallback( $output, 4 ) );
        $faq5_q      = $this->cleanText( $this->extractSection( $output, 'FAQ_5_QUESTION' ) );
        $faq5_a      = $this->cleanText( $this->extractFaqAnswerFallback( $output, 5 ) );
        $exam_tip    = $this->cleanText( $this->extractSection( $output, 'EXAM_TIP' ) );

        // Convert markdown headers to HTML in description
        $description = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $description );
        $description = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $description );
        $description = preg_replace( '/^\*\*(.+?)\*\*$/m', '<strong>$1</strong>', $description );

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
            'faq4_q'      => $faq4_q,
            'faq4_a'      => $faq4_a,
            'faq5_q'      => $faq5_q,
            'faq5_a'      => $faq5_a,
            'exam_tip'    => $exam_tip,
        ];
    }

    private function extractSection( string $output, string $tag ): string {
        // Find content between [TAG] and next [
        $pattern = '/\[' . preg_quote( $tag, '/' ) . '\]\s*(.*?)'
                 . '(?=\[[A-Z_0-9]+\]|$)/s';
        if ( preg_match( $pattern, $output, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    private function normalizeQuotes( string $text ): string {
        return str_replace(
            [ "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" ],
            [ '"',        '"',        "'",         "'"        ],
            $text
        );
    }

    private function extractFaqAnswerFallback( string $output, int $num ): string {
        // Try FAQ_N_ANSWER first (normal)
        $answer = $this->extractSection( $output, "FAQ_{$num}_ANSWER" );
        if ( ! empty( $answer ) ) {
            return $answer;
        }

        // Fallback: content between second occurrence
        // of FAQ_N_QUESTION and next tag
        $tag = "[FAQ_{$num}_QUESTION]";
        $first = strpos( $output, $tag );
        if ( $first === false ) {
            return '';
        }
        $second = strpos( $output, $tag, $first + 1 );
        if ( $second === false ) {
            return '';
        }

        // Get content after second occurrence
        $start = $second + strlen( $tag );
        $nextTag = strpos( $output, '[', $start );
        if ( $nextTag === false ) {
            return trim( substr( $output, $start ) );
        }
        return trim( substr( $output, $start, $nextTag - $start ) );
    }

    private function cleanText( string $text ): string {
        // Replace straight double quotes used as
        // quotation marks (not JSON delimiters) with
        // Unicode quotation marks that are safe in JSON
        // and display correctly in browsers
        
        // Pattern: word/space before and after the quote
        // indicates it's used as quotation, not delimiter
        $text = preg_replace(
            '/(\s|^)"([^"]+)"(\s|[.,!?;:]|$)/',
            '$1\u201C$2\u201D$3',
            $text
        );
        return trim($text);
    }
}
