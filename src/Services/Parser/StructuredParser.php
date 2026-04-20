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

        // Extract new sections
        $explanation = $this->extractSection( $output, 'EXPLANATION' );
        $keypoints_raw = $this->extractSection( $output, 'KEY_POINTS' );
        $mistake = $this->extractSection( $output, 'COMMON_MISTAKE' );
        $tip = $this->extractSection( $output, 'EXAM_TIP' );

        // Fallback: If no [EXPLANATION] tag, extract content before KEY_POINTS
        if ( empty( $explanation ) ) {
            $explanation = $this->extractExplanationFallback( $output );
            // If still no keypoints with tags, try without tags
            if ( empty( $keypoints_raw ) ) {
                $keypoints_raw = $this->extractSectionFallback( $output, 'KEY_POINTS' );
            }
            if ( empty( $mistake ) ) {
                $mistake = $this->extractSectionFallback( $output, 'COMMON_MISTAKE' );
            }
            if ( empty( $tip ) ) {
                $tip = $this->extractSectionFallback( $output, 'EXAM_TIP' );
            }
        }

        // Parse keypoints into array
        $keypoints = [];
        if ( ! empty( $keypoints_raw ) ) {
            $lines = explode( "\n", trim( $keypoints_raw ) );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                // Support both "-" and "*" bullets
                if ( ! empty( $line ) && ( strpos( $line, '-' ) === 0 || strpos( $line, '*' ) === 0 ) ) {
                    $keypoints[] = trim( substr( $line, 1 ) );
                }
            }
        }

        // Validation - check explanation exists
        if ( empty( $explanation ) ) {
            error_log( '[SC AI] Parse failed - explanation empty. Raw output: ' . substr( $output, 0, 500 ) );
            return null;
        }

        return [
            'explanation' => $explanation,
            'keypoints'   => $keypoints,
            'mistake'     => $mistake,
            'tip'         => $tip,
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

    private function extractExplanationFallback( string $output ): string {
        // Extract everything before KEY_POINTS (with or without brackets)
        $pattern = '/^(.*?)\n?\s*(?:\[?KEY_POINTS\]?|\[?KEYPOINTS\]?)/is';
        if ( preg_match( $pattern, $output, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    private function extractSectionFallback( string $output, string $tag ): string {
        // Extract content after TAG (without brackets) until next section
        $tag_upper = strtoupper( $tag );
        $pattern = '/\n?\s*(?:\[?' . preg_quote( $tag_upper, '/' ) . '\]?)\s*\n(.*?)(?:\n\s*(?:\[?[A-Z_0-9]+\]?|\n)|$)/is';
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
