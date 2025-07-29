<?php
/**
 * @var \StoreEngine\Classes\Order $order
 * @var \StoreEngine\Classes\Order\OrderItemProduct[] $order_items
 * @var bool $show_purchase_note
 */
?>
<div class="storeengine-order-details-shortcode">
	<?php do_action( 'storeengine/thankyou/before_order_details', $order ); ?>
	<div class="storeengine-order-items">
		<?php
		foreach ( $order_items as $order_item ) {
			/**
			 * @var \StoreEngine\Classes\Order\OrderItemProduct $order_item
			 */
			$product       = $order_item->get_product();
			$purchase_note = $product ? get_post_meta( $product->get_id(), '_purchase_note', true ) : '';
			?>
			<div class="storeengine-order-item">
				<div class="storeengine-order-item__content">
					<?php storeengine_product_image( 'storeengine_thumbnail', apply_filters( 'storeengine/order/item_image_post_id', $order_item->get_product_id(), $order_item ), [ 'class' => 'storeengine-thumbnail' ] ); ?>
					<div class="storeengine-order-item__title">
						<?php if ( $product && $product->is_visible() ) { ?>
							<h6>
								<a href="<?php echo esc_attr( apply_filters( 'storeengine/order/item_permalink', get_the_permalink( $product->get_id() ), $order_item ) ); ?>">
									<?php echo esc_html( apply_filters( 'storeengine/order/item_name', $order_item->get_name(), $order_item ) ); ?>
								</a>
							</h6>
						<?php } else { ?>
							<h6>
								<?php echo esc_html( apply_filters( 'storeengine/order/item_name', $order_item->get_name(), $order_item ) ); ?>
							</h6>
						<?php } ?>
						<?php storeengine_display_item_meta( $order_item ); ?>
						<p>
							<span><?php esc_html_e( 'Qty:', 'storeengine' ); ?> </span>
							<?php echo esc_html( $order_item->get_quantity() ); ?>
						</p>
					</div>
				</div>
				<div class="storeengine-order-item__price"><?php echo $order->get_formatted_line_subtotal( $order_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php if ( $show_purchase_note && $purchase_note ) { ?>
					<div class="storeengine-order-item__purchase-note"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php } ?>
			</div>
		<?php } ?>
		<?php foreach ( $order->get_order_item_totals() as $key => $total ) { ?>
			<div
				class="storeengine-thankyou-summery--item storeengine-thankyou_<?php echo esc_attr( $total['type'] ); ?>">
				<p><?php echo esc_html( $total['label'] ); ?></p>
				<p><?php echo wp_kses_post( $total['value'] ); ?></p>
			</div>
		<?php } ?>
	</div>
	<?php if ( $order->get_customer_note() ) { ?>
		<div class="storeengine-order-note">
			<p><?php esc_html_e( 'Note:', 'storeengine' ); ?></p>
			<p><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), [ 'br' => [] ] ); ?></p>
		</div>
	<?php } ?>
	<?php do_action( 'storeengine/thankyou/after_order_details', $order ); ?>
</div>

