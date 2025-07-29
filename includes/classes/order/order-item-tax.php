<?php

namespace StoreEngine\Classes\Order;

use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Formatting;

/**
 * @sse \WC_Order_Item_Tax
 * @sse \WC_Order_Item_Tax_Data_Store
 */
class OrderItemTax extends AbstractOrderItem {

	protected array $internal_meta_keys = [
		'_rate_code',
		'_rate_id',
		'_label',
		'_compound',
		'_tax_total',
		'_shipping_tax_total',
		'_rate_percent',
	];

	protected array $meta_key_to_props = [
		'_rate_code'          => 'rate_code',
		'_rate_id'            => 'rate_id',
		'_label'              => 'label',
		'_compound'           => 'compound',
		'_tax_total'          => 'tax_total',
		'_shipping_tax_total' => 'shipping_tax_total',
		'_rate_percent'       => 'rate_percent',
	];

	protected array $extra_data = [
		'rate_code'          => '',
		'rate_id'            => 0,
		'label'              => '',
		'compound'           => false,
		'tax_total'          => 0,
		'shipping_tax_total' => 0,
		'rate_percent'       => null,
	];

	protected function read_data(): array {
		$data = [];
		foreach ( $this->meta_key_to_props as $key => $prop ) {
			$data[ $prop ] = $this->get_metadata( $key );
		}

		return array_merge( parent::read_data(), $data );
	}

	/**
	 * Set order item name.
	 *
	 * @param string $value Name.
	 */
	public function set_name( string $value ) {
		$this->set_rate_code( $value );
	}

	/**
	 * Set item name.
	 *
	 * @param string $value Rate code.
	 */
	public function set_rate_code( string $value ) {
		$this->set_prop( 'rate_code', Formatting::clean( $value ) );
	}

	/**
	 * Set item name.
	 *
	 * @param string $value Label.
	 */
	public function set_label( string $value ) {
		$this->set_prop( 'label', Formatting::clean( $value ) );
	}

	/**
	 * Set tax rate id.
	 *
	 * @param int|string $value Rate ID.
	 */
	public function set_rate_id( $value ) {
		$this->set_prop( 'rate_id', absint( $value ) );
	}

	/**
	 * Set tax total.
	 *
	 * @param string|float|int $value Tax total.
	 */
	public function set_tax_total( $value ) {
		$this->set_prop( 'tax_total', $value ? Formatting::format_decimal( $value ) : 0 );
	}

	/**
	 * Set shipping tax total.
	 *
	 * @param string|float|int $value Shipping tax total.
	 */
	public function set_shipping_tax_total( $value ) {
		$this->set_prop( 'shipping_tax_total', $value ? Formatting::format_decimal( $value ) : 0 );
	}

	/**
	 * Set compound.
	 *
	 * @param bool|int|string $value If tax is compound.
	 */
	public function set_compound( $value ) {
		$this->set_prop( 'compound', (bool) $value );
	}

	/**
	 * Set rate value.
	 *
	 * @param float|string $value tax rate value.
	 */
	public function set_rate_percent( $value ) {
		$this->set_prop( 'rate_percent', (float) $value );
	}

	/**
	 * Set properties based on passed in tax rate by ID.
	 *
	 * @param int|string $tax_rate_id Tax rate ID.
	 */
	public function set_rate( $tax_rate_id ) {
		$tax_rate_id = absint( $tax_rate_id );
		$tax_rate    = Tax::_get_tax_rate( $tax_rate_id, OBJECT );

		$this->set_rate_id( $tax_rate_id );
		$this->set_rate_code( Tax::get_rate_code( $tax_rate ) );
		$this->set_label( Tax::get_rate_label( $tax_rate ) );
		$this->set_compound( Tax::is_compound( $tax_rate ) );
		$this->set_rate_percent( Tax::get_rate_percent_value( $tax_rate ) );
	}

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'tax';
	}

	/**
	 * Get rate code/name.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_name( string $context = 'view' ): string {
		return $this->get_rate_code( $context );
	}

	/**
	 * Get rate code/name.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_rate_code( string $context = 'view' ): string {
		return $this->get_prop( 'rate_code', $context );
	}

	/**
	 * Get label.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_label( string $context = 'view' ): string {
		$label = $this->get_prop( 'label', $context );
		if ( 'view' === $context ) {
			return $label ?: __( 'Tax', 'storeengine' );
		} else {
			return $label;
		}
	}

	/**
	 * Get tax rate ID.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_rate_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'rate_id', $context );
	}

	/**
	 * Get tax_total
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_tax_total( string $context = 'view' ) {
		return $this->get_prop( 'tax_total', $context );
	}

	/**
	 * Get shipping_tax_total
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_shipping_tax_total( string $context = 'view' ) {
		return $this->get_prop( 'shipping_tax_total', $context );
	}

	/**
	 * Get compound.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return bool
	 */
	public function get_compound( string $context = 'view' ): bool {
		return (bool) $this->get_prop( 'compound', $context );
	}

	/**
	 * Is this a compound tax rate?
	 *
	 * @return boolean
	 */
	public function is_compound(): bool {
		return $this->get_compound();
	}

	/**
	 * Get rate value
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return float|string
	 */
	public function get_rate_percent( string $context = 'view' ) {
		return $this->get_prop( 'rate_percent', $context );
	}
}

// End of file order-item-tax.php.
