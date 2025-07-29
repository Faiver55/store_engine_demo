<?php /** @noinspection DuplicatedCode */

namespace StoreEngine\Classes;

use Exception;
use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use WP_Error;

class Discounts {

	/**
	 * Reference to cart or order object.
	 *
	 * @var Cart|Order
	 */
	protected $object;

	/**
	 * An array of items to discount.
	 *
	 * @var array
	 */
	protected array $items = [];

	/**
	 * An array of discounts which have been applied to items.
	 *
	 * @var array[] Code => Item Key => Value
	 */
	protected array $discounts = [];

	/**
	 * Discounts Constructor.
	 *
	 * @param Cart|Order $object Cart or order object.
	 */
	public function __construct( $object = null ) {
		if ( is_a( $object, Cart::class ) ) {
			$this->set_items_from_cart( $object );
		} elseif ( is_a( $object, Order::class ) ) {
			$this->set_items_from_order( $object );
		}
	}

	/**
	 * Set items directly. Used by WC_Cart_Totals.
	 *
	 * @param array $items Items to set.
	 */
	public function set_items( array $items ) {
		$this->items     = $items;
		$this->discounts = [];
		uasort( $this->items, [ $this, 'sort_by_price' ] );
	}

	/**
	 * Normalise cart items which will be discounted.
	 *
	 * @param Cart $cart Cart object.
	 */
	public function set_items_from_cart( Cart $cart ) {
		$this->items     = [];
		$this->discounts = [];

		if ( ! is_a( $cart, Cart::class ) ) {
			return;
		}

		$this->object = $cart;

		foreach ( $cart->get_cart_items() as $key => $cart_item ) {
			$item                    = new stdClass();
			$item->key               = $key;
			$item->object            = $cart_item;
			$item->product_id        = $cart_item->product_id;
			$item->product_parent_id = $cart_item->product_parent_id ?? 0;
			$item->price_id          = $cart_item->price_id;
			$item->price             = $cart_item->get_price();
			$item->quantity          = $cart_item->quantity;
			$item->price             = Formatting::add_number_precision_deep( (float) $item->price * (float) $item->quantity );
			$item->compare_price     = Formatting::add_number_precision_deep( $cart_item->compare_price );
			$this->items[ $key ]     = $item;
		}

		uasort( $this->items, [ $this, 'sort_by_price' ] );
	}

	/**
	 * Normalise order items which will be discounted.
	 *
	 * @param Order $order Order object.
	 */
	public function set_items_from_order( Order $order ) {
		$this->items     = [];
		$this->discounts = [];

		if ( ! is_a( $order, Order::class ) ) {
			return;
		}

		$this->object = $order;

		foreach ( $order->get_items() as $order_item ) {
			$item               = new stdClass();
			$item->key          = $order_item->get_id();
			$item->object       = $order_item;
			$item->product_id   = $order_item->get_product_id();
			$item->variation_id = $order_item->get_variation_id();
			$item->price_id     = $order_item->get_price_id();
			$item->price        = $order_item->get_subtotal();
			$item->quantity     = $order_item->get_quantity();
			$item->price        = Formatting::add_number_precision_deep( $order_item->get_subtotal() );

			if ( $order->get_prices_include_tax() ) {
				$item->price += Formatting::add_number_precision_deep( $order_item->get_subtotal_tax() );
			}

			$this->items[ $order_item->get_id() ] = $item;
		}

		uasort( $this->items, [ $this, 'sort_by_price' ] );
	}

	/**
	 * Get the object concerned.
	 *
	 * @return object
	 */
	public function get_object() {
		return $this->object;
	}

	/**
	 * Get items.
	 *
	 * @return object[]
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Get items to validate.
	 *
	 * @return object[]
	 */
	public function get_items_to_validate(): array {
		return apply_filters( 'storeengine/coupon_get_items_to_validate', $this->get_items(), $this );
	}

	/**
	 * Get discount by key with or without precision.
	 *
	 * @param string $key name of discount row to return.
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return float
	 */
	public function get_discount( string $key, bool $in_cents = false ) {
		$item_discount_totals = $this->get_discounts_by_item( $in_cents );

		return $item_discount_totals[ $key ] ?? 0;
	}

