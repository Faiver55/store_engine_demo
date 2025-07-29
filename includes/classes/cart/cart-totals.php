<?php
/**
 * Cart totals calculation class.
 *
 * Methods are protected and class is final to keep this as an internal API.
 * May be opened in the future once structure is stable.
 *
 * Rounding guide:
 * - if something is being stored e.g. item total, store unrounded. This is so taxes can be recalculated later accurately.
 * - if calculating a total, round (if settings allow).
 *
 * @package StoreEngine\Classes\Cart
 * @version 1.0.0
 */

namespace StoreEngine\Classes\Cart;

use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Discounts;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Tax;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Cart_Totals
 */
final class CartTotals {
	use ItemTotals;

	/**
	 * Reference to cart object.
	 *
	 * @var ?Cart
	 */
	protected ?Cart $cart = null;

	/**
	 * Reference to customer object.
	 *
	 * @var ?Customer
	 */
	protected ?Customer $customer = null;

	/**
	 * Line items to calculate.
	 *
	 * @var array
	 */
	protected array $items = [];

	/**
	 * Fees to calculate.
	 *
	 * @var array
	 */
	protected array $fees = [];

	/**
	 * Shipping costs.
	 *
	 * @var array
	 */
	protected array $shipping = [];

	/**
	 * Applied coupon objects.
	 *
	 * @var Coupon[]
	 */
	protected array $coupons = [];

	/**
	 * Item/coupon discount totals.
	 *
	 * @var array
	 */
	protected array $coupon_discount_totals = [];

	/**
	 * Item/coupon discount tax totals.
	 *
	 * @var array
	 */
	protected array $coupon_discount_tax_totals = [];

	/**
	 * Should taxes be calculated?
	 *
	 * @var boolean
	 */
	protected bool $calculate_tax = true;

	/**
	 * Stores totals.
	 *
	 * @var array
	 */
	protected array $totals = [
		'fees_total'         => 0,
		'fees_total_tax'     => 0,
		'items_subtotal'     => 0,
		'items_subtotal_tax' => 0,
		'items_total'        => 0,
		'items_total_tax'    => 0,
		'total'              => 0,
		'shipping_total'     => 0,
		'shipping_tax_total' => 0,
		'discounts_total'    => 0,
	];

	/**
	 * Cache of tax rates for a given tax class.
	 *
	 * @var array
	 */
	protected array $item_tax_rates = [];

	/**
	 * Sets up the items provided, and calculate totals.
	 *
	 * @param Cart|null $cart Cart object to calculate totals for.
	 *
	 * @throws StoreEngineException If missing Cart object.
	 */
	public function __construct( ?Cart &$cart = null ) {
		if ( ! is_a( $cart, Cart::class ) ) {
			throw new StoreEngineException( __( 'A valid Cart object is required', 'storeengine' ), 'invalid-cart', $cart, 500 );
		}

		$this->cart          = $cart;
		$this->calculate_tax = TaxUtil::is_tax_enabled() && ! $cart->get_customer()->get_is_vat_exempt();
		$this->calculate();
	}

	/**
	 * Run all calculation methods on the given items in sequence.
	 */
	protected function calculate() {
		$this->calculate_item_totals();
		$this->calculate_shipping_totals();
		$this->calculate_fee_totals();
		$this->calculate_totals();
	}

	/**
	 * Get default blank set of props used per item.
	 *
	 * @return object
	 */
	protected function get_default_item_props(): object {
		return (object) [
			'object'             => null,
			'tax_class'          => '',
			'taxable'            => false,
			'quantity'           => 0,
			'product'            => false,
			'price_includes_tax' => false,
			'subtotal'           => 0,
			'subtotal_tax'       => 0,
			'subtotal_taxes'     => [],
			'total'              => 0,
			'total_tax'          => 0,
			'taxes'              => [],
		];
	}

	/**
	 * Get default blank set of props used per fee.
	 *
	 * @return object
	 */
	protected function get_default_fee_props(): object {
		return (object) [
			'object'    => null,
			'tax_class' => '',
			'taxable'   => true,
			'total_tax' => 0,
			'taxes'     => [],
		];
	}

	/**
	 * Get default blank set of props used per shipping row.
	 *
	 * @return object
	 */
	protected function get_default_shipping_props(): object {
		return (object) [
			'object'    => null,
			'tax_class' => '',
			'taxable'   => true,
			'total'     => 0,
			'total_tax' => 0,
			'taxes'     => [],
		];
	}

