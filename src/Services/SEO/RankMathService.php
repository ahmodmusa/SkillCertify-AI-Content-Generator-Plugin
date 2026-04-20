<?php

namespace SC_AI\ContentGenerator\Services\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math SEO Integration Service
 *
 * Automatically sets Rank Math focus keyword for scp_question posts
 *
 * @package SC_AI\ContentGenerator\Services\SEO
 */
class RankMathService {

	/**
	 * Boot the service
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'save_post_scp_question', [ $this, 'setFocusKeyword' ], 20 );
		add_action( 'save_post_scp_question', [ $this, 'setSeoTitle' ], 20 );
	}

	/**
	 * Set Rank Math focus keyword from post title
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function setFocusKeyword( int $post_id ): void {
		// Prevent autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check capability
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check existing keyword (do not override manual)
		$existing = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( ! empty( $existing ) ) {
			return;
		}

		// Get title
		$title = get_the_title( $post_id );
		if ( empty( $title ) ) {
			return;
		}

		// Clean keyword
		$keyword = strtolower( $title );
		$keyword = preg_replace( '/[^a-z0-9\s]/', '', $keyword );
		$keyword = trim( $keyword );

		// Save keyword
		update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
	}

	/**
	 * Set Rank Math SEO title from post title
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function setSeoTitle( int $post_id ): void {
		// Prevent autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check capability
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check existing title (do not override manual)
		$existing = get_post_meta( $post_id, 'rank_math_title', true );
		if ( ! empty( $existing ) ) {
			return;
		}

		// Get title
		$title = get_the_title( $post_id );
		if ( empty( $title ) ) {
			return;
		}

		// Save SEO title
		update_post_meta( $post_id, 'rank_math_title', $title );
	}
}
