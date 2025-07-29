<?php
/**
 * @var \StoreEngine\Classes\Customer $customer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--edit-account">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-frontend-account">
				<div class="storeengine-edit-account">
					<h4 class="storeengine-frontend-heading"><?php esc_html_e( 'Update account details', 'storeengine' ); ?></h4>
					<form class="storeengine-edit-account-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
						<input type="hidden" name="action" value="storeengine/frontend_dashboard_change_profile_account_details">
						<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
						<div class="storeengine-form-group">
							<div class="storeengine-form__inner">
								<label for="email"><?php echo esc_html__( 'Email', 'storeengine' ); ?></label>
								<input type="email" id="email" name="email" value="<?php echo esc_html( $customer->get_email() ); ?>" placeholder="<?php esc_html_e( 'Email', 'storeengine' ); ?>">
							</div>
						</div>
						<div class="storeengine-form-group">
							<div class="storeengine-form__inner">
								<label for="first_name"><?php echo esc_html__( 'First Name', 'storeengine' ); ?></label>
								<input type="text" id="first_name" name="first_name" value="<?php echo esc_html( $customer->get_first_name() ); ?>" placeholder="<?php esc_html_e( 'First Name', 'storeengine' ); ?>">
							</div>
							<div class="storeengine-form__inner">
								<label for="last_name"><?php echo esc_html__( 'last Name', 'storeengine' ); ?></label>
								<input type="text" id="last_name" name="last_name" value="<?php echo esc_html( $customer->get_last_name() ); ?>" placeholder="<?php esc_html_e( 'last Name', 'storeengine' ); ?>">
							</div>
						</div>
						<button type="submit"><?php esc_html_e( 'Save', 'storeengine' ); ?></button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-frontend-password">
				<div class="storeengine-edit-account">
					<h4 class="storeengine-frontend-dashboard-heading"><?php esc_html_e( 'Update Password', 'storeengine' ); ?></h4>

					<form class="storeengine-change-password-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
						<input type="hidden" name="action" value="storeengine/frontend_dashboard_change_password">
						<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
						<div class="storeengine-form-group">
							<div class="storeengine-form__inner">
								<label for="current_password"><?php echo esc_html__( 'Current Password', 'storeengine' ); ?></label>
								<input type="password" id="current_password" name="current_password" placeholder="<?php esc_html_e( 'Current Password', 'storeengine' ); ?>">
							</div>
						</div>
						<div class="storeengine-form-group">
							<div class="storeengine-form__inner">
								<label for="new_password"><?php echo esc_html__( 'New Password', 'storeengine' ); ?></label>
								<input type="password" id="new_password" name="new_password" placeholder="<?php esc_html_e( 'New Password', 'storeengine' ); ?>">
							</div>
							<div class="storeengine-form__inner">
								<label for="confirm_password"><?php echo esc_html__( 'Confirm Password', 'storeengine' ); ?></label>
								<input type="password" id="confirm_password" name="confirm_password" placeholder="<?php esc_html_e( 'Confirm Password', 'storeengine' ); ?>">
							</div>
						</div>

						<button type="submit"><?php esc_html_e( 'Save', 'storeengine' ); ?></button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