	/**
	 * Handles a cart or order object passed in for calculation. Normalises data
	 * into the same format for use by this class.
	 *
	 * Each item is made up of the following props, in addition to those returned by get_default_item_props() for totals.
	 *  - key: An identifier for the item (cart item key or line item ID).
	 *  - cart_item: For carts, the cart item from the cart which may include custom data.
	 *  - quantity: The qty for this line.
	 *  - price: The line price in cents.
	 *  - product: The product object this cart item is for.
	 */
	protected function get_items_from_cart() {
		$this->items = [];

		foreach ( $this->cart->get_cart_items() as $cart_item_key => $cart_item ) {
			$product                       = Helper::get_product( $cart_item->product_id );
			$item                          = $this->get_default_item_props();
			$item->key                     = $cart_item_key;
			$item->object                  = $cart_item;
			$item->tax_class               = $product ? $product->get_tax_class() : '';
			$item->taxable                 = ! $product || ProductTaxStatus::TAXABLE === $product->get_tax_status();
			$item->price_includes_tax      = TaxUtil::prices_include_tax();
			$item->quantity                = $cart_item->quantity;
			$item->price                   = Formatting::add_number_precision_deep( (float) $cart_item->get_price() * (float) $cart_item->quantity );
			$item->compare_price           = $cart_item->compare_price;
			$item->product_id              = $cart_item->product_id;
			$item->tax_rates               = $this->get_item_tax_rates( $item );
			$this->items[ $cart_item_key ] = $item;
		}

		// Using item->taxable as item->product->is_taxable.
	}

	/**
	 * Get item costs grouped by tax class.
	 *
	 * @return array
	 */
	protected function get_tax_class_costs(): array {
		$item_tax_classes     = wp_list_pluck( $this->items, 'tax_class' );
		$shipping_tax_classes = wp_list_pluck( $this->shipping, 'tax_class' );
		$fee_tax_classes      = wp_list_pluck( $this->fees, 'tax_class' );
		$costs                = array_fill_keys( $item_tax_classes + $shipping_tax_classes + $fee_tax_classes, 0 );
		$costs['non-taxable'] = 0;

		foreach ( $this->items + $this->fees + $this->shipping as $item ) {
			if ( 0 > $item->total ) {
				continue;
			}
			if ( ! $item->taxable ) {
				$costs['non-taxable'] += $item->total;
			} elseif ( 'inherit' === $item->tax_class ) {
				$costs[ reset( $item_tax_classes ) ] += $item->total;
			} else {
				$costs[ $item->tax_class ] += $item->total;
			}
		}

		return array_filter( $costs );
	}

	/**
	 * Get fee objects from the cart. Normalises data
	 * into the same format for use by this class.
	 */
	protected function get_fees_from_cart() {
		$this->fees = [];
		$this->cart->calculate_fees();

		$fee_running_total = 0;

		foreach ( $this->cart->get_fees() as $fee_key => $fee_object ) {
			$fee            = $this->get_default_fee_props();
			$fee->object    = $fee_object;
			$fee->tax_class = '';
			$fee->taxable   = true;
			$fee->total     = Formatting::add_number_precision_deep( $fee->object->amount );

			// Negative fees should not make the order total go negative.
			if ( 0 > $fee->total ) {
				$max_discount = NumberUtil::round( $this->get_total( 'items_total', true ) + $fee_running_total + $this->get_total( 'shipping_total', true ) ) * - 1;

				if ( $fee->total < $max_discount ) {
					$fee->total = $max_discount;
				}
			}

			$fee_running_total += $fee->total;

			if ( $this->calculate_tax ) {
				if ( 0 > $fee->total ) {
					// Negative fees should have the taxes split between all items so it works as a true discount.
					$tax_class_costs = $this->get_tax_class_costs();
					$total_cost      = array_sum( $tax_class_costs );

					if ( $total_cost ) {
						foreach ( $tax_class_costs as $tax_class => $tax_class_cost ) {
							if ( 'non-taxable' === $tax_class ) {
								continue;
							}

							$proportion = $tax_class_cost / $total_cost;
							$fee->taxes = Formatting::array_merge_recursive_numeric( $fee->taxes, Tax::calc_tax( $fee->total * $proportion, Tax::get_rates( $tax_class ) ) );
						}
					}
				} elseif ( $fee->object->taxable ) {
					$fee->taxes = Tax::calc_tax( $fee->total, Tax::get_rates( $fee->tax_class, $this->cart->get_customer() ), false );
				}
			}

			$fee->taxes     = apply_filters( 'storeengine/cart/totals_get_fees_from_cart_taxes', $fee->taxes, $fee, $this );
			$fee->total_tax = array_sum( array_map( [ $this, 'round_line_tax' ], $fee->taxes ) );

			// Set totals within object.
			$fee->object->total    = Formatting::remove_number_precision_deep( $fee->total );
			$fee->object->tax_data = Formatting::remove_number_precision_deep( $fee->taxes );
			$fee->object->tax      = Formatting::remove_number_precision_deep( $fee->total_tax );

			$this->fees[ $fee_key ] = $fee;
		}
	}

