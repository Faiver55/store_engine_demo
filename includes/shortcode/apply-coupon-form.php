<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use StoreEngine\Models\Cart as CartModel;

class ApplyCouponForm {
	public function __construct() {
		add_shortcode( 'storeengine_apply_coupon_form', array( $this, 'render_apply_coupon_form' ) );
	}
	public function render_apply_coupon_form() {
		ob_start();
			Template::get_template( 'shortcode/apply-coupon-form.php' );
		return ob_get_clean();
	}
}
