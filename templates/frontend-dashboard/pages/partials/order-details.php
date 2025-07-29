<?php
/**
 * Order details table
 *
 * @var Order $order
 */

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$purchase_note_statuses = [ 'completed', 'processing' ];
$purchase_note_statuses = apply_filters( 'storeengine/templates/frontend-dashboard/purchase_note_order_statuses', $purchase_note_statuses );
$show_purchase_note     = $order->has_status( $purchase_note_statuses );
?>
	<div class="storeengine-dashboard__section-wrapper">
		<?php do_action( 'storeengine/order_details/before_order_table', $order ); ?>
		<div class="storeengine-dashboard__section-title">
			<h4><?php echo isset( $title ) ? esc_html( $title ) : esc_html__( 'Order details', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--order-details">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--order-details">
				<thead>
				<tr>
					<th scope="col" class="col-product-name"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="col" class="col-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $order->get_line_product_items() as $item ) {
					$product           = $item->get_product();
					$purchase_note     = $product ? $product->get_purchase_note() : '';
					$is_visible        = $product && $product->is_visible();
					$product_permalink = apply_filters( 'storeengine/order/item_permalink', $is_visible ? get_permalink( $item->get_product_id() ) : '', $item, $order );
					?>
					<tr class="storeengine-dashboard__table--line-item order_item_product">
						<th scope="col" class="col-product-name">
							<span class="order-item-product--name">
								<?php echo wp_kses_post( apply_filters( 'storeengine/order/item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', $product_permalink, $item->get_name() ) : $item->get_name(), $item, $is_visible ) ); ?>
							</span>
							<span class="order-item-quantity">
								<?php
								$qty          = $item->get_quantity();
								$refunded_qty = $order->get_qty_refunded_for_item( $item->get_id() );

								if ( $refunded_qty ) {
									$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * - 1 ) ) . '</ins>';
								} else {
									$qty_display = esc_html( $qty );
								}

								echo apply_filters( 'storeengine/order/item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $qty_display ) . '</strong>', $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</span>
							<span class="order-item-meta">
								<?php
								do_action( 'storeengine/order/item_meta_start', $item->get_id(), $item, $order, false );

								storeengine_display_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								do_action( 'storeengine/order/item_meta_end', $item->get_id(), $item, $order, false );
								?>
							</span>
						</th>
						<td class="col-total">
							<?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
					<?php if ( $show_purchase_note && $purchase_note ) { ?>
						<tr class="storeengine-dashboard__table--product-purchase-note product-purchase-note">
							<td colspan="2"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php } ?>
				<?php } ?>
				</tbody>
				<tfoot>
				<?php foreach ( $order->get_order_item_totals() as $key => $total ) { ?>
					<tr class="storeengine-dashboard__table--order-total order_<?php echo esc_attr( $key ); ?>">
						<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
						<td><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
				}
				?>
				<?php if ( $order->get_customer_note() ) : ?>
					<tr class="storeengine-dashboard__table--order-note order_note">
						<th><?php esc_html_e( 'Note:', 'storeengine' ); ?></th>
						<td><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), [ 'br' => [] ] ); ?></td>
					</tr>
				<?php endif; ?>
				</tfoot>
			</table>
		</div>
		<?php do_action( 'storeengine/order_details/after_order_table', $order ); ?>
	</div>
<?php
