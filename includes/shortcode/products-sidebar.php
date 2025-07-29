<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Term;

class ProductsSidebar {

	public function __construct() {
		add_shortcode( 'storeengine_products_sidebar', [ $this, 'render' ] );
	}

	public function render() {
		ob_start();
		do_action( 'storeengine/templates/archive_product_sidebar' );
		return ob_get_clean();
	}

}
