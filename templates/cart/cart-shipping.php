<?php
/**
 * @var array $package
 * @var ShippingRate[] $available_methods
 * @var bool $show_package_details
 * @var bool $show_shipping_calculator
 * @var string $package_details
 * @var string $package_name
 * @var int $index
 * @var string $chosen_method
 * @var string $formatted_destination
 * @var string $has_calculated_shipping
 */

use StoreEngine\Classes\Countries;
use StoreEngine\Shipping\ShippingRate;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;

defined( 'ABSPATH' ) || exit;

$formatted_destination    = $formatted_destination ?? Countries::init()->get_formatted_address($package['destination'], ', ');
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';
?>
<tr class="storeengine-shipping-totals shipping">
	<th><?php echo wp_kses_post( $package_name ); ?></th>
	<td data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
			<ul id="storeengine_shipping_method" class="storeengine-shipping-methods">
				<?php foreach ( $available_methods as $method ) : ?>
					<?php
					if ( $chosen_method !== $method->get_id() && Helper::is_cart() ) {
						continue;
					}
					?>
					<li>
						<?php
						if ( 1 < count( $available_methods ) && ! Helper::is_cart() ) {
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
							printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', $index, esc_attr( sanitize_title( $method->get_id() ) ), esc_attr( $method->get_id() ), checked( $method->get_id(), $chosen_method, false ) );
						} else {
							printf( '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />', $index, esc_attr( sanitize_title( $method->get_id() ) ), esc_attr( $method->get_id() ) );
						}
						printf( '<label for="shipping_method_%1$s_%2$s">%3$s</label>', $index, esc_attr( sanitize_title( $method->get_id() ) ), ShippingUtils::cart_totals_shipping_method_label( $method ) );
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

						do_action( 'storeengine/shipping/after_shipping_rate', $method, $index );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( Helper::is_cart() ) : ?>
				<p class="storeengine-shipping-destination">
					<?php
					if ( $formatted_destination ) {
						// Translators: $s shipping destination.
						printf( esc_html__( 'Shipping to %s.', 'storeengine' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' );
						$calculator_text = esc_html__( 'Change address', 'storeengine' );
					} else {
						echo wp_kses_post( apply_filters( 'storeengine/shipping/estimate_html', __( 'Shipping options will be updated during checkout.', 'storeengine' ) ) );
					}
					?>
				</p>
			<?php endif; ?>
		<?php
		elseif ( ! $has_calculated_shipping || ! $formatted_destination ) :
			if ( Helper::is_cart() && 'no' === Helper::get_settings( 'storeengine_enable_shipping_calc', 'no' ) ) {
				echo wp_kses_post( apply_filters( 'storeengine/shipping/not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'storeengine' ) ) );
			} else {
				echo wp_kses_post( apply_filters( 'storeengine/shipping/may_be_available_html', __( 'Please enter your address to see available shipping options.', 'storeengine' ) ) );
			}
		elseif ( ! Helper::cart() ) :
			echo wp_kses_post( apply_filters( 'storeengine/shipping/no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'storeengine' ) ) );
		else :
			echo wp_kses_post(
				/**
				 * Provides a means of overriding the default 'no shipping available' HTML string.
				 *
				 * @param string $html                  HTML message.
				 * @param string $formatted_destination The formatted shipping destination.
				 */
				apply_filters(
				'storeengine/cart/no_shipping_available_html',
					// Translators: %s shipping destination.
					sprintf( esc_html__( 'No shipping options were found for %s.', 'storeengine' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' ),
					$formatted_destination
				)
			);

			$calculator_text = esc_html__( 'Enter a different address', 'storeengine' );
		endif;
		?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="storeengine-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>

	</td>
</tr>
