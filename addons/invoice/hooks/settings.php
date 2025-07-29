<?php

namespace StoreEngine\Addons\Invoice\Hooks;

class Settings {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/api/settings', [ $self, 'integrate_invoice_settings' ] );
	}

	public function integrate_invoice_settings( $settings ) {
		$settings->invoice = \StoreEngine\Addons\Invoice\Settings::get_settings_saved_data();

		return $settings;
	}

}
