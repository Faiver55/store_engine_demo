<?php

use StoreEngine\Utils\Helper;

?>
	<div class="storeengine-frontend-dashboard__invalid-order">
		<div class="storeengine-frontend-dashboard__invalid-order-header">
			<div><?php storeengine_render_icon( 'stoke-close icon-invalid' ); ?></div>
			<div class="storeengine-frontend-dashboard__invalid-order-header-title">
				<h2><?php esc_html_e( 'Invalid Order', 'storeengine' ); ?></h2>
				<?php if ( ! empty( $order ) ) { ?>
					<p>
						<?php
						printf(
						/* translators: %s. Order ID. */
							esc_html__( 'Order ID : #%s', 'storeengine' ),
							esc_html( $order )
						);
						?>
					</p>
				<?php } ?>
			</div>
		</div>
		<span class="storeengine-frontend-dashboard__invalid-order-sub-title"><?php esc_html_e( 'Order details', 'storeengine' ); ?></span>
		<div class="storeengine-frontend-dashboard__invalid-order-details">
			<div><?php storeengine_render_icon( 'stoke-close icon-invalid' ); ?></div>
			<p><?php esc_html_e( 'Invalid Order', 'storeengine' ); ?></p>
			<a class="storeengine-frontend-dashboard__invalid-order-details-back-btn" href="<?php echo esc_url( Helper::get_dashboard_url() ); ?>">
				<?php storeengine_render_icon( 'left-arrow' ); ?>
				<?php esc_html_e( 'Go To Dashboard', 'storeengine' ); ?>
			</a>
		</div>

	</div>
<?php