	/**
	 * Get shipping methods from the cart and normalise.
	 */
	protected function get_shipping_from_cart() {
		$this->shipping = [];

		if ( ! $this->cart->show_shipping() ) {
			return;
		}

		foreach ( $this->cart->calculate_shipping() as $key => $shipping_object ) {
			$shipping_line            = $this->get_default_shipping_props();
			$shipping_line->object    = $shipping_object;
			$shipping_line->tax_class = Helper::get_settings( 'shipping_tax_class' );
			$shipping_line->taxable   = true;
			$shipping_line->total     = Formatting::add_number_precision_deep( $shipping_object->cost );
			$shipping_line->taxes     = Formatting::add_number_precision_deep( $shipping_object->taxes, false );
			$shipping_line->taxes     = array_map( array( $this, 'round_item_subtotal' ), $shipping_line->taxes );
			$shipping_line->total_tax = array_sum( $shipping_line->taxes );

			$this->shipping[ $key ] = $shipping_line;
		}
	}

	/**
	 * Return array of coupon objects from the cart. Normalises data
	 * into the same format for use by this class.
	 */
	protected function get_coupons_from_cart() {
		$this->coupons = $this->cart->get_coupons();

		foreach ( $this->coupons as $coupon ) {
			switch ( $coupon->get_discount_type() ) {
				case 'fixed_product':
					$coupon->sort = 1;
					break;
				case 'percentage':
				case 'percent':
					$coupon->sort = 2;
					break;
				case 'fixedAmount':
				case 'fixed_cart':
					$coupon->sort = 3;
					break;
				default:
					$coupon->sort = 0;
					break;
			}

			// Allow plugins to override the default order.
			$coupon->sort = apply_filters( 'storeengine/coupon_sort', $coupon->sort, $coupon );
		}

		uasort( $this->coupons, [ $this, 'sort_coupons_callback' ] );
	}

	/**
	 * Sort coupons so discounts apply consistently across installs.
	 *
	 * In order of priority;
	 *  - sort param
	 *  - usage restriction
	 *  - coupon value
	 *  - ID
	 *
	 * @param Coupon $a Coupon object.
	 * @param Coupon $b Coupon object.
	 *
	 * @return int
	 */
	protected function sort_coupons_callback( Coupon $a, Coupon $b ): int {
		if ( $a->sort === $b->sort ) {
			// @TODO implement limit_usage_to_x_items coupon->get_limit_usage_to_x_items
			/** @see WC_Cart_Totals::sort_coupons_callback */

			return ( $a->get_amount() < $b->get_amount() ) ? - 1 : 1;
		}

		return ( $a->sort < $b->sort ) ? - 1 : 1;
	}

	/**
	 * Ran to remove all base taxes from an item. Used when prices include tax, and the customer is tax exempt.
	 *
	 * @param object $item Item to adjust the prices of.
	 *
	 * @return object
	 */
	protected function remove_item_base_taxes( object $item ): object {
		if ( $item->price_includes_tax && $item->taxable ) {
			if ( apply_filters( 'storeengine/adjust_non_base_location_prices', true ) ) {
				$base_tax_rates = Tax::get_base_tax_rates( '' );
			} else {
				/**
				 * If we want all customers to pay the same price on this store, we should not remove base taxes from a VAT exempt user's price,
				 * but just the relevant tax rate. See issue #20911.
				 */
				$base_tax_rates = $item->tax_rates;
			}

			// Work out a new base price without the shop's base tax.
			$taxes = Tax::calc_tax( $item->price, $base_tax_rates, true );

			// Now we have a new item price (excluding TAX).
			$item->price              = NumberUtil::round( $item->price - array_sum( $taxes ) );
			$item->price_includes_tax = false;
		}

		return $item;
	}

