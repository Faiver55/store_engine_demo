<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Utils\Formatting;

$current_url   = ( isset( $_SERVER['REQUEST_URI'] ) ) ? get_site_url() . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$dashboard_url = storeengine_get_dashboard_endpoint_url( 'index' );
$query         = new SubscriptionCollection( [
	'per_page' => $current_url === $dashboard_url ? 5 : 10,
	'page'     => max( 1, absint( get_query_var( 'paged' ) ) ),
	'orderby'  => 'id',
	'order'    => 'DESC',
	'where'    => [
		[
			'key'   => 'customer_id',
			'value' => get_current_user_id(),
		],
	],
] );

?>
	<div class="storeengine-dashboard__section-title">
		<h4><?php esc_html_e( 'Subscription History', 'storeengine' ); ?></h4>
		<?php if ( $query->get_found_results() && $current_url === $dashboard_url ) : ?>
			<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans' ) ); ?>" style="display:flex;justify-content:center;align-items:center;line-height:1;gap:2px">
				<?php esc_html_e( 'View All', 'storeengine' ); ?>
				<i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i>
			</a>
		<?php endif; ?>
	</div>
<?php if ( $query->have_results() ) : ?>
	<div class="storeengine-dashboard__section">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
			<thead>
			<tr>
				<th scope="col" class="col-subscription-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Subscription ID', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-subscription-item"><?php esc_html_e( 'Item', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Date', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Next Renewal', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $query->get_results() as $subscription ) : ?>
				<tr>
					<td class="col-subscription-id" data-label="<?php esc_attr_e( 'ID', 'storeengine' ); ?>">
						<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans', $subscription->get_id() ) ); ?>">
							<?php echo esc_html( $subscription->get_id() ); ?>
						</a>
					</td>
					<td class="col-subscription-items" data-label="<?php esc_attr_e( 'Item', 'storeengine' ); ?>">
						<?php
						$line_item = $subscription->get_items();
						if ( $line_item ) {
							/** @var \StoreEngine\Classes\Order\OrderItemProduct $line_item */
							$line_item = reset( $line_item );
							$item_link = $line_item->get_product() && 'publish' === $line_item->get_product()->get_status() ? get_permalink( $line_item->get_product_id() ) : null;
							if ( $item_link ) {
								printf(
									'<a href="%1$s">%2$s</a>',
									esc_url( $item_link ),
									esc_html( $line_item->get_name() )
								);
							} else {
								echo esc_html( $line_item->get_name() );
							}
						} else {
							esc_html_e( 'N/A', 'storeengine' );
						}
						?>
					</td>
					<td class="col-subscription-date" data-label="<?php esc_attr_e( 'Created Date', 'storeengine' ); ?>">
						<?php if ( $subscription->get_date_created_gmt() ) { ?>
						<time datetime="<?php echo esc_attr( $subscription->get_date_created_gmt()->format( 'Y-m-d H:i:s' ) ); ?>">
							<?php echo esc_html( $subscription->get_date_created_gmt()->date( 'd M Y, h:i A (T)' ) ); ?>
						</time>
						<?php } ?>
					</td>
					<td class="col-subscription-date" data-label="<?php esc_attr_e( 'Next renewal date', 'storeengine' ); ?>">
						<?php if ( $subscription->get_next_payment_date() ) { ?>
							<time datetime="<?php echo esc_attr( $subscription->get_next_payment_date()->format( 'Y-m-d H:i:s' ) ); ?>">
								<?php echo esc_html( $subscription->get_next_payment_date()->date( 'd M Y, h:i A (T)' ) ); ?>
							</time>
							<br/>
							<small><?php echo esc_attr( $subscription->get_payment_method_to_display( 'customer' ) ); ?></small>
						<?php } ?>
					</td>
					<td class="col-subscription-status" data-label="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
						<div class="storeengine-status storeengine-<?php echo esc_attr( $subscription->get_status() ); ?>">
							<?php echo esc_html( $subscription->get_status_title() ); ?>
						</div>
					</td>
					<td class="col-subscription-total" data-label="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
						<?php echo wp_kses_post( Formatting::price( $subscription->get_total() ) ); ?>
					</td>
					<td class="col-actions">
						<?php storeengine_render_dashboard_action_buttons( Utils::get_account_subscription_actions( $subscription ), 'subscription' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $dashboard_url !== $current_url ) {
			do_action( 'storeengine/templates/dashboard_subscription_pagination', $query );
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
<?php
endif;
