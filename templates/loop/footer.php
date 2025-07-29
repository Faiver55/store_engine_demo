<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="storeengine-product__footer">
	<?php
		do_action( 'storeengine/templates/before_product_loop_footer_inner' );
		do_action( 'storeengine/templates/product_loop_footer_content' );
		do_action( 'storeengine/templates/after_product_loop_footer_inner' );
	?>
</div>