	/**
	 * Only ran if storeengine/adjust_non_base_location_prices is true.
	 *
	 * If the customer is outside of the base location, this removes the base
	 * taxes. This is off by default unless the filter is used.
	 *
	 * Uses edit context so unfiltered tax class is returned.
	 *
	 * @param object $item Item to adjust the prices of.
	 *
	 * @return object
	 */
	protected function adjust_non_base_location_price( object $item ): object {
		if ( $item->price_includes_tax && $item->taxable ) {
			$base_tax_rates = Tax::get_base_tax_rates( '' );

			if ( $item->tax_rates !== $base_tax_rates ) {
				// Work out a new base price without the shop's base tax.
				$taxes     = Tax::calc_tax( $item->price, $base_tax_rates, true );
				$new_taxes = Tax::calc_tax( $item->price - array_sum( $taxes ), $item->tax_rates, false );

				// Now we have a new item price.
				$item->price = $item->price - array_sum( $taxes ) + array_sum( $new_taxes );
			}
		}

		return $item;
	}

	/**
	 * Get discounted price of an item with precision (in cents).
	 *
	 * @param string|int $item_key Item to get the price of.
	 *
	 * @return int
	 */
	protected function get_discounted_price_in_cents( $item_key ): int {
		$item = $this->items[ $item_key ];

		return isset( $this->coupon_discount_totals[ $item_key ] ) ? $item->price - $this->coupon_discount_totals[ $item_key ] : $item->price;
	}

	/**
	 * Get tax rates for an item. Caches rates in class to avoid multiple look-ups.
	 *
	 * @param object $item Item to get tax rates for.
	 *
	 * @return array of taxes
	 */
	protected function get_item_tax_rates( object $item ): array {
		if ( ! TaxUtil::is_tax_enabled() ) {
			return [];
		}

		$product   = Helper::get_product( $item->product_id );
		$tax_class = $product ? $product->get_tax_class() : '';

		if ( ! isset( $this->item_tax_rates[ $tax_class ] ) ) {
			$this->item_tax_rates[ $tax_class ] = Tax::get_rates( $product ? $product->get_tax_class() : '', $this->cart->get_customer() );
		}

		$item_tax_rates = $this->item_tax_rates[ $tax_class ];

		// Allow plugins to filter item tax rates.
		return apply_filters( 'storeengine/cart/totals_get_item_tax_rates', $item_tax_rates, $item, $this->cart );
	}

	/**
	 * Get item costs grouped by tax class.
	 *
	 * @return array
	 */
	protected function get_item_costs_by_tax_class() {
		$tax_classes = [ 'non-taxable' => 0 ];

		foreach ( $this->items + $this->fees + $this->shipping as $item ) {
			if ( ! isset( $tax_classes[ $item->tax_class ] ) ) {
				$tax_classes[ $item->tax_class ] = 0;
			}

			if ( $item->taxable ) {
				$tax_classes[ $item->tax_class ] += $item->total;
			} else {
				$tax_classes['non-taxable'] += $item->total;
			}
		}

		return $tax_classes;
	}

	/**
	 * Get a single total with or without precision (in cents).
	 *
	 * @param string $key Total to get.
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return int|float
	 */
	public function get_total( string $key = 'total', bool $in_cents = false ) {
		$totals = $this->get_totals( $in_cents );

		return $totals[ $key ] ?? 0;
	}

	/**
	 * Set a single total.
	 *
	 * @param string $key Total name you want to set.
	 * @param int|float $total Total to set.
	 */
	protected function set_total( string $key, $total ) {
		$this->totals[ $key ] = $total;
	}

	/**
	 * Get all totals with or without precision (in cents).
	 *
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return array.
	 */
	public function get_totals( bool $in_cents = false ): array {
		return $in_cents ? $this->totals : Formatting::remove_number_precision_deep( $this->totals );
	}

