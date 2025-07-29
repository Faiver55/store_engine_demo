<?php
/**
 * Order details.
 *
 * @var Order $order
 */

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notes     = $order->get_customer_order_notes();
$downloads = $order->get_downloadable_items();
$actions   = array_filter( storeengine_get_account_orders_actions( $order ), fn( $key ) => 'view' !== $key, ARRAY_FILTER_USE_KEY );

?>
<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--order">
	<div class="storeengine-frontend-dashboard-page--header storeengine-mb-6">
		<div class="storeengine-frontend-dashboard-page--header__left">
			<h3 class="storeengine-mt-0 storeengine-mb-2">
				<span class="order-number">
					<span aria-hidden="true">#</span>
					<?php echo esc_html( $order->get_order_number() ); ?>
				</span>
				<span class="storeengine-status storeengine-<?php echo esc_attr( $order->get_status() ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Order status:', 'storeengine' ); ?></span>
					<?php echo esc_html( $order->get_status_title() ); ?>
				</span>
			</h3>
			<div class="order-date">
				<?php
				printf(
				// translators: %s: Order created date.
					esc_html__( 'Created on %s', 'storeengine' ),
					sprintf(
						'<time datetime="%1$s" data-format="%3$s">%2$s</time>',
						esc_attr( $order->get_date_created_gmt()->date( 'Y-m-d H:i:s' ) ),
						sprintf(
							// translators: %1$s: Order created date. %2$s: Order created time.
							esc_html__( '%1$s at %2$s', 'storeengine' ),
							esc_html( $order->get_date_created_gmt()->date_i18n( 'M d, Y' ) ),
							esc_html( $order->get_date_created_gmt()->date_i18n( 'g:i A' ) )
						),
						esc_attr_x( 'MMM DD, YYYY [at] h:mm A', 'Moment.js supported date format for user-dashboard order date', 'storeengine' ),
					)
				);
				?>
			</div>
		</div>
		<div class="storeengine-frontend-dashboard-page--header__right">
			<?php storeengine_render_dashboard_action_buttons( $actions ); ?>
		</div>
	</div>
	<?php
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
		[ 'order' => $order ]
	);

	do_action( 'storeengine/order/after_order_details', $order );

	Template::get_template(
		'frontend-dashboard/pages/partials/customer-details.php',
		[ 'order' => $order ]
	);
	?>
</div>
<?php
