<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class ProductsArchive {

	public function __construct() {
		add_shortcode( 'storeengine_products_archive', [ $this, 'render' ] );
	}

	public function render() {
		ob_start();
		Template::get_template( 'shortcode/products-archive.php' );

		return ob_get_clean();
	}

}
