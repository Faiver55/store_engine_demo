<?php
/**
 * @var \StoreEngine\Classes\Order $order
 * @var \StoreEngine\Addons\Subscription\Classes\Subscription[] $subscriptions
 */

use StoreEngine\Utils\Formatting;

?>
<div class="storeengine-order-subscription-details storeengine-mt-6 storeengine-pt-6">
	<div class="storeengine-order-subscription-details-header">
		<h3><?php esc_html_e( 'Related Subscriptions', 'storeengine' ); ?></h3>
	</div>
	<div class="storeengine-order-subscription-details-body">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
			<thead>
			<tr>
				<th scope="col" class="col-subscription-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Subscription ID', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-subscription-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-next-payment-date"><?php esc_html_e( 'Next payment', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $subscriptions as $subscription ) : ?>
				<tr>
					<td class="col-subscription-id" data-label="<?php esc_attr_e( 'ID', 'storeengine' ); ?>"><?php echo esc_html( $subscription->get_id() ); ?></td>
					<td class="col-subscription-status storeengine-<?php echo esc_attr( $subscription->get_status() ); ?>" data-label="<?php esc_attr_e( 'Status', 'storeengine' ); ?>"><p><?php echo esc_html( $subscription->get_status_title() ); ?></p></td>
					<td class="col-subscription-next-payment-date" data-label="<?php esc_attr_e( 'Next renewal date', 'storeengine' ); ?>">
						<?php if ( $subscription->get_next_payment_date() ) { ?>
							<time datetime="<?php echo esc_attr( $subscription->get_next_payment_date()->format( 'Y-m-d H:i:s' ) ); ?>">
								<?php echo esc_html( $subscription->get_next_payment_date()->date( 'd M Y, h:i A (T)' ) ); ?>
							</time>
						<?php } ?>
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

