<?php
/**
 * Order customer details
 *
 * @var Order $order
 */

use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_shipping = $order->needs_shipping_address();

?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4><?php esc_html_e( 'Customer details', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--order-customer-details">
			<div class="storeengine-row storeengine-dashboard__order-addresses">
				<div class="<?php echo $show_shipping ? 'storeengine-col-6' : 'storeengine-col-12'; ?> storeengine-dashboard__order-billing-address order-billing-address">
					<h3 class="storeengine-address__title"><?php esc_html_e( 'Billing address', 'storeengine' ); ?></h3>
					<address>
						<p class="storeengine-customer-details--address storeengine-mb-2"><?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'storeengine' ) ) ); ?></p>
						<?php if ( $order->get_billing_phone() ) : ?>
							<p class="storeengine-customer-details--phone storeengine-mb-2">
								<a href="<?php echo esc_url( 'tel:' . $order->get_billing_phone() ); ?>">
									<span class="storeengine-icon storeengine-icon--phone" aria-hidden="true"></span>
									<?php echo esc_html( $order->get_billing_phone() ); ?>
								</a>
							</p>
						<?php endif; ?>

						<?php if ( $order->get_billing_email() ) : ?>
							<p class="storeengine-customer-details--email">
								<a href="<?php echo esc_url( 'mailto' . $order->get_billing_email() ); ?>">
									<span class="storeengine-icon storeengine-icon--email" aria-hidden="true"></span>
									<?php echo esc_html( $order->get_billing_email() ); ?>
								</a>
							</p>
						<?php endif; ?>

						<?php
						/**
						 * Action hook fired after an address in the order customer details.
						 *
						 * @param string $address_type Type of address (billing or shipping).
						 * @param Order $order Order object.
						 */
						do_action( 'storeengine/order/details_after_customer_address', 'billing', $order );
						?>
					</address>
				</div>
				<?php if ( $show_shipping ) { ?>
					<div class="storeengine-col-6 storeengine-dashboard__order-shipping-address order-shipping-address">
						<h3 class="storeengine-address__title"><?php esc_html_e( 'Shipping address', 'storeengine' ); ?></h3>
						<address>
							<p class="storeengine-customer-details--address storeengine-mb-2"><?php echo wp_kses_post( $order->get_formatted_shipping_address( esc_html__( 'N/A', 'storeengine' ) ) ); ?></p>
							<?php if ( $order->get_shipping_phone() ) : ?>
								<p class="storeengine-customer-details--phone">
									<a href="<?php echo esc_url( 'tel:' . $order->get_shipping_phone() ); ?>">
										<span class="storeengine-icon storeengine-icon--phone" aria-hidden="true"></span>
										<?php echo esc_html( $order->get_shipping_phone() ); ?>
									</a>
								</p>
							<?php endif; ?>

							<?php
							/**
							 * Action hook fired after an address in the order customer details.
							 *
							 * @param string $address_type Type of address (billing or shipping).
							 * @param Order $order Order object.
							 */
							do_action( 'storeengine/order/details_after_customer_address', 'shipping', $order );
							?>
						</address>
					</div>
				<?php } ?>
			</div>
		</div>
	</div>
<?php
