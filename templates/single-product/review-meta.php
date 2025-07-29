<?php
/**
 * The template to display the reviewers meta data (name, verified owner, review date)
 *
 * This template can be overridden by copying it to yourtheme/storeengine/single-course/review-meta.php.
 *
 * @package StoreEngine\Templates
 * @version 1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



global $comment;

if ( '0' === $comment->comment_approved ) { ?>
	<p class="storeengine-review-meta">
		<em class="storeengine-review-meta__awaiting-approval">
			<?php esc_html_e( 'Your review is awaiting approval', 'storeengine' ); ?>
		</em>
	</p>
<?php } else { ?>
	<div class="storeengine-review-meta">
		<?php do_action( 'storeengine/templates/review_display_rating', $comment ); ?> <span> . </span>
		<p>
			<time class="storeengine-review-meta__published-date" datetime="<?php echo esc_attr( get_comment_date( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( StoreEngine\Utils\Helper::get_date_format() ) ); ?></time>
		</p>

	</div>
	<?php
}
