<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;

Template::get_template(
	'frontend-dashboard/pages/partials/sub-menu.php',
	[
		'menu' => apply_filters('storeengine/templates/frontend-dashboard/affiliate-payment-settings-content', [
			'affiliate-partner' => __( 'Affiliate History', 'storeengine' ),
			'payment-settings'  => __( 'Payment Settings', 'storeengine' ),
		]),
	]
);
?>

<div class="storeengine-dashboard-settings__withdraw">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
		<input type="hidden" name="action" value="storeengine_affiliate/save_frontend_dashboard_withdraw_settings">
		<div class="storeengine-settings-info-heading"><?php echo esc_html__( 'Select a withdraw method', 'storeengine' ); ?></div>
		<div class="storeengine-form-block storeengine-form-block--withdraw-method">
		<?php if ( ! $is_enabled_paypal_withdraw && ! $is_enabled_echeck_withdraw && ! $is_enabled_bank_withdraw ) : ?>
			<span class="storeengine-sub-title"><?php esc_html_e( 'No Payment Method Selected in Admin Dashboard, Make Sure to Select a Payment Method to Withdrawal Money.', 'storeengine' ); ?></span>
		<?php endif; ?>
		<?php if ( $is_enabled_paypal_withdraw ) : ?>
			<label class="<?php echo esc_attr( 'storeengine-withdraw-method' . ( 'paypal' === $withdraw_method_type ? ' storeengine-withdraw-method--selected' : '' ) ); ?>" id="paypal-label">
				<input name="withdrawMethodType" type="radio" value="paypal" <?php checked( $withdraw_method_type, 'paypal', true ); ?>>
				<span class="storeengine-withdraw-method--label">
					<span class="storeengine-withdraw-method__heading"><?php echo esc_html__( 'Paypal', 'storeengine' ); ?></span>
					<span class="storeengine-withdraw-method__subheading"><?php echo esc_html__( 'Min withdraw $ 10', 'storeengine' ); ?></span>
				</span>
			</label>
		<?php endif; ?>
		<?php if ( $is_enabled_echeck_withdraw ) : ?>
			<label class="<?php echo esc_attr( 'storeengine-withdraw-method' . ( 'echeck' === $withdraw_method_type ? ' storeengine-withdraw-method--selected' : '' ) ); ?>" id="echeck-label">
				<input name="withdrawMethodType" type="radio" value="echeck" <?php checked( $withdraw_method_type, 'echeck', true ); ?>>
				<span class="storeengine-withdraw-method--label">
					<span class="storeengine-withdraw-method__heading"><?php echo esc_html__( 'E-Check', 'storeengine' ); ?></span>
					<span class="storeengine-withdraw-method__subheading"><?php echo esc_html__( 'Min withdraw $ 10', 'storeengine' ); ?></span>
				</span>
			</label>
		<?php endif; ?>
		<?php if ( $is_enabled_bank_withdraw ) : ?>
			<label class="<?php echo esc_attr( 'storeengine-withdraw-method' . ( 'bank' === $withdraw_method_type ? ' storeengine-withdraw-method--selected' : '' ) ); ?>" id="bank-label">
				<input name="withdrawMethodType" type="radio" value="bank" <?php checked( $withdraw_method_type, 'bank', true ); ?>>
				<span class="storeengine-withdraw-method--label">
					<span class="storeengine-withdraw-method__heading"><?php echo esc_html__( 'Bank Transfer', 'storeengine' ); ?></span>
					<span class="storeengine-withdraw-method__subheading"><?php echo esc_html__( 'Min withdraw $ 10', 'storeengine' ); ?></span>
				</span>
			</label>
		<?php endif; ?>
		</div>
		<?php // Tabs.. ?>
		<?php if ( $is_enabled_paypal_withdraw ) : ?>
		<div id="paypal" class="<?php echo esc_attr( 'storeengine-form-block storeengine-mb-4 storeengine-withdraw-method-form' . ( 'paypal' === $withdraw_method_type ? ' storeengine-withdraw-method-form--active' : '' ) ); ?>">
			<label for="paypalEmailAddress"><?php echo esc_html__( 'PayPal E-Mail Address', 'storeengine' ); ?></label>
			<input name="paypalEmailAddress" id="paypalEmailAddress" type="email" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_paypal_email', true ) ); ?>">
			<span class="storeengine-note"><?php echo esc_html__( 'We will use this email address to send the money to your Paypal account', 'storeengine' ); ?></span>
		</div>
		<?php endif; ?>
		<?php if ( $is_enabled_echeck_withdraw ) : ?>
		<div id="echeck" class="<?php echo esc_attr( 'storeengine-form-block storeengine-mb-4 storeengine-withdraw-method-form' . ( 'echeck' === $withdraw_method_type ? ' storeengine-withdraw-method-form--active' : '' ) ); ?>">
			<label for="echeckAddress"><?php echo esc_html__( 'Your Physical Address', 'storeengine' ); ?></label>
			<textarea name="echeckAddress" id="echeckAddress"><?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_echeck_address', true ) ); ?></textarea>
			<span class="storeengine-note"><?php echo esc_html__( 'We will send you an E-Check to this address directly.', 'storeengine' ); ?></span>
		</div>
		<?php endif; ?>
		<?php if ( $is_enabled_bank_withdraw ) : ?>
		<div id="bank" class="<?php echo esc_attr( 'storeengine-form-block storeengine-mb-4 storeengine-withdraw-method-form' . ( 'bank' === $withdraw_method_type ? ' storeengine-withdraw-method-form--active' : '' ) ); ?>">
			<div class="storeengine-form-block">
				<label for="bankAccountName"><?php echo esc_html__( 'Account Name', 'storeengine' ); ?></label>
				<input name="bankAccountName" id="bankAccountName" type="text" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_bank_acocunt_name', true ) ); ?>">
			</div>
			<div class="storeengine-form-block">
				<label for="bankAccountNumber"><?php echo esc_html__( 'Account Number', 'storeengine' ); ?></label>
				<input name="bankAccountNumber" id="bankAccountNumber" type="text" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_bank_acocunt_number', true ) ); ?>">
			</div>
			<div class="storeengine-form-block">
				<label for="bankName"><?php echo esc_html__( 'Bank Name', 'storeengine' ); ?></label>
				<input name="bankName" id="bankName" type="text" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_bank_name', true ) ); ?>">
			</div>
			<div class="storeengine-form-block">
				<label for="bankIBAN"><?php echo esc_html__( 'IBAN', 'storeengine' ); ?></label>
				<input name="bankIBAN" id="bankIBAN" type="text" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_bank_iban', true ) ); ?>">
			</div>
			<div class="storeengine-form-block">
				<label for="bankSWIFTCode"><?php echo esc_html__( 'BIC / SWIFT', 'storeengine' ); ?></label>
				<input name="bankSWIFTCode" id="bankSWIFTCode" type="text" value="<?php echo esc_html( get_user_meta( $current_user_id, 'storeengine_affiliate_withdraw_bank_swiftcode', true ) ); ?>">
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $is_enabled_paypal_withdraw || $is_enabled_echeck_withdraw || $is_enabled_bank_withdraw ) : ?>
		<div class="storeengine-form-submit-wrapper">
			<button type="submit" class="storeengine-btn storeengine-btn--bg-purple storeengine-btn--save-settings"><?php esc_html_e( 'Save Settings', 'storeengine' ); ?></button>
		</div>
		<?php endif; ?>
	</form>
	<script>
		(function($){
			$(document).on('change', '[name="withdrawMethodType"]', function() {
				const selected = $(this).val();
				$('.storeengine-withdraw-method-form').removeClass('storeengine-withdraw-method-form--active');
				$('.storeengine-withdraw-method-form#'+selected).addClass('storeengine-withdraw-method-form--active');
			} );
		})(jQuery);
	</script>
</div>
