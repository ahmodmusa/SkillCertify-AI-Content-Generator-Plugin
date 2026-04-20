<?php

namespace SC_AI\ContentGenerator\Admin;

defined( 'ABSPATH' ) || exit;

class AIContentMetaBox {
    public function boot(): void {
        add_action( 'add_meta_boxes', [ $this, 'registerMetaBox' ] );
        add_action( 'save_post_scp_question', [ $this, 'saveMetaBox' ], 10, 2 );
    }

    public function registerMetaBox(): void {
        add_meta_box(
            'scp_ai_content_editor',
            'AI Content Editor',
            [ $this, 'renderMetaBox' ],
            'scp_question',
            'normal',
            'high'
        );
    }

    public function renderMetaBox( \WP_Post $post ): void {
        wp_nonce_field( 'scp_ai_content_editor_nonce', 'scp_ai_content_editor_nonce' );

        $keypoints_raw = get_post_meta( $post->ID, '_scp_ai_keypoints', true );
        $keypoints = ! empty( $keypoints_raw ) ? json_decode( $keypoints_raw, true ) : [];
        if ( ! is_array( $keypoints ) ) {
            $keypoints = [];
        }
        $mistake = get_post_meta( $post->ID, '_scp_ai_mistake', true );
        $tip = get_post_meta( $post->ID, '_scp_ai_tip', true );
        ?>
        <div class="scp-ai-content-editor">
            <p class="description">
                <strong>Note:</strong> Explanation is now stored in post_content (edit via WordPress editor above).
            </p>

            <div class="scp-field-group">
                <label for="scp_ai_keypoints">
                    <strong>Key Points</strong>
                    <span class="description">(one per line, 3-5 points)</span>
                </label>
                <textarea 
                    name="scp_ai_keypoints" 
                    id="scp_ai_keypoints" 
                    rows="5" 
                    class="large-text"
                ><?php echo esc_textarea( implode( "\n", $keypoints ) ); ?></textarea>
            </div>

            <div class="scp-field-group">
                <label for="scp_ai_mistake">
                    <strong>Common Mistake</strong>
                    <span class="description">(1 short sentence)</span>
                </label>
                <input 
                    type="text" 
                    name="scp_ai_mistake" 
                    id="scp_ai_mistake" 
                    value="<?php echo esc_attr( $mistake ); ?>" 
                    class="large-text"
                >
            </div>

            <div class="scp-field-group">
                <label for="scp_ai_tip">
                    <strong>Exam Tip</strong>
                    <span class="description">(1 short actionable tip)</span>
                </label>
                <input 
                    type="text" 
                    name="scp_ai_tip" 
                    id="scp_ai_tip" 
                    value="<?php echo esc_attr( $tip ); ?>" 
                    class="large-text"
                >
            </div>
        </div>

        <style>
        .scp-ai-content-editor .description {
            font-style: italic;
            color: #666;
            margin-bottom: 15px;
            display: block;
        }
        .scp-field-group {
            margin-bottom: 15px;
        }
        .scp-field-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .scp-field-group label .description {
            font-weight: 400;
            margin-left: 5px;
        }
        </style>
        <?php
    }

    public function saveMetaBox( int $post_id, \WP_Post $post ): void {
        // Verify nonce
        if ( ! isset( $_POST['scp_ai_content_editor_nonce'] ) 
            || ! wp_verify_nonce( $_POST['scp_ai_content_editor_nonce'], 'scp_ai_content_editor_nonce' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user capability
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save Key Points
        if ( isset( $_POST['scp_ai_keypoints'] ) ) {
            $keypoints = array_filter( array_map( 'trim', explode( "\n", $_POST['scp_ai_keypoints'] ) ) );
            $json = json_encode( $keypoints, JSON_UNESCAPED_UNICODE );
            update_post_meta( $post_id, '_scp_ai_keypoints', $json );
        }

        // Save Mistake
        if ( isset( $_POST['scp_ai_mistake'] ) ) {
            $mistake = sanitize_text_field( $_POST['scp_ai_mistake'] );
            update_post_meta( $post_id, '_scp_ai_mistake', $mistake );
        }

        // Save Tip
        if ( isset( $_POST['scp_ai_tip'] ) ) {
            $tip = sanitize_text_field( $_POST['scp_ai_tip'] );
            update_post_meta( $post_id, '_scp_ai_tip', $tip );
        }

        // Clear cache
        $this->clearCache( $post_id );
    }

    private function clearCache( int $post_id ): void {
        delete_transient( 'scp_explanation_' . $post_id );
        delete_transient( 'scp_keypoints_' . $post_id );
        delete_transient( 'scp_mistake_' . $post_id );
        delete_transient( 'scp_tip_' . $post_id );
        delete_transient( 'scp_content_' . $post_id );
        delete_transient( 'scp_unified_content_' . $post_id );
    }
}
