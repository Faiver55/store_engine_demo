<?php
/**
 * Review Comments Template
 *
 * Closing li is left out on purpose!.
 *
 * This template can be overridden by copying it to yourtheme/storeengine/single-product/review.php.
 *
 * @package storeengine\Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<li <?php comment_class(); ?> id="storeengine-review-<?php comment_ID(); ?>">

	<div id="comment-<?php comment_ID(); ?>" class="storeengine-review_container">
		<div class="storeengine-review-name-with-thumnail">
			<?php do_action( 'storeengine/templates/review_thumbnail', $comment ); ?>
			<strong class="storeengine-review-meta__author"><?php comment_author(); ?> </strong>
		</div>
		<?php
		/**
		 * The storeengine/templates/review_before hook
		 */
		do_action( 'storeengine/templates/review_before', $comment );
		?>


		<div class="storeengine-review-content">

			<?php
			/**
			 * The storeengine/templates/review_before_comment_meta hook.
			 *
			 * @hooked storeengine_review_display_rating - 10
			 */
			do_action( 'storeengine/templates/review_before_comment_meta', $comment );

			/**
			 * The storeengine/templates/review_meta hook.
			 *
			 * @hooked storeengine_review_display_meta - 10
			 */
			do_action( 'storeengine/templates/review_meta', $comment );

			do_action( 'storeengine/templates/review_before_comment_text', $comment );

			/**
			 * The storeengine/templates/review_comment_text hook
			 *
			 * @hooked storeengine_review_display_comment_text - 10
			 */
			do_action( 'storeengine/templates/review_comment_text', $comment );

			do_action( 'storeengine/templates/review_after_comment_text', $comment );
			?>

		</div>
	</div>
