<?php
/** @var \StoreEngine\Classes\Order $order */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Helper;

$customer          = Helper::cart()->get_customer();
$states            = Countries::init()->get_states( $customer->get_billing_country() );
$billing_country   = $customer->get_billing_country();
$billing_country   = empty( $billing_country ) ? Countries::init()->get_base_country() : $billing_country;
$allowed_countries = Countries::init()->get_allowed_countries();
if ( ! array_key_exists( $billing_country, $allowed_countries ) ) {
	$billing_country = current( array_keys( $allowed_countries ) );
}
$address_fields = Countries::init()->get_address_fields( $billing_country );

$state_label = $address_fields['billing_state']['label'] ?? __( 'State / County', 'storeengine' );
$is_required = $address_fields['billing_state']['required'] ?? false;

?>
<div class="storeengine-ajax-checkout-form__billing-address">
	<h4 class="storeengine-checkout-form-section-heading"><?php esc_html_e( 'Billing address', 'storeengine' ); ?></h4>
	<?php if ( ! $is_digital_cart ) { ?>
	<div class="storeengine-form-group storeengine-form-group--same-as-shipping">
		<label><input type="radio" name="same_as_shipping" value="true" checked><?php esc_html_e('Same as shipping address', 'storeengine'); ?></label>
		<label><input type="radio" name="same_as_shipping" value="false"><?php esc_html_e('Use A different billing address', 'storeengine'); ?></label>
	</div>
	<?php } ?>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="billing_first_name"><?php esc_html_e( 'First Name', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="text" name="billing_first_name" placeholder="<?php esc_attr_e( 'First Name', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_first_name() ); ?>" required/>
		</div>
		<div class="storeengine-form__inner">
			<label for="billing_last_name"><?php esc_html_e( 'Last Name', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="text" name="billing_last_name" placeholder="<?php esc_attr_e( 'Last Name', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_last_name() ); ?>" required/>
		</div>
	</div>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="billing_email"><?php esc_html_e( 'Email address', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="email" name="billing_email" placeholder="<?php esc_attr_e( 'Email address', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_email() ); ?>" required/>
		</div>
		<div class="storeengine-form__inner">
			<label for="billing_phone"><?php esc_html_e( 'Phone Number', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="text" name="billing_phone" placeholder="<?php esc_attr_e( 'Phone', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_phone() ); ?>" required/>
		</div>
	</div>
	<div class="storeengine-form-address-group">
		<div class="storeengine-form-field">
			<label for="billing_address_1"><?php esc_html_e( 'Address Line 1', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="text" name="billing_address_1" placeholder="<?php esc_attr_e( 'Address', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_address_1() ); ?>" required/>
		</div>
		<div class="storeengine-form-field">
			<label for="billing_address_2"><?php esc_html_e( 'Address Line 2', 'storeengine' ); ?>&nbsp;<span class="optional">(<?php esc_html_e( 'optional', 'storeengine' ); ?>)</span></label><input type="text" name="billing_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, etc. (optional)', 'storeengine' ); ?> " value="<?php echo esc_attr( $customer->get_billing_address_2() ); ?>"/>
		</div>
		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="billing_country"><?php esc_html_e( 'Country', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
				<select name="billing_country" class="country_to_state" required>
					<?php foreach ( Countries::init()->get_allowed_countries() as $country_key => $country_name ) : ?>
						<option value="<?php echo esc_attr( $country_key ); ?>" <?php selected( $customer->get_billing_country(), $country_key ); ?>><?php echo esc_html( $country_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="storeengine-form__inner">
				<label for="billing_city"><?php esc_html_e( 'City', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
				<input type="text" name="billing_city" placeholder="<?php esc_attr_e( 'City', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_city() ); ?>" required/>
			</div>
		</div>
	</div>

	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="billing_state">
				<?php echo esc_html( $state_label ); ?>
				<?php if ( $is_required ) { ?>
					&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr>
				<?php } ?>
			</label>
			<?php if ( is_array( $states ) && empty( $states ) ) { ?>
				<input type="text" name="billing_state" value="<?php echo esc_attr( $customer->get_billing_state() ); ?>" readonly/>
			<?php } elseif ( ! is_null( $customer->get_billing_country() ) && is_array( $states ) ) { ?>
				<select name="billing_state">
					<option value=""><?php esc_html_e( 'Select an option&hellip;', 'storeengine' ); ?></option>
					<?php foreach ( $states as $sk => $sv ) { ?>
						<option value="<?php echo esc_attr( $sk ); ?>"<?php selected( $customer->get_billing_state(), $sk ); ?>><?php echo esc_html( $sv ); ?></option>
					<?php } ?>
				</select>
			<?php } else { ?>
				<input type="text" name="billing_state" placeholder="<?php esc_attr_e( 'State', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_state() ); ?>"<?php echo $is_required ? ' required' : ''; ?>/>
			<?php } ?>
		</div>
		<div class="storeengine-form__inner">
			<label for="billing_postcode"><?php esc_html_e( 'Postal Code', 'storeengine' ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label><input type="text" name="billing_postcode" placeholder="<?php esc_attr_e( 'Postal Code', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_postcode() ); ?>" required/>
		</div>
	</div>

</div>
