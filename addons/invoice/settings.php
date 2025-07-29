<?php

namespace StoreEngine\Addons\Invoice;

use StoreEngine\Classes\OrderStatus\Completed;
use StoreEngine\Classes\OrderStatus\Processing;

class Settings {

	public static function get_settings_saved_data() {
		$settings = get_option( STOREENGINE_INVOICE_SETTINGS );
		if ( $settings ) {
			return json_decode( $settings, true );
		}

		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/invoice/settings_default_data', [
			'date_format'                => 'd F, Y',
			'logo'                       => null,
			'invoice_mail_attachment'    => [
				'order_confirmation',
				'order_refund',
			],
			'invoice_paper_size'         => 'A4',
			'invoice_for_free_order'     => false,
			'invoice_show_product_image' => false,
			'invoice_front_view'         => 'preview',
			'invoice_front_btn'          => 'order_paid',
			'invoice_date_from'          => 'order_paid',
			'invoice_default_note'       => '',
			'invoice_footer_text'        => '',
		] );
	}

	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}

		update_option( STOREENGINE_INVOICE_SETTINGS, wp_json_encode( $settings_data ) );
	}

}
