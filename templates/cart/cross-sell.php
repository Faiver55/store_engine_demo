<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
if ( have_posts() ) :
	?>
<div class="storeengine-container">
	<div class="storeengine-row">
		<div class="storeengine-col-12">
			<div class="storeengine-product-single__content-item storeengine-single-course__content-item--description">
				<h2><?php echo __( 'You may be interested in', 'storeengine' ); ?></h2>
				<div class="storeengine-products storeengine-products--grid">
					<div class="storeengine-products__body">
						<div class="storeengine-row">
						<?php
							// Load posts loop.
						while ( have_posts() ) {
							the_post();
							Template::get_template( 'content-product.php', array( 'grid_class' => $grid_class ) );
						}
							wp_reset_postdata();
						?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
endif;
