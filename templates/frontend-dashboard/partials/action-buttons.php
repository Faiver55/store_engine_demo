<?php
/**
 * Action buttons
 *
 * @var array $action first item...
 * @var array $actions remaining items...
 * @var string $action_for
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! empty( $action ) || ! empty( $actions ) ) {
	?>
	<div class="storeengine-order-actions storeengine-dropdown storeengine-order-actions--<?php echo esc_attr( $action_for ); ?>">
		<?php
		foreach ( $action as $key => $item ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			storeengine_print_dropdown_button( $key, $item );
		}
		?>
		<?php if ( ! empty( $actions ) ) { ?>
		<button type="button" class="storeengine-btn storeengine-dropdown--toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle Dropdown', 'storeengine' ); ?></span>
			<?php storeengine_render_icon( 'three-dots-menu' ); ?>
		</button>
		<div class="storeengine-dropdown--menu">
			<?php
			foreach ( $actions as $key => $item ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				storeengine_print_dropdown_button( $key, $item );
			}
			?>
		</div>
		<?php } ?>
	</div>
	<?php
}

// End of file action-buttons.php.
