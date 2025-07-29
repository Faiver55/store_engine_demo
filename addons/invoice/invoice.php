<?php

namespace StoreEngine\Addons\Invoice;

use StoreEngine\Addons\Invoice\Ajax\FontDownloader;
use StoreEngine\Addons\Invoice\Hooks\Assets;
use StoreEngine\Addons\Invoice\Hooks\Email;
use StoreEngine\Addons\Invoice\Hooks\Order;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

final class Invoice extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'invoice';

	/**
	 * @inheritDoc
	 */
	public function define_constants() {
		define( 'STOREENGINE_INVOICE_VERSION', '1.0' );
		define( 'STOREENGINE_INVOICE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'invoice/' );
		define( 'STOREENGINE_INVOICE_TEMPLATE_DIR', STOREENGINE_INVOICE_DIR_PATH . 'templates/' );
		define( 'STOREENGINE_INVOICE_SETTINGS', 'storeengine_invoice_settings' );
	}

	public function addon_activation_hook() {
		Settings::save_settings();
	}

	/**
	 * @inheritDoc
	 */
	public function init_addon() {
		( new FontDownloader() )->dispatch_actions();
		( new Ajax\Pdf() )->dispatch_actions();
		( new Ajax\Settings() )->dispatch_actions();
		( new Ajax\Email() )->dispatch_actions();
		Assets::init();
		Order::init();
		Email::init();
		\StoreEngine\Addons\Invoice\Hooks\Settings::init();
	}
}