	/**
	 * Returns array of values for totals calculation.
	 *
	 * @param string $field Field name. Will probably be `total` or `subtotal`.
	 *
	 * @return array Items object
	 */
	protected function get_values_for_total( string $field ): array {
		return array_values( wp_list_pluck( $this->items, $field ) );
	}

	/**
	 * Get taxes merged by type.
	 *
	 * @param bool $in_cents If returned value should be in cents.
	 * @param array|string $types Types to merge and return. Defaults to all.
	 *
	 * @return array
	 */
	protected function get_merged_taxes( bool $in_cents = false, $types = [ 'items', 'fees', 'shipping' ] ): array {
		$items = [];
		$taxes = [];

		if ( is_string( $types ) ) {
			$types = [ $types ];
		}

		foreach ( $types as $type ) {
			if ( isset( $this->$type ) ) {
				$items = array_merge( $items, $this->$type );
			}
		}

		foreach ( $items as $item ) {
			foreach ( $item->taxes as $rate_id => $rate ) {
				if ( ! isset( $taxes[ $rate_id ] ) ) {
					$taxes[ $rate_id ] = 0;
				}
				$taxes[ $rate_id ] += $this->round_line_tax( $rate );
			}
		}

		return $in_cents ? $taxes : Formatting::remove_number_precision_deep( $taxes );
	}

	/**
	 * Combine item taxes into a single array, preserving keys.
	 *
	 * @param array $item_taxes Taxes to combine.
	 *
	 * @return array
	 */
	protected function combine_item_taxes( array $item_taxes ): array {
		$merged_taxes = [];
		foreach ( $item_taxes as $taxes ) {
			foreach ( $taxes as $tax_id => $tax_amount ) {
				if ( ! isset( $merged_taxes[ $tax_id ] ) ) {
					$merged_taxes[ $tax_id ] = 0;
				}
				$merged_taxes[ $tax_id ] += $tax_amount;
			}
		}

		return $merged_taxes;
	}

	/*
	|--------------------------------------------------------------------------
	| Calculation methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Calculate item totals.
	 */
	protected function calculate_item_totals() {
		$this->get_items_from_cart();
		$this->calculate_item_subtotals();
		$this->calculate_discounts();


		foreach ( $this->items as $item_key => $item ) {
			$item->total     = $this->get_discounted_price_in_cents( $item_key );
			$item->total_tax = 0;

			if ( $this->calculate_tax && $item->taxable ) {
				$total_taxes     = Tax::calc_tax( $item->total, $item->tax_rates, $item->price_includes_tax );
				$item->taxes     = apply_filters( 'storeengine/calculate_item_totals_taxes', $total_taxes, $item, $this );
				$item->total_tax = array_sum( array_map( array( $this, 'round_line_tax' ), $item->taxes ) );

				if ( $item->price_includes_tax ) {
					// Use un-rounded taxes so we can re-calculate from the orders screen accurately later.
					$item->total = $item->total - array_sum( $item->taxes );
				}
			}

			$this->cart->cart_items[ $item_key ]->line_tax_data['total'] = Formatting::remove_number_precision_deep( $item->taxes );
			$this->cart->cart_items[ $item_key ]->line_total             = Formatting::remove_number_precision( $item->total );
			$this->cart->cart_items[ $item_key ]->line_tax               = Formatting::remove_number_precision( $item->total_tax );
		}

		$items_total = $this->get_rounded_items_total( $this->get_values_for_total( 'total' ) );

		$this->set_total( 'items_total', $items_total );
		$this->set_total( 'items_total_tax', array_sum( array_values( wp_list_pluck( $this->items, 'total_tax' ) ) ) );

		$this->cart->set_cart_contents_total( $this->get_total( 'items_total' ) );
		$merged_taxes = $this->get_merged_taxes( false, [ 'items' ] );
		$this->cart->set_cart_contents_tax( array_sum( $merged_taxes ) );
		$this->cart->set_cart_contents_taxes( $merged_taxes );
	}

