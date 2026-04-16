<?php

namespace SC_AI\ContentGenerator\Services\Storage;

defined( 'ABSPATH' ) || exit;

class ProgressTracker {
    public function updateStatus( int $question_id, string $status, string $stage, string $error = '' ): void {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $wpdb->query( $wpdb->prepare( "
            INSERT INTO {$table}
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

        // Clear stats cache to show real-time updates
        delete_transient( SC_AI_CACHE_STATS );
        delete_transient( SC_AI_CACHE_ACTIVITIES );
    }

    public function getStatus( int $question_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $result = $wpdb->get_row( $wpdb->prepare( "
            SELECT status, content_stage, attempts, generated_at, error_msg
            FROM {$table}
            WHERE question_id = %d
        ", $question_id ) );

        if ( ! $result ) {
            return null;
        }

        return [
            'status' => $result->status,
            'stage' => $result->content_stage,
            'attempts' => (int) $result->attempts,
            'generated_at' => $result->generated_at,
            'error_msg' => $result->error_msg,
        ];
    }

    public function resetStuck(): int {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        return $wpdb->query( $wpdb->prepare( "
            UPDATE {$table}
            SET status = 'pending',
                error_msg = 'Reset from stuck processing'
            WHERE status = 'processing'
        " ) );
    }

    public function getStats(): array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $stats = $wpdb->get_row( "
            SELECT
                COUNT(*) as total,
                SUM( status = 'done'       ) as done,
                SUM( status = 'pending'    ) as pending,
                SUM( status = 'failed'     ) as failed,
                SUM( status = 'processing' ) as processing
            FROM {$table}
        " );

        return [
            'total' => (int) ( $stats->total ?? 0 ),
            'done' => (int) ( $stats->done ?? 0 ),
            'pending' => (int) ( $stats->pending ?? 0 ),
            'failed' => (int) ( $stats->failed ?? 0 ),
            'processing' => (int) ( $stats->processing ?? 0 ),
        ];
    }

    public function getDashboardStats(): array {
        $cache_key = SC_AI_CACHE_STATS;
        $stats = get_transient( $cache_key );
        
        if ( $stats !== false ) {
            return $stats;
        }

        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;

        // Get total count of all published questions
        $total = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$posts_table}
            WHERE post_type = 'scp_question' AND post_status = 'publish'
        " );

        // Get draft count (questions with draft content)
        $draft = $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID) FROM {$posts_table} p
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_description_draft'
            WHERE p.post_type = 'scp_question' AND p.post_status = 'publish' AND m.meta_value != ''
        " );

        // Get final count (questions with final content)
        $final = $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID) FROM {$posts_table} p
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_scp_description_final'
            WHERE p.post_type = 'scp_question' AND p.post_status = 'publish' AND m.meta_value != ''
        " );

        // Get pending count (questions with no AI content)
        $pending = $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID) FROM {$posts_table} p
            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m.meta_key = '_scp_description_draft'
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m.meta_key = '_scp_description_final'
            WHERE p.post_type = 'scp_question' AND p.post_status = 'publish'
            AND (m1.meta_value IS NULL OR m1.meta_value = '')
            AND (m2.meta_value IS NULL OR m2.meta_value = '')
        " );

        // Get last generation times from progress table
        $last_draft = $wpdb->get_var( "
            SELECT generated_at FROM {$progress_table}
            WHERE content_stage = 'draft' AND status = 'done' AND generated_at IS NOT NULL
            ORDER BY generated_at DESC LIMIT 1
        " );

        $last_final = $wpdb->get_var( "
            SELECT generated_at FROM {$progress_table}
            WHERE content_stage = 'final' AND status = 'done' AND generated_at IS NOT NULL
            ORDER BY generated_at DESC LIMIT 1
        " );

        $stats = [
            'total' => (int) $total,
            'draft' => (int) $draft,
            'final' => (int) $final,
            'pending' => (int) $pending,
            'last_draft' => $last_draft ? date( 'M j, g:i A', strtotime( $last_draft ) ) : '',
            'last_final' => $last_final ? date( 'M j, g:i A', strtotime( $last_final ) ) : '',
        ];

        set_transient( $cache_key, $stats, SC_AI_CACHE_TIME_STATS );
        return $stats;
    }

    public function getRecentActivities( int $limit = 15 ): array {
        $cache_key = SC_AI_CACHE_ACTIVITIES . $limit;
        $activities = get_transient( $cache_key );
        
        if ( $activities !== false ) {
            return $activities;
        }

        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;

        // Get recent successful generations with DISTINCT to avoid duplicates
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT DISTINCT
                p.question_id,
                p.content_stage,
                p.generated_at,
                pt.post_title as question_title
            FROM {$progress_table} p
            LEFT JOIN {$posts_table} pt ON p.question_id = pt.ID
            WHERE p.status = 'done' 
            AND p.generated_at IS NOT NULL
            AND p.content_stage IN ('draft', 'final')
            ORDER BY p.generated_at DESC
            LIMIT %d
        ", $limit ) );

        $activities = [];

        foreach ( $results as $row ) {
            $type = $row->content_stage === 'draft' ? 'draft' : 'final';
            $message = $type === 'draft' 
                ? 'Draft content generated' 
                : 'Final content generated';

            $activities[] = [
                'type' => $type,
                'message' => $message,
                'time' => $this->formatTimeAgo( $row->generated_at ),
                'question_id' => (int) $row->question_id,
                'question_title' => $row->question_title ?? '',
            ];
        }

        set_transient( $cache_key, $activities, SC_AI_CACHE_TIME_ACTIVITIES );
        return $activities;
    }

    private function formatTimeAgo( string $datetime ): string {
        $time = strtotime( $datetime );
        $now = current_time( 'timestamp' );
        $diff = $now - $time;

        if ( $diff < MINUTE_IN_SECONDS ) {
            return 'Just now';
        } elseif ( $diff < HOUR_IN_SECONDS ) {
            $minutes = floor( $diff / MINUTE_IN_SECONDS );
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        } elseif ( $diff < DAY_IN_SECONDS ) {
            $hours = floor( $diff / HOUR_IN_SECONDS );
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        } else {
            $days = floor( $diff / DAY_IN_SECONDS );
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }
    }

    public function getStatusTableData( int $page, int $per_page, string $filter ): array {
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        $offset = ( $page - 1 ) * $per_page;

        // Build WHERE clause based on filter
        $where = "WHERE p.post_type = 'scp_question' AND p.post_status = 'publish'";
        $filter_clause = '';

        if ( $filter === 'draft' ) {
            $filter_clause = "AND pr.content_stage = 'draft' AND pr.status = 'done'";
        } elseif ( $filter === 'final' ) {
            $filter_clause = "AND pr.content_stage = 'final' AND pr.status = 'done'";
        } elseif ( $filter === 'pending' ) {
            $filter_clause = "AND (pr.content_stage = 'none' OR pr.content_stage IS NULL)";
        }

        // Get total count
        $total_query = "
            SELECT COUNT(*)
            FROM {$posts_table} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            {$where} {$filter_clause}
        ";
        $total = $wpdb->get_var( $total_query );

        // Get paginated questions with single query for draft/final times
        $query = $wpdb->prepare( "
            SELECT
                p.ID as id,
                p.post_title as title,
                COALESCE(pr.content_stage, 'none') as status,
                pr.generated_at,
                MAX(CASE WHEN m1.meta_key = '_scp_description_draft' AND m1.meta_value != '' THEN pr_d.generated_at END) as draft_time,
                MAX(CASE WHEN m2.meta_key = '_scp_description_final' AND m2.meta_value != '' THEN pr_f.generated_at END) as final_time
            FROM {$posts_table} p
            LEFT JOIN {$progress_table} pr ON p.ID = pr.question_id
            LEFT JOIN {$postmeta_table} m1 ON p.ID = m1.post_id AND m1.meta_key = '_scp_description_draft'
            LEFT JOIN {$postmeta_table} m2 ON p.ID = m2.post_id AND m2.meta_key = '_scp_description_final'
            LEFT JOIN {$progress_table} pr_d ON p.ID = pr_d.question_id AND pr_d.content_stage = 'draft' AND pr_d.status = 'done'
            LEFT JOIN {$progress_table} pr_f ON p.ID = pr_f.question_id AND pr_f.content_stage = 'final' AND pr_f.status = 'done'
            {$where} {$filter_clause}
            GROUP BY p.ID, p.post_title, pr.content_stage, pr.generated_at
            ORDER BY p.ID DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset );

        $results = $wpdb->get_results( $query );

        $questions = [];
        foreach ( $results as $row ) {
            $questions[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'status' => $row->status,
                'draft_time' => $row->draft_time ? date( 'M j, g:i A', strtotime( $row->draft_time ) ) : '',
                'final_time' => $row->final_time ? date( 'M j, g:i A', strtotime( $row->final_time ) ) : '',
                'edit_link' => get_edit_post_link( $row->id ),
            ];
        }

        return [
            'questions' => $questions,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
}
