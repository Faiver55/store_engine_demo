<?php
/**
 * Payment methods
 *
 * Shows customer payment methods on the account page.
 */

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;

defined( 'ABSPATH' ) || exit;

$saved_methods = PaymentUtil::get_customer_saved_methods_list( get_current_user_id() );
$has_methods   = (bool) $saved_methods;
$types         = PaymentUtil::get_account_payment_methods_types();

do_action( 'storeengine/before_account_payment_methods', $has_methods );

if ( $has_methods ) :
	?>

	<table class="storeengine-dashboard-payment-methods shop_table shop_table_responsive account-payment-methods-table">
		<thead>
		<tr>
			<?php foreach ( PaymentUtil::get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
				<th class="storeengine-dashboard-payment-method storeengine-dashboard-payment-method--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>">
					<span class="nobr"><?php echo esc_html( $column_name ); ?></span>
				</th>
			<?php endforeach; ?>
		</tr>
		</thead>
		<?php foreach ( $saved_methods as $type => $methods ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
			<?php foreach ( $methods as $method ) : ?>
				<tr class="payment-method<?php echo esc_attr( ! empty( $method['is_default'] ) ? ' default-payment-method payment-method--' . $type : ' payment-method--' . $type ); ?>">
					<?php foreach ( PaymentUtil::get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
						<td class="storeengine-dashboard-payment-method storeengine-dashboard-payment-method--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php
							if ( has_action( 'storeengine/account_payment_methods_column_' . $column_id ) ) {
								do_action( 'storeengine/account_payment_methods_column_' . $column_id, $method );
							} elseif ( 'method' === $column_id ) {
								if ( ! empty( $method['method']['last4'] ) ) {
									/* translators: 1: credit card type 2: last 4 digits */
									echo sprintf( esc_html__( '%1$s ending in %2$s', 'storeengine' ), esc_html( PaymentUtil::get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
								} else {
									echo esc_html( PaymentUtil::get_credit_card_type_label( $method['method']['brand'] ) );
								}
							} elseif ( 'expires' === $column_id ) {
								echo esc_html( $method['expires'] );
							} elseif ( 'actions' === $column_id ) {
								foreach ( $method['actions'] as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
									echo '<a href="' . esc_url( $action['url'] ) . '" class="button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>&nbsp;';
								}
							}
							?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<div class="storeengine-dashboard-payment-methods-empty storeengine-notice storeengine-notice-warning">
		<p><?php esc_html_e( 'No saved methods found.', 'storeengine' ); ?></p>
	</div>
<?php endif; ?>
<?php do_action( 'storeengine/after_account_payment_methods', $has_methods ); ?>
<?php if ( Helper::get_payment_gateways()->get_available_payment_gateways() ) : ?>
	<a class="storeengine-btn storeengine-btn--preset-blue" href="<?php echo esc_url( Helper::get_account_endpoint_url( 'add-payment-method' ) ); ?>"><?php esc_html_e( 'Add payment method', 'storeengine' ); ?></a>
<?php endif; ?>
