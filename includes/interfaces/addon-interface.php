<?php
/**
 * Addon Interface.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AddonInterface {

	/**
	 * Initialize singleton addon.
	 *
	 * @return AddonInterface
	 */
	public static function init();

	/**
	 * Define constants.
	 * Developers are encourage to use Class Constants
	 *
	 * @return void
	 */
	public function define_constants();

	/**
	 * Loads the addon.
	 *
	 * @return void
	 */
	public function init_addon();

	/**
	 * Trigger once during addon activation.
	 *
	 * @return void
	 */
	public function addon_activation_hook();

	/**
	 * Trigger once during addon deactivation.
	 *
	 * @return void
	 */
	public function addon_deactivation_hook();
}
