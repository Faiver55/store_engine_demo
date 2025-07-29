<?php

namespace StoreEngine\Classes\Order;

use StoreEngine;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Utils\Formatting;

/**
 * @see \WC_Shipping_Rate
 */
class ShippingRate {

	/**
	 * Stores data for this rate.
	 *
	 * @var   array
	 */
	protected array $data = [
		'id'            => '',
		'method_id'     => '',
		'instance_id'   => 0,
		'label'         => '',
		'cost'          => 0,
		'taxes'         => [],
		'tax_status'    => ProductTaxStatus::TAXABLE,
		'description'   => '',
		'delivery_time' => '',
	];

	/**
	 * Stores meta data for this rate.
	 *
	 * @var   array
	 */
	protected array $meta_data = [];

	/**
	 * Constructor.
	 *
	 * @param string $id Shipping rate ID.
	 * @param string $label Shipping rate label.
	 * @param float|int $cost Cost.
	 * @param array $taxes Taxes applied to shipping rate.
	 * @param string $method_id Shipping method ID.
	 * @param int|string $instance_id Shipping instance ID.
	 * @param string $tax_status Tax status.
	 * @param string $description Shipping rate description.
	 * @param string $delivery_time Shipping rate delivery time.
	 */
	public function __construct( string $id = '', string $label = '', $cost = 0, array $taxes = [], string $method_id = '', $instance_id = 0, string $tax_status = ProductTaxStatus::TAXABLE, string $description = '', string $delivery_time = '' ) {
		$this->set_id( $id );
		$this->set_label( $label );
		$this->set_cost( $cost );
		$this->set_taxes( $taxes );
		$this->set_method_id( $method_id );
		$this->set_instance_id( $instance_id );
		$this->set_tax_status( $tax_status );
		$this->set_description( $description );
		$this->set_delivery_time( $delivery_time );
	}

	/**
	 * Magic methods to support direct access to props.
	 *
	 * @param string $key Key.
	 *
	 * @return bool
	 */
	public function __isset( string $key ) {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Magic methods to support direct access to props.
	 *
	 * @param string $key Key.
	 *
	 * @return mixed
	 */
	public function __get( string $key ) {
		if ( is_callable( [ $this, "get_$key" ] ) ) {
			return $this->{"get_$key"}();
		} elseif ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		} else {
			return '';
		}
	}

	/**
	 * Magic methods to support direct access to props.
	 *
	 * @param string $key Key.
	 * @param mixed $value Value.
	 */
	public function __set( string $key, $value ) {
		if ( is_callable( array( $this, "set_$key" ) ) ) {
			$this->{"set_$key"}( $value );
		} else {
			$this->data[ $key ] = $value;
		}
	}

	/**
	 * Set ID for the rate. This is usually a combination of the method and instance IDs.
	 *
	 * @param string $id Shipping rate ID.
	 */
	public function set_id( string $id ) {
		$this->data['id'] = $id;
	}

	/**
	 * Set shipping method ID the rate belongs to.
	 *
	 * @param string $method_id Shipping method ID.
	 */
	public function set_method_id( string $method_id ) {
		$this->data['method_id'] = $method_id;
	}

	/**
	 * Set instance ID the rate belongs to.
	 *
	 * @param int|string $instance_id Instance ID.
	 */
	public function set_instance_id( $instance_id ) {
		$this->data['instance_id'] = absint( $instance_id );
	}

	/**
	 * Set rate label.
	 *
	 * @param string $label Shipping rate label.
	 */
	public function set_label( string $label ) {
		$this->data['label'] = $label;
	}

	/**
	 * Set rate cost.
	 *
	 * @param int|float $cost Shipping rate cost.
	 */
	public function set_cost( $cost ) {
		$this->data['cost'] = abs( floatval( $cost ) );
	}

	/**
	 * Set rate taxes.
	 *
	 * @param array $taxes List of taxes applied to shipping rate.
	 */
	public function set_taxes( array $taxes ) {
		$this->data['taxes'] = ! empty( $taxes ) ? $taxes : [];
	}

