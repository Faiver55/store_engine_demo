<?php
/**
 * Hooks.
 */

namespace StoreEngine\Addons\CatalogMode;

use StoreEngine\Frontend\FloatingCart;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	protected function __construct() {
		add_filter( 'storeengine/admin/settings_default_data', [ $this, 'add_default_settings' ] );
		add_filter( 'storeengine/ajax/settings_fields', [ $this, 'add_settings_fields' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'add_script_data' ] );

		$this->dispatch_catalog_mode();
	}

	/**
	 * Dispatch catalog mode if enabled.
	 * Using anonymous function so actions can't be removed.
	 *
	 * @return void
	 */
	protected function dispatch_catalog_mode() {
		if ( ! Settings::isEnabled() || self::maybe_exclude_user() ) {
			return;
		}

		if ( Settings::get_settings( 'disable_cart_checkout' ) ) {
			add_action( 'init', function () {
				$cart_actions = apply_filters(
					'storeengine/catalog-mode/disabled_cart_actions',
					[
						'storeengine/add_to_cart',
						'storeengine/direct_checkout',
						'storeengine/update_checkout',
						'storeengine/refresh_cart',
						'storeengine/place_order',
						'storeengine/pay_order',
					]
				);

				foreach ( $cart_actions as $action ) {
					remove_all_actions( 'wp_ajax_' . $action );
					remove_all_actions( 'wp_ajax_nopriv_' . $action );

					add_action( 'wp_ajax_' . $action, [ $this, 'handle_cart_requests' ] );
					add_action( 'wp_ajax_nopriv_' . $action, [ $this, 'handle_cart_requests' ] );
				}
			} );
		}

		add_action( 'template_redirect', function () {
			if ( Settings::get_settings( 'disable_cart_checkout' ) ) {
				if ( Helper::is_checkout() || Helper::is_cart() ) {
					wp_safe_redirect( get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) );
					exit;
				}

				remove_action( 'wp_footer', [ FloatingCart::init(), 'display_cart_button' ] );
				remove_action( 'storeengine/templates/single_product_content', 'storeengine_single_view_cart' );
			}

			if ( self::should_replace_add_to_cart() ) {
				remove_action( 'storeengine/templates/product_loop_footer_content', 'storeengine_product_loop_add_to_cart' );
				remove_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_single_product_add_to_cart' );
				add_action(
					'storeengine/templates/product_loop_footer_content',
					[ $this, 'product_loop_add_to_cart_replacement' ]
				);

				add_action(
					'storeengine/templates/single-product/header_right_content',
					[ $this, 'single_product_add_to_cart_replacement' ]
				);
			}
		}, 999 );
	}

	public function product_loop_add_to_cart_replacement() {
		Template::get_template( 'catalog-mode/loop-add-to-cart.php', $this->get_replacement_args() );
	}

	public function single_product_add_to_cart_replacement() {
		Template::get_template( 'catalog-mode/single-product-add-to-cart.php', $this->get_replacement_args() );
	}

	protected function get_replacement_args(): array {
		$button_args = null;

		if ( Settings::get_settings( 'customize_add_to_cart' ) && Settings::get_settings( 'add_to_cart_link' ) ) {
			$button_text   = Helper::is_shop() ? 'add_to_cart_shop_page_text' : 'add_to_cart_product_page_text';
			$button_target = Settings::get_settings( 'add_to_cart_link_target' );
			$button_target = in_array( $button_target, [ '_self', '_blank' ] ) ? $button_target : '_self';
			$button_args   = [
				'button_text'   => Settings::get_settings( $button_text ),
				'button_link'   => Settings::get_settings( 'add_to_cart_link' ),
				'button_rel'    => 'noopener noreferrer',
				'button_target' => $button_target,
			];
		}

		return apply_filters(
			'storeengine/catalog-mode/add-to-cart-replacement-args',
			[
				'hide_pricing'            => Settings::get_settings( 'disable_price' ),
				'hide_add_to_cart'        => self::maybe_hide_add_to_cart(),
				'show_pricing'            => ! Settings::get_settings( 'disable_price' ),
				'pricing_placeholder'     => Settings::get_settings( 'price_placeholder' ),
				'add_to_cart_placeholder' => Settings::get_settings( 'add_to_cart_placeholder' ),
				'add_to_cart_button'      => $button_args,
			]
		);
	}

	public static function maybe_exclude_user(): bool {
		$excludes = Settings::get_settings( 'exclude_role' );

		if ( $excludes && is_user_logged_in() ) {
			$user = wp_get_current_user();
			foreach ( $excludes as $exclude ) {
				if ( in_array( $exclude, $user->roles, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function should_replace_add_to_cart(): bool {
		return ! ( ! self::maybe_hide_add_to_cart() && ! Settings::get_settings( 'disable_price' ) );
	}

	public static function maybe_hide_add_to_cart(): bool {
		return ! Settings::get_settings( 'hide_add_to_cart_in' ) || 'all' === Settings::get_settings( 'hide_add_to_cart_in' ) || 'shop' === Settings::get_settings( 'hide_add_to_cart_in' ) && Helper::is_shop() || 'product' === Settings::get_settings( 'hide_add_to_cart_in' ) && Helper::is_product();
	}

	public function handle_cart_requests() {
		wp_send_json_error( esc_html__( 'Products are view-only. Contact us for purchasing options.', 'storeengine' ) );
	}

	public function add_default_settings( array $settings ): array {
		return array_merge( $settings, [ 'catalog_mode' => Settings::get_default_settings() ] );
	}

	public function add_settings_fields( array $fields ): array {
		return array_merge( $fields, [ 'catalog_mode' => Settings::get_settings_fields() ] );
	}

	public function add_script_data( array $data ): array {
		$roles             = get_editable_roles();
		$data['userRoles'] = [
			[
				'label' => esc_html__( 'No exclusion', 'storeengine' ),
				'value' => '',
			],
		];

		foreach ( $roles as $role => ['name' => $name] ) {
			$data['userRoles'][] = [
				'label' => esc_html( $name ),
				'value' => esc_attr( $role ),
			];
		}

		return $data;
	}
}

// End of file hooks.php.