	/**
	 * Get all discount totals.
	 *
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return array
	 */
	public function get_discounts( bool $in_cents = false ): array {
		$discounts = $this->discounts;

		return $in_cents ? $discounts : Formatting::remove_number_precision_deep( $discounts );
	}

	/**
	 * Get all discount totals per item.
	 *
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return array
	 */
	public function get_discounts_by_item( bool $in_cents = false ): array {
		$discounts            = $this->discounts;
		$item_discount_totals = (array) array_shift( $discounts );

		foreach ( $discounts as $item_discounts ) {
			foreach ( $item_discounts as $item_key => $item_discount ) {
				$item_discount_totals[ $item_key ] += $item_discount;
			}
		}

		return $in_cents ? $item_discount_totals : Formatting::remove_number_precision_deep( $item_discount_totals );
	}

	/**
	 * Get all discount totals per coupon.
	 *
	 * @param bool $in_cents Should the totals be returned in cents, or without precision.
	 *
	 * @return array
	 */
	public function get_discounts_by_coupon( bool $in_cents = false ): array {
		$coupon_discount_totals = array_map( 'array_sum', $this->discounts );

		return $in_cents ? $coupon_discount_totals : Formatting::remove_number_precision_deep( $coupon_discount_totals );
	}

	/**
	 * Get discounted price of an item without precision.
	 *
	 * @param object $item Get data for this item.
	 *
	 * @return float
	 */
	public function get_discounted_price( object $item ): float {
		return Formatting::remove_number_precision_deep( $this->get_discounted_price_in_cents( $item ) );
	}

	/**
	 * Get discounted price of an item to precision (in cents).
	 *
	 * @param object $item Get data for this item.
	 *
	 * @return int
	 */
	public function get_discounted_price_in_cents( object $item ): int {
		return absint( NumberUtil::round( $item->price - $this->get_discount( $item->key, true ) ) );
	}

	/**
	 * Apply a discount to all items using a coupon.
	 *
	 * @param Coupon $coupon Coupon object being applied to the items.
	 * @param bool $validate Set false to skip coupon validation.
	 *
	 * @return bool|WP_Error True if applied or WP_Error instance in failure.
	 */
	public function apply_coupon( Coupon $coupon, bool $validate = true ) {
		if ( ! is_a( $coupon, Coupon::class ) ) {
			return new WP_Error( 'invalid_coupon', __( 'Invalid coupon', 'storeengine' ) );
		}

		$is_coupon_valid = $validate ? $this->is_coupon_valid( $coupon ) : true;

		if ( is_wp_error( $is_coupon_valid ) ) {
			return $is_coupon_valid;
		}

		$coupon_code = strtolower( $coupon->get_code() );
		if ( ! isset( $this->discounts[ $coupon_code ] ) || ! is_array( $this->discounts[ $coupon_code ] ) ) {
			$this->discounts[ $coupon_code ] = array_fill_keys( array_keys( $this->items ), 0 );
		}

		$items_to_apply = $this->get_items_to_apply_coupon( $coupon );

		// Core discounts are handled here as of 3.2.
		switch ( $coupon->get_discount_type() ) {
			case 'percentage':
			case 'percent':
				$this->apply_coupon_percent( $coupon, $items_to_apply );
				break;
			case 'fixed_product':
				$this->apply_coupon_fixed_product( $coupon, $items_to_apply );
				break;
			case 'fixedAmount':
			case 'fixed_cart':
				$this->apply_coupon_fixed_cart( $coupon, $items_to_apply );
				break;
			default:
				$this->apply_coupon_custom( $coupon, $items_to_apply );
				break;
		}

		return true;
	}

	/**
	 * Sort by price.
	 *
	 * @param object $a First element.
	 * @param object $b Second element.
	 *
	 * @return int
	 */
	protected function sort_by_price( object $a, object $b ): int {
		$price_1 = $a->price * $a->quantity;
		$price_2 = $b->price * $b->quantity;
		if ( $price_1 === $price_2 ) {
			return 0;
		}

		return ( $price_1 < $price_2 ) ? 1 : - 1;
	}

	/**
	 * Filter out all products which have been fully discounted to 0.
	 * Used as array_filter callback.
	 *
	 * @param object $item Get data for this item.
	 *
	 * @return bool
	 */
	protected function filter_products_with_price( object $item ): bool {
		return $this->get_discounted_price_in_cents( $item ) > 0;
	}

