<?php

namespace SC_AI\ContentGenerator\Services\Storage;

use SC_AI\ContentGenerator\Services\Deduplication\ExactDuplicateDetector;
use SC_AI\ContentGenerator\Services\Prompt\QuestionGenerationPrompt;

defined( 'ABSPATH' ) || exit;

class QuestionSaver {
    /**
     * Save an approved generated question to WordPress database
     *
     * @param array $question_data Normalized question data
     * @param int $skill_id Term ID of scp_category
     * @param array $audit_metadata Metadata regarding job, provider, review score, etc.
     * @return int|false Post ID on success, false on failure
     */
    public function saveQuestion( array $question_data, int $skill_id, array $audit_metadata = [] ) {
        $post_title   = sanitize_text_field( $question_data['question'] ?? '' );
        $post_content = wp_kses_post( $question_data['explanation'] ?? '' );
        $difficulty   = sanitize_key( $question_data['difficulty'] ?? 'medium' );

        if ( empty( $post_title ) || empty( $post_content ) || empty( $skill_id ) ) {
            error_log( '[SC AI QuestionSaver] Critical validation failure: missing title, content, or skill_id.' );
            return false;
        }

        // 0. Defensive Capacity Guard: Ensure difficulty pool target (30 Easy / 40 Medium / 50 Hard) is not exceeded
        $target_cap = match ( $difficulty ) {
            'easy' => defined( 'SC_AI_POOL_TARGET_EASY' ) ? SC_AI_POOL_TARGET_EASY : 30,
            'hard' => defined( 'SC_AI_POOL_TARGET_HARD' ) ? SC_AI_POOL_TARGET_HARD : 50,
            default => defined( 'SC_AI_POOL_TARGET_MEDIUM' ) ? SC_AI_POOL_TARGET_MEDIUM : 40,
        };

        if ( $this->getPublishedCount( $skill_id, $difficulty ) >= $target_cap ) {
            error_log( "[SC AI QuestionSaver] Capacity limit reached for {$difficulty} in skill #{$skill_id} ({$target_cap}). Rejecting question save." );
            return false;
        }

        $author_id = function_exists( 'get_current_user_id' ) && get_current_user_id() ? get_current_user_id() : 1;

        // 1. Insert scp_question Post
        $post_id = wp_insert_post( [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'scp_question',
            'post_author'  => $author_id,
        ], true );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            error_log( '[SC AI QuestionSaver] Failed to insert post: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error' ) );
            return false;
        }

        try {
            // 2. Assign Taxonomies: scp_category and scp_difficulty
            $cat_res = wp_set_object_terms( $post_id, [ (int) $skill_id ], 'scp_category', false );
            if ( is_wp_error( $cat_res ) || empty( $cat_res ) ) {
                throw new \RuntimeException( 'Failed to assign scp_category taxonomy.' );
            }

            $diff_res = wp_set_object_terms( $post_id, [ $difficulty ], 'scp_difficulty', false );
            if ( is_wp_error( $diff_res ) || empty( $diff_res ) ) {
                throw new \RuntimeException( 'Failed to assign scp_difficulty taxonomy.' );
            }

            // 3. Save Question Options
            $option_a = sanitize_text_field( $question_data['option_a'] ?? '' );
            $option_b = sanitize_text_field( $question_data['option_b'] ?? '' );
            $option_c = sanitize_text_field( $question_data['option_c'] ?? '' );
            $option_d = sanitize_text_field( $question_data['option_d'] ?? '' );

            if ( empty( $option_a ) || empty( $option_b ) || empty( $option_c ) || empty( $option_d ) ) {
                throw new \RuntimeException( 'One or more options are empty.' );
            }

            update_post_meta( $post_id, '_scp_option_a', $option_a );
            update_post_meta( $post_id, '_scp_option_b', $option_b );
            update_post_meta( $post_id, '_scp_option_c', $option_c );
            update_post_meta( $post_id, '_scp_option_d', $option_d );

            // 4. Save Correct Answer (Exact Option Text)
            $correct_text = sanitize_text_field( $question_data['correct_answer'] ?? '' );
            $options_map = [
                'A' => $option_a,
                'B' => $option_b,
                'C' => $option_c,
                'D' => $option_d,
            ];

            // If correct_answer was passed as letter (A/B/C/D), map to exact string
            if ( isset( $options_map[ strtoupper( $correct_text ) ] ) ) {
                $correct_text = $options_map[ strtoupper( $correct_text ) ];
            }

            // Verify correct answer is one of the options
            if ( ! in_array( $correct_text, [ $option_a, $option_b, $option_c, $option_d ], true ) ) {
                throw new \RuntimeException( 'Correct answer does not match any of the 4 options.' );
            }

            update_post_meta( $post_id, '_scp_correct_answer', $correct_text );

            // Save Difficulty fallback meta (easy, medium, hard)
            update_post_meta( $post_id, '_scp_difficulty', $difficulty );

            // 5. Save Educational AI Fields (matching AIContentMetaBox)
            $keypoints = (array) ( $question_data['key_points'] ?? [] );
            $keypoints_clean = array_values( array_filter( array_map( 'sanitize_text_field', $keypoints ) ) );
            $keypoints_json = wp_json_encode( $keypoints_clean, JSON_UNESCAPED_UNICODE );
            update_post_meta( $post_id, '_scp_ai_keypoints', $keypoints_json );

            $common_mistake = sanitize_text_field( $question_data['common_mistake'] ?? '' );
            update_post_meta( $post_id, '_scp_ai_mistake', $common_mistake );

            $exam_tip = sanitize_text_field( $question_data['exam_tip'] ?? '' );
            update_post_meta( $post_id, '_scp_ai_tip', $exam_tip );

            // 6. Store Question Hash for Fast Layer 1 Deduplication
            $hash = ExactDuplicateDetector::computeHash( $post_title );
            update_post_meta( $post_id, '_question_hash', $hash );

            // 8. Store Comprehensive AI Audit Metadata
            update_post_meta( $post_id, '_ai_generated', 1 );
            update_post_meta( $post_id, '_generation_date', current_time( 'mysql' ) );
            update_post_meta( $post_id, '_prompt_version', QuestionGenerationPrompt::PROMPT_VERSION );

            if ( isset( $audit_metadata['job_id'] ) ) {
                update_post_meta( $post_id, '_generation_job_id', absint( $audit_metadata['job_id'] ) );
            }
            if ( isset( $audit_metadata['provider'] ) ) {
                update_post_meta( $post_id, '_generation_provider', sanitize_text_field( $audit_metadata['provider'] ) );
            }
            if ( isset( $audit_metadata['model'] ) ) {
                update_post_meta( $post_id, '_generation_model', sanitize_text_field( $audit_metadata['model'] ) );
            }
            if ( isset( $audit_metadata['review_provider'] ) ) {
                update_post_meta( $post_id, '_review_provider', sanitize_text_field( $audit_metadata['review_provider'] ) );
            }
            if ( isset( $audit_metadata['review_model'] ) ) {
                update_post_meta( $post_id, '_review_model', sanitize_text_field( $audit_metadata['review_model'] ) );
            }
            if ( isset( $audit_metadata['review_score'] ) ) {
                update_post_meta( $post_id, '_review_score', intval( $audit_metadata['review_score'] ) );
            }

            // 8. Canonical Taxonomy Mapping (SkillCertify Pro Canonical Hierarchy Integration)
            $this->mapCanonicalTaxonomy( (int) $post_id, $skill_id, $difficulty, $question_data );

            // 9. Clear Transients
            delete_transient( 'scp_q_data_' . $post_id );
            delete_transient( 'scp_explanation_' . $post_id );
            delete_transient( 'scp_keypoints_' . $post_id );
            delete_transient( 'scp_mistake_' . $post_id );
            delete_transient( 'scp_tip_' . $post_id );
            delete_transient( 'scp_content_' . $post_id );
            delete_transient( 'scp_unified_content_' . $post_id );

            if ( defined( 'SC_AI_CACHE_STATS' ) ) {
                delete_transient( SC_AI_CACHE_STATS );
            }
            delete_transient( 'sc_ai_pool_stats_' . $skill_id );

            return (int) $post_id;

        } catch ( \Throwable $e ) {
            // Atomic Rollback Protection: Delete incomplete post if any step failed
            error_log( "[SC AI QuestionSaver] Rollback triggered for post #{$post_id}: " . $e->getMessage() );
            wp_delete_post( $post_id, true );
            return false;
        }
    }

    /**
     * Map newly created question to Canonical Taxonomy (Sector -> Domain -> Skill -> Topic -> Subtopic)
     *
     * @param int    $post_id       WordPress Post ID of scp_question
     * @param int    $skill_id      Legacy scp_category Term ID
     * @param string $difficulty    Question difficulty (easy, medium, hard)
     * @param array  $question_data Question payload including topic, subtopic
     * @return bool True if successfully mapped to canonical taxonomy, false otherwise
     */
    private function mapCanonicalTaxonomy( int $post_id, int $skill_id, string $difficulty, array $question_data ): bool {
        if ( ! class_exists( '\SCP_Taxonomy_Service' ) && ! class_exists( 'SCP_Taxonomy_Service' ) ) {
            return false;
        }

        try {
            $canonical_context = \SCP_Taxonomy_Service::resolve_legacy_category( $skill_id );
            if ( empty( $canonical_context['is_mapped'] ) || empty( $canonical_context['skill']['id'] ) ) {
                error_log( "[SC AI QuestionSaver] Notice: Category #{$skill_id} is not mapped to a canonical Skill. Canonical tagging skipped." );
                return false;
            }

            $canonical_skill_id = (int) $canonical_context['skill']['id'];
            $canonical_topics   = \SCP_Taxonomy_Service::get_topics( $canonical_skill_id );

            if ( empty( $canonical_topics ) || ! is_array( $canonical_topics ) ) {
                error_log( "[SC AI QuestionSaver] Notice: Canonical Skill #{$canonical_skill_id} has no topics defined. Canonical tagging skipped." );
                return false;
            }

            // 1. Resolve Canonical Topic
            $raw_topic     = trim( (string) ( $question_data['topic'] ?? '' ) );
            $raw_topic_low = strtolower( $raw_topic );
            $matched_topic = null;

            foreach ( $canonical_topics as $top ) {
                $top_name_low = strtolower( trim( (string) ( $top['name'] ?? '' ) ) );
                $top_slug_low = strtolower( trim( (string) ( $top['slug'] ?? '' ) ) );

                if ( $raw_topic_low === $top_name_low || $raw_topic_low === $top_slug_low ) {
                    $matched_topic = $top;
                    break;
                }
                if ( ! empty( $raw_topic_low ) && ( strpos( $top_name_low, $raw_topic_low ) !== false || strpos( $raw_topic_low, $top_name_low ) !== false ) ) {
                    $matched_topic = $top;
                    break;
                }
            }

            // Default to first canonical topic if no explicit match
            if ( ! $matched_topic ) {
                $matched_topic = $canonical_topics[0];
            }

            $topic_id = (int) $matched_topic['id'];

            // 2. Resolve Canonical Subtopic
            $canonical_subtopics = \SCP_Taxonomy_Service::get_subtopics( $topic_id );
            $subtopic_id         = 0;

            if ( ! empty( $canonical_subtopics ) && is_array( $canonical_subtopics ) ) {
                $raw_subtopic     = trim( (string) ( $question_data['subtopic'] ?? '' ) );
                $raw_subtopic_low = strtolower( $raw_subtopic );
                $matched_subtopic = null;

                if ( ! empty( $raw_subtopic_low ) ) {
                    foreach ( $canonical_subtopics as $sub ) {
                        $sub_name_low = strtolower( trim( (string) ( $sub['name'] ?? '' ) ) );
                        $sub_slug_low = strtolower( trim( (string) ( $sub['slug'] ?? '' ) ) );

                        if ( $raw_subtopic_low === $sub_name_low || $raw_subtopic_low === $sub_slug_low ) {
                            $matched_subtopic = $sub;
                            break;
                        }
                        if ( strpos( $sub_name_low, $raw_subtopic_low ) !== false || strpos( $raw_subtopic_low, $sub_name_low ) !== false ) {
                            $matched_subtopic = $sub;
                            break;
                        }
                    }
                }

                if ( ! $matched_subtopic ) {
                    $matched_subtopic = $canonical_subtopics[0];
                }

                $subtopic_id = (int) $matched_subtopic['id'];
            }

            if ( ! empty( $canonical_subtopics ) && empty( $subtopic_id ) ) {
                $subtopic_id = (int) $canonical_subtopics[0]['id'];
            }

            // Tag question in canonical table
            return \SCP_Taxonomy_Service::tag_question(
                $post_id,
                [
                    'skill_id'         => $canonical_skill_id,
                    'topic_id'         => $topic_id,
                    'subtopic_id'      => $subtopic_id,
                    'difficulty'       => $difficulty,
                    'mapping_method'   => 'ai_factory',
                    'confidence_score' => 1.000,
                    'is_reviewed'      => 1,
                ]
            );

        } catch ( \Throwable $e ) {
            // Failure of canonical mapping MUST NOT rollback the post or throw fatal errors
            error_log( "[SC AI QuestionSaver] Non-fatal canonical taxonomy tagging failure for post #{$post_id}: " . $e->getMessage() );
            return false;
        }
    }

    private function getPublishedCount( int $skill_id, string $difficulty ): int {
        global $wpdb;
        if ( ! $wpdb ) {
            return 0;
        }
        return (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_scp_difficulty'
            WHERE p.post_type = 'scp_question'
            AND p.post_status = 'publish'
            AND tt.term_id = %d
            AND pm.meta_value = %s
        ", $skill_id, $difficulty ) );
    }
}
