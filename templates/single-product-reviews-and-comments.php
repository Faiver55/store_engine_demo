<?php
/**
 * If the current post is protected by a password and
 * the visitor has not yet entered the password,
 * return early without loading the comments.
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// If neither reviews nor comments are enabled, return early
if ( post_password_required() ) {
	return;
}

global $current_user, $post;

$storeengine_comments_count = get_comments_number();

/**
 * Load the comments template if enable_product_comments
 */
$enable_product_comments = Helper::get_settings('enable_product_comments', false);
$enable_product_reviews  = Helper::get_settings('enable_product_reviews');

if ( $enable_product_comments ) {
	Helper::get_template( 'single-product-comments.php' );
}

if ( ! $enable_product_reviews ) {
	return;
}

/**
 * The storeengine/templates/single_product_feedback hook.
 *
 * @hooked storeengine_single_product_feedback - 10
 */
do_action( 'storeengine/templates/single_product_feedback' ); ?>

<div id="comments" class="storeengine-single-product__content-item storeengine-single-product__content-item--reviews">
	<?php
	/**
	 * The storeengine/templates/single_filter hook.
	 *
	 * @hooked single_filter - 10
	 */

	do_action( 'storeengine/templates/single_filter' );

	// @TODO 3x query for showing command & verifying if the user is commented.

	// @XXX this can be stored into product meta. For simplicity,
	//      we can suffix the meta-key with the user-id and store the timestamp or comment id.
	//      E.G get_post_meta( $prodId, 'user_review_' . get_current_user_id(), true );
	$user_comment = get_comments( [
		'user_id'  => $current_user->ID,
		'post_id'  => $post->ID,
		'meta_key' => 'storeengine_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'count'    => true,
	] );

	if ( ! $user_comment ) {
		// @XXX we're hiding the form if user already commented before.
		//      but are we blocking the request?!... user can make request through console or postman.
		Helper::get_template( 'single-product/review-form.php' );
	}

	$paged             = max( absint( get_query_var( 'cpage' ) ), 1); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$comments_per_page = 5;
	if ( have_comments() ) {
		$args = [
			'post_id'  => get_the_ID(),
			'status'   => 'approve',
			'number'   => $comments_per_page,
			'paged'    => $paged,
			'meta_key' => 'storeengine_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		];

		$comment_query = new WP_Comment_Query();
		$comments      = $comment_query->query( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	if ( ! empty( $comments ) ) : ?>
	<ol class="storeengine-review-list">
		<?php
		foreach ( $comments as $comment ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			storeengine_review_lists( $comment );
		}
		?>
	</ol>
	<?php endif; ?>
</div><!-- #comments -->
