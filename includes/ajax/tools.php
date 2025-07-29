<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Affiliate;
use StoreEngine\Addons\Membership\Membership;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;

class Tools extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'fetch_storeengine_status'     => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_storeengine_status' ],
			],
			'fetch_storeengine_pages'      => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_storeengine_pages' ],
			],
			'regenerate_storeengine_pages' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'regenerate_storeengine_pages' ],
			],
		];
	}

	protected function fetch_storeengine_status() {
		$tools = new \StoreEngine\Classes\Tools();

		wp_send_json_success( [
			'wordpress' => $tools->get_wordpress_environment_status(),
			'server'    => $tools->get_server_environment_status(),
		] );
	}

	protected function fetch_storeengine_pages() {
		global $storeengine_settings;
		$pages = apply_filters( 'storeengine/settings/tools/pages', [
			'shop_page'      => __( 'Store Shop', 'storeengine' ),
			'cart_page'      => __( 'Store Cart', 'storeengine' ),
			'checkout_page'  => __( 'Store Checkout', 'storeengine' ),
			'thankyou_page'  => __( 'Store Thank You', 'storeengine' ),
			'dashboard_page' => __( 'Store Dashboard', 'storeengine' ),
		] );

		$response = [];
		$idx      = 0;
		foreach ( $pages as $key => $label ) {
			$page       = Helper::get_settings( $key );
			$page       = $page ? get_post( $page ) : false;
			$response[] = [
				'key'         => $key,
				'index'       => ++$idx,
				'ID'          => $page ? $page->ID : null,
				'post_title'  => $page ? $page->post_title : $label,
				'post_name'   => $page ? $page->post_name : null,
				'post_status' => $page ? $page->post_status : null,
				'permalink'   => $page ? get_permalink( $page ) : null,
				'edit_link'   => $page && current_user_can( 'manage_options' ) ? get_edit_post_link( $page ) : null,
			];
		}

		wp_send_json_success( $response );
	}

	protected function regenerate_storeengine_pages() {
		Helper::create_initial_pages();
		wp_send_json_success();
	}
}
