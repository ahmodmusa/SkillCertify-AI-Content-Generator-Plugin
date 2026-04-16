<?php

namespace SC_AI\ContentGenerator\Repositories;

defined( 'ABSPATH' ) || exit;

class ProgressRepository {
    public function createProgressRecord( int $question_id, string $stage = SC_AI_STAGE_NONE ): bool {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $result = $wpdb->insert(
            $table,
            [
                'question_id' => $question_id,
                'status' => SC_AI_STATUS_PENDING,
                'content_stage' => $stage,
                'attempts' => 0,
                'generated_at' => null,
                'error_msg' => null,
            ],
            [ '%d', '%s', '%s', '%d', null, null ]
        );

        return $result !== false;
    }

    public function updateProgress( int $question_id, string $status, string $stage, string $error = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        $result = $wpdb->query( $wpdb->prepare( "
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

        return $result !== false;
    }

    public function getProgress( int $question_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        return $wpdb->get_row( $wpdb->prepare( "
            SELECT * FROM {$table} WHERE question_id = %d
        ", $question_id ) );
    }

    public function getAllProgress( int $limit = 100, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        return $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM {$table}
            ORDER BY generated_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset ) );
    }

    public function getFailedQuestions( int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        return $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM {$table}
            WHERE status = 'failed' AND attempts < 3
            ORDER BY generated_at ASC
            LIMIT %d
        ", $limit ) );
    }

    public function resetStuckProcessing(): int {
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        
        $result = $wpdb->update(
            $progress_table,
            [ 'status' => 'pending', 'content_stage' => 'none' ],
            [ 'status' => 'processing' ],
            [ '%s', '%s' ],
            [ '%s' ]
        );
        
        return $result !== false ? $result : 0;
    }

    public function deleteProgress( int $question_id ): void {
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        
        $wpdb->delete(
            $progress_table,
            [ 'question_id' => $question_id ],
            [ '%d' ]
        );
    }

    public function getDashboardStats(): array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;

        // Get counts from posts table
        $total = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$posts_table}
            WHERE post_type = 'scp_question' AND post_status = 'publish'
        " );

        // Count generated questions (using progress table for consistency with table data)
        $generated = $wpdb->get_var( "
            SELECT COUNT(DISTINCT pt.ID)
            FROM {$posts_table} pt
            INNER JOIN {$table} pr ON pt.ID = pr.question_id
            WHERE pr.content_stage = 'final' AND pr.status = 'done'
            AND pt.post_type = 'scp_question'
            AND pt.post_status = 'publish'
        " );

        // Count pending questions (no progress or content_stage = 'none')
        $pending = $wpdb->get_var( "
            SELECT COUNT(DISTINCT pt.ID)
            FROM {$posts_table} pt
            LEFT JOIN {$table} pr ON pt.ID = pr.question_id
            WHERE (pr.content_stage = 'none' OR pr.content_stage IS NULL)
            AND pt.post_type = 'scp_question'
            AND pt.post_status = 'publish'
        " );

        // Get last generation time
        $last_generated = $wpdb->get_var( "
            SELECT generated_at FROM {$table}
            WHERE content_stage = 'final' AND status = 'done'
            ORDER BY generated_at DESC LIMIT 1
        " );

        return [
            'total' => (int) ($total ?? 0),
            'draft' => (int) ($generated ?? 0),
            'final' => (int) ($generated ?? 0),
            'pending' => (int) ($pending ?? 0),
            'last_draft' => $last_generated ? date( 'M j, g:i A', strtotime( $last_generated ) ) : '',
            'last_final' => $last_generated ? date( 'M j, g:i A', strtotime( $last_generated ) ) : '',
        ];
    }

    public function getRecentActivities( int $limit = 15 ): array {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT DISTINCT
                p.question_id,
                p.content_stage,
                p.generated_at,
                p.status,
                pt.post_title as question_title
            FROM {$table} p
            INNER JOIN {$posts_table} pt ON p.question_id = pt.ID
            WHERE p.generated_at IS NOT NULL
            ORDER BY p.generated_at DESC
            LIMIT %d
        ", $limit ) );

        $activities = [];
        foreach ( $results as $row ) {
            $type = $row->content_stage === 'final' ? 'final' : 'draft';
            $message = $type === 'final' ? 'Final content generated' : 'Draft content generated';
            
            $activities[] = [
                'type' => $type,
                'message' => $message,
                'time' => date( 'M j, g:i A', strtotime( $row->generated_at ) ),
                'question_id' => (int) $row->question_id,
                'question_title' => $row->question_title,
            ];
        }

        return $activities;
    }

    public function getStatusTableData( int $page, int $per_page, string $filter ): array {
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $posts_table = $wpdb->posts;
        $offset = ( $page - 1 ) * $per_page;

        // Build WHERE clause based on filter
        $where = "WHERE pt.post_type = 'scp_question' AND pt.post_status = 'publish'";
        
        if ( $filter === 'complete' ) {
            $where .= " AND pr.content_stage = 'final' AND pr.status = 'done'";
        } elseif ( $filter === 'pending' ) {
            $where .= " AND (pr.content_stage = 'none' OR pr.content_stage IS NULL)";
        }

        // Sort: pending first (oldest to newest), then complete (newest to oldest)
        $order_by = "ORDER BY CASE WHEN pr.content_stage = 'none' OR pr.content_stage IS NULL THEN 0 ELSE 1 END, pt.ID ASC";
        if ( $filter === 'complete' ) {
            $order_by = "ORDER BY pt.ID DESC";
        }

        // Get total count
        $total_query = "
            SELECT COUNT(*)
            FROM {$posts_table} pt
            LEFT JOIN {$progress_table} pr ON pt.ID = pr.question_id
            {$where}
        ";
        $total = $wpdb->get_var( $total_query );

        // Get paginated questions
        $query = $wpdb->prepare( "
            SELECT
                pt.ID as id,
                pt.post_title as title,
                COALESCE(pr.content_stage, 'none') as status,
                pr.generated_at
            FROM {$posts_table} pt
            LEFT JOIN {$progress_table} pr ON pt.ID = pr.question_id
            {$where}
            {$order_by}
            LIMIT %d OFFSET %d
        ", $per_page, $offset );

        $results = $wpdb->get_results( $query );

        $questions = [];
        foreach ( $results as $row ) {
            $draft_time = '';
            $final_time = '';

            // Get draft time from meta
            $draft_meta = get_post_meta( $row->id, '_scp_description_draft', true );
            if ( ! empty( $draft_meta ) ) {
                $draft_progress = $wpdb->get_var( $wpdb->prepare( "
                    SELECT generated_at FROM {$progress_table}
                    WHERE question_id = %d AND content_stage = 'draft' AND status = 'done'
                    ORDER BY generated_at DESC LIMIT 1
                ", $row->id ) );
                $draft_time = $draft_progress ? date( 'M j, g:i A', strtotime( $draft_progress ) ) : '';
            }

            // Get final time from meta
            $final_meta = get_post_meta( $row->id, '_scp_description_final', true );
            if ( ! empty( $final_meta ) ) {
                $final_progress = $wpdb->get_var( $wpdb->prepare( "
                    SELECT generated_at FROM {$progress_table}
                    WHERE question_id = %d AND content_stage = 'final' AND status = 'done'
                    ORDER BY generated_at DESC LIMIT 1
                ", $row->id ) );
                $final_time = $final_progress ? date( 'M j, g:i A', strtotime( $final_progress ) ) : '';
            }

            $questions[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'status' => $row->status,
                'draft_time' => $draft_time,
                'final_time' => $final_time,
                'generated_time' => $final_time ?: $draft_time,
                'edit_link' => get_permalink( $row->id ),
            ];
        }

        return [
            'questions' => $questions,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public function getStats(): object {
        global $wpdb;
        $table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;

        return $wpdb->get_row( "
            SELECT
                COUNT(*) as total,
                SUM( status = 'done'       ) as done,
                SUM( status = 'pending'    ) as pending,
                SUM( status = 'failed'     ) as failed,
                SUM( status = 'processing' ) as processing
            FROM {$table}
        " );
    }
}
