<?php
/**
 * Login Template.
 *
 * Template variables:
 *
 * @var string $form_title Form title text
 * @var string $username_label Label for the username field
 * @var string $username_placeholder Placeholder for the username field
 * @var string $password_label Label for the password field
 * @var string $password_placeholder Placeholder for the password field
 * @var string $remember_label Label for remembered checkbox
 * @var string $login_button_label Label for login button
 * @var string $reset_password_label Label for reset password link
 * @var boolean $show_logged_in_message Whether to show a logged-in message
 * @var string $register_url URL for registration
 * @var string $login_redirect_url URL to redirect after login
 * @var string $logout_redirect_url URL to redirect after logout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-login-form-wrapper">
	<?php do_action( 'storeengine/templates/shortcode/before_login' ); ?>
	<h2 class="storeengine-login-form-heading"><?php echo esc_html( $form_title ); ?></h2>
	<form id="storeengine_login_form" class="storeengine-login-form" action="#" method="post">
		<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
		<div class="storeengine-form-group">
			<label for="username"
				   class="storeengine-form-group__title">
				<?php echo esc_html( $username_label ); ?>
			</label>
			<input id="username" type="text" class="storeengine-form-control" name="username"
				   placeholder="<?php echo esc_attr( $username_placeholder ); ?>">
		</div>
		<div class="storeengine-form-group">
			<label for="password"
				   class="storeengine-form-group__title"><?php echo esc_html( $password_label ); ?></label>
			<div class="storeengine-password-wrapper">
				<input id="password" type="password" class="storeengine-form-control" name="password"
					   placeholder="<?php echo esc_attr( $password_placeholder ); ?>">
				<button type="button" class="toggle-password" data-toggle="false"
						aria-label="<?php echo esc_attr( $password_label ); ?>">
					<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<div class="storeengine-form-group storeengine-d-flex storeengine-flex-row storeengine-justify-content-between">
			<div class="storeengine-form-group__inner storeengine-d-flex storeengine-flex-row">
				<input name="rememberme" type="checkbox" id="rememberme"
					   value="forever" <?php checked( ! empty( $_POST['rememberme'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>>
				<label for="rememberme"><?php echo esc_html( $remember_label ); ?></label>
			</div>
			<div class="storeengine-form-group__inner">
				<a class="storeengine-form-text-link"
				   href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html( $reset_password_label ); ?></a>
			</div>
		</div>
		<?php do_action( 'storeengine/templates/login_form_before_submit' ); ?>
		<div class="storeengine-form-group">
			<input type="hidden" name="redirect_to"
				   value="<?php echo esc_url_raw( isset( $_GET['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) : $login_redirect_url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>"/>

			<?php if ( isset( $_GET['action'] ) && '' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<input type="hidden" name="action"
					   value="<?php echo esc_attr( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>"/>
			<?php } ?>

			<button class="storeengine-btn storeengine-btn--bg-blue"
					type="submit"><?php echo esc_html( $login_button_label ); ?></button>
		</div>
	</form>
	<?php if ( get_option( 'users_can_register' ) ) { ?>
		<div class="storeengine-login-form-info">
			<p><?php esc_html_e( 'Don\'t have an account?', 'storeengine' ); ?> <a
					href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register Now', 'storeengine' ); ?></a>
			</p>
		</div>
	<?php } ?>
	<?php do_action( 'storeengine/templates/shortcode/after_login' ); ?>
</div>
