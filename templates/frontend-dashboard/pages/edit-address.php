<?php
/**
 * @var \StoreEngine\Classes\Customer $customer
 * @var array $countries
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$customer_id = get_current_user_id();

$address_types = apply_filters(
	'storeengine/dashboard/edit_address_types',
	[
		'billing'  => __( 'Billing address', 'storeengine' ),
		'shipping' => __( 'Shipping address', 'storeengine' ),
	],
	$customer_id
);

?>

<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--edit-address">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-col-12">
				<p class='storeengine-address-header'><?php echo apply_filters( 'storeengine/dashboard/edit_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'storeengine' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>
		</div>
		<div class="storeengine-row">
			<?php foreach ( $address_types as $addr_type => $label ) {
				$address = storeengine_get_dashboard_formatted_address( $addr_type );
				?>
				<div class="storeengine-col-lg-6" id="<?php echo esc_attr( 'storeengine-' . $addr_type . '-address' ); ?>">
					<div class="storeengine-frontend-address storeengine-frontend-dashboard-address-<?php echo esc_attr( $addr_type ); ?>">
						<header class="storeengine-address-title">
							<h4 class="storeengine-frontend-heading storeengine-mx-0 storeengine-my-3"><?php echo esc_html( $label ); ?></h4>
							<p class="storeengine-flex storeengine-mt-0 storeengine-mb-3 storeengine-flex-align-center">
								<a href="<?php echo esc_url( \StoreEngine\Utils\Helper::get_account_endpoint_url( 'edit-address', $addr_type ) ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-address-edit storeengine-address-edit--<?php echo esc_attr( $addr_type ); ?>">
									<?php
									if ( $address ) {
										/* translators: %1$s: Address title (editing). */
										printf( esc_html__( 'Edit %1$s', 'storeengine' ), esc_html( $label ) );
									} else {
										/* translators: %1$s: Address title (adding). */
										printf( esc_html__( 'Add %1$s', 'storeengine' ), esc_html( $label ) );
									}
									?>
								</a>
							</p>
						</header>
						<address>
							<?php
							echo $address ? wp_kses_post( $address ) : esc_html__( 'You have not set up this type of address yet.', 'storeengine' );

							/**
							 * Used to output content after core address fields.
							 *
							 * @param string $addr_type Address type.
							 */
							do_action( 'storeengine/dashboard/after-edit-address', $addr_type );
							?>
						</address>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
</div>
