<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( count( $categories ) ) {
	?>
<h4 class="storeengine-archive-product-widget__title"><?php esc_html_e( 'Category', 'storeengine' ); ?></h4>
<div class="storeengine-archive-product-widget__body">
	<?php foreach ( $categories as $parent_category ) : ?>
		<label class="parent-term">
			<input class="storeengine-archive-product-filter" type="checkbox" name="category" value="<?php echo esc_attr( urldecode( $parent_category->slug ) ); ?>"/>
			<span class="checkmark"></span>
			<span><?php echo esc_html( $parent_category->name ); ?></span>
		</label>
		<?php if ( count( $parent_category->children ) ) : ?>
			<?php foreach ( $parent_category->children as $child_category ) : ?>
				<label class="child-term">
					<input class="storeengine-archive-product-filter" type="checkbox" name="category" value="<?php echo esc_attr( urldecode( $child_category->slug ) ); ?>"/>
					<span class="checkmark"></span>
					<span><?php echo esc_html( $child_category->name ); ?></span>
				</label>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
<?php
}
