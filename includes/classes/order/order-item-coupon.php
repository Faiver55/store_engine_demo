<?php

namespace StoreEngine\Classes\Order;

use StoreEngine\Utils\Formatting;

/**
 * @see \WC_Order_Item_Coupon
 * @see \WC_Order_Item_Coupon_Data_Store
 */
class OrderItemCoupon extends AbstractOrderItem {

	protected array $meta_key_to_props = [
		'_discount_amount'     => 'discount',
		'_discount_amount_tax' => 'discount_tax',
	];

	protected array $internal_meta_keys = [ '_discount_amount', '_discount_amount_tax' ];

	protected array $extra_data = [
		'code'         => '',
		'discount'     => 0,
		'discount_tax' => 0,
	];

	protected function read_data(): array {
		return array_merge( parent::read_data(), [
			'discount'     => $this->get_metadata( '_discount_amount' ),
			'discount_tax' => $this->get_metadata( '_discount_amount_tax' ),
		] );
	}

	/**
	 * Set order item name.
	 *
	 * @param string|int|float $value Coupon code.
	 */
	public function set_name( $value ) {
		$this->set_code( $value );
	}

	/**
	 * Set code.
	 *
	 * @param string|int|float $value Coupon code.
	 */
	public function set_code( string $value ) {
		$this->set_prop( 'code', Formatting::format_coupon_code( $value ) );
	}

	/**
	 * Set discount amount.
	 *
	 * @param string|float $value Discount.
	 */
	public function set_discount( $value ) {
		$this->set_prop( 'discount', Formatting::format_decimal( $value ) );
	}

	/**
	 * Set discounted tax amount.
	 *
	 * @param string|float $value Discount tax.
	 */
	public function set_discount_tax( $value ) {
		$this->set_prop( 'discount_tax', Formatting::format_decimal( $value ) );
	}

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'coupon';
	}

	/**
	 * Get order item name.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_name( string $context = 'view' ): string {
		return $this->get_code( $context );
	}

	/**
	 * Get coupon code.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_code( string $context = 'view' ): string {
		return $this->get_prop( 'code', $context );
	}

	/**
	 * Get discount amount.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_discount( string $context = 'view' ) {
		return $this->get_prop( 'discount', $context );
	}

	/**
	 * Get discounted tax amount.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_discount_tax( string $context = 'view' ) {
		return $this->get_prop( 'discount_tax', $context );
	}

	/*
	|-For backwards compatibility with legacy code.
	|
	*/

	/**
	 * @param string $context
	 *
	 * @return float
	 * @deprecated
	 * @see OrderItemCoupon::get_discount()
	 */
	public function get_discount_amount( string $context = 'view' ): float {
		return (float) $this->get_discount( $context );
	}

	/**
	 * @param float $value
	 *
	 * @return void
	 * @deprecated
	 * @see OrderItemCoupon::set_discount()
	 */
	public function set_discount_amount( float $value ) {
		$this->set_discount( $value );
	}
}
