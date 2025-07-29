<?php

namespace StoreEngine\Classes\Order;

use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;

/**
 * @see \WC_Order_Item_Fee
 * @see \WC_Order_Item_Fee_Data_Store
 */
class OrderItemFee extends AbstractOrderItem {
	protected array $meta_key_to_props = [
		'_tax_class'     => 'tax_class',
		'_tax_status'    => 'tax_status',
		'_amount'        => 'amount',
		'_line_total'    => 'total',
		'_line_tax'      => 'total_tax',
		'_line_tax_data' => 'taxes',
	];

	/**
	 * Data stored in meta keys.
	 *
	 * @var array
	 */
	protected array $internal_meta_keys = [];

	/**
	 * Order Data array. This is the core order data exposed in APIs
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'tax_class'  => '',
		'tax_status' => ProductTaxStatus::TAXABLE,
		'amount'     => '',
		'total'      => '',
		'total_tax'  => '',
		'taxes'      => [
			'total' => [],
		],
	];

	protected function read_data(): array {
		return array_merge( parent::read_data(), [
			'tax_class'  => $this->get_metadata( '_tax_class' ),
			'tax_status' => $this->get_metadata( '_tax_status' ),
			'amount'     => $this->get_metadata( '_amount' ),
			'total'      => $this->get_metadata( '_line_total' ),
			'total_tax'  => $this->get_metadata( '_line_tax' ),
			'taxes'      => $this->get_metadata( '_line_tax_data' ),
		] );
	}

	/**
	 * Get item costs grouped by tax class.
	 *
	 * @param Order $order Order object.
	 *
	 * @return array
	 */
	protected function get_tax_class_costs( Order $order ): array {
		$order_item_tax_classes = $order->get_items_tax_classes();
		$costs                  = array_fill_keys( $order_item_tax_classes, 0 );
		$costs['non-taxable']   = 0;

		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			if ( 0 > $item->get_total() ) {
				continue;
			}
			if ( ProductTaxStatus::TAXABLE !== $item->get_tax_status() ) {
				$costs['non-taxable'] += $item->get_total();
			} elseif ( 'inherit' === $item->get_tax_class() ) {
				$inherit_class            = reset( $order_item_tax_classes );
				$costs[ $inherit_class ] += $item->get_total();
			} else {
				$costs[ $item->get_tax_class() ] += $item->get_total();
			}
		}

