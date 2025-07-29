<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;

class ContinueShopping {
	public function __construct() {
		add_shortcode( 'storeengine_continue_shopping', array( $this, 'render_continue_shopping' ) );
	}
	public function render_continue_shopping() {
		ob_start();
		Template::get_template( 'shortcode/continue-shopping.php'  );
		return ob_get_clean();
	}
}
