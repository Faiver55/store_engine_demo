<?php
/**
 * @var \StoreEngine\Classes\Order|WP_Error $order Order object.
 */
?>
<div class="storeengine-thankyou-order-info-shortcode">
	<?php if ( is_wp_error($order) ) : ?>
		<div class="storeengine-thankyou-order-info-error">
			<h4 class="storeengine-thankyou-order-info-heading"><i class="storeengine-icon storeengine-icon--notify-warning" aria-hidden="true"></i> <?php esc_html_e( 'Something went wrong.', 'storeengine' ); ?></h4>
			<p class="storeengine-thankyou-order-info-heading-message">
				<?php echo wp_kses_post( $order->get_error_message() ); ?>
			</p>
		</div>
	<?php else :
		$order_placed_date = $order->get_order_placed_date() ? $order->get_order_placed_date() : $order->get_date_created_gmt();
		?>
		<div class="storeengine-thankyou-order-info-success">
			<div class="storeengine-thankyou-order-info-success__content">
				<div class="storeengine-thankyou-order-info-success__content-order">
					<div class="storeengine-thankyou-order-info-success__icon">
						<i class="storeengine-icon storeengine-icon--checkmark-badge"></i>
					</div>
					<div class="storeengine-thankyou-order-info-success__order">
						<h4 class="storeengine-thankyou-order-info-heading"><?php esc_html_e( 'Thank You For Your Order', 'storeengine' ); ?></h4>
						<p>
							<span><?php esc_html_e( 'Order ID: #', 'storeengine' ); ?></span>
							<span><?php echo esc_html( $order->get_id() ); ?></span>
							<time datetime="<?php echo esc_attr( $order_placed_date->date_i18n( 'Y-m-d H:i:s' ) ); ?>" class="storeengine-ml-2"><?php echo esc_html( '(' . $order_placed_date->date_i18n( 'F j, Y' ) . ')' ); ?></time>
						</p>
						<p>
							<span><?php esc_html_e( 'Order Status:', 'storeengine' ); ?></span>
							<span class="storeengine-<?php echo esc_attr( $order->get_status() ); ?> storeengine-ml-2"><?php echo esc_html( $order->get_status_title() ); ?></span>
						</p>
					</div>
				</div>
			</div>
			<div class="storeengine-thankyou-order-info-success__email">
				<p>
					<span><?php esc_html_e( 'Email:', 'storeengine' ); ?></span>
					<a class="storeengine-ml-2" href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a>
				</p>
			</div>
			<?php do_action( 'storeengine/thankyou/order_info', $order ); ?>
		</div>
	<?php endif; ?>
</div>
