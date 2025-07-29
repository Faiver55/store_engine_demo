<?php
/**
 * Dashboard top bar.
 *
 * @var Subscription $subscription
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notes     = $subscription->get_customer_order_notes();
$downloads = $subscription->get_downloadable_items();
$actions   = array_filter( Utils::get_account_subscription_actions( $subscription ), fn( $key ) => 'view' !== $key, ARRAY_FILTER_USE_KEY );
?>
<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--plan">
	<div class="storeengine-frontend-dashboard-page--header storeengine-mb-6">
		<div class="storeengine-frontend-dashboard-page--header__left">
			<h3 class="storeengine-mt-0 storeengine-mb-2">
				<span class="order-number">
					<span class="screen-reader-text"><?php esc_html_e( 'Plan number:', 'storeengine' ); ?></span>
					<span aria-hidden="true">#</span>
					<?php echo esc_html( $subscription->get_order_number() ); ?>
				</span>
				<span class="storeengine-status storeengine-<?php echo esc_attr( $subscription->get_status() ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Plan status:', 'storeengine' ); ?></span>
					<?php echo esc_html( $subscription->get_status_title() ); ?>
				</span>
			</h3>
			<div class="order-date">
				<?php
				printf(
				// translators: %s: Subscription date.
					esc_html__( 'Subscribed on %s', 'storeengine' ),
					sprintf(
						'<time datetime="%1$s" data-format="%3$s">%2$s</time>',
						esc_attr( $subscription->get_date_created_gmt()->date( 'Y-m-d H:i:s' ) ),
						sprintf(
						// translators: %1$s: Order created date. %2$s: Order created time.
							esc_html__( '%1$s at %2$s', 'storeengine' ),
							esc_html( $subscription->get_date_created_gmt()->date_i18n( 'M d, Y' ) ),
							esc_html( $subscription->get_date_created_gmt()->date_i18n( 'g:i A' ) )
						),
						esc_attr_x( 'MMM DD, YYYY [at] h:mm A', 'Moment.js supported date format for user-dashboard order date', 'storeengine' ),
					)
				);
				?>
			</div>
		</div>
		<div class="storeengine-frontend-dashboard-page--header__right">
			<?php storeengine_render_dashboard_action_buttons( $actions, 'subscription' ); ?>
		</div>
	</div>

	<?php

	Template::get_template(
		'frontend-dashboard/pages/partials/plan-details.php',
		[ 'subscription' => $subscription ]
	);

	if ( $notes ) {
		Template::get_template(
			'frontend-dashboard/pages/partials/order-notes.php',
			[ 'notes' => $notes ]
		);
	}

	if ( ! empty( $downloads ) ) {
		Template::get_template(
			'frontend-dashboard/pages/partials/order-downloads.php',
			[ 'downloads' => $downloads ]
		);
	}

	Template::get_template(
		'frontend-dashboard/pages/partials/order-details.php',
		[
			'title' => __( 'Subscription totals', 'storeengine' ),
			'order' => $subscription,
		]
	);

	do_action( 'storeengine/subscription/after_subscription_details', $subscription );

	Template::get_template(
		'frontend-dashboard/pages/partials/customer-details.php',
		[ 'order' => $subscription ]
	);
	?>
</div>
<?php