	/**
	 * Get items which the coupon should be applied to.
	 *
	 * @param Coupon $coupon Coupon object.
	 *
	 * @return array
	 */
	protected function get_items_to_apply_coupon( Coupon $coupon ): array {
		$items_to_apply = [];

		foreach ( $this->get_items_to_validate() as $item ) {
			$item_to_apply = clone $item; // Clone the item so changes to this item do not affect the originals.

			if ( 0 === $this->get_discounted_price_in_cents( $item_to_apply ) || 0 >= $item_to_apply->quantity ) {
				continue;
			}

			if ( ! $coupon->is_valid_for_product(
				$item_to_apply->product_id ?? $item_to_apply->object->product_id,
				$item_to_apply->object
				) && ! $coupon->is_valid_for_cart() ) {
				continue;
			}

			$items_to_apply[] = $item_to_apply;
		}

		/**
		 * Filters the items that a coupon should be applied to.
		 *
		 * This filter allows you to modify the items that a coupon will be applied to before the discount calculations take place.
		 *
		 * @param array $items_to_apply The items that the coupon will be applied to.
		 * @param Coupon $coupon The coupon object.
		 * @param Discounts $this The discounts instance.
		 *
		 * @return array The modified list of items that the coupon should be applied to.
		 */
		return apply_filters( 'storeengine/coupon_get_items_to_apply', $items_to_apply, $coupon, $this );
	}

	/**
	 * Apply percent discount to items and return an array of discounts granted.
	 *
	 * @param Coupon $coupon Coupon object. Passed through filters.
	 * @param array $items_to_apply Array of items to apply the coupon to.
	 *
	 * @return int Total discounted.
	 */
	protected function apply_coupon_percent( $coupon, $items_to_apply ) {
		$total_discount        = 0;
		$cart_total            = 0;
		$limit_usage_qty       = 0;
		$applied_count         = 0;
		$adjust_final_discount = true;

		if ( null !== $coupon->get_limit_usage_to_x_items() ) {
			$limit_usage_qty = $coupon->get_limit_usage_to_x_items();
		}

		$coupon_amount = $coupon->get_amount();

		foreach ( $items_to_apply as $item ) {
			// Find out how much price is available to discount for the item.
			$discounted_price = $this->get_discounted_price_in_cents( $item );

			// Get the price we actually want to discount, based on settings.
			$price_to_discount = ( 'yes' === get_option( 'storeengine/calc_discounts_sequentially', 'no' ) ) ? $discounted_price : NumberUtil::round( $item->price );

			// See how many and what price to apply to.
			$apply_quantity    = $limit_usage_qty && ( $limit_usage_qty - $applied_count ) < $item->quantity ? $limit_usage_qty - $applied_count : $item->quantity;
			$apply_quantity    = max( 0, apply_filters( 'storeengine/coupon_get_apply_quantity', $apply_quantity, $item, $coupon, $this ) );
			$price_to_discount = ( $price_to_discount / $item->quantity ) * $apply_quantity;

			// Run coupon calculations.
			$discount = floor( $price_to_discount * ( $coupon_amount / 100 ) );

			if ( is_a( $this->object, Cart::class ) && has_filter( 'storeengine/coupon_get_discount_amount' ) ) {
				// Send through the legacy filter, but not as cents.
				$filtered_discount = Formatting::add_number_precision( apply_filters( 'storeengine/coupon_get_discount_amount', Formatting::remove_number_precision( $discount ), Formatting::remove_number_precision( $price_to_discount ), $item->object, false, $coupon ) );

				if ( $filtered_discount !== $discount ) {
					$discount              = $filtered_discount;
					$adjust_final_discount = false;
				}
			}

			$discount       = Formatting::round_discount( min( $discounted_price, $discount ), 0 );
			$cart_total     = $cart_total + $price_to_discount;
			$total_discount = $total_discount + $discount;
			$applied_count  = $applied_count + $apply_quantity;

			// Store code and discount amount per item.
			$this->discounts[ strtolower($coupon->get_code()) ][ $item->key ] += $discount;
		}

		// Work out how much discount would have been given to the cart as a whole and compare to what was discounted on all line items.
		$cart_total_discount = Formatting::round_discount( $cart_total * ( $coupon_amount / 100 ), 0 );

		if ( $total_discount < $cart_total_discount && $adjust_final_discount ) {
			$total_discount += $this->apply_coupon_remainder( $coupon, $items_to_apply, $cart_total_discount - $total_discount );
		}

		return $total_discount;
	}

