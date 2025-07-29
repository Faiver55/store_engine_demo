<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


?>

<div class="storeengine-ajax-checkout-form__contact-information">
	<h4 class="storeengine-checkout-form-section-heading"><?php esc_html_e( 'Contact', 'storeengine' ); ?></h4>
	<div class="storeengine-form-field storeengine-form-field--user-info">
		<div class="storeengine-form-field__inner">
			<label for="user_email"><?php esc_html_e( 'Email or phone number', 'storeengine' ); ?></label>
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="storeengine-form-field__login-link">
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'storeengine' ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<p class="storeengine-form-field__login-link">
					<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Logout', 'storeengine' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<input type="email" name="user_email" id="user_email" value="<?php echo esc_attr( $current_user_email ); ?>" placeholder="<?php esc_attr_e( 'Email or phone number', 'storeengine' ); ?>" required<?php wp_readonly( is_user_logged_in() ); ?>>
	</div>

	<?php if ( is_user_logged_in() ) : ?>
		<div class="storeengine-form-field storeengine-mb-3">
			<label class="storeengine-flex storeengine-flex-align-center" style="display:flex;gap:5px">
				<input type="checkbox" name="subscribe_to_email">
				<span><?php esc_html_e( 'Email me with news and offers', 'storeengine' ); ?></span>
			</label>
		</div>
	<?php endif; ?>
</div>