		return array_filter( $costs );
	}

	/**
	 * Calculate item taxes.
	 *
	 * @param array $calculate_tax_for Location data to get taxes for. Required.
	 *
	 * @return bool  True if taxes were calculated.
	 * @throws StoreEngineException
	 */
	public function calculate_taxes( array $calculate_tax_for = [] ): bool {
		if ( ! isset( $calculate_tax_for['country'], $calculate_tax_for['state'], $calculate_tax_for['postcode'], $calculate_tax_for['city'] ) ) {
			return false;
		}
		// Use regular calculation unless the fee is negative.
		if ( 0 <= $this->get_total() ) {
			return parent::calculate_taxes( $calculate_tax_for );
		}

		if ( TaxUtil::is_tax_enabled() && $this->get_order() ) {
			// Apportion taxes to order items, shipping, and fees.
			$order           = $this->get_order();
			$tax_class_costs = $this->get_tax_class_costs( $order );
			$total_costs     = NumberUtil::array_sum( $tax_class_costs );
			$discount_taxes  = array();
			if ( $total_costs ) {
				foreach ( $tax_class_costs as $tax_class => $tax_class_cost ) {
					if ( 'non-taxable' === $tax_class ) {
						continue;
					}
					$proportion                     = $tax_class_cost / $total_costs;
					$cart_discount_proportion       = $this->get_total() * $proportion;
					$calculate_tax_for['tax_class'] = $tax_class;
					$tax_rates                      = Tax::find_rates( $calculate_tax_for );
					$discount_taxes                 = Formatting::array_merge_recursive_numeric( $discount_taxes, Tax::calc_tax( $cart_discount_proportion, $tax_rates ) );
				}
			}
			$this->set_taxes( array( 'total' => $discount_taxes ) );
		} else {
			$this->set_taxes( false );
		}

		do_action( 'storeengine/order/item_fee_after_calculate_taxes', $this, $calculate_tax_for );

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set fee amount.
	 *
	 * @param string|int|float $value Amount.
	 */
	public function set_amount( $value ) {
		$this->set_prop( 'amount', Formatting::format_decimal( $value ) );
	}

	/**
	 * Set tax class.
	 *
	 * @param string $value Tax class.
	 *
	 * @throws StoreEngineException
	 */
	public function set_tax_class( string $value ) {
		if ( $value && ! in_array( $value, Tax::get_tax_class_slugs(), true ) ) {
			$this->error( 'order_item_fee_invalid_tax_class', __( 'Invalid tax class', 'storeengine' ) );
		}

		$this->set_prop( 'tax_class', $value );
	}

	/**
	 * Set tax_status.
	 *
	 * @param string $value Tax status.
	 */
	public function set_tax_status( string $value ) {
		if ( in_array( $value, [ ProductTaxStatus::TAXABLE, ProductTaxStatus::NONE ], true ) ) {
			$this->set_prop( 'tax_status', $value );
		} else {
			$this->set_prop( 'tax_status', ProductTaxStatus::TAXABLE );
		}
	}

	/**
	 * Set total.
	 *
	 * @param string|int|float $amount Fee amount (do not enter negative amounts).
	 */
	public function set_total( $amount ) {
		$this->set_prop( 'total', Formatting::format_decimal( $amount ) );
	}

	/**
	 * Set total tax.
	 *
	 * @param string|int|float $amount Amount.
	 */
	public function set_total_tax( $amount ) {
		$this->set_prop( 'total_tax', Formatting::format_decimal( $amount ) );
	}

	/**
	 * Set taxes.
	 *
	 * This is an array of tax ID keys with total amount values.
	 *
	 * @param array|string $raw_tax_data Raw tax data.
	 */
	public function set_taxes( $raw_tax_data ) {
		$raw_tax_data = maybe_unserialize( $raw_tax_data );
		$tax_data     = [ 'total' => [] ];
		if ( ! empty( $raw_tax_data['total'] ) ) {
			$tax_data['total'] = array_map( [ Formatting::class, 'format_decimal' ], $raw_tax_data['total'] );
		}
		$this->set_prop( 'taxes', $tax_data );

		if ( TaxUtil::tax_round_at_subtotal() ) {
			$this->set_total_tax( NumberUtil::array_sum( $tax_data['total'] ) );
		} else {
			$this->set_total_tax( NumberUtil::array_sum( array_map( [
				Formatting::class,
				'round_tax_total',
			], $tax_data['total'] ) ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get fee amount.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|int|float
	 */
	public function get_amount( string $context = 'view' ) {
		return $this->get_prop( 'amount', $context );
	}

	/**
	 * Get order item name.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_name( string $context = 'view' ): string {
		$name = $this->get_prop( 'name', $context );
		if ( 'view' === $context ) {
			return $name ? $name : __( 'Fee', 'storeengine' );
		} else {
			return $name;
		}
	}

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'fee';
	}

	/**
	 * Get tax class.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_tax_class( string $context = 'view' ): string {
		return $this->get_prop( 'tax_class', $context );
	}

	/**
	 * Get tax status.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_tax_status( string $context = 'view' ): string {
		return $this->get_prop( 'tax_status', $context );
	}

	/**
	 * Get total fee.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|int|float
	 */
	public function get_total( string $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get total tax.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|int|float
	 */
	public function get_total_tax( string $context = 'view' ) {
		return $this->get_prop( 'total_tax', $context );
	}

	/**
	 * Get fee taxes.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return array
	 */
	public function get_taxes( string $context = 'view' ): array {
		return $this->get_prop( 'taxes', $context );
	}
}

// End of file order-item-fee.php.