	/**
	 * Apply fixed product discount to items.
	 *
	 * @param Coupon $coupon Coupon object. Passed through filters.
	 * @param array $items_to_apply Array of items to apply the coupon to.
	 * @param int $amount Fixed discount amount to apply in cents. Leave blank to pull from coupon.
	 *
	 * @return int Total discounted.
	 */
	protected function apply_coupon_fixed_product( $coupon, $items_to_apply, $amount = null ) {
		$total_discount  = 0;
		$amount          = $amount ? $amount : Formatting::add_number_precision( $coupon->get_amount() );
		$limit_usage_qty = 0;
		$applied_count   = 0;

		if ( null !== $coupon->get_limit_usage_to_x_items() ) {
			$limit_usage_qty = $coupon->get_limit_usage_to_x_items();
		}

		foreach ( $items_to_apply as $item ) {
			// Find out how much price is available to discount for the item.
			$discounted_price = $this->get_discounted_price_in_cents( $item );

			// Get the price we actually want to discount, based on settings.
			$price_to_discount = ( 'yes' === get_option( 'storeengine/calc_discounts_sequentially', 'no' ) ) ? $discounted_price : $item->price;

			// Run coupon calculations.
			if ( $limit_usage_qty ) {
				$apply_quantity = $limit_usage_qty - $applied_count < $item->quantity ? $limit_usage_qty - $applied_count : $item->quantity;
				$apply_quantity = max( 0, apply_filters( 'storeengine/coupon_get_apply_quantity', $apply_quantity, $item, $coupon, $this ) );
				$discount       = min( $amount, $item->price / $item->quantity ) * $apply_quantity;
			} else {
				$apply_quantity = apply_filters( 'storeengine/coupon_get_apply_quantity', $item->quantity, $item, $coupon, $this );
				$discount       = $amount * $apply_quantity;
			}

			if ( is_a( $this->object, Cart::class ) && has_filter( 'storeengine/coupon_get_discount_amount' ) ) {
				// Send through the legacy filter, but not as cents.
				$discount = Formatting::add_number_precision( apply_filters( 'storeengine/coupon_get_discount_amount', Formatting::remove_number_precision( $discount ), Formatting::remove_number_precision( $price_to_discount ), $item->object, false, $coupon ) );
			}

			$discount       = min( $discounted_price, $discount );
			$total_discount = $total_discount + $discount;
			$applied_count  = $applied_count + $apply_quantity;

			// Store code and discount amount per item.
			$this->discounts[ strtolower($coupon->get_code()) ][ $item->key ] += $discount;
		}

		return $total_discount;
	}

	/**
	 * Apply fixed cart discount to items.
	 *
	 * @param Coupon $coupon Coupon object. Passed through filters.
	 * @param array $items_to_apply Array of items to apply the coupon to.
	 * @param int $amount Fixed discount amount to apply in cents. Leave blank to pull from coupon.
	 *
	 * @return int Total discounted.
	 */
	protected function apply_coupon_fixed_cart( $coupon, $items_to_apply, $amount = null ) {
		$total_discount = 0;
		$amount         = $amount ? $amount : Formatting::add_number_precision( $coupon->get_amount() );
		$items_to_apply = array_filter( $items_to_apply, array( $this, 'filter_products_with_price' ) );
		$item_count     = array_sum( wp_list_pluck( $items_to_apply, 'quantity' ) );

		if ( ! $item_count ) {
			return $total_discount;
		}

		if ( ! $amount ) {
			// If there is no amount we still send it through so filters are fired.
			$total_discount = $this->apply_coupon_fixed_product( $coupon, $items_to_apply, 0 );
		} else {
			$per_item_discount = absint( $amount / $item_count ); // round it down to the nearest cent.

			if ( $per_item_discount > 0 ) {
				$total_discount = $this->apply_coupon_fixed_product( $coupon, $items_to_apply, $per_item_discount );

				/**
				 * If there is still discount remaining, repeat the process.
				 */
				if ( $total_discount > 0 && $total_discount < $amount ) {
					$total_discount += $this->apply_coupon_fixed_cart( $coupon, $items_to_apply, $amount - $total_discount );
				}
			} elseif ( $amount > 0 ) {
				$total_discount += $this->apply_coupon_remainder( $coupon, $items_to_apply, $amount );
			}
		}

		return $total_discount;
	}