	/**
	 * Subtotals are costs before discounts.
	 *
	 * To prevent rounding issues we need to work with the inclusive price where possible
	 * otherwise we'll see errors such as when working with a 9.99 inc price, 20% VAT which would
	 * be 8.325 leading to totals being 1p off.
	 *
	 * Pre-tax coupons come off the price the customer thinks they are paying - tax is calculated
	 * afterward.
	 *
	 * e.g. $100 bike with $10 coupon = customer pays $90 and tax worked backwards from that.
	 */
	protected function calculate_item_subtotals() {
		$merged_subtotal_taxes = []; // Taxes indexed by tax rate ID for storage later.

		$adjust_non_base_location_prices = apply_filters( 'storeengine/adjust_non_base_location_prices', true );
		$is_customer_vat_exempt          = $this->cart->get_customer()->get_is_vat_exempt();

		foreach ( $this->items as $item_key => $item ) {
			if ( $item->price_includes_tax ) {
				if ( $is_customer_vat_exempt ) {
					$item = $this->remove_item_base_taxes( $item );
				} elseif ( $adjust_non_base_location_prices ) {
					$item = $this->adjust_non_base_location_price( $item );
				}
			}

			$item->subtotal = $item->price;

			if ( $this->calculate_tax ) { // assuming all items are taxable.
				$item->subtotal_taxes = Tax::calc_tax( $item->subtotal, $item->tax_rates, $item->price_includes_tax );
				$item->subtotal_tax   = array_sum( array_map( [ $this, 'round_line_tax' ], $item->subtotal_taxes ) );

				if ( $item->price_includes_tax ) {
					// Use unrounded taxes so we can re-calculate from the orders screen accurately later.
					$item->subtotal = $item->subtotal - array_sum( $item->subtotal_taxes );
				}

				foreach ( $item->subtotal_taxes as $rate_id => $rate ) {
					if ( ! isset( $merged_subtotal_taxes[ $rate_id ] ) ) {
						$merged_subtotal_taxes[ $rate_id ] = 0;
					}
					$merged_subtotal_taxes[ $rate_id ] += $this->round_line_tax( $rate );
				}
			}

			$this->cart->cart_items[ $item_key ]->line_tax_data     = [ 'subtotal' => Formatting::remove_number_precision_deep( $item->subtotal_taxes ) ];
			$this->cart->cart_items[ $item_key ]->line_subtotal     = Formatting::remove_number_precision( $item->subtotal );
			$this->cart->cart_items[ $item_key ]->line_subtotal_tax = Formatting::remove_number_precision( $item->subtotal_tax );
		}

		$items_subtotal = $this->get_rounded_items_total( $this->get_values_for_total( 'subtotal' ) );

		// Prices are not rounded here because they should already be rounded based on settings in `get_rounded_items_total` and in `round_line_tax` method calls.
		$this->set_total( 'items_subtotal', $items_subtotal );
		$this->set_total( 'items_subtotal_tax', array_sum( $merged_subtotal_taxes ) );

		$this->cart->set_subtotal( $this->get_total( 'items_subtotal' ) );
		$this->cart->set_subtotal_tax( $this->get_total( 'items_subtotal_tax' ) );
	}

	/**
	 * Calculate COUPON based discounts which change item prices.
	 *
	 * @uses Discounts class.
	 */
	protected function calculate_discounts() {
		$this->get_coupons_from_cart();

		$discounts = new Discounts( $this->cart );

		// Set items directly so the discounts class can see any tax adjustments made thus far using subtotals.
		$discounts->set_items( $this->items );

		foreach ( $this->coupons as $coupon ) {
			$discounts->apply_coupon( $coupon );
		}

		$coupon_discount_amounts     = $discounts->get_discounts_by_coupon( true );
		$coupon_discount_tax_amounts = [];

		// See how much tax was 'discounted' per item and per coupon.
		if ( $this->calculate_tax ) {
			foreach ( $discounts->get_discounts( true ) as $coupon_code => $coupon_discounts ) {
				$coupon_discount_tax_amounts[ $coupon_code ] = 0;

				foreach ( $coupon_discounts as $item_key => $coupon_discount ) {
					$item = $this->items[ $item_key ];

					if ( $item->taxable ) {
						// Item subtotals were sent, so set 3rd param.
						$item_tax = array_sum( Tax::calc_tax( $coupon_discount, $item->tax_rates, $item->price_includes_tax ) );

						// Sum total tax.
						$coupon_discount_tax_amounts[ $coupon_code ] += $item_tax;

						// Remove tax from discount total.
						if ( $item->price_includes_tax ) {
							$coupon_discount_amounts[ $coupon_code ] -= $item_tax;
						}
					}
				}
			}
		}

		$this->coupon_discount_totals     = $discounts->get_discounts_by_item( true );
		$this->coupon_discount_tax_totals = $coupon_discount_tax_amounts;

		if ( TaxUtil::prices_include_tax() ) {
			$this->set_total( 'discounts_total', array_sum( $this->coupon_discount_totals ) - array_sum( $this->coupon_discount_tax_totals ) );
		} else {
			$this->set_total( 'discounts_total', array_sum( $this->coupon_discount_totals ) );
		}

		$this->set_total( 'discounts_tax_total', array_sum( $this->coupon_discount_tax_totals ) );
		$this->cart->set_coupon_discount_totals( Formatting::remove_number_precision_deep( $coupon_discount_amounts ) );
		$this->cart->set_coupon_discount_tax_totals( Formatting::remove_number_precision_deep( $coupon_discount_tax_amounts ) );

		// Add totals to cart object. Note: Discount total for cart is excl tax.
		$this->cart->set_discount_total( $this->get_total( 'discounts_total' ) );
		$this->cart->set_discount_tax( $this->get_total( 'discounts_tax_total' ) );
	}

