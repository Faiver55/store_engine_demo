<?php
/**
 * Hooks.
 */

namespace StoreEngine\Addons\CatalogMode;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	protected static ?array $settings = null;

	public static function isEnabled(): bool {
		return self::get_settings( 'enabled' );
	}

	public static function get_settings( string $key, $default = null ) {
		self::load_settings();

		$value = self::$settings[ $key ] ?? $default;

		return apply_filters( 'storeengine/catalog-mode/get_settings', $value, $key );
	}

	public static function load_settings() {
		if ( null === self::$settings ) {
			self::$settings = (array) Helper::get_settings( 'catalog_mode', [] );

			// Clean up empty list.
			self::$settings['exclude_role'] = array_filter( self::$settings['exclude_role'] ?? [] );

			// Trigger once.
			self::$settings = apply_filters( 'storeengine/catalog-mode/settings', self::$settings );
		}

		return self::$settings;
	}

	public static function get_default_settings(): array {
		return [
			'enabled'                       => true,
			'disable_price'                 => true,
			'price_placeholder'             => '',
			'hide_add_to_cart_in'           => 'all',
			'add_to_cart_placeholder'       => '',
			'disable_cart_checkout'         => true,
			'customize_add_to_cart'         => true,
			'add_to_cart_shop_page_text'    => __( 'Request a Quote', 'storeengine' ),
			'add_to_cart_product_page_text' => __( 'Request a Quote', 'storeengine' ),
			'add_to_cart_link'              => '#',
			'add_to_cart_link_target'       => '_self',
			'exclude_role'                  => [ 'administrator' ],
		];
	}

	public static function get_settings_fields(): array {
		return [
			'enabled'                       => 'boolean',
			'disable_price'                 => 'boolean',
			'price_placeholder'             => 'safe_text',
			'disable_cart_checkout'         => 'boolean',
			'hide_add_to_cart_in'           => 'string',
			'customize_add_to_cart'         => 'boolean',
			'add_to_cart_shop_page_text'    => 'string',
			'add_to_cart_product_page_text' => 'string',
			'add_to_cart_link'              => 'url',
			'add_to_cart_link_target'       => 'string',
			'add_to_cart_placeholder'       => 'safe_text',
			'exclude_role'                  => 'array-string',
		];
	}
}

// End of file settings.php.
