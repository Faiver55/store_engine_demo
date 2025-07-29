<?php

namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelperAddon {

	public static function get_setting( string $name, $default = null ) {
		$settings = Settings::get_settings_saved_data();

		return $settings[ $name ] ?? $default;
	}

	public static function sanitize_email_template_data( $template_data ) {
		if ( ! is_array( $template_data ) ) {
			return $template_data;
		}
		$prepared_template_data = [];
		foreach ( $template_data as $template_name => $template_arr ) {
			$prepared_template_data[ $template_name ] = self::sanitize_email_template_item( $template_arr );
		}

		return $prepared_template_data;
	}

	public static function sanitize_email_template_item( $template_arr ) {
		if ( ! is_array( $template_arr ) ) {
			return $template_arr;
		}

		return array(
			'is_enable'     => filter_var( sanitize_text_field( $template_arr['is_enable'] ), FILTER_VALIDATE_BOOLEAN ),
			'email_subject' => sanitize_text_field( $template_arr['email_subject'] ),
			'email_heading' => sanitize_text_field( $template_arr['email_heading'] ),
			'email_content' => wp_kses_post( $template_arr['email_content'] ),
		);
	}

}
