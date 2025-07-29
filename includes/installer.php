<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoreEngine\Admin\Settings;
use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Classes\Role;
use StoreEngine\Utils\Helper;

class Installer {
	protected $storeengine_version;

	public static function init() {
		$self                      = new self();
		$self->storeengine_version = get_option( 'storeengine_version' );
		Database::init()->wpdb_table_fix();
		if ( ! get_option( 'storeengine_db_version' ) ) {
			Database::create_initial_custom_table();
		}
		Settings::init();
		$self->add_role();
		// if first time install then run below method
		if ( ! $self->storeengine_version ) {
			Helper::create_initial_pages();
		}
		// Save option table data
		$self->save_option();

		// Need for flush rewrite rules.
		PermalinkRewrite::init();
		Database::init()->register_product_post_type();

		// Force a flush of rewrite rules even if the corresponding hook isn't initialized yet.
		if ( ! has_action( 'storeengine/flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}

		/**
		 * Flush the rewrite rules after install or update.
		 */
		do_action( 'storeengine/flush_rewrite_rules' );
	}

	public function save_option() {
		if ( ! $this->storeengine_version ) {
			add_option( 'storeengine_version', STOREENGINE_VERSION );
		}

		if ( ! get_option( 'storeengine_first_install_time' ) ) {
			add_option( 'storeengine_first_install_time', Helper::get_time() );
		}
	}

	public function add_role() {
		// customer role
		Role::add_customer_role();
		// shop manager role
		Role::add_shop_manager_role();
	}
}
