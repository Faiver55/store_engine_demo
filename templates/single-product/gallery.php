<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-gallery_wrapper">
	<?php if ( storeengine_has_product_gallery() ) : ?>
	<div class="storeengine-row">
		<!-- Thumbnail Gallery Carousel -->
		<div class="storeengine-col-12 storeengine-col-lg-2">
			<div class="carousel carousel-nav storeengine-product-single__thumbnail" data-flickity='{"asNavFor":".carousel-main","draggable":false,"percentPosition":false,"groupCells":"100%","pageDots":false,"loop":true,"prevNextButtons":false}'>
				<span class="carousel-cell"><?php storeengine_product_image(); ?></span>
				<?php storeengine_product_gallery(); ?>
			</div>
		</div>

		<!-- Main Product Image Carousel -->
		<div class="storeengine-col-12 storeengine-col-lg-10">
			<div class="carousel carousel-main" data-flickity='{"contain":true,"pageDots":false,"loop":true,"prevNextButtons":false}'>
				<?php storeengine_product_image(); ?>
				<?php storeengine_product_gallery(); ?>
			</div>
		</div>
	</div>
	<?php else : ?>
	<div class="storeengine-row">
		<!-- Thumbnail Gallery Carousel -->
		<div class="storeengine-col-12">
			<?php storeengine_product_image(); ?>
		</div>
	</div>
	<?php endif; ?>
</div>
