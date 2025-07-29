<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Helper;

$customer          = Helper::cart()->get_customer();
$states            = Countries::init()->get_states( $customer->get_shipping_country() );
$shipping_country  = $customer->get_shipping_country();
$shipping_country  = empty( $shipping_country ) ? Countries::init()->get_base_country() : $shipping_country;
$allowed_countries = Countries::init()->get_allowed_countries();
if ( ! array_key_exists( $shipping_country, $allowed_countries ) ) {
	$shipping_country = current( array_keys( $allowed_countries ) );
}
$address_fields = Countries::init()->get_address_fields( $shipping_country );

$state_label = $address_fields['shipping_state']['label'] ?? __( 'State / County', 'storeengine' );
$is_required = $address_fields['shipping_state']['required'] ?? false;
?>
<div class="storeengine-ajax-checkout-form__shipping-address">
	<h4 class="storeengine-checkout-form-section-heading"><?php esc_html_e( 'Shipping address', 'storeengine' ); ?></h4>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="shipping_first_name"><?php esc_attr_e( 'First Name', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<input type="text" name="shipping_first_name" id="shipping_first_name" value="<?php echo esc_attr( $customer->get_shipping_first_name() ); ?>" placeholder="<?php esc_attr_e( 'First Name', 'storeengine' ); ?>" required/>
		</div>
		<div class="storeengine-form__inner">
			<label for="shipping_last_name"><?php esc_attr_e( 'Last Name', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<input type="text" name="shipping_last_name" id="shipping_last_name" value="<?php echo esc_attr( $customer->get_shipping_last_name() ); ?>" placeholder="<?php esc_attr_e( 'Last Name', 'storeengine' ); ?>" required/>
		</div>
	</div>
	<div class="storeengine-form-field">
		<label for="shipping_phone"><?php esc_attr_e( 'Phone Number', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
		<input type="text" name="shipping_phone" id="shipping_phone" value="<?php echo esc_attr( $customer->get_shipping_phone() ); ?>" placeholder="<?php esc_attr_e( 'Phone', 'storeengine' ); ?>" required/>
	</div>
	<div class="storeengine-form-field">
		<label for="shipping_address_1"><?php esc_attr_e( 'Address Line 1', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
		<input type="text" name="shipping_address_1" id="shipping_address_1" placeholder="<?php esc_attr_e( 'Address', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_address_1() ); ?>" required/>
	</div>
	<div class="storeengine-form-field">
		<label for="shipping_address_2"><?php esc_attr_e( 'Address Line 2', 'storeengine' ); ?></label>
		<input type="text" name="shipping_address_2" id="shipping_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, etc. (optional)', 'storeengine' ); ?> " value="<?php echo esc_attr( $customer->get_shipping_address_2() ); ?>"/>
	</div>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="shipping_country"><?php esc_attr_e( 'Country', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<select name="shipping_country" id="shipping_country">
			<?php foreach ( Countries::init()->get_allowed_countries() as $country_key => $country_name ) : ?>
				<option value="<?php echo esc_attr( $country_key ); ?>" <?php selected( $customer->get_shipping_country(), $country_key ); ?>><?php echo esc_html( $country_name ); ?></option>
			<?php endforeach; ?>
			</select>
		</div>
		<div class="storeengine-form__inner">
			<label for="shipping_city"><?php esc_attr_e( 'City', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<input type="text" name="shipping_city" placeholder="<?php esc_attr_e( 'City', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_city() ); ?>" required/>
		</div>
	</div>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="shipping_state">
				<?php echo esc_html( $state_label ); ?>
				<?php if ( $is_required ) { ?>
					&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr>
				<?php } ?>
			</label>
			<?php if ( is_array( $states ) && empty( $states ) ) { ?>
				<input type="text" name="shipping_state" value="<?php echo esc_attr( $customer->get_shipping_state() ); ?>" readonly/>
			<?php } elseif ( ! is_null( $customer->get_shipping_country() ) && is_array( $states ) ) { ?>
				<select name="shipping_state">
					<option value=""><?php esc_html_e( 'Select an option&hellip;', 'storeengine' ); ?></option>
					<?php foreach ( $states as $sk => $sv ) { ?>
						<option value="<?php echo esc_attr( $sk ); ?>"<?php selected( $customer->get_shipping_state(), $sk ); ?>><?php echo esc_html( $sv ); ?></option>
					<?php } ?>
				</select>
			<?php } else { ?>
				<input type="text" name="shipping_state" placeholder="<?php esc_attr_e( 'State', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_state() ); ?>"<?php echo $is_required ? ' required' : ''; ?>/>
			<?php } ?>
		</div>
		<div class="storeengine-form__inner">
			<label for="shipping_postal_code"><?php esc_attr_e( 'Postal Code', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<input type="text" name="shipping_postal_code" id="shipping_postal_code" value="<?php echo esc_attr( $customer->get_shipping_postcode() ); ?>" placeholder="<?php esc_attr_e( 'Postal Code', 'storeengine' ); ?>" required/>
		</div>
	</div>
</div>
