<?php
namespace StoreEngine\Addons\Email;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Email extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'email';

	public function define_constants() {
		define( 'STOREENGINE_EMAIL_VERSION', '1.0' );
		define( 'STOREENGINE_EMAIL_VERSION_NAME', 'storeengine_email_version' );
		define( 'STOREENGINE_EMAIL_SETTINGS_NAME', 'storeengine_email_settings' );
	}

	public function init_addon() {
		new Hooks();
		new Ajax();
	}

	public function addon_activation_hook() {
		// @TODO check if calling settings::save_settings() is necessary.
		Admin\Settings::save_settings();
	}
}
