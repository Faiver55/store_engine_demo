<?php
/**
 * Order details table
 *
 * @var Subscription[] $subscriptions
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4><?php esc_html_e( 'Related subscriptions', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--subscription-list">
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
					<th scope="col" class="col-actions"></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $subscriptions as $subscription ) : ?>
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
							<?php } ?>
						</td>
						<td class="col-subscription-status storeengine-<?php echo esc_attr( $subscription->get_status() ); ?>" data-label="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
							<p><?php echo esc_html( $subscription->get_status_title() ); ?></p>
						</td>
						<td class="col-subscription-total" data-label="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
							<?php echo wp_kses_post( Formatting::price( $subscription->get_total() ) ); ?>
						</td>
						<td class="col-actions"><?php // @TODO renew/cancellation action buttons. ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php
