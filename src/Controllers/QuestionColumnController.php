<?php

namespace SC_AI\ContentGenerator\Controllers;

defined( 'ABSPATH' ) || exit;

class QuestionColumnController {
    private object $progress_repository;
    private array $status_cache = [];

    public function __construct( object $progress_repository ) {
        $this->progress_repository = $progress_repository;
    }

    /**
     * Register all hooks
     */
    public function boot(): void {
        add_filter( 'manage_scp_question_posts_columns', [ $this, 'addColumn' ] );
        add_filter( 'the_posts', [ $this, 'prefetchStatus' ], 10, 2 );
        add_action( 'manage_scp_question_posts_custom_column', [ $this, 'renderColumn' ], 10, 2 );
        add_action( 'add_meta_boxes', [ $this, 'registerMetaBox' ] );
        add_action( 'save_post_scp_question', [ $this, 'saveMetaBox' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
    }

    /**
     * Add AI status column to scp_question list table
     */
    public function addColumn( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['sc_ai_status'] = 'AI Content';
            }
        }
        return $new_columns;
    }

    /**
     * Prefetch all statuses to avoid N+1 queries
     */
    public function prefetchStatus( array $posts, \WP_Query $query ): array {
        global $pagenow;

        // Only run on scp_question list screen
        if ( $pagenow !== 'edit.php' || ( $_GET['post_type'] ?? '' ) !== 'scp_question' ) {
            return $posts;
        }

        if ( empty( $posts ) ) {
            return $posts;
        }

        // Collect all post IDs
        $post_ids = array_map( fn( $post ) => $post->ID, $posts );

        // Single query to fetch all statuses
        global $wpdb;
        $progress_table = $wpdb->prefix . SC_AI_PROGRESS_TABLE;
        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT question_id, status, generated_at
            FROM {$progress_table}
            WHERE question_id IN ({$placeholders})
        ", $post_ids ) );

        // Cache results keyed by question_id
        $this->status_cache = [];
        foreach ( $results as $row ) {
            $this->status_cache[ $row->question_id ] = [
                'status' => $row->status,
                'generated_at' => $row->generated_at,
            ];
        }

        return $posts;
    }

    /**
     * Render AI status column
     */
    public function renderColumn( string $column_name, int $post_id ): void {
        if ( $column_name !== 'sc_ai_status' ) {
            return;
        }

        $status_info = $this->status_cache[ $post_id ] ?? null;
        $status = $status_info['status'] ?? 'not_started';
        $generated_at = $status_info['generated_at'] ?? null;

        $nonce = wp_create_nonce( 'sc_ai_nonce' );

        switch ( $status ) {
            case 'not_started':
                echo '<span class="sc-ai-badge not-started">— Not Started</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            case 'pending':
                echo '<span class="sc-ai-badge pending">⏳ Pending</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            case 'processing':
                echo '<span class="sc-ai-badge processing">🔄 Processing</span>';
                break;

            case 'done':
                echo '<span class="sc-ai-badge done">✅ Done</span>';
                if ( $generated_at ) {
                    $time = \SC_AI\ContentGenerator\Helpers\DateHelper::formatTimeAgo( $generated_at );
                    echo '<small class="sc-ai-time">' . esc_html( $time ) . '</small>';
                }
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( wp_create_nonce('sc_ai_generate') ) . '" style="margin-top:4px;display:block">🔄 Regenerate</button>';
                break;

            case 'failed':
                echo '<span class="sc-ai-badge failed">❌ Failed</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;

            default:
                echo '<span class="sc-ai-badge not-started">— Not Started</span>';
                echo '<br>';
                echo '<button class="button button-small sc-ai-col-btn" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">Generate</button>';
                break;
        }
    }

