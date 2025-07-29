<?php
namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * @deprecated
 */
abstract class AbstractPaymentGateway {
	protected bool $enabled        = false;
	protected bool $is_live        = false;
	protected string $total_fields = '';


	public function __construct() {
		$this->init_settings();
		if ( ! $this->enabled ) {
			return;
		}
	}

	abstract public function init_settings();

	public function _handle_purchase( $response, $product_fields, $field_data_array, $entry_id, $page_id, $shipping ) {
		return $this->handle_purchase( $response, $product_fields, $field_data_array, $entry_id, $page_id, $shipping );
	}

	public function handle_purchase( $response, $product_fields, $field_data_array, $entry_id, $page_id, $shipping ) {
		return $response;
	}

	abstract public function gateway_footer_scripts();


}
