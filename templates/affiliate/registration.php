<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<div class="storeengine-logged-in-message">
	<!-- Add a button -->
	<?php if ( is_user_logged_in() ) : ?>
		<?php if ( $affiliate_pending ) : ?>
			<p><?php esc_html_e('Your affiliate registration request is pending approval. We’ll review your application and notify you soon. Thank you for your patience!', 'storeengine'); ?></p>
		<?php elseif ( ! user_can( get_current_user_id(), 'storeengine_affiliate' ) ) : ?>
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<p><?php esc_html_e( 'Earn rewards by sharing our products! Join our affiliate program and start earning commissions for every referral. Click the button below to submit your application — it’s quick and easy.', 'storeengine'); ?></p>
				<input type="hidden" name="action" value="storeengine/apply_for_affiliation">
				<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
				<button type="submit" class="storeengine-btn storeengine-btn--bg-blue"><?php esc_html_e( 'Apply for affiliate', 'storeengine' ); ?></button>
			</form>
		<?php else : ?>
			<p>
				<?php esc_html_e('You are already an affiliate member! Start earning commissions by promoting our products.', 'storeengine'); ?>
			</p>
			<a href="<?php echo esc_url(\StoreEngine\Utils\Helper::get_account_endpoint_url('affiliate-partner')); ?>"><?php esc_html_e('Affiliate Dashboard', 'storeengine'); ?></a>
		<?php endif; ?>
	<?php else : ?>
		<?php
		if ( isset( $_GET['registration_success'] ) && 'true' === $_GET['registration_success'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="success-message">' . esc_html__( 'Registration successful! You can now log in.', 'storeengine' ) . '</div>';
		}
		?>
		<form id="storeengine_affiliate_registration_form" class="storeengine-affiliate-registration-form storeengine-login-form-wrapper" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
			<input type="hidden" name="action" value="storeengine/register_for_affiliate">

			<?php if ( ! empty( $form_title) ) : ?>
			<h2 class="storeengine-login-form-heading"><?php echo esc_html( $form_title ); ?></h2>
			<?php endif; ?>

			<div class="storeengine-form-group">
				<?php if ( $first_name_label ) : ?>
					<label class="storeengine-form-group__title" for="affiliate_first_name"><?php echo esc_html( $first_name_label ); ?></label>
				<?php endif; ?>
				<input id="affiliate_first_name" type="text" class="storeengine-form-control" name="first_name" value="" placeholder="<?php echo esc_attr( $first_name_placeholder ); ?>" required>
			</div>
			<div class="storeengine-form-group">
				<?php if ( $last_name_label ) : ?>
					<label class="storeengine-form-group__title" for="affiliate_last_name"><?php echo esc_html( $last_name_label ); ?></label>
				<?php endif; ?>
				<input id="affiliate_last_name" type="text" class="storeengine-form-control" name="last_name" value="" placeholder="<?php echo esc_attr( $last_name_placeholder ); ?>" required>
			</div>
			<div class="storeengine-form-group">
				<?php if ( $email_label ) : ?>
					<label class="storeengine-form-group__title" for="affiliate_email"><?php echo esc_html( $email_label ); ?></label>
				<?php endif; ?>
				<input id="affiliate_email" type="email" class="storeengine-form-control" name="email" value="" placeholder="<?php echo esc_attr( $email_placeholder ); ?>" required>
			</div>
			<div class="storeengine-form-group">
				<?php if ( $password_label ) : ?>
					<label class="storeengine-form-group__title" for="password"><?php echo esc_html( $password_label ); ?></label>
				<?php endif; ?>
				<input id="password" type="password" class="storeengine-form-control" name="password" value="" placeholder="<?php echo esc_attr( $password_placeholder ); ?>" required>
			</div>
			<?php do_action( 'storeengine/affiliate/templates/registration-form-before-submit' ); ?>
			<div class="storeengine-form-group">
				<label aria-hidden="true">&nbsp;</label>
				<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php echo esc_html( $registration_button_label ); ?></button>
			</div>
		</form>
	<?php endif; ?>
</div>
