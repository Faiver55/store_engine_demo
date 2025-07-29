<?php
namespace  StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

class Miscellaneous {
	public static function init() {
		$self = new self();
		add_action( 'admin_bar_menu', array( $self, 'add_admin_bar_menu' ), 90 );
	}
	public function add_admin_bar_menu( $wp_admin_bar ) {
		$dashboard_page_id = (int) Helper::get_settings( 'dashboard_page' );
		$title             = ( current_user_can( 'storeengine_shop_manager' ) ? esc_html__( 'Store Dashboard', 'storeengine' ) : esc_html__( 'Customer Dashboard', 'storeengine' ) );
		if ( $dashboard_page_id ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'storeenginedashboard',
					'title'  => $title,
					'href'   => get_the_permalink( $dashboard_page_id ),
					'parent' => 'site-name',
				)
			);
		}

		if ( is_singular( Helper::PRODUCT_POST_TYPE ) && current_user_can( 'storeengine_shop_manager' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'storeengineproducts',
					'title' => esc_html__( 'Edit Product', 'storeengine' ),
					'href'  => esc_url( admin_url( 'admin.php?page=storeengine-products&id=' . get_the_ID() . '&action=edit' ) ),
				)
			);
		}
	}
}
