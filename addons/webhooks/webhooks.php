<?php

namespace StoreEngine\Addons\Webhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Webhooks\Events\Dispatch;

final class Webhooks extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'webhooks';

	/**
	 * Defines CONSTANTS for Whole Addon.
	 */
	public function define_constants() {
		define( 'STOREENGINE_WEBHOOKS_VERSION', '1.0' );
		define( 'STOREENGINE_WEBHOOKS_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'webhooks/' );
	}

	public static function get_events() {
		return apply_filters( 'storeengine/webhooks_events', [
			'product_created',
			'product_updated',
			'product_deleted',
			'product_restored',
			'coupon_created',
			'coupon_updated',
			'coupon_deleted',
			'coupon_restored',
			'order_created',
			'order_updated',
			'order_deleted',
			'order_restored',
			'customer_created',
			'customer_updated',
			'customer_deleted',
		] );
	}
	public static function get_event_labels() {
		return apply_filters( 'storeengine/webhooks_event_labels', [
			[
				'label' => __( 'Product Created', 'storeengine' ),
				'value' => 'product_created',
			],
			[
				'label' => __( 'Product Updated', 'storeengine' ),
				'value' => 'product_updated',
			],
			[
				'label' => __( 'Product Deleted', 'storeengine' ),
				'value' => 'product_deleted',
			],
			[
				'label' => __( 'Product Restored', 'storeengine' ),
				'value' => 'product_restored',
			],
			[
				'label' => __( 'Coupon Created', 'storeengine' ),
				'value' => 'coupon_created',
			],
			[
				'label' => __( 'Coupon Updated', 'storeengine' ),
				'value' => 'coupon_updated',
			],
			[
				'label' => __( 'Coupon Deleted', 'storeengine' ),
				'value' => 'coupon_deleted',
			],
			[
				'label' => __( 'Coupon Restored', 'storeengine' ),
				'value' => 'coupon_restored',
			],
			[
				'label' => __( 'Order Created', 'storeengine' ),
				'value' => 'order_created',
			],
			[
				'label' => __( 'Order Updated', 'storeengine' ),
				'value' => 'order_updated',
			],
			[
				'label' => __( 'Order Deleted', 'storeengine' ),
				'value' => 'order_deleted',
			],
			[
				'label' => __( 'Order Restored', 'storeengine' ),
				'value' => 'order_restored',
			],
			[
				'label' => __( 'Customer Created', 'storeengine' ),
				'value' => 'customer_created',
			],
			[
				'label' => __( 'Customer Updated', 'storeengine' ),
				'value' => 'customer_updated',
			],
			[
				'label' => __( 'Customer Deleted', 'storeengine' ),
				'value' => 'customer_deleted',
			],
		] );
	}

	public function init_addon() {
		Database::init();
		Dispatch::init();

		add_filter( 'storeengine/admin_menu_list', [ $this, 'admin_menu_items' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'backend_scripts_data' ] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-webhooks' => [
				'title'      => __( 'WebHooks', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 91,
			],
		] );
	}

	public function backend_scripts_data( array $data ): array {
		$data['webhooks'] = self::get_event_labels();

		return $data;
	}

	public function addon_activation_hook() {
		Database::init();
		Helper::flush_rewire_rules();
	}

	public function addon_deactivation_hook() {
		Helper::flush_rewire_rules();
	}
}
