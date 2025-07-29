<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;

class ProceedToCheckout {
	public function __construct() {
		add_shortcode( 'storeengine_proceed_to_checkout', array( $this, 'render_proceed_to_checkout' ) );
	}
	public function render_proceed_to_checkout() {
		ob_start();
		Template::get_template( 'shortcode/proceed-to-checkout.php'  );
		return ob_get_clean();
	}
}