    /**
     * Register meta box on edit page
     */
    public function registerMetaBox(): void {
        add_meta_box(
            'sc_ai_content_box',
            'AI Generated Content',
            [ $this, 'renderMetaBox' ],
            'scp_question',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function renderMetaBox( \WP_Post $post ): void {
        $description = get_post_meta( $post->ID, '_scp_ai_description', true );
        $faqs_raw = get_post_meta( $post->ID, '_scp_ai_faqs', true );
        $exam_tip = get_post_meta( $post->ID, '_scp_ai_exam_tip', true );

        $faqs = json_decode( $faqs_raw, true );
        if ( ! is_array( $faqs ) ) {
            $faqs = [];
        }

        wp_nonce_field( 'sc_ai_save_content', 'sc_ai_content_nonce' );

        echo '<div class="sc-ai-meta-box">';

        // Description
        echo '<div class="sc-ai-field">';
        echo '<label><strong>Description</strong></label>';
        echo '<textarea name="sc_ai_description" id="sc_ai_description" rows="6" style="width:100%">' . esc_textarea( $description ) . '</textarea>';
        echo '</div>';

        // Exam Tip
        echo '<div class="sc-ai-field" style="margin-top:12px">';
        echo '<label><strong>Exam Tip</strong></label>';
        echo '<textarea name="sc_ai_exam_tip" id="sc_ai_exam_tip" rows="3" style="width:100%">' . esc_textarea( $exam_tip ) . '</textarea>';
        echo '</div>';

        // FAQs
        echo '<div class="sc-ai-field" style="margin-top:12px">';
        echo '<label><strong>FAQs</strong></label>';
        echo '<div id="sc-ai-faqs-wrap">';
        foreach ( $faqs as $i => $faq ) {
            echo '<div class="sc-ai-faq-item" style="border:1px solid #ddd; padding:10px; margin-bottom:8px; border-radius:4px;">';
            echo '<input type="text" name="sc_ai_faqs[' . $i . '][question]" value="' . esc_attr( $faq['question'] ?? '' ) . '" placeholder="Question" style="width:100%; margin-bottom:6px" />';
            echo '<textarea name="sc_ai_faqs[' . $i . '][answer]" rows="2" placeholder="Answer" style="width:100%">' . esc_textarea( $faq['answer'] ?? '' ) . '</textarea>';
            echo '<button type="button" class="button button-small sc-ai-remove-faq" style="margin-top:4px; color:#a00">Remove</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" id="sc-ai-add-faq" class="button button-small" style="margin-top:8px">+ Add FAQ</button>';
        echo '</div>';

        // Generate Button
        echo '<div class="sc-ai-field" style="margin-top:16px">';
        $has_content = !empty( get_post_meta( $post->ID, '_scp_ai_description', true ) );
        $btn_label = $has_content ? '🔄 Regenerate AI Content' : '🤖 Generate AI Content';
        $nonce = wp_create_nonce( 'sc_ai_nonce' );
        echo '<button type="button" class="button button-primary sc-ai-col-btn" data-post-id="' . esc_attr( $post->ID ) . '" data-nonce="' . esc_attr( $nonce ) . '" id="sc-ai-generate-btn">';
        echo esc_html( $btn_label );
        echo '</button>';
        echo '<span id="sc-ai-generate-status" style="margin-left:10px"></span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Save meta box data
     */
    public function saveMetaBox( int $post_id ): void {
        // Check nonce
        if ( ! isset( $_POST['sc_ai_content_nonce'] ) || ! wp_verify_nonce( $_POST['sc_ai_content_nonce'], 'sc_ai_save_content' ) ) {
            return;
        }

        // Check capability
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check not autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Save description
        update_post_meta( $post_id, '_scp_ai_description', wp_kses_post( $_POST['sc_ai_description'] ?? '' ) );

        // Save exam tip
        update_post_meta( $post_id, '_scp_ai_exam_tip', sanitize_textarea_field( $_POST['sc_ai_exam_tip'] ?? '' ) );

        // Save FAQs
        $faqs = [];
        foreach ( ( $_POST['sc_ai_faqs'] ?? [] ) as $faq ) {
            if ( empty( $faq['question'] ) && empty( $faq['answer'] ) ) {
                continue;
            }
            $faqs[] = [
                'question' => sanitize_text_field( $faq['question'] ?? '' ),
                'answer' => sanitize_textarea_field( $faq['answer'] ?? '' ),
            ];
        }
        update_post_meta( $post_id, '_scp_ai_faqs', wp_json_encode( $faqs ) );
    }

    /**
     * Enqueue assets on scp_question pages only
     */
    public function enqueueAssets(): void {
        $screen = get_current_screen();

        // List page
        if ( $screen && $screen->post_type === 'scp_question' && $screen->base === 'edit' ) {
            wp_enqueue_script(
                'sc-ai-question-column',
                SC_AI_PLUGIN_URL . 'assets/js/question-column.js',
                [ 'jquery' ],
                SC_AI_VERSION,
                true
            );

            wp_localize_script( 'sc-ai-question-column', 'scAiCol', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_ai_nonce' ),
            ] );

            wp_add_inline_style( 'dashicons', '
                .sc-ai-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    margin-bottom: 4px;
                }
                .sc-ai-badge.not-started { background:#e2e3e5; color:#383d41; }
                .sc-ai-badge.pending { background:#fff3cd; color:#856404; }
                .sc-ai-badge.processing { background:#cce5ff; color:#004085; }
                .sc-ai-badge.done { background:#d4edda; color:#155724; }
                .sc-ai-badge.failed { background:#f8d7da; color:#721c24; }
                .sc-ai-time { display:block; color:#999; font-size:11px; }
                .column-sc_ai_status { width:160px; }
            ' );
        }

        // Edit page
        if ( $screen && $screen->post_type === 'scp_question' && in_array( $screen->base, [ 'post', 'post-new' ] ) ) {
            wp_enqueue_script(
                'sc-ai-question-column',
                SC_AI_PLUGIN_URL . 'assets/js/question-column.js',
                [ 'jquery' ],
                SC_AI_VERSION,
                true
            );

            wp_localize_script( 'sc-ai-question-column', 'scAiCol', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_ai_nonce' ),
            ] );

            wp_add_inline_script( 'sc-ai-question-column', "
                document.addEventListener('DOMContentLoaded', function () {
                  var wrap  = document.getElementById('sc-ai-faqs-wrap');
                  var count = wrap ? wrap.querySelectorAll('.sc-ai-faq-item').length : 0;

                  document.getElementById('sc-ai-add-faq')
                    ?.addEventListener('click', function () {
                      var i = count++;
                      var div = document.createElement('div');
                      div.className = 'sc-ai-faq-item';
                      div.style.cssText = 'border:1px solid #ddd;padding:10px;margin-bottom:8px;border-radius:4px;';
                      div.innerHTML =
                        '<input type=\"text\" name=\"sc_ai_faqs[' + i + '][question]\" placeholder=\"Question\" style=\"width:100%;margin-bottom:6px\" />' +
                        '<textarea name=\"sc_ai_faqs[' + i + '][answer]\" rows=\"2\" placeholder=\"Answer\" style=\"width:100%\"></textarea>' +
                        '<button type=\"button\" class=\"button button-small sc-ai-remove-faq\" style=\"margin-top:4px;color:#a00\">Remove</button>';
                      wrap.appendChild(div);
                    });

                  document.addEventListener('click', function (e) {
                    if (e.target.classList.contains('sc-ai-remove-faq')) {
                      e.target.closest('.sc-ai-faq-item').remove();
                    }
                  });

                  // Override generate button behavior on edit page
                  var genBtn = document.getElementById('sc-ai-generate-btn');
                  var status = document.getElementById('sc-ai-generate-status');
                  if (genBtn) {
                    genBtn.addEventListener('click', function () {
                      genBtn.disabled = true;
                      genBtn.textContent = 'Generating...';
                      status.textContent = '';

                      fetch(scAiCol.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                          action:  'sc_ai_generate',
                          post_id: genBtn.dataset.postId,
                          nonce:   scAiCol.nonce
                        })
                      })
                      .then(function (r) { return r.json(); })
                      .then(function (data) {
                        if (data.success) {
                          status.innerHTML = '<span style=\"color:green\">✅ Done! Reload to see content.</span>';
                          genBtn.disabled = false;
                          genBtn.textContent = '🤖 Generate AI Content';
                        } else {
                          status.innerHTML = '<span style=\"color:red\">❌ Failed. Try again.</span>';
                          genBtn.disabled = false;
                          genBtn.textContent = '🤖 Generate AI Content';
                        }
                      })
                      .catch(function () {
                        status.innerHTML = '<span style=\"color:red\">❌ Error. Try again.</span>';
                        genBtn.disabled = false;
                        genBtn.textContent = '🤖 Generate AI Content';
                      });
                    });
                  }
                });
            " );
        }
    }
}
