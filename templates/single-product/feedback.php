<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Query;
?>

<div class="storeengine-single-product__content-item storeengine-single-product__content-item--feedback">
	<div class="storeengine-product-feedback-ratings">
		<div class="storeengine-row storeengine-align-items-center">
			<div class="storeengine-col-md-3">
				<div class="storeengine-review">
					<p class="storeengine-avg-rating">
						<?php echo number_format( $rating->rating_avg, 1 ); ?>
					</p>
					<p class="storeengine-avg-rating-html">
						<?php echo wp_kses_post( Helper::star_rating_generator( $rating->rating_avg ) ); ?>
					</p>
				</div>
			</div>
			<div class="storeengine-col-md-3">
				<div class="storeengine-review-count">
					<p class="storeengine-avg-rating"><?php echo esc_html( $rating->rating_count ); ?></p>
					<p class="storeengine-avg-review"><?php esc_html_e( 'Reviews', 'storeengine' ); ?> </p>
				</div>
			</div>
			<div class="storeengine-col-md-6">
				<div class="storeengine-ratings-list">
					<?php
					foreach ( $rating->count_by_value as $key => $value ) {
						$rating_count_percent = round( ( $value > 0 ) ? ( $value * 100 ) / $rating->rating_count : 0 ); ?>
						<div class="storeengine-ratings-list-item">
							<div class="storeengine-ratings-list-item-col"><i class="storeengine-icon storeengine-icon--star"></i><?php echo esc_html( $key ); ?></div>
							<div class="storeengine-ratings-list-item-col"></div>
							<div class="storeengine-ratings-list-item-fill">
								<div class="storeengine-ratings-list-item-fill-bar" style="width: <?php echo esc_html( $rating_count_percent ); ?>%;"></div>
							</div>
							<div class="storeengine-ratings-list-item-label">
								<?php echo esc_html( $value ) . '<span>(' . esc_html( $rating_count_percent ) . '%) </span>'; ?>
							</div>
						</div>
						<?php
					} ?>
				</div>
			</div>
		</div>
	</div>
</div>
