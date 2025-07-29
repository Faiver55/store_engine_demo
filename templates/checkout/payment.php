<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

$available_gateways = Helper::get_payment_gateways()->get_available_payment_gateways();
?>
<div class="storeengine-ajax-checkout-form__payments">
	<?php if ( $needs_payment ?? false ) { ?>
	<div class="storeengine-checkout-available-payments">
		<h6><?php esc_html_e( 'Payment Method', 'storeengine' ); ?></h6>
		<?php
		if ( ! empty( $available_gateways ) ) :
			foreach ( $available_gateways as $gateway ) : ?>
				<div class="storeengine-checkout-available-payment-item storeengine-checkout-available-payment-item--<?php echo esc_attr( $gateway->id ); ?>">
					<div class="storeengine-accordion-drawer">
						<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="storeengine-accordion-drawer__trigger" type="radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>"<?php checked( $gateway->is_current() ); ?>>
						<label class="storeengine-accordion-drawer__title" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
							<?php echo $gateway->get_icon(); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped ?>
							<span><?php echo esc_html( $gateway->get_title() ); ?></span>
						</label>
						<div class="storeengine-payment-box storeengine-accordion-drawer__content-wrapper">
							<div class="storeengine-accordion-drawer__content"><?php
							if ( ( $gateway->has_fields() || $gateway->get_description() ) ) {
								$gateway->payment_fields();
							}
							?></div>
						</div>
					</div>
				</div>
				<?php
			endforeach;
		else : ?>
			<p class="storeengine-error-message"><?php esc_html_e( 'No payment methods are currently available. This may be a temporary issue. Please contact our support team for help placing your order.', 'storeengine' ); ?></p>
		<?php endif; ?>
	</div>
	<?php } ?>

	<div class="storeengine-place-order">
		<noscript>
			<?php
			/* translators: $1 and $2 opening and closing emphasis tags respectively */
			printf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'storeengine' ), '<em>', '</em>' );
			?>
			<br/><button type="submit" class="button alt" name="storeengine_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'storeengine' ); ?>"><?php esc_html_e( 'Update totals', 'storeengine' ); ?></button>
		</noscript>

		<!-- Show terms -->
		<?php do_action( 'storeengine/checkout_page/before_submit' ); ?>

		<div class="storeengine-payment-elements storeengine-checkout__order-btn" id="storeengine-payment-elements">
			<div id="paypal-button-container" style="display:none"></div>
			<button type="submit" name="storeengine_checkout_place_order" id="storeengine_place_order"<?php disabled( empty( $available_gateways ) ); ?>><?php echo esc_html( $place_order_label ?? __('Place Order', 'storeengine') ); ?></button>
		</div>

		<?php do_action( 'storeengine/checkout_page/after_submit' ); ?>
	</div>
</div>
