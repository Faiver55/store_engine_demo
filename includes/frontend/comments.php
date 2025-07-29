<?php

namespace StoreEngine\Frontend;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comments {
	public static function init() {
		$self = new self();
		add_action( 'comment_post', array( $self, 'add_comment_rating' ), 1 );
	}

	/**
	 * Rating field for comments.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function add_comment_rating( $comment_id ) {
		if ( isset( $_POST['comment_post_ID'] ) && 'storeengine_product' === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) { // phpcs:ignore input var ok, CSRF ok.
			$comment_post_ID    = isset( $_POST['comment_post_ID'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['comment_post_ID'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$storeengine_rating = isset( $_POST['storeengine_rating'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['storeengine_rating'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			wp_update_comment(
				[
					'comment_ID'   => $comment_id,
					'comment_type' => 'storeengine_product',
				]
			);

			if ( ! $storeengine_rating ) { // phpcs:ignore input var ok, CSRF ok.
				return;
			}

			add_comment_meta( $comment_id, 'storeengine_rating', $storeengine_rating, true );

			/**
			 * Fires after adding product rating.
			 *
			 * @param int $comment_id Comment id.
			 * @param int $comment_post_ID Post id.
			 * @param int $storeengine_rating Rating.
			 */
			do_action( 'storeengine/frontend/after_product_rating', $comment_id, $comment_post_ID, $storeengine_rating );
		}
	}
}
