<?php
/**
 * Notice template.
 *
 * @var string $notice_key
 * @var string $classes
 * @var array $notice
 */

?>
	<div id="storeengine-notice-<?php echo esc_attr( $notice['key'] ); ?>" class="<?php echo esc_attr( $classes ); ?>" role="alert">
		<?php if ( $notice['title'] ) { ?>
			<h3 class="notice-title" style="display:flex;align-items:center;gap:5px">
				<span class="storeengine-icon storeengine-icon--<?php echo esc_attr( $notice['icon'] ); ?>" style="display:flex" aria-hidden="true"></span>
				<span><?php echo esc_html( $notice['title'] ); ?></span>
			</h3>
		<?php } ?>
		<div class="storeengine-notice__wrapper">
			<?php if ( ! $notice['title'] && $notice['icon'] ) { ?>
				<div class="storeengine-notice__icon">
					<span class="storeengine-icon storeengine-icon--<?php echo esc_attr( $notice['icon'] ); ?>" aria-hidden="true"></span>
				</div>
			<?php } ?>
			<div class="storeengine-notice__content"><?php echo wp_kses_post( $notice['message'] ); ?></div>
			<?php if ( $notice['has_buttons'] ) { ?>
				<div class="storeengine-notice__control">
					<?php if ( $notice['button_text'] && $notice['button_action'] ) { ?>
						<a class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue" href="<?php echo esc_url( $notice['button_action'] ); ?>">
							<?php echo esc_html( $notice['button_text'] ); ?>
						</a>
					<?php } ?>
					<?php if ( $notice['dismissible'] ) { ?>
						<button class="storeengine-notice-close notice-dismiss" data-notice="<?php echo esc_attr( $notice['key'] ); ?>">
							<!-- <span class="storeengine-icon storeengine-icon--close" aria-hidden="true"></span>-->
							<span class="screen-reader-text"><?php _e( 'Dismiss this notice', 'storeengine' ); ?></span>
						</button>
					<?php } ?>
				</div>
			<?php } ?>
		</div>
	</div>
<?php
