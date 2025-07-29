<?php

namespace StoreEngine\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Base {
	public static function get_settings_saved_data() {
		$settings = get_option( STOREENGINE_SETTINGS_NAME );
		if ( $settings ) {
			return json_decode( $settings, true );
		}

		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/admin/settings_default_data', [
			// General
			'store_name'                        => 'StoreEngine',
			'store_email'                       => get_option( 'admin_email' ),
			'store_currency'                    => 'USD',
			'store_currency_position'           => 'left',
			'store_currency_thousand_separator' => ',',
			'store_currency_decimal_separator'  => '.',
			'store_currency_decimal_limit'      => 2,
			'store_country'                     => '',
			'store_address_1'                   => '',
			'store_address_2'                   => '',
			'store_city'                        => '',
			'store_state'                       => '',
			'store_postcode'                    => '',
			// Brand & Style
			'store_logo'                        => '',
			'global_primary_color'              => '#008DFF',
			'global_secondary_color'            => '#FF5000',
			'global_text_color'                 => '#0F0E16',
			'global_border_color'               => '#ebebeb',
			'global_background_color'           => '#FAFAFA',
			'global_placeholder_color'          => '#8E949A',
			// Product
			'enable_direct_checkout'            => false,
			'enable_product_reviews'            => false,
			'enable_product_comments'           => false,
			'enable_related_products'           => false,
			'enable_product_tax'                => false,
			// Product Archive
			'product_archive_sidebar_position'  => 'right',
			'product_archive_filters'           => [
				'search'   => [
					'status' => true,
					'order'  => 0,
				],
				'category' => [
					'status' => true,
					'order'  => 1,
				],
				'tags'     => [
					'status' => true,
					'order'  => 2,
				],
			],
			'product_archive_products_per_row'  => [
				'desktop' => 3,
				'tablet'  => 2,
				'mobile'  => 1,
			],
			'product_archive_products_per_page' => 12,
			'product_archive_products_order'    => '',
			// Page
			'shop_page'                         => '',
			'cart_page'                         => '',
			'checkout_page'                     => '',
			'thankyou_page'                     => '',
			'dashboard_page'                    => '',
			'membership_pricing_page'           => '',
			'affiliate_registration_page'       => '',
			// Tax settings
			'prices_include_tax'                => false,
			'tax_based_on'                      => 'shipping',
			'shipping_tax_class'                => '',
			'tax_round_at_subtotal'             => false,
			'tax_classes'                       => '',
			'tax_display_shop'                  => 'excl',
			'tax_display_cart'                  => 'excl',
			'price_display_suffix'              => '',
			'tax_total_display'                 => 'itemized',
			'auth_redirect_type'                => 'storeengine',
			'auth_redirect_url'                 => '',
			'checkout_default_country'          => 'US',
			'enable_floating_cart'              => true,
		] );
	}

	public static function save_settings( array $form_data = [] ): bool {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}

		return update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( $settings_data ) );
	}
}
