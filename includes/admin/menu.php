<?php

namespace StoreEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', [ $self, 'admin_menu' ] );
	}

	public static function get_menu_lists() {
		$menu_items = [
			STOREENGINE_PLUGIN_SLUG                => [
				'title'      => __( 'Dashboard', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 0,
			],
			STOREENGINE_PLUGIN_SLUG . '-products'  => [
				'title'      => __( 'Products', 'storeengine' ),
				'capability' => 'manage_options',
				'sub_items'  => [
					[
						'slug'  => '',
						'title' => __( 'All Products', 'storeengine' ),
					],
					[
						'slug'  => 'category',
						'title' => __( 'Category', 'storeengine' ),
					],
					[
						'slug'  => 'tags',
						'title' => __( 'Tags', 'storeengine' ),
					],
					[
						'slug'  => 'attributes',
						'title' => __( 'Attributes', 'storeengine' ),
					],
				],
				'priority'   => 10,
			],
			STOREENGINE_PLUGIN_SLUG . '-orders'    => [
				'title'      => __( 'Orders', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 20,
			],
			STOREENGINE_PLUGIN_SLUG . '-coupons'   => [
				'title'      => __( 'Coupons', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 30,
			],
			STOREENGINE_PLUGIN_SLUG . '-customers' => [
				'title'      => __( 'Customers', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 40,
			],
			STOREENGINE_PLUGIN_SLUG . '-payments'  => [
				'title'      => __( 'Payments', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 50,
			],
			STOREENGINE_PLUGIN_SLUG . '-addons'    => [
				'title'      => __( 'Add-ons', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 90,
			],
			STOREENGINE_PLUGIN_SLUG . '-tools'     => [
				'title'      => __( 'Tools', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 90,
			],
			STOREENGINE_PLUGIN_SLUG . '-settings'  => [
				'title'      => __( 'Settings', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 99,
			],
		];

		if ( ! defined( 'STOREENGINE_PRO_VERSION' ) ) {
			$menu_items[ STOREENGINE_PLUGIN_SLUG . '-get-pro' ] = [
				'parent_slug' => STOREENGINE_PLUGIN_SLUG,
				'title'       => '<span class="dashicons dashicons-awards storeengine-blue-color"></span> ' . __( 'Get Pro', 'ablocks' ),
				'capability'  => 'manage_options',
				'priority'    => 100,
			];
		}


		$menu = apply_filters( 'storeengine/admin_menu_list', $menu_items);

		uasort( $menu, function ( $a, $b ) {
			return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
		} );

		return $menu;
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'StoreEngine', 'storeengine' ),
			__( 'StoreEngine', 'storeengine' ),
			'manage_options',
			STOREENGINE_PLUGIN_SLUG,
			[ $this, 'load_main_template' ],
			$this->get_menu_icon(),
			55
		);

		foreach ( self::get_menu_lists() as $item_key => $item ) {
			add_submenu_page(
				STOREENGINE_PLUGIN_SLUG,
				$item['title'],
				$item['title'],
				$item['capability'],
				$item_key,
				[ $this, 'load_main_template' ]
			);
		}
	}

	protected function get_menu_icon() {
		return apply_filters( 'storeengine/admin/toplevel_inactive_menu_icon', STOREENGINE_ASSETS_URI . 'images/logo.svg' );
	}

	public function load_main_template() {
		echo '<div id="storeengine-admin" class="storeengine-admin"></div>';
	}
}
