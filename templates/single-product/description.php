<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-product-single__content-item storeengine-single-course__content-item--description">
	<h2><?php esc_html_e( 'Description', 'storeengine' ); ?></h2>
	<?php
		the_content();
	?>
</div>
