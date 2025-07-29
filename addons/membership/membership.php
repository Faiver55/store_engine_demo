<?php

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

final class Membership extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'membership';

	public function define_constants() {
		define( 'STOREENGINE_MEMBERSHIP_VERSION', '1.0' );
		define( 'STOREENGINE_MEMBERSHIP_POST_TYPE', 'storeengine_groups' );
		define( 'STOREENGINE_MEMBERSHIP_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'membership/' );
		define( 'STOREENGINE_MEMBERSHIP_ASSETS_DIR', STOREENGINE_PLUGIN_ROOT_URI . 'addons/membership/assets/' );
		define( 'STOREENGINE_MEMBERSHIP_TEMPLATE_DIR', STOREENGINE_MEMBERSHIP_DIR_PATH . 'templates/' );
	}

	public function init_addon() {
		Api::init();
		Hooks::init();
		Database::init();
		SaveIntegrationData::init();
		TemplateRedirect::init();
		Ajax::init();
		Shortcode::init();
		if ( ! is_admin() ) {
			Frontend::init();
		}
		add_filter( 'storeengine/settings/tools/pages', function ( $pages ) {
			$pages['membership_pricing_page'] = __( 'Store Membership Pricing', 'storeengine' );
			return $pages;
		} );
	}

	public function addon_activation_hook() {
	}
}
