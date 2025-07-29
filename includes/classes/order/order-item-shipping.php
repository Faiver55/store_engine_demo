<?php

namespace StoreEngine\Classes\Order;

use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Tax;
use StoreEngine\Shipping\ShippingZones;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;

class OrderItemShipping extends AbstractOrderItem {

	protected array $internal_meta_keys = [
		'method_id',
		'instance_id',
		'total',
		'total_tax',
		'taxes',
		'tax_status',
	];

	protected array $meta_key_to_props = [];

	protected array $extra_data = [
		'method_title' => '',
		'method_id'    => '',
		'instance_id'  => '',
		'total'        => 0,
		'total_tax'    => 0,
		'taxes'        => [
			'total' => [],
		],
		'tax_status'   => ProductTaxStatus::TAXABLE,
	];

	protected function read_data(): array {
		$data = [];
		foreach ( $this->meta_key_to_props as $key => $prop ) {
			$data[ $prop ] = $this->get_metadata( $key );
		}

		return array_merge( parent::read_data(), $data );
	}

	/**
	 * Calculate item taxes.
	 *
	 * @param  array $calculate_tax_for Location data to get taxes for. Required.
	 * @return bool  True if taxes were calculated.
	 */
	public function calculate_taxes( $calculate_tax_for = [] ): bool {
		if ( ! isset( $calculate_tax_for['country'], $calculate_tax_for['state'], $calculate_tax_for['postcode'], $calculate_tax_for['city'], $calculate_tax_for['tax_class'] ) ) {
			return false;
		}
		if ( TaxUtil::is_tax_enabled() && ProductTaxStatus::TAXABLE === $this->get_tax_status() ) {
			$tax_rates = Tax::find_shipping_rates( $calculate_tax_for );
			$taxes     = Tax::calc_tax( $this->get_total(), $tax_rates, false );
			$this->set_taxes( array( 'total' => $taxes ) );
		} else {
			$this->set_taxes( false );
		}

		/**
		 * Fires after calculated order item shipping tax.
		 *
		 * @param OrderItemShipping $this Current order item shipping object.
		 * @param array $calculate_tax_for Holds address data.
		 */
		do_action( 'storeengine/order_item/shipping/after_calculate_taxes', $this, $calculate_tax_for );

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set order item name.
	 *
	 * @param string $value Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	public function set_name( $value ) {
		$this->set_method_title( $value );
	}

	/**
	 * Set method title.
	 *
	 * @param string $value Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	public function set_method_title( $value ) {
		$this->set_prop( 'name', Formatting::clean( $value ) );
		$this->set_prop( 'method_title', Formatting::clean( $value ) );
	}

	/**
	 * Set shipping method id.
	 *
	 * @param string $value Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	public function set_method_id( $value ) {
		$this->set_prop( 'method_id', Formatting::clean( $value ) );
	}

	/**
	 * Set shipping instance id.
	 *
	 * @param string $value Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	public function set_instance_id( $value ) {
		$this->set_prop( 'instance_id', Formatting::clean( $value ) );
	}

	/**
	 * Set total.
	 *
	 * @param string|int|float $value Value to set.
	 */
	public function set_total( $value ) {
		$this->set_prop( 'total', Formatting::format_decimal( $value ) );
	}

	/**
	 * Set total tax.
	 *
	 * @param string $value Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	protected function set_total_tax( $value ) {
		$this->set_prop( 'total_tax', Formatting::format_decimal( $value ) );
	}

	/**
	 * Set taxes.
	 *
	 * This is an array of tax ID keys with total amount values.
	 *
	 * @param string|array $raw_tax_data Value to set.
	 * @throws StoreEngineException May throw exception if data is invalid.
	 */
	public function set_taxes( $raw_tax_data ) {
		$raw_tax_data = maybe_unserialize( $raw_tax_data );
		$tax_data     = array(
			'total' => [],
		);
		if ( isset( $raw_tax_data['total'] ) ) {
			$tax_data['total'] = array_map( '\StoreEngine\Utils\Formatting::format_decimal', $raw_tax_data['total'] );
		} elseif ( ! empty( $raw_tax_data ) && is_array( $raw_tax_data ) ) {
			// Older versions just used an array.
			$tax_data['total'] = array_map( '\StoreEngine\Utils\Formatting::format_decimal', $raw_tax_data );
		}
		$this->set_prop( 'taxes', $tax_data );

		if ( TaxUtil::tax_round_at_subtotal() ) {
			$this->set_total_tax( NumberUtil::array_sum( $tax_data['total'] ) );
		} else {
			$this->set_total_tax( NumberUtil::array_sum( array_map( [ Formatting::class, 'round_tax_total' ], $tax_data['total'] ) ) );
		}
	}

	/**
	 * Set tax_status.
	 *
	 * @param string $value Tax status.
	 * @deprecated Order shipping lines don't support setting tax status, hook into the shipping method instead.
	 *
	 * @return void
	 */
	public function set_tax_status( $value ) {
	}

	/**
	 * Set properties based on passed in shipping rate object.
	 *
	 * @param ShippingRate $shipping_rate Shipping rate to set.
	 */
	public function set_shipping_rate( ShippingRate $shipping_rate ) {
		$this->set_method_title( $shipping_rate->get_label() );
		$this->set_method_id( $shipping_rate->get_method_id() );
		$this->set_instance_id( $shipping_rate->get_instance_id() );
		$this->set_total( $shipping_rate->get_cost() );
		$this->set_taxes( $shipping_rate->get_taxes() );
		$this->set_meta_data( $shipping_rate->get_meta_data() );
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'shipping';
	}

	/**
	 * Get order item name.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_name( string $context = 'view' ): string {
		return $this->get_method_title( $context );
	}

	/**
	 * Get title.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_method_title( string $context = 'view' ) {
		$method_title = $this->get_prop( 'method_title', $context );
		if ( 'view' === $context ) {
			return $method_title ? $method_title : __( 'Shipping', 'storeengine' );
		} else {
			return $method_title;
		}
	}

	/**
	 * Get method ID.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_method_id( string $context = 'view' ) {
		return $this->get_prop( 'method_id', $context );
	}

	/**
	 * Get instance ID.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_instance_id( string $context = 'view' ) {
		return $this->get_prop( 'instance_id', $context );
	}

	/**
	 * Get total cost.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_total( string $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get total tax.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_total_tax( string $context = 'view' ) {
		return $this->get_prop( 'total_tax', $context );
	}

	/**
	 * Get taxes.
	 *
	 * @param  string $context View or edit context.
	 * @return array
	 */
	public function get_taxes( string $context = 'view' ) {
		return $this->get_prop( 'taxes', $context );
	}

	/**
	 * Get tax class.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_tax_class( string $context = 'view' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return Helper::get_settings( 'shop_shipping_tax_class', '' );
	}

	/**
	 * Get tax status.
	 *
	 * @param  string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_tax_status( string $context = 'view' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$shipping_method = ShippingZones::get_shipping_method( $this->get_instance_id() );
		return $shipping_method ? $shipping_method->get_tax_status() : ProductTaxStatus::TAXABLE;
	}
}

// End of file order-item-shipping.php.
