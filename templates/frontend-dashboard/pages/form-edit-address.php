<?php
/**
 * @var array $address
 * @var string $load_address
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_label = ( 'billing' === $load_address ) ? esc_html__( 'Billing address', 'storeengine' ) : esc_html__( 'Shipping address', 'storeengine' );

?>

<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--edit-address">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-col-lg-12 storeengine-frontend-address storeengine-column-items storeengine-edit-address">
				<h4 class="storeengine-frontend-heading">
					<?php
					printf(
					/* translators: %s: Address title */
						esc_html__( 'Edit %s', 'storeengine' ),
						esc_html( $form_label )
					);
					?>
				</h4>
				<form id="storeengineEditAddressForm" class="storeengine-edit-address-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate>
					<div class="storeengine-row">
						<?php
						foreach ( $address as $key => $field ) {
							storeengine_form_field( $key, $field, Helper::get_post_data_by_key( $key, $field['value'] ) );
						}
						?>
					</div>
					<div class="storeengine-row">
						<div class="storeengine-col-12">
							<input type="hidden" name="action" value="storeengine/frontend_dashboard_edit_address">
							<input type="hidden" name="address_type" value="<?php echo esc_attr( $load_address ); ?>">
							<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
							<button class='storeengine-btn--save-address' type="submit"><?php esc_html_e( 'Save Address', 'storeengine' ); ?></button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
