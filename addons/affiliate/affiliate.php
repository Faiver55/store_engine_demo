<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Integrations\Email;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Settings\Affiliate as AffiliateSettings;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;

final class Affiliate extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'affiliate';

	public function define_constants() {
		define( 'STOREENGINE_AFFILIATE_VERSION', '1.0' );
		define( 'STOREENGINE_AFFILIATE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'affiliate/' );
		define( 'STOREENGINE_AFFILIATE_ASSETS_DIR', STOREENGINE_PLUGIN_ROOT_URI . 'addons/affiliate/assets/' );
		define( 'STOREENGINE_AFFILIATE_TEMPLATE_DIR', STOREENGINE_AFFILIATE_DIR_PATH . 'templates/' );
		define( 'STOREENGINE_AFFILIATE_SETTINGS_NAME', 'storeengine_affiliate_settings' );
		define( 'STOREENGINE_AFFILIATE_COOKIE_KEY', 'storeengine_affiliate' );
	}

	public function init_addon() {
		add_action( 'init', [ Role::class, 'add_affiliate_role' ] );
		$this->dispatch_hooks();
	}

	public function dispatch_hooks() {
		Shortcode::init();
		Ajax::init();
		Post::init();
		CookieHandler::init();
		Hooks::init();
	}

	public function addon_activation_hook() {
		Database::init();
		AffiliateSettings::save_settings();
		Helper::flush_rewire_rules();
	}

	public function addon_deactivation_hook() {
		Helper::flush_rewire_rules();
		// @TODO Drop db if admin choose to clean up all the data upon deactivation/uninstallation.
	}
}
