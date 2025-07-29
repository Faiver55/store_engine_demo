<?php
/**
 * Plugin Name:     StoreEngine
 * Plugin URI:      https://storeengine.pro
 * Description:     Powerful WordPress eCommerce Plugin for Payments, Memberships, Affiliates, Sales & More
 * Version:         1.3.2
 * Author:          Kodezen
 * Author URI:      http://kodezen.com
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     storeengine
 * Domain Path:     /i18n/languages/
 *
 * Requires PHP: 7.4
 * Requires at least: 6.5
 * Tested up to: 6.8
 *
 * @package StoreEngine
 */

use StoreEngine\ActionQueue;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StoreEngine {

	protected static ?StoreEngine $instance = null;

	public ?Customer $customer = null;
	public ?Cart $cart         = null;

	private function __construct() {
		$this->define_constants();
		$this->set_global_settings();
		$this->load_dependency();
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'storeengine_loaded', array( $this, 'init_plugin' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'dispatch_post_hooks' ) );
	}

	public static function init(): StoreEngine {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function define_constants() {
		/**
		 * Defines CONSTANTS for Whole plugins.
		 */
		define( 'STOREENGINE_VERSION', '1.3.2' );
		define( 'STOREENGINE_DB_VERSION', '1.0' );
		define( 'STOREENGINE_PLUGIN_FILE', __FILE__ );
		define( 'STOREENGINE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'STOREENGINE_PLUGIN_SLUG', 'storeengine' );
		define( 'STOREENGINE_SETTINGS_NAME', 'storeengine_settings' );
		define( 'STOREENGINE_ADDONS_SETTINGS_NAME', 'storeengine_addons' );
		define( 'STOREENGINE_PAYMENTS_SETTINGS_NAME', 'storeengine_payments_settings' );
		define( 'STOREENGINE_PLUGIN_ROOT_URI', plugins_url( '/', __FILE__ ) );
		/** @define "STOREENGINE_ROOT_DIR_PATH" "./" */
		define( 'STOREENGINE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		/** @define "STOREENGINE_INCLUDES_DIR_PATH" "./includes/" */
		define( 'STOREENGINE_INCLUDES_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'includes/' );
		define( 'STOREENGINE_ASSETS_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'assets/' );
		define( 'STOREENGINE_ASSETS_URI', STOREENGINE_PLUGIN_ROOT_URI . 'assets/' );
		define( 'STOREENGINE_ADDONS_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'addons/' );
		define( 'STOREENGINE_LIBRARY_PATH', STOREENGINE_ROOT_DIR_PATH . 'libraries/' );
		define( 'STOREENGINE_TEMPLATE_DEBUG_MODE', false );
		/** @define "STOREENGINE_TEMPLATE_PATH" "./templates/" */
		define( 'STOREENGINE_TEMPLATE_PATH', STOREENGINE_ROOT_DIR_PATH . 'templates/' );
		define( 'STOREENGINE_BLOCK_TEMPLATES_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'templates/block-templates/' );
	}

	/**
	 * When WP has loaded all plugins, trigger the `storeengine_loaded` hook.
	 *
	 * This ensures `storeengine_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 */
	public function on_plugins_loaded() {
		/**
		 * When WP has loaded all plugins, trigger the storeengine_loaded hook.
		 */
		do_action( 'storeengine_loaded' );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init_plugin() {
		/**
		 * Before the plugin initialization.
		 */
		do_action( 'storeengine/before_init' );
		StoreEngine\Utils\Caching::init();
		$this->load_addons();
		StoreEngine\Payment_Gateways::init();
		$this->dispatch_hooks();
		/**
		 * Initialize the plugin.
		 */
		do_action( 'storeengine_init' );
	}

	public function dispatch_hooks() {
		StoreEngine\Hooks::init();
		StoreEngine\Database::init();
		StoreEngine\PermalinkRewrite::init();
		StoreEngine\Shipping\Shipping::init();
		StoreEngine\API::init();
		StoreEngine\Ajax::init();
		StoreEngine\Assets::init();
		StoreEngine\Shortcode::init();
		StoreEngine\Integrations::init();
		StoreEngine\Migration::init();
		StoreEngine\Miscellaneous::init();
		StoreEngine\Schedule::init();
		Tax::init();

		if ( is_admin() ) {
			StoreEngine\Admin::init();
		}

		StoreEngine\Frontend::init();
	}

	public function dispatch_post_hooks() {
		StoreEngine\Post::init();
	}

	public function load_addons() {
		StoreEngine\Addons::init();
	}

	public function load_textdomain() {
		$locale = determine_locale();

		/**
		 * Filter to adjust the plugin locale to use for translations.
		 */
		$locale = apply_filters( 'plugin_locale', $locale, 'storeengine' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment

		unload_textdomain( 'storeengine', true );
		load_textdomain( 'storeengine', WP_LANG_DIR . '/storeengine/storeengine-' . $locale . '.mo' );
		load_plugin_textdomain(
			'storeengine',
			false,
			plugin_basename( dirname( STOREENGINE_PLUGIN_FILE ) ) . '/i18n/languages'
		);
	}

	public function set_global_settings() {
		$GLOBALS['storeengine_settings'] = json_decode( get_option( STOREENGINE_SETTINGS_NAME, '{}' ) );
		$GLOBALS['storeengine_addons']   = json_decode( get_option( STOREENGINE_ADDONS_SETTINGS_NAME, '{}' ) );
	}

	public function load_dependency() {
		// Internal Autoload
		require_once STOREENGINE_INCLUDES_DIR_PATH . 'autoload.php';

		// Autoload prefixed packages.
		if ( file_exists( STOREENGINE_ROOT_DIR_PATH . 'vendor/prefixed/autoload.php' ) ) {
			require_once STOREENGINE_ROOT_DIR_PATH . 'vendor/prefixed/autoload.php';
		}

		// Autoload other packages.
		if ( file_exists( STOREENGINE_ROOT_DIR_PATH . 'vendor/autoload.php' ) ) {
			require_once STOREENGINE_ROOT_DIR_PATH . 'vendor/autoload.php';
		}

		// Frontend Template and Hooks
		require_once STOREENGINE_INCLUDES_DIR_PATH . 'frontend/functions.php';
		require_once STOREENGINE_INCLUDES_DIR_PATH . 'frontend/hooks.php';
	}

	/**
	 * Initialize and load the cart functionality.
	 */
	public function load_cart() {
		if ( ! did_action( 'storeengine/before_init' ) || doing_action( 'storeengine/before_init' ) ) {
			/* translators: 1: initialize_session 2: storeengine/before_init */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( '%1$s should not be called before the %2$s action.', 'storeengine' ), 'storeengine_load_cart', 'storeengine/before_init' ), '1.0.0' );

			return;
		}

		$this->initialize_cart();
	}

	/**
	 * Initialize the customer and cart objects and setup customer saving on shutdown.
	 *
	 * Note, storeengine_start()->customer is session based. Changes to customer data via this property are not persisted to the database automatically.
	 *
	 * @return void
	 */
	public function initialize_cart() {
		if ( did_action( 'storeengine/cart/initialized' ) && $this->customer && $this->cart ) {
			return;
		}

		if ( ! $this->customer instanceof Customer ) {
			$this->customer = new Customer( get_current_user_id(), true );
		}

		if ( ! $this->cart instanceof Cart ) {
			$this->cart = Cart::init();
		}

		/**
		 * Fires after cart initialization finished.
		 */
		do_action( 'storeengine/cart/initialized' );
	}

	/**
	 * @return ?Cart
	 */
	public function get_cart(): ?Cart {
		return $this->cart;
	}

	/**
	 * Get queue instance.
	 *
	 * @return ActionQueue
	 * @throws StoreEngineException
	 */
	public function queue(): ActionQueue {
		return ActionQueue::get_instance();
	}

	public function activate() {
		StoreEngine\Installer::init();
	}

	public function deactivate() {
	}
}

/**
 * Initializes the main plugin
 *
 * @return StoreEngine
 */
function storeengine(): StoreEngine {
	return StoreEngine::init();
}

/**
 * Initializes the main plugin
 *
 * @return StoreEngine
 * @deprecated in favor of storeengine()
 * @see storeengine()
 */
function storeengine_start(): StoreEngine {
	return storeengine();
}

// Plugin Start
storeengine();