	/**
	 * Set tax status.
	 *
	 * @param string $value Tax status.
	 */
	public function set_tax_status( string $value ) {
		if ( in_array( $value, [ ProductTaxStatus::TAXABLE, ProductTaxStatus::NONE ], true ) ) {
			$this->data['tax_status'] = $value;
		}
	}

	/**
	 * Set rate description.
	 *
	 * @param string $description Shipping rate description.
	 */
	public function set_description( string $description ) {
		$this->data['description'] = $description;
	}

	/**
	 * Set rate delivery time.
	 *
	 * @param string $delivery_time Shipping rate delivery time.
	 */
	public function set_delivery_time( string $delivery_time ) {
		$this->data['delivery_time'] = $delivery_time;
	}

	/**
	 * Get ID for the rate. This is usually a combination of the method and instance IDs.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return apply_filters( 'storeengine/shipping_rate_id', $this->data['id'], $this );
	}

	/**
	 * Get shipping method ID the rate belongs to.
	 *
	 * @return string
	 */
	public function get_method_id(): string {
		return apply_filters( 'storeengine/shipping_rate_method_id', $this->data['method_id'], $this );
	}

	/**
	 * Get instance ID the rate belongs to.
	 *
	 * @return int
	 */
	public function get_instance_id(): int {
		return apply_filters( 'storeengine/shipping_rate_instance_id', $this->data['instance_id'], $this );
	}

	/**
	 * Get rate label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return apply_filters( 'storeengine/shipping_rate_label', $this->data['label'], $this );
	}

	/**
	 * Get rate cost.
	 *
	 * @return float
	 */
	public function get_cost(): float {
		return (float) apply_filters( 'storeengine/shipping_rate_cost', $this->data['cost'], $this );
	}

	/**
	 * Get rate taxes.
	 *
	 * @return array
	 */
	public function get_taxes(): array {
		return apply_filters( 'storeengine/shipping_rate_taxes', $this->data['taxes'], $this );
	}

	/**
	 * Get shipping tax.
	 *
	 * @return float
	 */
	public function get_shipping_tax(): float {
		return apply_filters( 'storeengine/get_shipping_tax', count( $this->get_taxes() ) > 0 && ! StoreEngine::init()->customer->get_is_vat_exempt() ? (float) array_sum( $this->get_taxes() ) : 0.0, $this );
	}


	/**
	 * Get tax status.
	 *
	 * @return string
	 */
	public function get_tax_status(): string {
		/**
		 * Filter to allow tax status to be overridden for a shipping rate.
		 *
		 * @param string $tax_status Tax status.
		 * @param ShippingRate $this Shipping rate object.
		 */
		return apply_filters( 'storeengine/shipping_rate_tax_status', $this->data['tax_status'], $this );
	}

	/**
	 * Get rate description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		/**
		 * Filter the shipping rate description.
		 *
		 * @param string $description The current description.
		 * @param ShippingRate $this The shipping rate.
		 */
		return apply_filters( 'storeengine/shipping_rate_description', $this->data['description'], $this );
	}

	/**
	 * Get rate delivery time.
	 *
	 * @return string
	 */
	public function get_delivery_time(): string {
		/**
		 * Filter the shipping rate delivery time.
		 *
		 * @param string $delivery_time The current description.
		 * @param ShippingRate $this The shipping rate.
		 */
		return apply_filters( 'storeengine/shipping_rate_delivery_time', $this->data['delivery_time'], $this );
	}

	/**
	 * Add some meta data for this rate.
	 *
	 * @param string $key Key.
	 * @param mixed $value Value.
	 */
	public function add_meta_data( string $key, $value ) {
		$this->meta_data[ Formatting::clean( $key ) ] = Formatting::clean( $value );
	}

	/**
	 * Get all meta data for this rate.
	 *
	 * @return array
	 */
	public function get_meta_data(): array {
		return $this->meta_data;
	}
}


// End of file shipping-rate.php
