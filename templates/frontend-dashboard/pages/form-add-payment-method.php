<?php
/**
 * Add payment method form form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-add-payment-method.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.8.0
 */

use StoreEngine\Utils\Helper;

defined( 'ABSPATH' ) || exit;

$available_gateways = Helper::get_payment_gateways()->get_available_payment_gateways();

if ( $available_gateways ) : ?>
	<form id="add_payment_method" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<div id="payment" class="storeengine-dashboard-payment storeengine-p-0">
			<ul class="storeengine-dashboard-payment-methods payment_methods methods storeengine-p-0 storeengine-m-0" style="list-style:none">
				<?php
				// Chosen Method.
				if ( count( $available_gateways ) ) {
					current( $available_gateways )->set_current();
				}

				foreach ( $available_gateways as $gateway ) {
					?>
					<li class="storeengine-payment-method storeengine-payment-method--<?php echo esc_attr( $gateway->id ); ?> payment_method_<?php echo esc_attr( $gateway->id ); ?>">
						<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio storeengine-mt-0 storeengine-ml-0" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> />
						<label id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>"><?php echo wp_kses_post( $gateway->get_title() ); ?> <?php echo wp_kses_post( $gateway->get_icon() ); ?></label>						<?php
						if ( $gateway->has_fields() || $gateway->get_description() ) {
							echo '<div class="storeengine-payment-box storeengine-payment-box--' . esc_attr( $gateway->id ) . ' payment_box payment_method_' . esc_attr( $gateway->id ) . '" style="display: none;">';
							$gateway->payment_fields();
							echo '</div>';
						}
						?>
					</li>
					<?php
				}
				?>
			</ul>

			<?php do_action( 'woocommerce_add_payment_method_form_bottom' ); ?>

			<div class="form-row" style="display:flex;justify-content:right;margin-top:24px;">
				<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
				<input type="hidden" name="storeengine_add_payment_method" id="storeengine_add_payment_method" value="1" />
				<input type="hidden" name="action" value="storeengine/payment_method/add" />
				<button type="submit" class="storeengine-btn storeengine-btn--preset-blue" id="place_order" value="<?php esc_attr_e( 'Add payment method', 'storeengine' ); ?>"><?php esc_html_e( 'Add payment method', 'storeengine' ); ?></button>
			</div>
		</div>
	</form>
<?php else : ?>
	<div class="storeengine-dashboard-payment-gateways-empty storeengine-notice storeengine-notice-warning">
		<p><?php esc_html_e( 'New payment methods can only be added during checkout. Please contact us if you require assistance.', 'storeengine' ); ?></p>
	</div>
<?php endif; ?>
