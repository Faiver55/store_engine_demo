<?php

namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Error;

class OrderDownloads {

	public function __construct() {
		add_shortcode('storeengine_order_downloads', [ $this, 'render' ]);
	}

	public function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
		$order      = Helper::get_order_by_key( $order_hash );
		$order      = $order instanceof WP_Error ? false : $order;

		if ( ! $order ) {
			return '';
		}

		ob_start();
		Template::get_template( 'shortcode/order-downloads.php', [
			'order' => $order,
		] );
		return ob_get_clean();
	}
}
