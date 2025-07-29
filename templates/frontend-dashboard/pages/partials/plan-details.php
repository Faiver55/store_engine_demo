<?php
/**
 * Order details table
 *
 * @var Subscription $subscription
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dates_to_display = [
	'start'                   => _x( 'Start date', 'customer subscription table header', 'storeengine' ),
	'last_order_date_created' => _x( 'Last order date', 'customer subscription table header', 'storeengine' ),
	'next_payment'            => _x( 'Next payment date', 'customer subscription table header', 'storeengine' ),
	'end'                     => _x( 'End date', 'customer subscription table header', 'storeengine' ),
	'trial_end'               => _x( 'Trial end date', 'customer subscription table header', 'storeengine' ),
];
$dates_to_display = apply_filters( 'storeengine/subscription/details_table_dates_to_display', $dates_to_display, $subscription );
?>
	<div class="storeengine-dashboard__section-wrapper">
		<?php do_action( 'storeengine/plan_details/before_plan_table', $subscription ); ?>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--plan-details">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--plan-details">
				<tbody>
				<tr>
					<th><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<td><?php echo esc_html( $subscription->get_status_title() ); ?></td>
				</tr>
				<?php foreach ( $dates_to_display as $date_type => $date_title ) { ?>
					<?php if ( $subscription->get_date( $date_type ) ) { ?>
						<tr>
							<th><?php echo esc_html( $date_title ); ?></th>
							<td><?php echo esc_html( $subscription->get_date_to_display( $date_type ) ); ?></td>
						</tr>
					<?php } ?>
				<?php } ?>
				<?php if ( $subscription->get_next_payment_date() ) { ?>
					<tr>
						<th><?php esc_html_e( 'Payment', 'storeengine' ); ?></th>
						<td>
							<span data-is_manual="<?php echo esc_attr( Formatting::bool_to_string( $subscription->is_manual() ) ); ?>" class="subscription-payment-method"><?php echo esc_html( $subscription->get_payment_method_to_display( 'customer' ) ); ?></span>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php do_action( 'storeengine/plan_details/after_plan_table', $subscription ); ?>
	</div>
<?php
