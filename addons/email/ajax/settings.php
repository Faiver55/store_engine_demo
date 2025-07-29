<?php

namespace StoreEngine\Addons\Email\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Email\Admin\Settings as EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Settings extends AbstractAjaxHandler {
	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_email';

	public function __construct() {
		$this->actions = [
			'get_email_settings'  => [
				'callback'   => [ $this, 'get_email_settings' ],
				'capability' => 'manage_options',
			],
			'save_email_settings' => [
				'callback'   => [ $this, 'save_email_settings' ],
				'capability' => 'manage_options',
				'fields'     => apply_filters( 'storeengine/email/settings_fields', [
					'form_name'          => 'string',
					'email_address'      => 'email',
					'email_content_type' => 'string',
					'header_image'       => 'string',
					'footer_text'        => 'post',
					'order_confirmation' => [
						'admin'    => [
							'is_enable'     => 'boolean',
							'email_subject' => 'string',
							'email_heading' => 'string',
							'email_content' => 'post',
						],
						'customer' => [
							'is_enable'     => 'boolean',
							'email_subject' => 'string',
							'email_heading' => 'string',
							'email_content' => 'post',
						],
					],
					'order_status'       => [
						'customer' => [
							'is_enable'     => 'boolean',
							'email_subject' => 'string',
							'email_heading' => 'string',
							'email_content' => 'post',
						],
					],
					'order_note'         => [
						'customer' => [
							'is_enable'     => 'boolean',
							'email_subject' => 'string',
							'email_heading' => 'string',
							'email_content' => 'post',
						],
					],
					'order_refund'       => [
						'customer' => [
							'is_enable'     => 'boolean',
							'email_subject' => 'string',
							'email_heading' => 'string',
							'email_content' => 'post',
						],
					],
				] ),
			],
		];
	}

	public function get_email_settings() {
		wp_send_json_success( EmailSettings::get_settings_saved_data() );
	}

	public function save_email_settings( $payload ) {
		$payload['email_content_type'] = ! empty( $payload['email_content_type'] ) && in_array( $payload['email_content_type'], [
			'html',
			'plainText',
		], true ) ? $payload['email_content_type'] : 'html';

		EmailSettings::save_settings( $payload );
		$saved_data = EmailSettings::get_settings_saved_data();
		wp_send_json_success( $saved_data );
	}

}
