<?php
/**
 * Order details table
 *
 * @var Order[] $subscription_orders
 * @var Subscription $subscription
 */

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4><?php esc_html_e( 'Related orders', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--subscription-list">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
				<thead>
				<tr>
					<th scope="col" class="col-subscription-id">
						<span class="screen-reader-text"><?php esc_html_e( 'Order', 'storeengine' ); ?></span>
						#
					</th>
					<th scope="col" class="col-subscription-item"><?php esc_html_e( 'Date', 'storeengine' ); ?></th>
					<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $subscription_orders as $order ) {
					$order      = \StoreEngine\Utils\Helper::get_order( $order );
					$item_count = $order->get_item_count();
					?>
					<tr>
						<td class="col-order-id" data-label="<?php esc_attr_e( 'Order ID', 'storeengine' ); ?>">
							<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'orders', $order->get_id() ) ); ?>">
								<?php echo esc_html( $order->get_id() ); ?>
							</a>
						</td>
						<td class="col-order-order-date" data-label="<?php esc_attr_e( 'Order Placed', 'storeengine' ); ?>">
							<?php if ( $order->get_order_placed_date_gmt() ) { ?>
								<time datetime="<?php echo esc_attr( $order->get_order_placed_date_gmt()->format( 'Y-m-d H:i:s' ) ); ?>"><?php echo esc_html( $order->get_order_placed_date_gmt()->date( 'd M Y, h:i A (T)' ) ); ?></time>
							<?php } ?>
						</td>
						<td class="col-order-status" data-label="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
							<div class="storeengine-status storeengine-<?php echo esc_attr( $order->get_status() ); ?>">
								<?php echo esc_html( $order->get_status_title() ); ?>
							</div>
						</td>
						<td class="col-order-total" data-label="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
							<?php
							// translators: $1: formatted order total for the order, $2: number of items bought
							echo wp_kses_post( sprintf( _n( '%1$s for %2$d item', '%1$s for %2$d items', $item_count, 'storeengine' ), $order->get_formatted_order_total(), $item_count ) );
							?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php
