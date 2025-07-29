<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( count( $tags ) ) {
	?>
<h4 class="storeengine-archive-product-widget__title"><?php esc_html_e( 'Tag', 'storeengine' ); ?></h4>
<div class="storeengine-archive-product-widget__body">
<?php foreach ( $tags as $tag_item ) : ?>
	<label>
		<input class="storeengine-archive-product-filter" type="checkbox" name="tags" value="<?php echo esc_attr( urldecode( $tag_item->slug ) ); ?>"/>
		<span class="checkmark"></span>
		<span><?php echo esc_html( $tag_item->name ); ?></span>
	</label>
	<?php endforeach; ?>
</div>
<?php
}
