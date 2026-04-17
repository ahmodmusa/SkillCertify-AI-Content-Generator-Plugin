<?php

namespace SC_AI\ContentGenerator\Frontend;

defined( 'ABSPATH' ) || exit;

class Shortcodes {
    public static function register(): void {
        add_shortcode( 'sc_ai_description', [ __CLASS__, 'renderDescription' ] );
        add_shortcode( 'sc_ai_faqs', [ __CLASS__, 'renderFaqs' ] );
        add_shortcode( 'sc_ai_exam_tip', [ __CLASS__, 'renderExamTip' ] );
    }

    public static function renderDescription( $atts ): string {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(),
        ], $atts );

        $post_id = (int) $atts['post_id'];
        $content = TemplateFunctions::getUnifiedDescription( $post_id );

        return $content ? '<div class="sc-ai-description">' . $content . '</div>' : '';
    }

    public static function renderFaqs( $atts ): string {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(),
        ], $atts );

        $post_id = (int) $atts['post_id'];
        $faqs = TemplateFunctions::getUnifiedFaqs( $post_id );

        if ( empty( $faqs ) ) {
            return '';
        }

        $output = '<div class="sc-ai-faqs">';
        foreach ( $faqs as $faq ) {
            $question = $faq['q'] ?? $faq['question'] ?? '';
            $answer = $faq['a'] ?? $faq['answer'] ?? '';
            if ( $question && $answer ) {
                $output .= '<div class="sc-ai-faq-item">';
                $output .= '<h3>' . esc_html( $question ) . '</h3>';
                $output .= '<p>' . esc_html( $answer ) . '</p>';
                $output .= '</div>';
            }
        }
        $output .= '</div>';

        return $output;
    }

    public static function renderExamTip( $atts ): string {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(),
        ], $atts );

        $post_id = (int) $atts['post_id'];
        $tip = TemplateFunctions::getAiExamTip( $post_id );

        if ( ! $tip ) {
            return '';
        }

        return '<div class="sc-ai-exam-tip" style="background:#e7f3ff;padding:15px;border-radius:8px;border-left:4px solid #0073aa;margin:20px 0;">'
            . '<strong>💡 Exam Tip:</strong> ' . esc_html( $tip )
            . '</div>';
    }
}
