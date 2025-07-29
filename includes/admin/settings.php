<?php
namespace StoreEngine\Admin;

use StoreEngine\Admin\Settings\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public static function init() {
		$self = new self();
		$self->save_settings();
	}

	public static function save_settings() {
		Base::save_settings();
	}
}
