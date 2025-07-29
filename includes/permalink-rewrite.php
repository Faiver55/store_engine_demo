<?php
namespace StoreEngine;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PermalinkRewrite {
	public static function init() {
		$self = new self();
		add_filter( 'query_vars', array( $self, 'register_query_vars' ) );
		add_action( 'generate_rewrite_rules', array( $self, 'add_rewrite_rules' ) );
	}
	public function register_query_vars( $query_vars ) {
		$query_vars[] = 'storeengine_dashboard_page';
		$query_vars[] = 'storeengine_dashboard_sub_page';
		$query_vars[] = 'order_pay';
		$query_vars[] = 'order_id';
		$query_vars[] = 'payment-methods';
		$query_vars[] = 'add-payment-method';
		$query_vars[] = 'delete-payment-method';
		$query_vars[] = 'set-default-payment-method';
		return $query_vars;
	}
	public function add_rewrite_rules( $wp_rewrite ) {
		$new_rules           = [];
		$dashboard_page_id   = (int) Helper::get_settings( 'dashboard_page' );
		$dashboard_page_slug = get_post_field( 'post_name', $dashboard_page_id );
		$dashboard_pages     = Helper::get_frontend_dashboard_menu_items();
		foreach ( $dashboard_pages as $dashboard_key => $dashboard_page ) {
			$new_rules[ "($dashboard_page_slug)/$dashboard_key/?$" ]                  = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&storeengine_dashboard_page=' . $dashboard_key;
			$new_rules[ "($dashboard_page_slug)/$dashboard_key/(?!page/)(.+?)/?$" ]   = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&storeengine_dashboard_page=' . $dashboard_key . '&storeengine_dashboard_sub_page=' . $wp_rewrite->preg_index( 2 );
			$new_rules[ "($dashboard_page_slug)/$dashboard_key/page/([0-9]{1,})/?$" ] = 'index.php?pagename=' . $dashboard_page_slug . '&storeengine_dashboard_page=' . $dashboard_key . '&paged=' . $wp_rewrite->preg_index( 2 );
		}

		$checkout_page      = Helper::get_settings('checkout_page');
		$checkout_page_slug = get_post_field( 'post_name', $checkout_page );
		$new_rules[ "($checkout_page_slug)/order-pay/([0-9]+)/?$" ] = 'index.php?pagename=' . $checkout_page_slug . '&order_pay=true&order_id=' . $wp_rewrite->preg_index( 2 );

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

}