	/**
	 * Triggers the cart fees API, grabs the list of fees, and calculates taxes.
	 *
	 * Note: This class sets the totals for the 'object' as they are calculated. This is so that APIs like the fees API can see these totals if needed.
	 */
	protected function calculate_fee_totals() {
		$this->get_fees_from_cart();

		$this->set_total( 'fees_total', array_sum( wp_list_pluck( $this->fees, 'total' ) ) );
		$this->set_total( 'fees_total_tax', array_sum( wp_list_pluck( $this->fees, 'total_tax' ) ) );

		$this->cart->fees_api()->set_fees( wp_list_pluck( $this->fees, 'object' ) );
		$this->cart->set_fee_total( Formatting::remove_number_precision_deep( array_sum( wp_list_pluck( $this->fees, 'total' ) ) ) );
		$this->cart->set_fee_tax( Formatting::remove_number_precision_deep( array_sum( wp_list_pluck( $this->fees, 'total_tax' ) ) ) );
		$this->cart->set_fee_taxes( Formatting::remove_number_precision_deep( $this->combine_item_taxes( wp_list_pluck( $this->fees, 'taxes' ) ) ) );
	}

	/**
	 * Calculate any shipping taxes.
	 */
	protected function calculate_shipping_totals() {
		$this->get_shipping_from_cart();
		$this->set_total( 'shipping_total', array_sum( wp_list_pluck( $this->shipping, 'total' ) ) );
		$this->set_total( 'shipping_tax_total', array_sum( wp_list_pluck( $this->shipping, 'total_tax' ) ) );

		$this->cart->set_shipping_total( $this->get_total( 'shipping_total' ) );
		$this->cart->set_shipping_tax( $this->get_total( 'shipping_tax_total' ) );
		$this->cart->set_shipping_taxes( Formatting::remove_number_precision_deep( $this->combine_item_taxes( wp_list_pluck( $this->shipping, 'taxes' ) ) ) );
	}

	/**
	 * Main cart totals.
	 */
	protected function calculate_totals() {
		$this->set_total(
			'total',
			NumberUtil::round(
				$this->get_total( 'items_total', true ) +
				$this->get_total( 'fees_total', true ) +
				$this->get_total( 'shipping_total', true ) +
				array_sum( $this->get_merged_taxes( true ) ),
				0
			)
		);

		// Shipping and fee taxes are rounded separately because they were entered excluding taxes (as opposed to item prices, which may or may not be including taxes depending upon settings).
		$this->cart->set_total_tax(
			array_sum( $this->get_merged_taxes( false, [ 'items' ] ) ) +
			NumberUtil::round(
				array_sum( $this->get_merged_taxes( false, [ 'fees', 'shipping' ] ) ),
				Formatting::get_price_decimals()
			)
		);

		// Allow plugins to hook and alter totals before final total is calculated.
		if ( has_action( 'storeengine/calculate_totals' ) ) {
			do_action( 'storeengine/calculate_totals', $this->cart );
		}

		// Allow plugins to filter the grand total, and sum the cart totals in case of modifications.
		$this->cart->set_total( max( 0, apply_filters( 'storeengine/calculated_total', $this->get_total( 'total' ), $this->cart ) ) );
	}
}

// End of file cart-totals.php.
