<?php
/**
 * @var \StoreEngine\Classes\Order $order
 * @var string|null $invoice_date
 */

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

?>
<table class="invoice-header">
	<tr>
		<?php
		if ( ! empty( HelperAddon::get_setting( 'logo' ) ) ) : ?>
			<td class="header">
				<img style="background: none;" height="30"
					 src="<?php echo esc_attr( wp_get_attachment_image_url( HelperAddon::get_setting( 'logo' ), 'full' ) ); ?>"
					 alt="Logo"/>
			</td>
		<?php endif; ?>
		<td class="invoice-data">
			<h3 class="invoice">INVOICE</h3>
			<br>
			<table>
				<tr class="invoice-date">
					<th>Invoice Number:</th>
					<td># <?php echo esc_html( $order->get_id() ); ?></td>
				</tr>
				<?php if ( $invoice_date ) : ?>
					<tr>
						<th>Invoice Date:</th>
						<td><?php echo esc_html( $invoice_date ); ?></td>
					</tr>
				<?php endif; ?>
			</table>
		</td>
	</tr>
</table>

<table class="order-data-addresses">
	<tr>
		<td>
			<h4 class="address-title">Invoice To:</h4>
		</td>
	</tr>
	<tr>
		<td class="address billing-address">
			<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
			<div class="billing-phone"><?php echo esc_html( $order->get_billing_phone() ); ?></div>
			<div class="billing-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
		</td>
		<td class="address shipping-address">
			<?php
			if ( $order->has_shipping_address() && $order->needs_shipping_address() ) {
				echo wp_kses_post( $order->get_formatted_shipping_address() );
			}
			?>
		</td>
		<td class="shop-details">
			<h4><?php echo esc_html( Helper::get_settings( 'store_name' ) ); ?></h4>
			<?php if ( ! empty( Helper::get_shop_address() ) ) : ?>
				<p><?php echo wp_kses_post( Helper::get_shop_address() ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<table class="products-table">
	<thead>
	<tr class="product-header">
		<?php if ( HelperAddon::get_setting( 'invoice_show_product_image', false ) ) : ?>
			<th></th>
		<?php endif; ?>
		<th>Product</th>
		<th>Price</th>
		<th>Qty</th>
		<th>Subtotal</th>
		<?php if ( ! empty( $order->get_taxes() ) ) : ?>
			<th>Tax</th>
		<?php endif; ?>
	</tr>
	</thead>
	<tbody>
	<?php /** @var \StoreEngine\Classes\Order\OrderItemProduct $item */
	foreach ( $order->get_items() as $item ) : ?>
		<tr class="product-body-row">
			<?php
			if ( HelperAddon::get_setting( 'invoice_show_product_image', false ) ) : ?>
				<td>
					<img width="80" height="80" class="product-image"
						 src="<?php echo esc_attr( ! empty( get_the_post_thumbnail_url( $item->get_product_id() ) ) ? get_the_post_thumbnail_url( $item->get_product_id() ) : storeengine_placeholder_image_src() ); ?>"
						 alt="<?php echo esc_attr( $item->get_name() ); ?>"/>
				</td>
			<?php endif; ?>
			<td class="product-content">
				<span
					class="item-name"><?php echo esc_html( apply_filters( 'storeengine/order/item_name', $item->get_name(), $item ) ); ?></span>
			</td>
			<td><?php echo wp_kses_post( Formatting::price( $item->get_price(), [
	'currency' => $order->get_currency(),
] ) ); ?></td>
			<td><?php echo esc_html( $item->get_quantity() ); ?></td>
			<td><?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<?php
			if ( $item->get_total_tax() > 0 ) : ?>
				<td><?php echo wp_kses_post( Formatting::price( $item->get_total_tax(), [
	'currency' => $order->get_currency(),
] ) ); ?></td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<table class="order-totals">
	<?php foreach ( $order->get_order_item_totals() as $item_total ) : ?>
		<tr>
			<td><strong><?php echo esc_html( $item_total['label'] ); ?></strong></td>
			<td></td>
			<td class="value"><?php echo wp_kses_post( $item_total['value'] ); ?></td>
		</tr>
	<?php endforeach; ?>
</table>

<table class="order-info">
	<tr class="order-number">
		<th>Order Number:</th>
		<td><?php echo esc_html( $order->get_id() ); ?></td>
	</tr>
	<tr class="order-date">
		<th>Order Date:</th>
		<td><?php echo esc_html( $order->get_date_created()->format( HelperAddon::get_setting( 'date_format', 'd F, Y' ) ) ); ?></td>
	</tr>
</table>

<?php if ( ! empty( HelperAddon::get_setting( 'invoice_default_note' ) ) ) : ?>
	<div class="notes">
		<h4>Notes</h4>
		<p><?php echo esc_html( HelperAddon::get_setting( 'invoice_default_note' ) ); ?></p>
	</div>
<?php endif; ?>

<div id="footer">
	<?php echo wp_kses_post( HelperAddon::get_setting( 'invoice_footer_text' ) ); ?><br>
</div>
