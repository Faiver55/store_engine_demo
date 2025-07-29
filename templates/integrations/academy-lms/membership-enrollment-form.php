<?php
/**
 * Membership enroll form
 *
 * @var int $product_id
 * @var IntegrationRepositoryData[] $integrations
 * @var int $count
 */

use StoreEngine\Classes\Data\IntegrationRepositoryData;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use Academy\Helper as AcademyHelnper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$current = current( $integrations );

?>
<div class="academy-widget-enroll__add-to-cart academy-widget-enroll__add-to-cart--storeengine">
	<form class="storeengine-ajax-add-to-cart-form" action="#" method="post">
		<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
		<input type="hidden" name="academy_course_id" value="<?php echo esc_attr( get_the_ID() ?? 0 ); ?>">
		<?php if ( 1 === $count ) : ?>
			<input type="hidden" name="price_id"
				   id="product-<?php echo esc_attr( $current->price->get_product_id() ); ?>-price-<?php echo esc_attr( $current->price->get_id() ); ?>"
				   value="<?php echo esc_attr( $current->price->get_id() ); ?>" checked/>
		<?php else : ?>
			<div class="storeengine-single-product-prices">
				<?php foreach ( $integrations as $idx => $integration ) :
					$checked = ( 0 === $idx );
					$id_attr = 'product-price-' . $integration->price->get_product_id() . '-' . $integration->price->get_id();
					?>
				<label class="storeengine-single-product-price">
					<span class="storeengine-single-product-price-summery">
						<span class="storeengine-single-product-price-label">
							<input type="radio" name="price_id" value="<?php echo esc_attr( $integration->price->get_id() ); ?>"<?php checked( $checked ); ?>/>
							<span class="storeengine-single-product-price-name"><?php echo esc_html( $integration->price->get_price_name() ); ?></span>
						</span>
						<span class="storeengine-single-product-price-value">
							<?php $integration->price->print_price_summery_html(); ?>
						</span>
					</span>
					<span class="storeengine-single-product-price-details"><?php $integration->price->print_formatted_price_meta_html(); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="academy-add-to-cart-button">
			<button
				class="academy-btn academy-btn--preset-purple academy-btn--add-to-cart storeengine-btn--membership-add-to-cart storeengine-btn--add-to-cart"
				type="submit" data-action="buy_now">
				<?php if ( 1 === $count ) : ?>
					<?php esc_html_e( 'Purchase Membership - &nbsp;', 'storeengine' ); ?>
					<span
						class="academy-course-price"><?php echo wp_kses_post( $integrations[0]->price->get_price_html() ); ?></span>
				<?php else : ?>
					<?php esc_html_e( 'Purchase Membership', 'storeengine' ); ?>
				<?php endif; ?>
			</button>
		</div>
	</form>
</div>