	/**
	 * Apply custom coupon discount to items.
	 *
	 * @param Coupon $coupon Coupon object. Passed through filters.
	 * @param array $items_to_apply Array of items to apply the coupon to.
	 *
	 * @return int Total discounted.
	 */
	protected function apply_coupon_custom( $coupon, $items_to_apply ) {
		$limit_usage_qty = 0;
		$applied_count   = 0;

		if ( null !== $coupon->get_limit_usage_to_x_items() ) {
			$limit_usage_qty = $coupon->get_limit_usage_to_x_items();
		}

		// Apply the coupon to each item.
		foreach ( $items_to_apply as $item ) {
			// Find out how much price is available to discount for the item.
			$discounted_price = $this->get_discounted_price_in_cents( $item );

			// Get the price we actually want to discount, based on settings.
			$price_to_discount = Formatting::remove_number_precision( ( 'yes' === get_option( 'storeengine/calc_discounts_sequentially', 'no' ) ) ? $discounted_price : $item->price );

			// See how many and what price to apply to.
			$apply_quantity = $limit_usage_qty && ( $limit_usage_qty - $applied_count ) < $item->quantity ? $limit_usage_qty - $applied_count : $item->quantity;
			$apply_quantity = max( 0, apply_filters( 'storeengine/coupon_get_apply_quantity', $apply_quantity, $item, $coupon, $this ) );

			// Run coupon calculations.
			$discount      = Formatting::add_number_precision( $coupon->get_discount_amount( $price_to_discount / $item->quantity, $item->object->get_data(), true ) ) * $apply_quantity;
			$discount      = Formatting::round_discount( min( $discounted_price, $discount ), 0 );
			$applied_count = $applied_count + $apply_quantity;

			// Store code and discount amount per item.
			$this->discounts[ strtolower($coupon->get_code()) ][ $item->key ] += $discount;
		}

		// Allow post-processing for custom coupon types (e.g. calculating discrepancy, etc).
		$this->discounts[ strtolower($coupon->get_code()) ] = apply_filters( 'storeengine/coupon_custom_discounts_array', $this->discounts[ strtolower($coupon->get_code()) ], $coupon );

		return array_sum( $this->discounts[ strtolower($coupon->get_code()) ] );
	}

	/**
	 * Deal with remaining fractional discounts by splitting it over items
	 * until the amount is expired, discounting 1 cent at a time.
	 *
	 * @param Coupon $coupon Coupon object if applicable. Passed through filters.
	 * @param array $items_to_apply Array of items to apply the coupon to.
	 * @param int $amount Fixed discount amount to apply.
	 *
	 * @return int Total discounted.
	 */
	protected function apply_coupon_remainder( Coupon $coupon, array $items_to_apply, $amount ) {
		$total_discount = 0;

		foreach ( $items_to_apply as $item ) {
			for ( $i = 0; $i < $item->quantity; $i ++ ) {
				// Find out how much price is available to discount for the item.
				$price_to_discount = $this->get_discounted_price_in_cents( $item );

				// Run coupon calculations.
				$discount = min( $price_to_discount, 1 );

				// Store totals.
				$total_discount += $discount;

				// Store code and discount amount per item.
				$this->discounts[ strtolower($coupon->get_code()) ][ $item->key ] += $discount;

				if ( $total_discount >= $amount ) {
					break 2;
				}
			}
			if ( $total_discount >= $amount ) {
				break;
			}
		}

		return $total_discount;
	}

