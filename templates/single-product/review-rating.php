<?php
/**
 * The template to display the reviewers star rating in reviews
 *
 * This template can be overridden by copying it to yourtheme/storeengine/review-rating.php.
 *
 * @package StoreEngine\Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $comment;
$rating      = get_comment_meta( $comment->comment_ID, 'storeengine_rating', true );
$rating_meta = intval( $rating );

if ( 0 === $rating_meta ) {
	$rating_obj    = json_decode( $rating );
	$ratings_array = get_object_vars( $rating_obj );    // Converted obj to array
	$rating_count  = count( $ratings_array );
	$rating        = array_sum( $ratings_array ) / $rating_count;
}
$rating = number_format( $rating, 0 );
?>
<div class="storeengine-review__rating">
	<?php echo esc_html( $rating ); ?> <span class="storeengine-review__rating-count"></span>
	<?php echo wp_kses_post( StoreEngine\Utils\Helper::star_rating_generator( $rating ) ); ?>
</div>
