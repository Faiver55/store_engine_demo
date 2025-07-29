<?php

namespace StoreEngine;

use StoreEngine\Admin\Notices;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {

	public static function init() {
		$self = new self();
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_admin_menu_css' ) );
		add_action( 'admin_enqueue_scripts', [ $self, 'backend_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $self, 'backend_inline_style' ] );
		add_action( 'wp_enqueue_scripts', [ $self, 'frontend_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $self, 'frontend_scripts' ] );
		add_action( 'wp_footer', [ $self, 'display_price_placeholder' ] );
	}

	public function web_fonts_url( $font ) {
		$font_url = add_query_arg( 'family', rawurlencode( $font ), '//fonts.googleapis.com/css2' );

		return add_query_arg( 'display', 'swap', $font_url );
	}

	public function enqueue_admin_menu_css( $hook ) {
		wp_enqueue_style( 'storeengine-admin-menu', STOREENGINE_ASSETS_URI . 'css/menu.css', [], STOREENGINE_VERSION, 'all' );

		if ( strpos( $hook, '_page_' . STOREENGINE_PLUGIN_SLUG ) !== false ) {
			return;
		}

		Notices::init()->dispatch_notices();
		if ( ! empty( Admin\Notices::get_notices() ) ) {
			wp_enqueue_style( 'storeengine-admin-notice', STOREENGINE_ASSETS_URI . 'css/notices.css', [], STOREENGINE_VERSION, 'all' );
			wp_add_inline_style( 'storeengine-admin-notice', $this->get_dynamic_css() );
			wp_enqueue_style(
				'storeengine-frontend-icon',
				STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
				[],
				filemtime( STOREENGINE_ASSETS_DIR_PATH . 'library/icons/storeengine-icons.css' )
			);
		}
	}

	public function frontend_scripts() {
		// CSS
		wp_enqueue_style(
			'storeengine-frontend-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
			'all'
		);

		wp_enqueue_style(
			'storeengine-frontend-style',
			STOREENGINE_ASSETS_URI . 'build/frontend.css',
			[],
			filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/frontend.css' ),
			'all'
		);
		wp_add_inline_style( 'storeengine-frontend-style', $this->get_dynamic_css() );

		wp_enqueue_style( 'storeengine-web-font', $this->web_fonts_url( 'Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		// Add Flickity CSS
		if ( Helper::is_product() ) {
			wp_enqueue_style(
				'flickity',
				STOREENGINE_ASSETS_URI . 'library/flickity/flickity.min.css',
				[],
				STOREENGINE_VERSION,
				null
			);
		}

		// JS
		$dependencies = include_once STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/frontend.%s.asset.php', STOREENGINE_VERSION );

		do_action( 'storeengine/enqueue_frontend_scripts' );

		wp_enqueue_script(
			'storeengine-frontend-scripts',
			STOREENGINE_ASSETS_URI .
			sprintf( 'build/frontend.%s.js', STOREENGINE_VERSION ),
			array_merge( [ 'jquery', 'wp-util' ], $dependencies['dependencies'] ),
			$dependencies['version'],
			true
		);

		global $product, $post;
		if ( ! $product && function_exists( 'storeengine_initialize_product_data' ) ) {
			storeengine_initialize_product_data( $post );
			global $product;
		}
		if ( $product instanceof VariableProduct && 'variable' === $product->get_type() ) {
			wp_localize_script(
				'storeengine-frontend-scripts',
				'StoreEngineProductVariations',
				self::get_product_variations( $product )
			);
		}

		wp_localize_script(
			'storeengine-frontend-scripts',
			'StoreEngineGlobal',
			$this->get_frontend_script_data()
		);

		wp_set_script_translations(
			'storeengine-frontend-scripts',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'languages'
		);

		// Add Flickity JS
		if ( Helper::is_product() ) {
			wp_enqueue_script(
				'flickity',
				STOREENGINE_ASSETS_URI . 'library/flickity/flickity.pkgd.min.js',
				[],
				STOREENGINE_VERSION,
				true
			);
		}
	}

	public static function get_product_variations( VariableProduct $product ): array {
		global $product;
		$pricing    = [];
		$taxonomies = [];
		$variations = array_map( function ( $variant ) use ( &$taxonomies ) {
			$attributes = [];
			foreach ( $variant->get_attributes() as $attribute ) {
				$attributes[ $attribute->taxonomy ] = $attribute->slug;
				$taxonomies[]                       = $attribute->taxonomy;
			}

			return [
				'id'                 => $variant->get_id(),
				'name'               => $variant->get_name(),
				'price'              => $variant->get_price(),
				'sku'                => $variant->get_sku(),
				'pricing_id'         => (int) $variant->get_pricing_id(),
				'featured_image_url' => wp_get_attachment_image_url( $variant->get_featured_image() ),
				'attributes'         => $attributes,
			];
		}, $product->get_available_variants() );

		foreach ( $product->get_prices() as $price ) {
			$pricing[ $price->get_id() ] = $price->get_price();
		}

		return [
			'taxonomies' => array_values( array_unique( $taxonomies ) ),
			'pricing'    => $pricing,
			'variations' => $variations,
		];
	}

	public function get_frontend_script_data() {
		$data = [];
		if ( Helper::is_checkout() || Helper::is_edit_address_page() ) {
			$data = [
				'states'                    => array_merge( Countries::init()->get_allowed_country_states(), Countries::init()->get_shipping_country_states() ),
				'state_labels'              => array_filter( array_map( fn( $data ) => [
					'label'    => $data['state']['label'] ?? '',
					'required' => $data['state']['required'] ?? false,
				], Countries::init()->get_country_locale() ) ),
				'state_label'               => __( 'State / County', 'storeengine' ),
				'is_required'               => '&nbsp;<abbr class="storeengine-required" title="' . esc_attr__( 'required', 'storeengine' ) . '">*</abbr>',
				'i18n_select_state_text'    => esc_attr__( 'Select an option&hellip;', 'storeengine' ),
				'i18n_no_matches'           => _x( 'No matches found', 'enhanced select', 'storeengine' ),
				'i18n_ajax_error'           => _x( 'Loading failed', 'enhanced select', 'storeengine' ),
				'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'storeengine' ),
				'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'storeengine' ),
				'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'storeengine' ),
				'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'storeengine' ),
				'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'storeengine' ),
				'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'storeengine' ),
				'i18n_load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'storeengine' ),
				'i18n_searching'            => _x( 'Searching&hellip;', 'enhanced select', 'storeengine' ),
			];
		}

		return apply_filters(
			'storeengine/frontend_scripts_data',
			array_merge(
				$this->_get_script_data(),
				[
					'checkout_page_url'       => esc_url( Helper::get_page_permalink( 'checkout_page' ) ),
					'thankyou_page_url'       => esc_url( Helper::get_page_permalink( 'thankyou_page' ) ),
					'payment_gateways'        => $this->get_payment_method_script_data(),
					'checkout_with_order_pay' => (bool) get_query_var( 'order_pay' ), // @deprecated.
					'is_page'                 => [
						'is_checkout'           => Helper::is_checkout(),
						'is_order_pay'          => (bool) get_query_var( 'order_pay' ),
						'is_add_payment_method' => Helper::is_add_payment_method_page(),
					],
				],
				$data
			)
		);
	}

	public function _get_script_data(): array {
		global $storeengine_addons;

		$integrations = array_map( fn( $integration ) => [
			'value' => $integration->get_id(),
			'label' => $integration->get_label(),
			'icon'  => $integration->get_logo(),
		], Integrations::get_instance()->get_integrations() );

		return [
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'storeengine_nonce' => wp_create_nonce( 'storeengine_nonce' ),
			'rest_url'          => esc_url_raw( rest_url() ),
			'namespace'         => STOREENGINE_PLUGIN_SLUG . '/v1/',
			'plugin_root_url'   => STOREENGINE_PLUGIN_ROOT_URI,
			'plugin_root_path'  => STOREENGINE_ROOT_DIR_PATH,
			'ajaxurl'           => esc_url( admin_url( 'admin-ajax.php' ) ),
			'admin_url'         => admin_url(),
			'site_url'          => site_url(),
			'route_path'        => wp_parse_url( admin_url(), PHP_URL_PATH ),
			'admin_menu_lists'  => wp_json_encode( Admin\Menu::get_menu_lists() ),
			'current_user_id'   => get_current_user_id(),
			'is_rtl'            => is_rtl(),
			'is_admin'          => is_admin(),
			'addons'            => $storeengine_addons,
			'timezone'          => wp_timezone_string(),
			'current_user_can'  => [
				'manage_options' => current_user_can( 'manage_options' ),
			],
			'currency_options'  => Helper::get_currency_options(),
			'integrations'      => array_values( $integrations ),
			'is_tax_enabled'    => Helper::get_settings( 'enable_product_tax' ),
			'is_pro'            => Helper::is_active_storeengine_pro(),
		];
	}

	public function get_payment_method_script_data() {
		return apply_filters( 'storeengine/frontend_scripts_payment_method_data', [] );
	}

	/**
	 * Enqueue Files on Start Plugin
	 *
	 * @param string $hook
	 *
	 * @function backend_scripts
	 */
	public function backend_scripts( $hook ) {
		if ( ! strpos( $hook, '_page_' . STOREENGINE_PLUGIN_SLUG ) !== false ) {
			return;
		}

		remove_all_actions( 'admin_notices' );

		wp_enqueue_style(
			'storeengine-admin-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
		);

		wp_enqueue_style(
			'storeengine-admin-style',
			STOREENGINE_ASSETS_URI . 'build/backend.css',
			[ 'wp-components' ],
			filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/backend.css' ),
			'all'
		);

		wp_add_inline_style( 'storeengine-admin-style', $this->get_dynamic_css() );

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		// js
		$dependencies = include_once STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/backend.%s.asset.php', STOREENGINE_VERSION );
		wp_enqueue_script(
			'storeengine-admin-scripts',
			STOREENGINE_ASSETS_URI .
			sprintf( 'build/backend.%s.js', STOREENGINE_VERSION ),
			$dependencies['dependencies'],
			$dependencies['version'],
			true
		);
		wp_localize_script(
			'storeengine-admin-scripts',
			'StoreEngineGlobal',
			$this->get_backend_script_data()
		);
		wp_set_script_translations(
			'storeengine-admin-scripts',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);
	}

	public function get_backend_script_data(): array {
		$currencies = [];

		foreach ( Helper::get_currencies() as $code => $label ) {
			$currencies[] = [
				'code'   => $code,
				'name'   => $label,
				'symbol' => Helper::get_currency_symbol( $code ),
			];
		}

		return apply_filters(
			'storeengine/backend_scripts_data',
			array_merge( $this->_get_script_data(), [
				'currency_options' => Helper::get_currency_options(),
				'countries'        => Countries::init()->get_countries(),
				'currencies'       => $currencies,
			] )
		);
	}

	protected function get_dynamic_css(): string {
		global $storeengine_settings;

		/**
		 * Filter css properties
		 */
		$css = apply_filters( 'storeengine/dynamic_css_root_properties', [
			'--storeengine-primary-color'     => esc_attr( $storeengine_settings->global_primary_color ?? '' ),
			'--storeengine-secondary-color'   => esc_attr( $storeengine_settings->global_secondary_color ?? '' ),
			'--storeengine-text-color'        => esc_attr( $storeengine_settings->global_text_color ?? '' ),
			'--storeengine-placeholder-color' => esc_attr( $storeengine_settings->global_placeholder_color ?? '' ),
			'--storeengine-border-color'      => esc_attr( $storeengine_settings->global_border_color ?? '' ),
			'--storeengine-background-color'  => esc_attr( $storeengine_settings->global_background_color ?? '' ),
		] );

		// Filter out empty values to avoid invalid CSS.
		$filtered_css = array_filter( $css, fn( $value ) => trim( ltrim( rtrim( $value, ';' ), ':' ) ) !== '' );

		return ':root {' . PHP_EOL . implode( PHP_EOL, array_map( fn( $key, $value ) => "\t$key:$value;", array_keys( $filtered_css ), $filtered_css ) ) . PHP_EOL . '}';
	}

	public function display_price_placeholder() {
		?>
		<script type="text/html" id="tmpl-storeengine-price">
			<p class="storeengine-product__price-amount">
				{{{ data.price }}}
			</p>
		</script>
		<?php
	}

	public function backend_inline_style() {
		$custom_css = '
		.storeengine-blue-color {
				color: #27e527 !important;
		}';
		wp_add_inline_style( 'admin-bar', $custom_css );
	}
}
