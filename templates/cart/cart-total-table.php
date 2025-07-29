<?php

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$cart = storeengine_cart();
?>

<?php do_action( 'storeengine/cart/before_cart_totals' ); ?>

<table class="storeengine-cart-sub-total-table">
	<tr class="order-subtotal">
		<th scope="row"><?php esc_html_e( 'Subtotal', 'storeengine' ); ?></th>
		<td data-title="<?php esc_attr_e( 'Subtotal', 'storeengine' ); ?>"><?php echo $cart->get_cart_subtotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>

	<?php foreach ( Helper::cart()->get_coupons() as $coupon ) { ?>
	<tr class="storeengine-cart-sub-total-table__coupon">
		<th scope="row"><small><?php Formatting::cart_totals_coupon_label( $coupon ); ?></small></th>
		<td data-title="<?php echo esc_attr( Formatting::cart_totals_coupon_label( $coupon, false ) ); ?>"><?php Formatting::cart_totals_coupon_html( $coupon ); ?></td>
	</tr>
	<?php } ?>

	<?php
	if ( Helper::cart()->needs_shipping() && Helper::cart()->show_shipping() ) {
		Formatting::cart_totals_shipping_html();
	}
	?>

	<?php foreach ( Helper::cart()->get_fees() as $fee ) { ?>
	<tr class="order-fee">
		<th scope="row"><?php Formatting::cart_totals_fee_label( $fee ); ?></th>
		<td data-title="<?php esc_attr( Formatting::cart_totals_fee_label( $fee, false ) ); ?>"><?php echo Formatting::price( $fee->amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>
	<?php } ?>

	<?php
	if ( TaxUtil::is_tax_enabled() && ! Helper::cart()->display_prices_including_tax() ) {
		$taxable_address = StoreEngine::init()->customer->get_taxable_address();
		$estimated_text  = '';

		if ( StoreEngine::init()->customer->is_customer_outside_base() && ! StoreEngine::init()->customer->has_calculated_shipping() ) {
			/* translators: %s location. */
			$estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'storeengine' ) . '</small>', Countries::init()->estimated_for_prefix( $taxable_address[0] ) . Countries::get_instance()->get_country( $taxable_address[0] ) );
		}

		if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
			foreach ( Helper::cart()->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				?>
				<tr class="storeengine-cart-sub-total-table__tax-rate">
					<th><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
				</tr>
				<?php
			}
		} else {
			?>
			<tr class="tax-total">
				<th scope="row"><?php echo esc_html( Countries::get_instance()->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<td data-title="<?php echo esc_attr( Countries::get_instance()->tax_or_vat() ); ?>"><?php echo Formatting::price( Helper::cart()->get_taxes_total() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<?php
		}
	}
	?>

	<?php do_action( 'storeengine/cart/cart_totals_before_order_total' ); ?>

	<tr class="order-total">
		<th scope="row"><strong><?php esc_html_e( 'Total', 'storeengine' ); ?></strong></th>
		<td data-title="<?php esc_attr_e( 'Total', 'storeengine' ); ?>"><?php Formatting::cart_totals_order_total_html(); ?></td>
	</tr>

	<?php do_action( 'storeengine/cart/cart_totals_after_order_total' ); ?>
</table>

<?php do_action( 'storeengine/cart/after_cart_totals' ); ?>
