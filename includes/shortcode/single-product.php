<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class SingleProduct {

	public function __construct() {
		add_shortcode( 'storeengine_single_product', array( $this, 'render' ) );
	}

	public function render(): string {
		ob_start();
		Template::get_template( 'single-product.php' );
		$output = Helper::remove_line_break( ob_get_clean() );
		return Helper::remove_tag_space( $output );
	}
}
