<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Formatting;

$current_url   = ( isset( $_SERVER['REQUEST_URI'] ) ) ? get_site_url() . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$dashboard_url = storeengine_get_dashboard_endpoint_url( 'index' );
$query         = new OrderCollection( [
	'per_page' => $current_url === $dashboard_url ? 5 : 10,
	'page'     => max( 1, absint( get_query_var( 'paged' ) ) ),
	'where'    => [
		[
			'key'   => 'customer_id',
			'value' => get_current_user_id(),
		],
		[
			'key'     => 'status',
			'value'   => [ 'draft', 'auto-draft', 'trash' ],
			'compare' => 'NOT IN',
		],
	],
], 'order' );

?>
	<div class="storeengine-dashboard__section-title">
		<h4><?php esc_html_e( 'Order History', 'storeengine' ); ?></h4>
		<?php if ( $query->get_found_results() && $current_url === $dashboard_url ) : ?>
			<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'orders' ) ); ?>" style="display:flex;justify-content:center;align-items:center;line-height:1;gap:2px">
				<?php esc_html_e( 'View All', 'storeengine' ); ?>
				<i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i>
			</a>
		<?php endif; ?>
	</div>
<?php if ( $query->have_results() ) : ?>
	<div class="storeengine-dashboard__section">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--orders">
			<thead>
			<tr>
				<th scope="col" class="col-order-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Order ID', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-order-order-date"><?php esc_html_e( 'Order Placed', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-items"><?php esc_html_e( 'Product Items', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $query->get_results() as $order ) : ?>
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
					<td class="col-order-items" data-label="<?php esc_attr_e( 'Item', 'storeengine' ); ?>">
						<?php
						$total_qty = array_sum( array_map( fn( $order_item ) => $order_item->get_quantity(), $order->get_items() ) );
						printf(
						/* translators: %d Total number of items in the order. */
							esc_html( _n( '%d item', '%d items', $total_qty, 'storeengine' ) ),
							esc_html( $total_qty )
						);
						?>
					</td>
					<td class="col-order-status" data-label="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
						<div class="storeengine-status storeengine-<?php echo esc_attr( $order->get_status() ); ?>">
							<?php echo esc_html( $order->get_status_title() ); ?>
						</div>
					</td>
					<td class="col-order-total" data-label="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
						<?php
						if ( $order->get_total_refunded() > 0 ) :
							$net_amount = $order->get_total() - $order->get_total_refunded();
							?>
							<s><?php echo wp_kses_post( Formatting::price( $order->get_total() ) ); ?></s>
							<p><?php echo wp_kses_post( Formatting::price( $net_amount ) ); ?></p>
						<?php
						else :
							echo wp_kses_post( Formatting::price( $order->get_total() ) );
						endif;
						?>
					</td>
					<td class="col-actions">
						<?php storeengine_render_dashboard_action_buttons( storeengine_get_account_orders_actions( $order ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $dashboard_url !== $current_url ) {
			do_action( 'storeengine/templates/dashboard_order_pagination', $query );
		}
		?>
	</div>
<?php else : ?>
	<div class="storeengine-oops storeengine-oops__message">
		<div class="storeengine-oops__icon">
			<h3 class="storeengine-oops__heading"><?php esc_html_e( 'No data Available!', 'storeengine' ); ?></h3>
			<h3 class="storeengine-oops__text"><?php esc_html_e( 'No purchase data was found to see the available list here.', 'storeengine' ); ?></h3>
		</div>
	</div>
<?php endif;
