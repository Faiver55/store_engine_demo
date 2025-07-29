<?php
/**
 * @var string $label
 * @var string $content
 */
?>
<div class="storeengine-product-single__content-item storeengine-single-course__content-item--description">
	<?php if ( ! empty( $label ) ) : ?>
		<h2><?php echo esc_html( $label ); ?></h2>
	<?php endif; ?>
	<?php echo wp_kses_post( $content ); ?>
</div>
