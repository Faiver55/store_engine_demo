<?php
/**
 * @var $integration IntegrationRepositoryData
 * @var $features array
 */

use StoreEngine\Classes\Data\IntegrationRepositoryData;

?>

<div class="storeengine-col-lg-4 storeengine-col-md-6">
	<div class="storeengine-card">
		<div class="storeengine-card-header storeengine-card-header--featured">
			<h3><?php echo esc_html( $integration->price->get_name() ); ?></h3>
		</div>
		<div class="storeengine-card-body">
			<div class="storeengine-card-pricing">
				<?php if ( $integration->price->get_price() ) {
					$integration->price->print_price_html();
				} else {
					echo esc_html__( 'Free', 'storeengine' );
				} ?>
			</div>
			<form action="#" class="storeengine-ajax-add-to-cart-form" method="post">
				<?php wp_nonce_field( 'storeengine_nonce', 'storeengine_nonce' ); ?>
				<input type="hidden" name="product_id" value="<?php echo esc_html( $integration->price->get_product_id() ); ?>"/>
				<input type="hidden" name="price_id" value="<?php echo esc_html( $integration->price->get_id() ); ?>"/>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit" data-action="buy_now"><?php esc_html_e( 'Buy Now', 'storeengine' ); ?></button>
			</form>
			<div class="storeengine-card-features">
				<?php foreach ( $features as $feature ) : ?>
					<div class="storeengine-card-feature">
						<span class="storeengine-icon storeengine-icon--<?php echo esc_attr( $feature['icon'] ); ?>" aria-hidden="true"></span>
						<span class="storeengine-feature-label"><?php echo esc_html( $feature['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