	/**
	 * Ensure coupon exists or throw exception.
	 *
	 * A coupon is also considered to no longer exist if it has been placed in the trash, even if the trash has not yet
	 * been emptied.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_exists( Coupon $coupon ): bool {
		if ( ! $coupon->get_id() || 'trash' === $coupon->get_status() ) {
			/* translators: %s: coupon code */
			throw new Exception( sprintf( __( 'Coupon "%s" does not exist!', 'storeengine' ), esc_html( $coupon->get_code() ) ), 105 );
		}

		return true;
	}

	/**
	 * Ensure coupon usage limit is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_usage_limit( Coupon $coupon ) {
		// @TODO validate coupon usage limit.
	}

	/**
	 * Ensure coupon user usage limit is valid or throw exception.
	 *
	 * Per user usage limit - check here if user is logged in (against user IDs).
	 * Checked again for emails later on in WC_Cart::check_customer_coupons().
	 *
	 * @param Coupon $coupon Coupon data.
	 * @param int $user_id User ID.
	 *
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_user_usage_limit( $coupon, $user_id = 0 ) {
		// @TODO validate if the coupon is limited to certain user/group.
	}

	/**
	 * Ensure coupon date is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_expiry_date( Coupon $coupon ): bool {
		if ( $coupon->get_date_expires() && apply_filters( 'storeengine/coupon_validate_expiry_date', time() > $coupon->get_date_expires()->getTimestamp(), $coupon, $this ) ) {
			/* translators: %s: coupon code */
			throw new Exception( sprintf( __( 'This coupon (%s) has expired.', 'storeengine' ), $coupon->get_code() ), 107 );
		}

		return true;
	}

	/**
	 * Ensure coupon amount is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_minimum_amount( Coupon $coupon ): bool {
		$subtotal = Formatting::remove_number_precision( $this->get_object_subtotal() );

		if ( $coupon->get_minimum_amount() > 0 && apply_filters( 'storeengine/coupon_validate_minimum_amount', $coupon->get_minimum_amount() > $subtotal, $coupon, $subtotal ) ) {
			/* translators: %s: coupon minimum amount */
			throw new Exception( sprintf( __( 'The minimum spend for this coupon is %s.', 'storeengine' ), Formatting::price( $coupon->get_minimum_amount() ) ), 108 );
		}

		return true;
	}

	/**
	 * Ensure coupon amount is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_maximum_amount( Coupon $coupon ): bool {
		$subtotal = Formatting::remove_number_precision( $this->get_object_subtotal() );

		if ( $coupon->get_maximum_amount() > 0 && apply_filters( 'storeengine/coupon_validate_maximum_amount', $coupon->get_maximum_amount() < $subtotal, $coupon ) ) {
			/* translators: %s: coupon maximum amount */
			throw new Exception( sprintf( __( 'The maximum spend for this coupon is %s.', 'storeengine' ), Formatting::price( $coupon->get_maximum_amount() ) ), 112 );
		}

		return true;
	}

	/**
	 * Ensure coupon is valid for products in the list is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_product_ids( Coupon $coupon ): bool {
		if ( count( $coupon->get_product_ids() ) > 0 ) {
			$valid = false;

			foreach ( $this->get_items_to_validate() as $item ) {
				if ( $item->product_id && ( in_array( $item->product_id, $coupon->get_product_ids(), true ) || in_array( $item->product_parent_id, $coupon->get_product_ids(), true ) ) ) {
					$valid = true;
					break;
				}
			}

			if ( ! $valid ) {
				throw new Exception( __( 'Sorry, this coupon is not applicable to selected products.', 'storeengine' ), 109 );
			}
		}

		return true;
	}

	/**
	 * Ensure coupon is valid for product categories in the list is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_product_categories( Coupon $coupon ): bool {
		if ( count( $coupon->get_product_categories() ) > 0 ) {
			$valid = false;

			foreach ( $this->get_items_to_validate() as $item ) {
				if ( $coupon->get_exclude_sale_items() && $item->product && $item->price && $item->compare_price > 0 ) {
					continue;
				}

				$product_cats = Helper::get_product_cat_ids( $item->product_id );

				if ( $item->product_parent_id ) {
					$product_cats = array_merge( $product_cats, Helper::get_product_cat_ids( $item->product_parent_id ) );
				}

				// If we find an item with a cat in our allowed cat list, the coupon is valid.
				if ( count( array_intersect( $product_cats, $coupon->get_product_categories() ) ) > 0 ) {
					$valid = true;
					break;
				}
			}

			if ( ! $valid ) {
				throw new Exception( __( 'Sorry, this coupon is not applicable to selected products.', 'storeengine' ), 109 );
			}
		}

		return true;
	}

	/**
	 * Ensure coupon is valid for sale items in the list is valid or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_sale_items( $coupon ) {
		if ( $coupon->get_exclude_sale_items() ) {
			$valid = true;

			foreach ( $this->get_items_to_validate() as $item ) {
				if ( $item->product && $item->price & $item->compare_price > 0 ) {
					$valid = false;
					break;
				}
			}

			if ( ! $valid ) {
				throw new Exception( __( 'Sorry, this coupon is not valid for sale items.', 'storeengine' ), 110 );
			}
		}

		return true;
	}

	/**
	 * All exclusion rules must pass at the same time for a product coupon to be valid.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_excluded_items( $coupon ) {
		// @TODO validate excluded product/items.
	}

	/**
	 * Cart discounts cannot be added if non-eligible product is found.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_eligible_items( $coupon ) {
		if ( ! $coupon->is_type( Helper::get_coupon_types() ) ) {
			$this->validate_coupon_sale_items( $coupon );
			$this->validate_coupon_excluded_product_ids( $coupon );
			$this->validate_coupon_excluded_product_categories( $coupon );
		}

		return true;
	}

	/**
	 * Exclude products.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_excluded_product_ids( $coupon ) {
		// Exclude Products.
		if ( count( $coupon->get_excluded_product_ids() ) > 0 ) {
			$products = [];

			foreach ( $this->get_items_to_validate() as $item ) {
				if ( $item->product_id && in_array( $item->product_id, $coupon->get_excluded_product_ids(), true ) || in_array( $item->product_parent_id, $coupon->get_excluded_product_ids(), true ) ) {
					$products[] = $item->object->name;
				}
			}

			if ( ! empty( $products ) ) {
				/* translators: %s: products list */
				throw new Exception( sprintf( __( 'Sorry, this coupon is not applicable to the products: %s.', 'storeengine' ), implode( ', ', $products ) ), 113 );
			}
		}

		return true;
	}

	/**
	 * Exclude categories from product list.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_excluded_product_categories( $coupon ) {
		if ( count( $coupon->get_excluded_product_categories() ) > 0 ) {
			$categories = [];

			foreach ( $this->get_items_to_validate() as $item ) {
				if ( ! $item->product ) {
					continue;
				}

				$product_cats = Helper::get_product_cat_ids( $item->product_id );

				if ( $item->product_parent_id ) {
					$product_cats = array_merge( $product_cats, Helper::get_product_cat_ids( $item->product_parent_id ) );
				}

				$cat_id_list = array_intersect( $product_cats, $coupon->get_excluded_product_categories() );
				if ( count( $cat_id_list ) > 0 ) {
					foreach ( $cat_id_list as $cat_id ) {
						$cat          = get_term( $cat_id, 'product_cat' );
						$categories[] = $cat->name;
					}
				}
			}

			if ( ! empty( $categories ) ) {
				/* translators: %s: categories list */
				throw new Exception( sprintf( __( 'Sorry, this coupon is not applicable to the categories: %s.', 'storeengine' ), implode( ', ', array_unique( $categories ) ) ), 114 );
			}
		}

		return true;
	}

	/**
	 * Ensure coupon is valid for allowed emails or throw exception.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool
	 * @throws Exception Error message.
	 */
	protected function validate_coupon_allowed_emails( $coupon ) {
		$restrictions = $coupon->get_email_restrictions();

		if ( ! is_array( $restrictions ) || empty( $restrictions ) ) {
			return true;
		}

		$user         = wp_get_current_user();
		$check_emails = array( $user->user_email );

		if ( $this->object instanceof Cart ) {
			$check_emails[] = $this->object->get_customer()->get_billing_email();
		} elseif ( $this->object instanceof Order ) {
			$check_emails[] = $this->object->get_billing_email();
		}

		$check_emails = array_unique( array_filter( array_map( 'strtolower', array_map( 'sanitize_email', $check_emails ) ) ) );

		if ( ! self::is_coupon_emails_allowed( $check_emails, $restrictions ) ) {
			// We check for supplied billing email. On shortcode, this will be present for checkout requests.
			$billing_email = $_POST['billing_email'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			if ( ! is_null( $billing_email ) ) {
				/* translators: %s: coupon code */
				$err = sprintf( __( 'Please enter a valid email to use coupon code "%s".', 'storeengine' ), esc_html( $coupon->get_code() ) );
			} else {
				/* translators: %s: coupon code */
				$err = sprintf( __( 'Please enter a valid email at checkout to use coupon code "%s".', 'storeengine' ), esc_html( $coupon->get_code() ) );
			}
			throw new Exception( $err, 102 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return true;
	}

	/**
	 * Get the object subtotal
	 *
	 * @return int
	 */
	protected function get_object_subtotal(): int {
		if ( is_a( $this->object, Cart::class ) ) {
			return Formatting::add_number_precision( $this->object->get_displayed_subtotal() );
		} elseif ( is_a( $this->object, Order::class ) ) {
			$subtotal = Formatting::add_number_precision( $this->object->get_subtotal() );

			if ( $this->object->get_prices_include_tax() ) {
				// Add tax to tax-exclusive subtotal.
				$subtotal = $subtotal + Formatting::add_number_precision( NumberUtil::round( $this->object->get_total_tax(), Formatting::get_price_decimals() ) );
			}

			return $subtotal;
		} else {
			return array_sum( wp_list_pluck( $this->items, 'price' ) );
		}
	}

	/**
	 * Check if a coupon is valid.
	 *
	 * Error Codes:
	 * - 100: Invalid filtered.
	 * - 101: Invalid removed.
	 * - 102: Not yours removed.
	 * - 103: Already applied.
	 * - 104: Individual use only.
	 * - 105: Not exists.
	 * - 106: Usage limit reached.
	 * - 107: Expired.
	 * - 108: Minimum spend limit not met.
	 * - 109: Not applicable.
	 * - 110: Not valid for sale items.
	 * - 111: Missing coupon code.
	 * - 112: Maximum spend limit met.
	 * - 113: Excluded products.
	 * - 114: Excluded categories.
	 *
	 * @param Coupon $coupon Coupon data.
	 *
	 * @return bool|WP_Error
	 */
	public function is_coupon_valid( Coupon $coupon ) {
		try {
			$this->validate_coupon_exists( $coupon );
			$this->validate_coupon_usage_limit( $coupon );
			$this->validate_coupon_user_usage_limit( $coupon );
			$this->validate_coupon_expiry_date( $coupon );
			$this->validate_coupon_minimum_amount( $coupon );
			$this->validate_coupon_maximum_amount( $coupon );
			$this->validate_coupon_product_ids( $coupon );
			$this->validate_coupon_product_categories( $coupon );
			$this->validate_coupon_excluded_items( $coupon );
			$this->validate_coupon_eligible_items( $coupon );
			$this->validate_coupon_allowed_emails( $coupon );

			if ( ! apply_filters( 'storeengine/coupon_is_valid', true, $coupon, $this ) ) {
				throw new Exception( __( 'Coupon is not valid.', 'storeengine' ), 100 );
			}
		} catch ( StoreEngineException $e ) {
			return $e->get_wp_error();
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_coupon', $e->getMessage(), [ 'status' => 400 ] );
		}

		return true;
	}

	/**
	 * Checks if the given email address(es) matches the ones specified on the coupon.
	 *
	 * @param array $check_emails Array of customer email addresses.
	 * @param array $restrictions Array of allowed email addresses.
	 *
	 * @return bool
	 */
	public static function is_coupon_emails_allowed( array $check_emails, array $restrictions ): bool {
		foreach ( $check_emails as $check_email ) {
			// With a direct match we return true.
			if ( in_array( $check_email, $restrictions, true ) ) {
				return true;
			}

			// Go through the allowed emails and return true if the email matches a wildcard.
			foreach ( $restrictions as $restriction ) {
				// Convert to PHP-regex syntax.
				$regex = '/^' . str_replace( '*', '(.+)?', $restriction ) . '$/';
				preg_match( $regex, $check_email, $match );
				if ( ! empty( $match ) ) {
					return true;
				}
			}
		}

		// No matches, this one isn't allowed.
		return false;
	}
}

// End of file discounts.php
