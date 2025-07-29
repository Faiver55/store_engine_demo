<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;

class Settings extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	public function __construct() {
		$this->actions = [
			'update_settings' => [
				'callback' => [ $this, 'update_settings' ],
				'fields'   => [
					'date_format'                => 'string',
					'logo'                       => 'int',
					'invoice_mail_attachment'    => 'string',
					'invoice_paper_size'         => 'string',
					'invoice_show_product_image' => 'bool',
					'invoice_front_view'         => 'string',
					'invoice_front_btn'          => 'string',
					'invoice_date_from'          => 'string',
					'invoice_default_note'       => 'string',
					'invoice_footer_text'        => 'string',
				],
			],
		];
	}

	public function update_settings( array $payload ) {
		$payload['invoice_mail_attachment'] = explode( ',', ! empty( $payload['invoice_mail_attachment'] ) ? $payload['invoice_mail_attachment'] : [] );

		\StoreEngine\Addons\Invoice\Settings::save_settings( $payload );

		wp_send_json_success( \StoreEngine\Addons\Invoice\Settings::get_settings_saved_data() );
	}

}
