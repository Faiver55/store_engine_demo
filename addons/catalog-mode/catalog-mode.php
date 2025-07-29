<?php
/**
 * Catalog Mode Addon.
 */

namespace StoreEngine\Addons\CatalogMode;

use StoreEngine\Admin\Settings\Base;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Frontend\FloatingCart;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CatalogMode extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'catalog-mode';

	protected static ?array $settings = null;

	public function define_constants() {
		define( 'STOREENGINE_CATALOG_MODE_VERSION', '1.0' );
	}

	public function init_addon() {
		Hooks::init();
	}

	public function addon_activation_hook() {
		if ( false === Helper::get_settings( 'catalog_mode', false ) ) {
			Base::save_settings( [ 'catalog_mode' => Settings::get_default_settings() ] );
		}
	}
}

// End of file catalog-mode.php
