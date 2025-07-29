<?php

namespace StoreEngine\Addons\Invoice\Hooks;

class Assets {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/backend_scripts_data', [ $self, 'add_scripts_data' ] );
	}

	public function add_scripts_data( array $data ): array {
		return array_merge( $data, [
			'invoice' => [
				'fonts_downloaded' => (bool) get_option( 'storeengine_invoice_fonts_downloaded', false ),
			],
		] );
	}

}
