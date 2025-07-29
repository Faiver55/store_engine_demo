<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

class Settings extends AbstractAjaxHandler {
	protected array $payment_fields;
	protected array $settings_fields;

	public function __construct() {
		$this->payment_fields  = apply_filters( 'storeengine/payment_settings_fields', [] );
		$this->settings_fields = apply_filters( 'storeengine/ajax/settings_fields', [
			'store_name'                        => 'string',
			'store_email'                       => 'string',
			'store_address_1'                   => 'string',
			'store_address_2'                   => 'string',
			'store_city'                        => 'string',
			'store_state'                       => 'string',
			'store_postcode'                    => 'string',
			'store_country'                     => 'string',
			'store_currency'                    => 'string',
			'store_currency_position'           => 'string',
			'store_currency_thousand_separator' => 'string',
			'store_currency_decimal_separator'  => 'string',
			'store_currency_decimal_limit'      => 'integer',
			// Brand & Style
			'store_logo'                        => 'absint',
			'global_primary_color'              => 'hex_color',
			'global_secondary_color'            => 'hex_color',
			'global_text_color'                 => 'hex_color',
			'global_border_color'               => 'hex_color',
			'global_background_color'           => 'hex_color',
			'global_placeholder_color'          => 'hex_color',
			// Products
			'enable_direct_checkout'            => 'boolean',
			'enable_product_reviews'            => 'boolean',
			'enable_product_comments'           => 'boolean',
			'enable_related_products'           => 'boolean',
			'enable_product_tax'                => 'boolean',
			// Product Archive
			'product_archive_sidebar_position'  => 'string',
			'product_archive_filters'           => [
				'search'   => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
				'category' => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
				'tags'     => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
			],
			'product_archive_products_per_row'  => [
				'desktop' => 'integer',
				'tablet'  => 'integer',
				'mobile'  => 'integer',
			],
			'product_archive_products_per_page' => 'integer',
			'product_archive_products_order'    => 'string',
			// Pages
			'shop_page'                         => 'integer',
			'cart_page'                         => 'integer',
			'checkout_page'                     => 'integer',
			'thankyou_page'                     => 'integer',
			'dashboard_page'                    => 'integer',
			'membership_pricing_page'           => 'integer',
			'affiliate_registration_page'       => 'integer',
			// Tax
			'prices_include_tax'                => 'boolean',
			'tax_based_on'                      => 'string',
			'shipping_tax_class'                => 'string',
			'tax_round_at_subtotal'             => 'boolean',
			'tax_classes'                       => 'string',
			'tax_display_shop'                  => 'string',
			'tax_display_cart'                  => 'string',
			'price_display_suffix'              => 'string',
			'tax_total_display'                 => 'string',
			'auth_redirect_type'                => 'string',
			'auth_redirect_url'                 => 'url',
			'checkout_default_country'          => 'country',
			'enable_floating_cart'              => 'boolean',
		] );
		$this->actions = [
			'update_base_settings'         => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_base_settings' ],
				'fields'     => $this->settings_fields,
			],
			'update_payments_settings'     => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_payments_settings' ],
				'fields'     => [
					'payments' => $this->payment_fields,
				],
			],
			'verify_payment_method_config' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'verify_payment_method_config' ],
				'fields'     => [
					'method' => 'string',
					'config' => array_merge( ...array_values( $this->payment_fields ) ),
				],
			],
		];
	}

	protected array $tax_total_display_options = [ 'single', 'itemized' ];
	protected array $tax_display_options       = [ 'incl', 'excl' ];
	protected array $tax_base_options          = [ 'shipping', 'billing', 'base' ];

	protected array $archive_filter_options = [ 'search', 'category', 'tags' ];

	protected function populate_field_data( array $fields, $payload = [], $defaults = [] ): array {
		$output = [];

		foreach ( $fields as $field => $type ) {
			if ( is_array( $type ) ) {
				$_defaults        = array_key_exists( $field, $defaults ) ? $defaults[ $field ] : [];
				$_payload         = array_key_exists( $field, $payload ) ? $payload[ $field ] : $_defaults;
				$_payload         = null === $_payload || '' === $_payload ? [] : $_payload;
				$output[ $field ] = $this->populate_field_data( $type, $_payload, $_defaults );
			} else {
				$output[ $field ] = $payload[ $field ] ?? ( $defaults[ $field ] ?? '' );
			}
		}

		return $output;
	}

	protected function update_base_settings( $payload ) {
		// Filling up blanks with saved data.
		// Don't set from default data as it can reset saved data if field is unset.
		// @XXX needs more testing.
		// @see BaseSettings::get_settings_default_data
		$default = BaseSettings::get_settings_saved_data();
		// Set from default if not set and save the changes.

		// Prepare filter widget settings.
		$payload['product_archive_filters'] = $payload['product_archive_filters'] ?? ( $default['product_archive_filters'] ?? [] );

		foreach ( $this->archive_filter_options as $option ) {
			if ( empty( $payload['product_archive_filters'][ $option ] ) ) {
				$payload['product_archive_filters'][ $option ] = [
					'status' => false,
					'order'  => $default['product_archive_filters'][ $option ]['order'] ?? 0,
				];
			} else {
				$payload['product_archive_filters'][ $option ] = wp_parse_args( $payload['product_archive_filters'][ $option ], [
					'status' => true,
					'order'  => 0,
				] );
			}
		}

		// Validate global tax settings.
		if ( ! empty( $payload['tax_total_display'] ) && ! in_array( $payload['tax_total_display'], $this->tax_total_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid cart & checkout tax total display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_display_cart'] ) && ! in_array( $payload['tax_display_cart'], $this->tax_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid cart & checkout tax display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_display_shop'] ) && ! in_array( $payload['tax_display_shop'], $this->tax_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid shop tax display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_based_on'] ) && ! in_array( $payload['tax_based_on'], $this->tax_base_options, true ) ) {
			wp_send_json_error( __( 'Invalid tax address base.', 'storeengine' ) );
		}

		$payload['auth_redirect_type'] = $payload['auth_redirect_type'] ?? ( $default['auth_redirect_type'] ?? 'storeengine' );
		$payload['auth_redirect_url']  = $payload['auth_redirect_url'] ?? ( $default['auth_redirect_url'] ?? '' );

		if ( ! in_array( $payload['auth_redirect_type'], [ 'default', 'storeengine', 'custom' ], true ) ) {
			wp_send_json_error( __( 'Invalid dashboard login redirect.', 'storeengine' ) );
		}

		if ( 'custom' === $payload['auth_redirect_type'] ) {
			if ( ! $payload['auth_redirect_url'] ) {
				wp_send_json_error( __( 'Login URL is required.', 'storeengine' ) );
			} else {
				if ( filter_var( $payload['auth_redirect_url'], FILTER_VALIDATE_URL ) === false ) {
					wp_send_json_error( __( 'Login URL is invalid.', 'storeengine' ) );
				}

				if ( is_ssl() && ! str_starts_with( $payload['auth_redirect_url'], 'https://' ) ) {
					wp_send_json_error( __( 'Invalid dashboard login redirect URL. Please use secure (https) URL.', 'storeengine' ) );
				}

				if ( str_starts_with( $payload['auth_redirect_url'], Helper::get_dashboard_url() ) ) {
					// Prevent redirect loop.
					wp_send_json_error( __( 'Dashboard URL is not allowed. Please use StoreEngine as auth redirect instead.', 'storeengine' ) );
				}

				// Remove all allowed hosts except the site url.
				remove_all_filters( 'allowed_redirect_hosts' );
				if ( ! wp_validate_redirect( $payload['auth_redirect_url'] ) ) {
					wp_send_json_error( __( 'Login URL is not allowed.', 'storeengine' ) );
				}
			}
		}

		// Prepare Order by.
		$valid_orderby                             = [ 'menu_order', 'title', 'date', 'modified', 'ID' ];
		$payload['product_archive_products_order'] = $payload['product_archive_products_order'] ?? ( $default['product_archive_products_order'] ?? '' );
		$payload['product_archive_products_order'] = in_array( $payload['product_archive_products_order'], $valid_orderby, true ) ? $payload['product_archive_products_order'] : '';

		// Prepare settings.
		$settings = $this->populate_field_data( $this->settings_fields, $payload, $default );

		$is_update = BaseSettings::save_settings( $settings );

		// Clear any unwanted data and flush rules.
		Helper::flush_rewire_rules();

		do_action( 'storeengine/admin/after_save_settings', $is_update, 'base', $payload );

		wp_send_json_success( $is_update );
	}


	protected function update_payments_settings( $payload ) {
		if ( empty( $payload['payments'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'storeengine' ) );
		}

		foreach ( $payload['payments'] as $gateway => $data ) {
			if ( ! array_key_exists( $gateway, $this->payment_fields ) ) {
				continue;
			}

			do_action( 'storeengine/admin/save_gateways/' . $gateway . '/settings', $data );
		}

		wp_send_json_success( true );
	}

	/**
	 * Verify payment verification payload data.
	 *
	 * @param array $payload
	 *
	 * @throws StoreEngineException
	 */
	protected function verify_payment_config_payload( array $payload ) {
		if ( empty( $payload['method'] ) ) {
			throw new StoreEngineException( __( 'payment method is required.', 'storeengine' ) );
		}
		if ( empty( $payload['config'] ) ) {
			throw new StoreEngineException( __( 'Missing required fields.', 'storeengine' ) );
		}

		if ( ! array_key_exists( $payload['method'], $this->payment_fields ) ) {
			throw new StoreEngineException( __( 'Payment method doesnt exists.', 'storeengine' ) );
		}
	}

	protected function verify_payment_method_config( $payload ) {
		try {
			$this->verify_payment_config_payload( $payload );

			$payment_method = $payload['method'];
			do_action( "storeengine/admin/verify_payment_{$payment_method}_config", $payload['config'] );

			wp_send_json_success();
		} catch ( StoreEngineException $e ) {
			/* translators: %s. Error message. */
			wp_send_json_error( sprintf( esc_html__( 'Failed to verify payment method settings. Error: %s', 'storeengine' ), esc_html( $e->getMessage() ) ) );
		}
	}

	protected function sanitize_bank_transfer_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'bank_transfer',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'accounts'     => [],
			'index'        => $index,
		];
	}

	protected function sanitize_check_payment_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'check_payment',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'index'        => $index,
		];
	}

	protected function sanitize_cash_on_delivery_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'cash_on_delivery',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'index'        => $index,
		];
	}

	protected function sanitize_paypal_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'                  => 'paypal',
			'is_enabled'            => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'is_enabled_sandbox'    => (bool) sanitize_text_field( $field_settings['is_enabled_sandbox'] ),
			'sandbox_client_id'     => sanitize_text_field( $field_settings['sandbox_client_id'] ),
			'sandbox_client_secret' => sanitize_text_field( $field_settings['sandbox_client_secret'] ),
			'live_client_id'        => sanitize_text_field( $field_settings['live_client_id'] ),
			'live_client_secret'    => sanitize_text_field( $field_settings['live_client_secret'] ),
			'index'                 => $index,
		];
	}

	protected function sanitize_stripe_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'                 => 'stripe',
			'is_enabled'           => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'is_enabled_test_mode' => (bool) sanitize_text_field( $field_settings['is_enabled_test_mode'] ),
			'test_publishable_key' => sanitize_text_field( $field_settings['test_publishable_key'] ),
			'test_secret_key'      => sanitize_text_field( $field_settings['test_secret_key'] ),
			'live_publishable_key' => sanitize_text_field( $field_settings['live_publishable_key'] ),
			'live_secret_key'      => sanitize_text_field( $field_settings['live_secret_key'] ),
			'index'                => $index,
		];
	}
}
