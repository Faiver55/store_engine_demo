<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storeengine-dashboard-withdrawal-info-wrapper">
	<div class="storeengine-dashboard-withdrawal-info">
		<div class="storeengine-dashboard-withdrawal-info--inner-wrap">
			<div class="storeengine-dashboard-withdrawal-info__icon">
				<span class="storeengine-icon storeengine-icon--withdraw"></span>
			</div>
			<div class="storeengine-dashboard-withdrawal-info__content">
				<span class="storeengine-cta-sub-title"><?php esc_html_e( 'Available Balance', 'storeengine' ); ?></span>
				<h4 class="storeengine-cta-title"><?php echo wp_kses_post( $available_amount ); ?></h4>
			</div>
		</div>
		<div class="storeengine-dashboard-withdrawal-info__action"></div>
	</div>

	<p class="storeengine-note">
		<span class="storeengine-icon storeengine-icon--info-primary"></span>
		<?php esc_html_e( 'Manage your withdrawal method', 'storeengine' ); ?> <a href="<?php echo esc_url( $payment_settings_url ); ?>"><?php esc_html_e( 'Settings', 'storeengine' ); ?></a>
	</p>
	<?php if ( $show_withdraw_button ) : ?>
	<form id="storeengine_withdrawal" class="storeengine-dashboard-instructor-earning-withdrawal" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
		<input type="hidden" name="action" value="storeengine_affiliate/affiliate_earning_withdrawal">
		<ul class="storeengine-dashboard-instructor-earning-withdrawal_type">
			<?php if ( 'paypal' === $withdraw_method_type ) : ?>
			<li>
				<label>
					<p>
						<span class="storeengine-icon storeengine-icon--paypal"></span>
						<span class="storeengine-withdraw-title"><?php esc_html_e( 'Paypal', 'storeengine' ); ?></span>
					</p>
					<input type="radio" name="withdrawal_type" value="paypal" checked>
				</label>
			</li>
			<?php elseif ( 'echeck' === $withdraw_method_type ) : ?>
			<li>
				<label>
					<p>
						<span class="storeengine-icon storeengine-icon--e-check"></span>
						<span class="storeengine-withdraw-title"><?php esc_html_e( 'E-check', 'storeengine' ); ?></span>
					</p>
					<input type="radio" name="withdrawal_type" value="echeck" checked>
				</label>
			</li>
			<?php elseif ( 'bank' === $withdraw_method_type ) : ?>
			<li>
				<label>
					<p>
						<span class="storeengine-icon storeengine-icon--bank-transfer"></span>
						<span class="storeengine-withdraw-title"><?php esc_html_e( 'Bank Transfer', 'storeengine' ); ?></span>
					</p>
					<input type="radio" name="withdrawal_type" value="bank_transfer" checked>
				</label>
			</li>
			<?php endif; ?>
		</ul>
		<div class="storeengine-withdrawal-amount-action">
			<input type="number" name="withdrawal_amount" class="storeengine-input" value="" placeholder="<?php esc_attr_e( 'Enter withdrawal amount', 'storeengine' ); ?>">
			<button class="storeengine-btn storeengine-btn--preset-purple"><?php echo esc_html__( 'Withdraw', 'storeengine' ); ?></button>
		</div>
	</form>
	<?php else : ?>
		<p class="storeengine-info"><?php esc_html_e( 'Sorry, you do not have sufficient balance to withdraw.', 'storeengine' ); ?></p>
	<?php endif; ?>
</div>

<div class="kzui-table kzui-table--withdraw">
	<div class="kzui-table__container">
		<div class="kzui-table__table kzui-table--has-slider">
			<div class="kzui-table__head">
				<div class="kzui-table__head-row">
					<div class="kzui-table__row-cell kzui-table__header-row-cell"><?php esc_html_e( 'Method', 'storeengine' ); ?></div>
					<div class="kzui-table__row-cell kzui-table__header-row-cell"><?php esc_html_e( 'Requested On', 'storeengine' ); ?></div>
					<div class="kzui-table__row-cell kzui-table__header-row-cell"><?php esc_html_e( 'Amount', 'storeengine' ); ?></div>
					<div class="kzui-table__row-cell kzui-table__header-row-cell"><?php esc_html_e( 'Status', 'storeengine' ); ?></div>
				</div>
			</div>
			<div class="kzui-table__body">
				<?php if ( is_array( $withdraw_history ) && count( $withdraw_history ) ) : ?>
					<?php foreach ( $withdraw_history as $withdraw_item ) : ?>
						<div class="kzui-table__body-row">
							<div class="kzui-table__row-cell"><?php echo esc_html( $withdraw_item['payment_method'] ); ?></div>
							<div class="kzui-table__row-cell"><?php echo esc_html( $withdraw_item['created_at'] ); ?></div>
							<div class="kzui-table__row-cell"><?php echo esc_html( $withdraw_item['payout_amount'] ); ?></div>
							<div class="kzui-table__row-cell"><?php echo esc_html( $withdraw_item['status'] ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="storeengine-oops storeengine-oops__message">
						<div class="storeengine-oops__icon">
							<img src="<?php echo esc_url( STOREENGINE_ASSETS_URI . 'images/NoDataAvailable.svg' ); ?>" alt="<?php esc_attr_e( 'Oops! No data available.', 'storeengine' ); ?>"><?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
						</div>
						<h3 class="storeengine-oops__heading"><?php esc_html_e( 'No data Available!', 'storeengine' ); ?></h3>
						<h3 class="storeengine-oops__text"><?php esc_html_e( 'No data was found to see the available list here.', 'storeengine' ); ?></h3>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
