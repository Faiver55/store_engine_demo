<?php
namespace StoreEngine\Addons\Affiliate;

use StoreEngine\Addons\Affiliate\Shortcode\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
class Shortcode {

	public static function init() {
		$self = new self();
		$self->dispatch_shortcode();
	}
	public function dispatch_shortcode() {
		new Registration();
	}
}
