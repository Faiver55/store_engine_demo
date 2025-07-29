<?php
/**
 * Abstract Addon
 *
 * @version 1.0.0
 * @since StoreEngine 0.0.4
 */

namespace StoreEngine\Classes;

use StoreEngine\Interfaces\AddonInterface;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAddon implements AddonInterface {

	/**
	 * Addon Name.
	 * Unique name for the addon. This will be used for identifying & loading the addon,
	 * and triggering its action & filter hooks.
	 *
	 * @var string
	 */
	protected string $addon_name;

	/**
	 * Trigger Initialization hooks.
	 */
	protected function __construct() {
	}

	protected bool $running = false;

	final public function run() {
		if ( $this->running ) {
			return;
		}
		$this->running = true;
		if ( ! $this->addon_name ) {
			$class = get_class( $this );
			/* translators: %s: Addon abstraction class, usually a class extending AbstractAddon. */
			_doing_it_wrong( esc_html( $class ), sprintf( esc_html__( '%s must redeclare addon_name property.', 'storeengine' ), esc_html( $class ) ), '0.0.4' );

			return;
		}

		$this->define_constants();

		add_action( "storeengine/addons/activated_{$this->addon_name}", [ $this, 'addon_activation_hook' ] );
		add_action( "storeengine/addons/deactivated_{$this->addon_name}", [ $this, 'addon_deactivation_hook' ] );

		// if disable then stop running addons
		if ( ! Helper::get_addon_active_status( $this->addon_name ) ) {
			return;
		}

		$this->init_addon();

		/**
		 * Fires after specific addon loaded.
		 */
		do_action( "storeengine/addons/$this->addon_name/loaded" );
	}

	public function addon_activation_hook() {
	}

	public function addon_deactivation_hook() {
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
