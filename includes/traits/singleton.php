<?php
/**
 * Singleton
 *
 * @version 1.0.0
 * @since 0.0.4
 */

namespace StoreEngine\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Singleton {

	protected static $instance;

	public static function init() {
		return self::get_instance();
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'storeengine' ), '0.0.4' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'storeengine' ), '0.0.4' );
	}
}
