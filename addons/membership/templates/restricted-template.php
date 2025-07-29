<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$page_title = isset( $args['page_title'] ) ? sanitize_text_field( $args['page_title'] ) : '';
$page_text  = isset( $args['message'] ) ? sanitize_text_field( $args['message'] ) : '';
$prices     = $args['prices'];
?>

<main id="site-content" class="storeengine-membership-main-container">
	<div class="storeengine-membership-container-div storeengine-width-50 storeengine-mx-auto">
		<h2><?php echo esc_html( $page_title ); ?></h2>
		<div class="storeengine-unauthorized-container">
			<?php echo wp_kses_post( wpautop( $page_text ) ); ?>
		</div>
		<?php if ( ! empty( $prices ) ) : ?>
			<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--single" action="#"
				  method="post">
				<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
				<?php if ( 1 === count( $prices ) ) : ?>
					<input type="hidden" name="price_id" value="<?php echo esc_attr( $prices[0]->get_id() ); ?>">
					<p><?php echo $prices[0]->get_price_html(); ?></p>
				<?php
				else :
					Helper::get_template( 'membership/prices.php', [ 'prices' => $prices ] );
				endif;
				?>
				<button class="storeengine-btn storeengine-btn--direct-checkout" type="submit"
						data-action="buy_now"><?php esc_html_e( 'Purchase Now', 'storeengine' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</main>
