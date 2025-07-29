<?php
/**
 * @var $item_data array key-value item data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="storeengine-cart-item-data">
	<?php foreach ( $item_data as $data ) : ?>
		<li class="<?php echo sanitize_html_class( 'storeengine-cart-item-data-' . strtolower(str_replace(' ', '-', $data['label'])) ); ?>">
			<b><?php echo wp_kses_post( $data['label'] ); ?></b>:
			<span><?php echo wp_kses_post( $data['value'] ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>
