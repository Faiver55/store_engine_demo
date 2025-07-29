<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
?>
<div class="storeengine-product-single__header">
	<?php
	do_action( 'storeengine/templates/single-product/before_header' );
	?>

	<div class="storeengine-d-flex">
		<div class="storeengine-entry-left">
			<?php
			Template::get_template( 'single-product/gallery.php' );
			?>
		</div>
		<div class="storeengine-entry-right">
			<h1 class="storeengine-single__title"><?php the_title(); ?></h1>
			<div class="storeengine-entry_taxonomy">
				<?php Template::get_template( 'single-product/categories.php' ); ?>
				<?php Template::get_template( 'single-product/tag.php' ); ?>
			</div>
			<?php do_action( 'storeengine/templates/single-product/header_right_content' ); ?>
		</div>
	</div>
	<?php
	do_action( 'storeengine/templates/single-product/after_header' );
	?>
</div>
