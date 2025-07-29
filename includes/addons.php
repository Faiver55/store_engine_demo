<?php

namespace StoreEngine;

use StoreEngine\Classes\AbstractAddon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Addons {
	public static function init() {
		$self = new self();
		// Load all addons
		$self->addons_loader();
	}

	private function addons_loader() {
		$Autoload = Autoload::get_instance();
		$addons   = apply_filters( 'storeengine/addons/loader_args', [
			'paypal'         => 'Paypal',
			'stripe'         => 'Stripe',
			'affiliate'      => 'Affiliate',
			'membership'     => 'Membership',
			'subscription'   => 'Subscription',
			'invoice'        => 'Invoice',
			'email'          => 'Email',
			'webhooks'       => 'Webhooks',
			'migration-tool' => 'MigrationTool',
			'catalog-mode'   => 'CatalogMode',
		] );

		foreach ( $addons as $addon_name => $addon_class_name ) {
			$addon_root_path = STOREENGINE_ADDONS_DIR_PATH . $addon_name;
			// Register the addon's root namespace and path.
			$addon_namespace = 'StoreEngine\\Addons\\' . $addon_class_name;
			$Autoload->add_namespace_directory( $addon_namespace, $addon_root_path );

			/**
			 * Initialize the addon's main class.
			 *
			 * @var AbstractAddon $class Addon's main class.
			 */
			$class = $addon_namespace . '\\' . $addon_class_name;
			$class = $class::init();
			$class->run();
		}
	}
}
