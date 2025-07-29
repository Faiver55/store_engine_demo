<?php

namespace StoreEngine\Addons\Email\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Settings {

	public function __construct() {
		add_filter( 'storeengine/api/settings', array( $this, 'include_settings' ) );
	}

	public function include_settings( $settings ) {
		if ( ! isset( $settings->email ) ) {
			$settings->email = self::get_settings_saved_data();
		}

		return $settings;
	}

	public static function get_settings_saved_data() {
		$settings = get_option( 'storeengine_email_settings' );
		if ( $settings ) {
			return json_decode( $settings, true );
		}

		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/email/settings_default_data', [
			'form_name'          => _x( 'StoreEngine', 'System Email Form Name', 'storeengine' ),
			'email_address'      => get_option( 'admin_email' ),
			'email_content_type' => 'html',
			'header_image'       => '',
			'footer_text'        => '<!-- wp:heading --><h2 class="wp-block-heading">Thank You</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Storeengine</p><!-- /wp:paragraph -->',
			// template
			'order_confirmation' => [
				'admin'    => [
					'is_enable'     => true,
					'email_subject' => 'A new order placed',
					'email_heading' => '{user_display_name} has placed a new order',
					'email_content' => '<p>You\'ve received the following order from {user_display_name}:</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p>',
				],
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Your order has been placed',
					'email_heading' => 'Thank you for your order',
					'email_content' => '<p>Hi {user_display_name},</p><p>Just to let you know — we\'ve received your order.</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_status'       => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Your order status has been changed',
					'email_heading' => 'Order #{order_id} status changed',
					'email_content' => '<p>Hi {user_display_name},</p><p>The following order(#{order_id}) status has been changed from <strong>{order_old_status}</strong> to <strong>{order_new_status}</strong>:</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_note'         => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Order Note - #{order_id}',
					'email_heading' => 'A note has been added to your order',
					'email_content' => '<p>Hi {user_display_name},</p><p>The following note has been added to your order:</p><blockquote>{order_note}</blockquote><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_refund'       => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Order Refunded: #{order_id}',
					'email_heading' => 'Order Refunded',
					'email_content' => '<p>Hi {user_display_name},</p><p>Your order(#{order_id}) has been refunded. There are more details below for your reference:</p><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>Refunds:</p><p>{order_refunds}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
		] );
	}

	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );

		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}

		update_option( 'storeengine_email_settings', wp_json_encode( $settings_data ), false );
	}
}

