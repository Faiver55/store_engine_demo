<?php
/**
 * The Cart
 * StoreEngine cart.
 *
 * @package StoreEngine\Classes
 * @since 0.0.1
 * @var 1.5.0
 */

namespace StoreEngine\Classes;

use stdClass;
use StoreEngine;
use StoreEngine\Classes\Cart\CartTotals;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Models\Price;
use StoreEngine\Shipping\Shipping;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\ShippingUtils;
use StoreEngine\Utils\TaxUtil;
use WP_Error;

/**
 * @see \WC_Cart
 */
#[\AllowDynamicProperties]
class Cart {

	protected int $cart_id;
	protected int $cart_user_id = 0;
	protected string $cart_hash;

	/**
	 * @var CartItem[]
	 */
	public array $cart_items = [];

	protected ?CartFees $fees = null;

	protected array $applied_coupons = [];

	protected array $coupon_discount_totals = [];

	protected array $coupon_discount_tax_totals = [];

	/**
	 * This stores the chosen shipping methods for the cart item packages.
	 *
	 * @var array
	 */
	protected array $shipping_methods = [];

	protected array $choosen_shipping_methods = [];

	/**
	 * Whether the shipping totals have been calculated. This will only return true if shipping was calculated, not if
	 * shipping is disabled or if there are no cart contents.
	 *
	 * @var bool
	 */
	protected bool $has_calculated_shipping = false;

	/**
	 * Total defaults used to reset.
	 *
	 * @var array
	 */
	protected array $default_totals = [
		'subtotal'            => 0,
		'subtotal_tax'        => 0,
		'shipping_total'      => 0,
		'shipping_tax'        => 0,
		'shipping_taxes'      => [],
		'discount_total'      => 0,
		'discount_tax'        => 0,
		'cart_contents_total' => 0,
		'cart_contents_tax'   => 0,
		'cart_contents_taxes' => [],
		'fee_total'           => 0,
		'fee_tax'             => 0,
		'fee_taxes'           => [],
		'total'               => 0,
		'total_tax'           => 0,
	];

	protected array $totals = [];

	protected array $meta = [];

	protected bool $is_dirty = false;

	protected static ?Cart $instance = null;

	/**
	 * Create and return instance.
	 *
	 * @return self
	 */
	public static function init(): Cart {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Cookie events - cart cookies need to be set before headers are sent.
		add_action( 'storeengine/cart/add_to_cart', [ $this, 'maybe_set_cart_cookies' ] );
		add_action( 'wp', [ $this, 'maybe_set_cart_cookies' ], 99 );
		add_action( 'shutdown', [ $this, 'maybe_set_cart_cookies' ], 0 );
		add_action( 'wp_logout', [ $this, 'remove_cart_cookies' ] );

		$this->cart_hash    = Helper::get_cart_hash_from_cookie();
		$this->cart_user_id = get_current_user_id();
		$this->fees         = new CartFees();

		add_action( 'storeengine/cart/loaded', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/cart/add_to_cart', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/cart/item_update_quantity', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/applied_coupon', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/removed_coupon', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/cart/item_removed', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/cart/item_restored', [ $this, 'calculate_cart_totals' ], 20, 0 );
		add_action( 'storeengine/update_checkout', [ $this, 'calculate_cart_totals' ], 20, 0 );

		add_action( 'wp_loaded', [ __CLASS__, 'handle_remove_cart_item_request' ], 20 );
		add_action( 'wp_loaded', [ __CLASS__, 'handle_remove_coupon_request' ], 20 );

		add_action( 'storeengine/cart/check_items', [ $this, 'validate_items' ] );
		add_action( 'shutdown', [ $this, 'store_on_database' ], 100 );

		// Load cart from db.
		$this->load_cart();
	}

	private function load_cart() {
		if ( ! $this->cart_hash && ! $this->cart_user_id ) {
			return;
		}

		$carts = \StoreEngine\Models\Cart::get_carts_by_hash_or_user_id( $this->cart_hash, $this->cart_user_id );

		if ( empty( $carts ) ) {
			return;
		}

		do_action_ref_array( 'storeengine/cart/loading_from_session', [ &$this, &$carts ] );

		$cart = reset( $carts );

		if ( ! $this->cart_hash ) {
			$this->cart_hash = $cart->cart_hash;
		}

		$this->cart_id = $cart->cart_id;
		$cart_data     = maybe_unserialize( $cart->cart_data );

		if ( ! is_array( $cart_data ) ) {
			// fallback for old cart.
			$cart_data = json_decode( $cart_data, true );
		}

		$has_multiple = count( $carts ) > 1;
		/** @var CartItem[] $cart_items */
		$cart_items = [];
		$cart_fees  = [];
		$coupons    = [];
		$meta       = [];

		if ( ! empty( $cart_data ) ) {
			$cart_items = array_filter( $cart_data['items'] ?? [] );
			$cart_fees  = array_filter( $cart_data['fees'] ?? [] );
			$coupons    = $cart_data['coupons'];
			$meta       = $cart_data['meta'];

			if ( ! empty( $cart_items ) && is_array( reset( $cart_items ) ) ) {
				// Backward compatibility.
				$cart_items = array_map( fn( $item ) => new CartItem( $item['key'], $item ), $cart_items );
			}
		}

		if ( $has_multiple ) {
			$carts          = $this->merge_carts( $carts );
			$coupons        = $carts['coupons'];
			$cart_items     = $carts['items'];
			$cart_fees      = $carts['fees'];
			$meta           = $carts['meta'];
			$this->is_dirty = true;
		}

		if ( empty( $cart_items ) ) {
			return;
		}

		global $wpdb;
		foreach ( $cart_items as $key => $item ) {
			$validate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}storeengine_product_price WHERE id = %d AND product_id = %d;",
					$item->price_id, $item->product_id
				)
			);

			if ( ! $validate ) {
				continue;
			}

			$this->cart_items[ $key ] = $item;
		}

		if ( $cart_items !== $this->cart_items ) {
			// @TODO trigger notification for the user that invalid item is removed from cart.
			$this->is_dirty = true;
		}

		$coupons               = array_unique( $coupons );
		$this->applied_coupons = array_combine( $coupons, array_map( fn( $coupon ) => new Coupon( $coupon ), $coupons ) );
		$this->meta            = array_filter( $meta );
		$this->fees->set_fees( $cart_fees );

		do_action_ref_array( 'storeengine/cart/loaded_from_session', [ &$this ] );

		$has_subscription = $this->is_cart_consist_subscription_product();
		$addon_active     = Helper::get_addon_active_status( 'subscription' );

		if ( $has_subscription && ! $addon_active ) {
			$this->clear_cart();
		}

		/**
		 * Fires after cart loaded.
		 */
		do_action_ref_array( 'storeengine/cart/loaded', [ &$this ] );
	}

	/**
	 * Merge multiple carts.
	 *
	 * @param array $carts
	 *
	 * @return array {
	 * @var CartItem $items
	 * @var array $fees
	 * @var array $coupons
	 * @var array $meta
	 * }
	 */
	private function merge_carts( array $carts ): array {
		$merged_items = [];
		$cart_coupons = [];
		$merged_fees  = [];
		$cart_meta    = [];

		foreach ( $carts as $cart ) {
			$cart_data = maybe_unserialize( $cart->cart_data );

			if ( empty( $cart_data['items'] ) ) {
				// Empty cart data!
				continue;
			}

			$cart_items       = array_values( array_filter( $cart_data['items'] ) );
			$cart_coupons     = array_merge( $cart_coupons, $cart_data['coupons'] ?? [] );
			$merged_fees      = array_merge( $merged_fees, $cart_data['fees'] ?? [] );
			$cart_meta        = array_merge( $cart_meta, $cart_data['meta'] ?? [] );
			$has_subscription = false;

			// Check items.
			foreach ( $cart_items as $cart_item ) {
				if ( is_array( $cart_item ) ) {
					// Backward compatibility.
					$cart_item = new CartItem( $cart_item['key'], $cart_item );
				}

				$key = $cart_item->key;

				if ( 'subscription' === $cart_item->price_type ) {
					if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
						continue;
					}

					if ( empty( $merged_items ) ) {
						$merged_items[ $key ] = $cart_item;
						$has_subscription     = true;
						break;
					}
					continue;
				}

				if ( ! isset( $merged_items[ $key ] ) ) {
					$has_same_product_id = array_filter( $merged_items, function ( $item ) use ( $cart_item ) {
						return $item->product_id === $cart_item->product_id &&
							   $item->price_id === $cart_item->price_id &&
							   $item->get_price() === $cart_item->get_price();
					} );

					if ( empty( $has_same_product_id ) ) {
						$merged_items[ $key ] = $cart_item;
					}
				} else {
					$merged_items[ $key ]->quantity += $cart_item->quantity;
					// Add to sub-total.
					$merged_items[ $key ]->line_subtotal = (float) ( $merged_items[ $cart_item->price_id ]->price * $merged_items[ $cart_item->price_id ]->quantity );
				}
			}

			if ( $has_subscription ) {
				break;
			}
		}

		$rest_of_carts     = array_slice( $carts, 1 );
		$rest_of_carts_ids = wp_list_pluck( $rest_of_carts, 'cart_id' );

		if ( ! empty( $rest_of_carts_ids ) ) {
			// @TODO Use wp list pluck -> $rest_of_carts, 'cart_hash';
			//       use cache delete multi with the cart hashes
			// @TODO use object cache for cart data.

			// Delete other rows.
			global $wpdb;
			// Prepare placeholder for cart ids.
			$placeholders = implode( ',', array_fill( 0, count( $rest_of_carts_ids ), ' %d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_cart WHERE cart_id IN ($placeholders)", $rest_of_carts_ids ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above.
		}

		return [
			'items'   => array_values( $merged_items ),
			'fees'    => array_unique( $merged_fees ),
			'coupons' => array_unique( $cart_coupons ),
			'meta'    => array_unique( $cart_meta ),
		];
	}

	public function validate_items() {
		$this->validate_cart_items();
		$this->validate_coupon();
	}

	private function validate_cart_items(): void {
		$cart_items        = $this->cart_items;
		$price_ids         = wp_list_pluck( $this->cart_items, 'price_id' );
		$product_prices    = Price::get_pricing_with_products( $price_ids );
		$missing_price_ids = array_diff( $price_ids, array_keys( $product_prices ) );

		// Rebuild items.
		$new_cart_items = [];
		foreach ( $this->cart_items as $cart_item ) {
			if ( in_array( $cart_item->price_id, $missing_price_ids ) || ! isset( $product_prices[ $cart_item->price_id ] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				continue;
			}

			$product_price = $product_prices[ $cart_item->price_id ];
			$price         = (float) $product_price->price;

			$product_type = get_post_meta( $product_price->product_id, '_storeengine_product_type', true );
			if ( 'variable' === $product_type && ( ! isset( $cart_item->variation_id ) || 0 === $cart_item->variation_id ) ) {
				continue;
			}


			if ( isset( $cart_item->variation_id ) && $cart_item->variation_id > 0 ) {
				$variation = Helper::get_product_variation( $cart_item->variation_id );
				if ( ! $variation ) {
					continue;
				}
				$price += (float) $variation->get_price();
			}

			$new_cart_items[ $cart_item->key ] = $cart_item->set_data( array_merge(
				[
					'name'          => $product_price->post_title,
					'price_name'    => $product_price->price_name,
					'price'         => $price,
					'compare_price' => (float) $product_price->compare_price,
					'line_subtotal' => (float) ( $cart_item->quantity * $product_price->price ),
				],
				$product_price->settings
			) );
		}

		$this->cart_items         = $new_cart_items;
		$diff_with_new_cart_items = Helper::array_diff_recursive( $new_cart_items, $cart_items );
		$this->is_dirty           = count( $diff_with_new_cart_items ) > 0 || ( count( $new_cart_items ) !== count( $cart_items ) );
	}

	private function validate_coupon() {
		foreach ( $this->applied_coupons as $coupon ) {
			if ( is_wp_error( $coupon->validate_coupon() ) ) {
				unset( $this->applied_coupons[ strtolower( $coupon->get_code() ) ] );
				$this->is_dirty = true;
			}
		}
	}

	public function clear_cart() {
		// Trigger db-save.
		$this->is_dirty = true;

		// Reset values.
		$this->cart_items = [];
		$this->fees->remove_all_fees();
		$this->shipping_methods           = [];
		$this->coupon_discount_totals     = [];
		$this->coupon_discount_tax_totals = [];
		$this->applied_coupons            = [];
		$this->totals                     = $this->default_totals;
		$this->meta                       = [];
	}

	/**
	 * Remove coupons from the cart of a defined type. Type 1 is before tax, type 2 is after tax.
	 */
	public function remove_coupons() {
		$this->set_coupon_discount_totals();
		$this->set_coupon_discount_tax_totals();
		$this->set_applied_coupons();
		$this->is_dirty = true;
	}

	/**
	 * Generate a unique ID for the cart item being added.
	 *
	 * @param int $price_id - id of the product the key is being generated for.
	 * @param int $product_id - id of the product the key is being generated for.
	 * @param int|float $amount - id of the product the key is being generated for.
	 * @param int $variation_id of the product the key is being generated for.
	 * @param array $variation data for the cart item.
	 * @param array $item_data other cart item data passed which affects this items uniqueness in the cart.
	 *
	 * @return string cart item key
	 */
	public function generate_cart_item_id( int $price_id, int $product_id, $amount = 0, int $variation_id = 0, array $variation = [], array $item_data = [] ): string {
		$id_parts = [ $price_id, $product_id, $amount ];

		if ( $variation_id ) {
			$id_parts[] = $variation_id;
		}

		if ( ! empty( $variation ) ) {
			$variation_key = '';
			foreach ( $variation as $key => $value ) {
				$variation_key .= trim( $key ) . trim( $value );
			}

			$id_parts[] = $variation_key;
		}

		if ( ! empty( $item_data ) ) {
			$item_data_key = '';
			foreach ( $item_data as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = http_build_query( $value );
				}

				$item_data_key .= trim( $key ) . trim( $value );
			}

			$id_parts[] = $item_data_key;
		}

		$cart_item_id = md5( implode( '_', $id_parts ) );

		/**
		 * Filter cart item id after generation.
		 *
		 * @param string $cart_item_id Cart item id.
		 * @param int $product_id Product id.
		 * @param int $variation_id Variation id.
		 * @param array $item_data Item data.
		 */
		return apply_filters( 'storeengine/cart/item_id', $cart_item_id, $product_id, $variation_id, $variation, $item_data );
	}

	/**
	 * @param int $price_id
	 * @param int $quantity
	 * @param int $variation_id
	 * @param array $variation
	 * @param array $item_data
	 *
	 * @return true|WP_Error
	 */
	public function add_product_to_cart( int $price_id, int $quantity = 1, int $variation_id = 0, array $variation = [], array $item_data = [] ) {
		try {
			$quantity   = absint( $quantity );
			$price      = new \StoreEngine\Classes\Price( $price_id );
			$price_id   = $price->get_id();
			$product_id = $price->get_product_id();

			if ( ! $quantity ) {
				return new WP_Error( 'invalid-qty', __( 'Invalid quantity!', 'storeengine' ), [ 'status' => 400 ] );
			}

			if ( ! $price->get_id() ) {
				return new WP_Error( 'invalid-price-id', __( 'Invalid price id!', 'storeengine' ), [ 'status' => 400 ] );
			}

			if ( 'trash' === get_post_status( $price->get_product_id() ) ) {
				return new WP_Error( 'product-in-trash', __( 'This product is no longer available!', 'storeengine' ), [ 'status' => 422 ] );
			}

			if ( $price->is_subscription() && ! Helper::get_addon_active_status( 'subscription' ) ) {
				return new WP_Error( 'subscription-addon-not-enabled', __( 'Subscription addon is not enable.', 'storeengine' ), [ 'status' => 400 ] );
			}

			if ( $price->is_subscription() || ! empty( $this->meta['has_subscription'] ) ) {
				// Clear all cart item for current subscription product, or previously has subscription.
				// We don't support multiple subscription or mixed checkout.
				$this->clear_cart();
			}

			$this->meta['has_subscription'] = $price->is_subscription();
			$this->meta['has_trial']        = $price->is_trial();

			$amount = $price->get_price();

			if ( 0 < $variation_id ) {
				$variation_obj = Helper::get_product_variation( $variation_id );

				if ( ! $variation_obj ) {
					return new WP_Error( 'invalid-product-variation', __( 'Invalid variation selected!', 'storeengine' ) );
				}

				$amount = $price->get_price() + (float) $variation_obj->get_price();
			}

			/**
			 * Filter cart item data during add to cart.
			 *
			 * @param array $item_data Item data.
			 * @param int $price_id Price id.
			 * @param int $product_id Product id.
			 * @param int $variation_id Variation id.
			 * @param int $quantity Quantity.
			 */
			$item_data = (array) apply_filters( 'storeengine/cart/add_item_data', $item_data, $price_id, $product_id, $variation_id, $quantity );

			// Generate a ID based on product ID, variation ID, variation data, and other cart item data.
			// Use price id to allow multi price per product in cart.
			// Use amount to allow custom price based same product in cart.
			$cart_id = $this->generate_cart_item_id(
				0, //$price->get_id(),
				$price->get_product_id(),
				0, //$amount,
				$variation_id,
				$variation,
				$item_data
			);

			// Find the cart item key in the existing cart.
			$cart_item_key = $this->find_product_in_cart( $cart_id );

			if ( $cart_item_key ) {
				// Remove this condition to allow multi price per product in cart.
				if ( $this->cart_items[ $cart_item_key ]->price_id === $price->get_id() ) {
					$total_qty = (int) $this->cart_items[ $cart_item_key ]->quantity + $quantity;
					// Update qty & price.
					$this->cart_items[ $cart_item_key ]->quantity      = $total_qty;
					$this->cart_items[ $cart_item_key ]->line_subtotal = (float) ( $price->get_price() * $total_qty );

					// Trigger database update.
					$this->is_dirty = true;

					return true;
				} else {
					// Also, remove this to allow multi price per product in cart.
					$this->remove_cart_item( $cart_item_key );
				}
			}

			$cart_item_data = array_merge(
				[
					'key'           => $cart_id,
					'product_id'    => $price->get_product_id(),
					'variation_id'  => $variation_id,
					'variation'     => $variation,
					'price_id'      => $price->get_id(),
					'name'          => $price->get_post_title(),
					'price_name'    => $price->get_price_name(),
					'price_type'    => $price->get_price_type(),
					'price'         => $amount,
					'compare_price' => $price->get_compare_price(),
					'quantity'      => $quantity,
					'line_subtotal' => $amount * $quantity,
					'item_data'     => $item_data,
				],
				$price->get_settings(),
			);

			$cart_item = new CartItem( $cart_id, $cart_item_data );

			/**
			 * Filter cart item data after add to cart.
			 *
			 * @param CartItem $cart_item_data Cart item data.
			 * @param string $cart_id Cart item id.
			 */
			$this->cart_items[ $cart_id ] = apply_filters( 'storeengine/cart/add_item', $cart_item, $cart_id );

			if ( ! $price->is_subscription() && $price->get_setup_fee() ) {
				$feeId = sanitize_title( sprintf( '%s-%s-%s', $product_id, $price_id, $price->get_setup_fee_name() ) );
				$this->add_fee( $price->get_setup_fee_name(), $price->get_setup_fee_price(), $feeId );
			}

			// Trigger db update before user tapping into the data.
			$this->is_dirty = true;

			/**
			 * Fires after adding product on Cart.
			 *
			 * @param string $cart_id Cart id.
			 * @param int $price_id Price id.
			 * @param int $product_id Product id.
			 * @param int $variation_id Variation id.
			 * @param int $quantity Quantity.
			 * @param array $item_data Item data.
			 */
			do_action( 'storeengine/cart/add_to_cart', $cart_id, $price_id, $product_id, $variation_id, $quantity, $item_data );

			return true;
		} catch ( StoreEngineException $e ) {
			return $e->get_wp_error();
		}
	}

	public function update_quantity( string $item_key, int $quantity = 1 ) {
		if ( 0 === $quantity || $quantity < 0 ) {
			// If we're setting qty to 0 we're removing the item from the cart.
			if ( $this->remove_cart_item( $item_key ) ) {
				return true;
			}
		}

		if ( ! $this->item_exists( $item_key ) ) {
			return new WP_Error( 'item-not-found', __( 'Item not found!', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( 'subscription' === $this->cart_items[ $item_key ]->price_type ) {
			return new WP_Error( 'item-can-not-update', __( 'Can not update quantity!', 'storeengine' ), [ 'status' => 404 ] );
		}

		// Update qty.
		$this->cart_items[ $item_key ]->quantity = $quantity;

		$this->is_dirty = true;

		/**
		 * Fires after updating cart item quantity.
		 *
		 * @param string $item_key Cart item key.
		 * @param int $quantity Cart item quantity.
		 * @param Cart $this Cart instance.
		 */
		do_action( 'storeengine/cart/item_update_quantity', $item_key, $quantity, $this );

		return true;
	}

	/**
	 * Returns an array of cart line items.
	 *
	 * @return CartItem[]
	 */
	public function get_cart_items(): array {
		return $this->cart_items;
	}

	public function has_items(): bool {
		return ! empty( array_filter( $this->cart_items ) );
	}

	public function item_exists( string $item_key ): bool {
		return isset( $this->cart_items[ $item_key ] );
	}

	public function get_cart_item( string $item_key ): ?CartItem {
		return $this->cart_items[ $item_key ] ?? null;
	}

	public function remove_cart_item( string $item_key ): bool {
		if ( isset( $this->cart_items[ $item_key ] ) ) {
			// Trigger db update.
			$this->is_dirty = true;

			/**
			 * Fires before removing cart item.
			 *
			 * @param string $item_key Cart item key.
			 * @param Cart $this Cart instance.
			 */
			do_action_ref_array( 'storeengine/cart/remove_item', [ &$this, $item_key ] );

			$cart_item = $this->cart_items[ $item_key ];

			if ( isset( $cart_item->setup_fee ) && $cart_item->setup_fee ) {
				$this->fees->remove_fee( sanitize_title( sprintf( '%s-%s-%s', $cart_item->product_id, $cart_item->price_id, $cart_item->setup_fee_name ) ) );
			}


			unset( $this->cart_items[ $item_key ] );

			$this->is_cart_consist_subscription_product();

			/**
			 * Fires after removing cart item.
			 *
			 * @param string $item_key Cart item key.
			 * @param Cart $this Cart instance.
			 */
			do_action_ref_array( 'storeengine/cart/item_removed', [ &$this, $item_key ] );

			return true;
		}

		return false;
	}

	public function get_items_count(): int {
		return count( $this->cart_items );
	}

	public function get_count(): int {
		$count = 0;
		foreach ( $this->cart_items as $cart_item ) {
			$count += $cart_item->quantity;
		}

		return $count;
	}

	public function get_cart_item_by_product( int $product_id, int $price_id = null ): ?CartItem {
		foreach ( $this->cart_items as $cart_item ) {
			if ( $product_id && $price_id ) {
				if ( (int) $cart_item->product_id === $product_id && (int) $cart_item->price_id === $price_id ) {
					return $cart_item;
				}

				continue;
			}

			if ( (int) $cart_item->product_id === $product_id ) {
				return $cart_item;
			}
		}

		return null;
	}

	/**
	 * @param int $product_id
	 *
	 * @return CartItem[]
	 */
	public function get_cart_items_by_product( int $product_id ): array {
		return array_filter( $this->cart_items, function ( $cart_item ) use ( $product_id ) {
			return $cart_item->product_id === $product_id;
		} );
	}

	/**
	 * Trigger an action so 3rd parties can add custom fees.
	 */
	public function calculate_fees() {
		do_action( 'storeengine/cart/calculate_fees', $this );
	}

	/**
	 * Return reference to fees API.
	 *
	 * @return CartFees
	 */
	public function fees_api() {
		return $this->fees;
	}

	/**
	 * Add additional fee to the cart.
	 *
	 * This method should be called on a callback attached to the
	 * woocommerce_cart_calculate_fees action during cart/checkout. Fees do not
	 * persist.
	 *
	 * @param string $name Unique name for the fee. Multiple fees of the same name cannot be added.
	 * @param string|int|float $amount Fee amount (do not enter negative amounts).
	 * @param string $id Unique id for the fee. Multiple fees of the id name cannot be added.
	 * @param bool $taxable Is the fee taxable? (default: false).
	 * @param string $tax_class The tax class for the fee if taxable. A blank string is standard tax class. (default: '').
	 *
	 * @uses WC_Cart_Fees::add_fee
	 */
	public function add_fee( string $name, $amount, string $id = '', bool $taxable = true, string $tax_class = '' ) {
		$this->fees_api()->add_fee( [
			'name'      => $name,
			'id'        => $id,
			'amount'    => (float) $amount,
			'taxable'   => $taxable,
			'tax_class' => $tax_class,
		] );
	}

	/**
	 * Return all added fees from the Fees API.
	 *
	 * @return array
	 * @uses CartFees::get_fees
	 */
	public function get_fees(): array {
		return $this->fees_api()->get_fees();
	}


	/**
	 * Gets the sub-total (after calculation).
	 *
	 * @param bool $compound whether to include compound taxes.
	 *
	 * @return string formatted price
	 */
	public function get_cart_subtotal( bool $compound = false ): string {
		/**
		 * If the cart has compound tax, we want to show the subtotal as cart + shipping + non-compound taxes (after discount).
		 */
		if ( $compound ) {
			$cart_subtotal = Formatting::price( $this->get_cart_contents_total() + $this->get_shipping_total() + $this->get_taxes_total( false, false ) );
		} elseif ( $this->display_prices_including_tax() ) {
			$cart_subtotal = Formatting::price( $this->get_subtotal() + $this->get_subtotal_tax() );

			if ( $this->get_subtotal_tax() > 0 && ! TaxUtil::prices_include_tax() ) {
				$cart_subtotal .= ' <small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>';
			}
		} else {
			$cart_subtotal = Formatting::price( $this->get_subtotal() );
			if ( $this->get_subtotal_tax() > 0 && TaxUtil::prices_include_tax() ) {
				$cart_subtotal .= ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
			}
		}

		/**
		 * Filter cart subtotal.
		 *
		 * @param string $cart_subtotal Cart subtotal.
		 * @param int $compound Compound.
		 * @param Cart $this Cart instance.
		 */
		return apply_filters( 'storeengine/cart/subtotal', $cart_subtotal, $compound, $this );
	}

	/**
	 * Get the product row price per item.
	 *
	 * @param float $price Price.
	 * @param ?int $priceId Price ID.
	 * @param ?int $product Product ID.
	 *
	 * @return string formatted price
	 */
	public function get_product_price( float $price, ?int $priceId = null, ?int $product = null ): string {
		$price = new \StoreEngine\Classes\Price( $priceId );
		if ( $this->display_prices_including_tax() ) {
			$product_price = Formatting::get_price_including_tax( $price->get_price(), $priceId, $product );
		} else {
			$product_price = Formatting::get_price_excluding_tax( $price->get_price(), $priceId, $product );
		}

		$formatted_price = Formatting::price( $product_price );

		/**
		 * Filter formatted product price from price(float).
		 *
		 * @param string $formatted_price Formatted price.
		 * @param \StoreEngine\Classes\Price $price Price.
		 * @param ?int $priceId Price ID.
		 * @param ?int $product Product ID.
		 */
		return apply_filters( 'storeengine/cart/product_price', $formatted_price, $price, $priceId, $product );
	}

	/**
	 * Get the product row subtotal.
	 *
	 * Gets the tax etc. to avoid rounding issues.
	 *
	 * When on the checkout (review order), this will get the subtotal based on the customer's tax rate rather than the base rate.
	 *
	 * @param float $price Product object.
	 * @param int $price_id
	 * @param int $product Product object.
	 * @param int $quantity Quantity being purchased.
	 * @param bool $taxable
	 *
	 * @return string formatted price
	 */
	public function get_product_subtotal( float $price, int $price_id, int $product, int $quantity, bool $taxable = true ): string {
		$price = new \StoreEngine\Classes\Price( $price_id );

		if ( TaxUtil::is_tax_enabled() && $taxable ) {
			if ( $this->display_prices_including_tax() ) {
				$product_subtotal = Formatting::price(
					Formatting::get_price_including_tax(
						$price->get_price(),
						null,
						$product,
						[
							'qty'     => $quantity,
							'taxable' => $taxable,
						]
					)
				);

				if ( ! TaxUtil::prices_include_tax() && $this->get_subtotal_tax() > 0 ) {
					$product_subtotal .= ' <small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>';
				}
			} else {
				$product_subtotal = Formatting::price(
					Formatting::get_price_excluding_tax(
						$price->get_price(),
						null,
						$product,
						[
							'qty'     => $quantity,
							'taxable' => $taxable,
						]
					)
				);

				if ( TaxUtil::prices_include_tax() && $this->get_subtotal_tax() > 0 ) {
					$product_subtotal .= ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
				}
			}
		} else {
			$row_price        = $price->get_price() * $quantity;
			$product_subtotal = Formatting::price( $row_price );
		}

		/**
		 * Filter formatted product subtotal from price(float).
		 *
		 * @param String $product_subtotal Formatted product subtotal.
		 * @param int $product Product ID.
		 * @param int $quantity Quantity.
		 * @param Cart $this Cart instance.
		 */
		return (string) apply_filters( 'storeengine/cart/product_subtotal', $product_subtotal, $price, $quantity, $this );
	}

	public function get_total_discount() {
		$total = 0;
		foreach ( $this->get_coupons() as $coupon ) {
			$total += $this->get_coupon_discount_amount( $coupon->get_code(), $this->display_prices_including_tax() );
		}

		return $total;
	}

	/**
	 * Get the discount amount for a used coupon.
	 *
	 * @param string $code coupon code.
	 * @param bool $ex_tax inc or ex tax.
	 *
	 * @return float discount amount
	 */
	public function get_coupon_discount_amount( string $code, bool $ex_tax = true ): float {
		$discount_amount = $this->coupon_discount_totals[ $code ] ?? 0;

		if ( ! $ex_tax ) {
			$discount_amount += $this->get_coupon_discount_tax_amount( $code );
		}

		return Formatting::round_discount( $discount_amount, Formatting::get_price_decimals() );
	}

	/**
	 * Get the discount tax amount for a used coupon (for tax inclusive prices).
	 *
	 * @param string $code coupon code.
	 *
	 * @return float discount amount
	 */
	public function get_coupon_discount_tax_amount( string $code ): float {
		return Formatting::round_discount( $this->coupon_discount_tax_totals[ $code ] ?? 0, Formatting::get_price_decimals() );
	}

	public function apply_coupon( $coupon_code ) {
		$coupon = new Coupon( $coupon_code );

		$is_valid = $coupon->validate_coupon();
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		$this->applied_coupons[ strtolower( $coupon->code ) ] = $coupon;
		$this->is_dirty                                       = true;

		/**
		 * Fires after applied coupon on cart.
		 */
		do_action( 'storeengine/applied_coupon' );

		return true;
	}

	public function remove_coupon( $coupon_code ) {
		$coupon_code = strtolower( $coupon_code );
		if ( isset( $this->applied_coupons[ $coupon_code ] ) ) {
			unset( $this->applied_coupons[ $coupon_code ] );
			$this->is_dirty = true;

			/**
			 * Fires after removing coupon from cart.
			 */
			do_action( 'storeengine/removed_coupon' );

			return true;
		}

		return new WP_Error( 'not_found_coupon', __( 'Coupon not found!', 'storeengine' ) );
	}

	/**
	 * @return Coupon[]
	 */
	public function get_coupons(): array {
		return $this->applied_coupons;
	}

	public function is_coupon_applied( $coupon_code ): bool {
		return isset( $this->applied_coupons[ strtolower( $coupon_code ) ] );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function calculate_cart_totals() {
		$this->reset_totals();
		if ( ! count( $this->cart_items ) ) {
			return;
		}

		/**
		 * Fires before calculating cart totals.
		 *
		 * @param Cart $this Cart instance.
		 */
		do_action( 'storeengine/cart/before_calculate_totals', $this );

		new CartTotals( $this );

		/**
		 * Fires after calculating cart totals.
		 *
		 * @param Cart $this Cart instance.
		 */
		do_action( 'storeengine/cart/after_calculate_totals', $this );
	}

	/**
	 * Looks at the totals to see if payment is actually required.
	 *
	 * @return bool
	 */
	public function needs_payment(): bool {
		$needs_payment = 0 < $this->get_total( 'edit' );

		/**
		 * Filter cart needs payment.
		 *
		 * @param bool $needs_payment Payment needed or not.
		 * @param Cart $this Cart instance.
		 */
		return apply_filters( 'storeengine/cart/needs_payment', $needs_payment, $this );
	}

	/**
	 * Get selected shipping methods after calculation.
	 *
	 * @return array
	 */
	public function get_shipping_methods(): array {
		return $this->shipping_methods;
	}

	/**
	 * Whether the shipping totals have been calculated.
	 *
	 * @return bool
	 */
	public function has_calculated_shipping(): bool {
		return $this->has_calculated_shipping;
	}

	/**
	 * Looks through the cart to see if shipping is actually required.
	 *
	 * @return bool whether the cart needs shipping
	 */
	public function needs_shipping(): bool {
		if ( ! ShippingUtils::is_shipping_enabled() || 0 === ShippingUtils::get_shipping_methods_count( true ) ) {
			return false;
		}

		$needs_shipping = false;

		foreach ( $this->get_cart_items() as $cart_item ) {
			$product = Helper::get_product( $cart_item->product_id );
			if ( $product && $product->needs_shipping() ) {
				$needs_shipping = true;
				break;
			}
		}

		return apply_filters( 'storeengine/cart/needs_shipping', $needs_shipping );
	}

	/**
	 * Sees if the customer has entered enough data to calculate shipping.
	 *
	 * @return bool
	 */
	public function show_shipping(): bool {
		// If there are no shipping methods or no cart contents, no need to calculate shipping.
		if ( ! ShippingUtils::is_shipping_enabled() || 0 === ShippingUtils::get_shipping_methods_count( true ) || ! $this->get_count() ) {
			return false;
		}

		if ( Helper::get_settings( 'storeengine/shipping/cost_requires_address', true ) ) {
			$customer = $this->get_customer();

			if ( ! $customer instanceof Customer || ! $customer->has_full_shipping_address() ) {
				return false;
			}
		}

		/**
		 * Filter to allow plugins to prevent shipping calculations.
		 *
		 * @param bool $ready Whether the cart is ready to calculate shipping.
		 */
		return apply_filters( 'storeengine/cart/ready_to_calc_shipping', true );
	}

	public function is_product_in_cart( $product_id, $price_id = 0 ) {
		foreach ( $this->cart_items as $item_key => $cart_item ) {
			if ( $cart_item->product_id === $product_id && $cart_item->price_id === $price_id ) {
				return $item_key;
			}
		}

		return '';
	}

	public function is_price_in_cart( $price_id ) {
		foreach ( $this->cart_items as $item_key => $cart_item ) {
			if ( (int) $cart_item->price_id === $price_id ) {
				return $item_key;
			}
		}

		return '';
	}

	public function has_onetime_products(): bool {
		$has = false;
		foreach ( $this->cart_items as $cart_item ) {
			if ( 'onetime' === $cart_item->price_type ) {
				$has = true;
				break;
			}
		}

		return $has;
	}

	/**
	 * Check if product is in the cart and return cart item key.
	 *
	 * Cart item key will be unique based on the item and its properties, such as variations.
	 *
	 * @param string|bool $cart_id id of product to find in the cart.
	 *
	 * @return string cart item key
	 */
	public function find_product_in_cart( $cart_id = false ): string {
		if ( false !== $cart_id ) {
			if ( isset( $this->cart_items[ $cart_id ] ) ) {
				return $cart_id;
			}
		}

		return '';
	}

	public function is_cart_consist_subscription_product(): bool {
		$has_subscription = false;
		$has_trial        = false;
		$active_addon     = Helper::get_addon_active_status( 'subscription' );

		foreach ( $this->cart_items as $cart_item ) {
			if ( 'subscription' === $cart_item->price_type && $active_addon ) {
				$has_subscription = true;
				$has_trial        = $cart_item->trial && $cart_item->trial_days > 0;
				break;
			}
		}

		$old_has_sub   = $this->meta['has_subscription'] ?? false;
		$old_has_trial = $this->meta['has_trial'] ?? false;

		$this->meta['has_subscription'] = $has_subscription;
		$this->meta['has_trial']        = $has_trial;

		if ( ( ! $old_has_sub && $has_subscription ) || ( ! $old_has_trial && $has_trial ) ) {
			$this->is_dirty = true;
		}

		return $has_subscription;
	}

	/**
	 * Set multiple meta data together.
	 *
	 * @param array<string,mixed> $metadata Meta data.
	 *
	 * @return void
	 */
	public function set_meta_multiple( array $metadata ) {
		foreach ( $metadata as $meta_key => $value ) {
			$this->set_meta( $meta_key, $value );
		}
	}

	/**
	 * Add/set cart meta data.
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Value to store, any serializable value.
	 *
	 * @return void
	 */
	public function set_meta( string $key, $value ) {
		if ( ! empty( $this->meta[ $key ] ) && $value === $this->meta[ $key ] ) {
			return;
		}

		$this->meta[ $key ] = $value;
		$this->is_dirty     = true;
	}

	/**
	 * Remove cart metadata.
	 *
	 * @param string $key Meta key.
	 *
	 * @return void
	 */
	public function remove_meta( string $key ) {
		if ( ! empty( $this->meta[ $key ] ) ) {
			unset( $this->meta[ $key ] );
			$this->is_dirty = true;
		}
	}

	/**
	 * Get cart metadata.
	 *
	 * @param string $key Meta key.
	 *
	 * @return mixed|null meta value if exists or null.
	 */
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? null;
	}

	public function has_subscription_product(): bool {
		return ! empty( $this->meta['has_subscription'] ) && $this->meta['has_subscription'];
	}

	public function is_cart_empty(): bool {
		return 0 === count( $this->cart_items );
	}

	public function store_on_database() {
		if ( self::$instance !== $this ) {
			return;
		}

		if ( ! $this->is_dirty ) {
			return;
		}

		try {
			$this->calculate_cart_totals();
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			Helper::log_error( $e );
		}

		$data = [
			'items'   => $this->cart_items,
			'fees'    => $this->get_fees(),
			'coupons' => ! empty( $this->cart_items ) ? array_keys( $this->applied_coupons ) : [],
			'totals'  => $this->totals,
			'meta'    => $this->meta,
		];

		if ( isset( $this->cart_id ) ) {
			\StoreEngine\Models\Cart::update( $this->cart_id, $this->cart_hash, $data );
		} else {
			$this->cart_id = \StoreEngine\Models\Cart::create( $this->cart_user_id, $this->cart_hash, $data );
		}

		$this->is_dirty = false;
	}

	public function get_cart_hash(): string {
		return $this->cart_hash;
	}

	/**
	 * Returns 'incl' if tax should be included in cart, otherwise returns 'excl'.
	 *
	 * @return string
	 */
	public function get_tax_price_display_mode(): string {
		if ( $this->get_customer() && $this->get_customer()->get_is_vat_exempt() ) {
			return 'excl';
		}

		return Helper::get_settings( 'tax_display_cart', 'excl' );
	}

	/**
	 * Get packages to calculate shipping for.
	 *
	 * This lets us calculate costs for carts that are shipped to multiple locations.
	 *
	 * Shipping methods are responsible for looping through these packages.
	 *
	 * By default, we pass the cart itself as a package - plugins can change this.
	 * through the filter and break it up.
	 *
	 * @return array of cart items
	 */
	public function get_shipping_packages(): array {
		return apply_filters(
			'storeengine/cart/shipping_packages',
			[
				[
					'contents'        => $this->get_items_needing_shipping(),
					'contents_cost'   => array_sum( wp_list_pluck( $this->get_items_needing_shipping(), 'line_total' ) ),
					'applied_coupons' => $this->get_coupons(),
					'user'            => [
						'ID' => get_current_user_id(),
					],
					'destination'     => [
						'country'   => $this->get_customer()->get_shipping_country(),
						'state'     => $this->get_customer()->get_shipping_state(),
						'postcode'  => $this->get_customer()->get_shipping_postcode(),
						'city'      => $this->get_customer()->get_shipping_city(),
						'address_1' => $this->get_customer()->get_shipping_address_1(),
						'address_2' => $this->get_customer()->get_shipping_address_2(),
					],
					'cart_subtotal'   => $this->get_displayed_subtotal(),
				],
			],
			$this
		);
	}

	/**
	 * Get only items that need shipping.
	 *
	 * @return CartItem[]
	 */
	protected function get_items_needing_shipping(): array {
		return array_filter( $this->get_cart_items(), [ $this, 'filter_items_needing_shipping' ] );
	}

	/**
	 * Filter items needing shipping callback.
	 *
	 * @param CartItem $item Item to check for shipping.
	 *
	 * @return bool
	 */
	protected function filter_items_needing_shipping( CartItem $item ): bool {
		$product = Helper::get_product( $item->product_id );

		return $product && $product->needs_shipping();
	}

	/**
	 * Return whether-or-not the cart is displaying prices including tax, rather than excluding tax.
	 *
	 * @return bool
	 */
	public function display_prices_including_tax(): bool {
		$result = 'incl' === $this->get_tax_price_display_mode();

		/**
		 * Filtering if display prices including tax or not!
		 *
		 * @param bool $result True / False.
		 */
		return apply_filters( 'storeengine/cart/display_prices_including_tax', $result );
	}

	/**
	 * Get cart's owner.
	 *
	 * @return Customer|null
	 */
	public function get_customer(): ?Customer {
		return StoreEngine::init()->customer;
	}

	/**
	 * Reset cart totals to the defaults. Useful before running calculations.
	 */
	private function reset_totals() {
		$this->totals = $this->default_totals;

		/**
		 * Fires after reset the cart.
		 *
		 * @param Cart $this Cart instance.
		 */
		do_action( 'storeengine/cart/reset', $this );
	}

	/**
	 * @param string $item_key
	 *
	 * @return string
	 */
	public static function get_remove_item_url( string $item_key ): string {
		$cart_page_url   = Helper::get_page_permalink( 'cart_page' );
		$remove_item_url = $cart_page_url ? wp_nonce_url( add_query_arg( 'remove_item', $item_key, $cart_page_url ), 'storeengine/cart' ) : '';


		/**
		 * Filter cart item remove url.
		 *
		 * @param string $remove_item_url The remove of a cart item.
		 *
		 * @returns string Remove item url
		 */
		return apply_filters( 'storeengine/cart/get_remove_url', $remove_item_url );
	}

	public static function handle_remove_cart_item_request(): void {
		if ( isset( $_GET['remove_item'], $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'storeengine/cart' ) ) {
			StoreEngine\Utils\Caching::nocache_headers();

			$item_key = sanitize_text_field( wp_unslash( $_GET['remove_item'] ) );
			$item     = self::init()->get_cart_item( $item_key );

			if ( $item ) {
				self::init()->remove_cart_item( $item_key );
			}

			if ( wp_get_referer() ) {
				$remove = [
					'remove_item',
					'add-to-cart',
					'added-to-cart',
					'order_again',
					'_wpnonce',
				];
				wp_safe_redirect( remove_query_arg( $remove, add_query_arg( 'removed_item', '1', wp_get_referer() ) ) );
				exit;
			}
		}
	}

	public static function handle_remove_coupon_request(): void {
		if ( isset( $_GET['remove_coupon'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'storeengine/cart/remove_coupon' ) ) {
			StoreEngine\Utils\Caching::nocache_headers();

			$coupon_code = sanitize_text_field( urldecode( wp_unslash( $_GET['remove_coupon'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $coupon_code ) {
				self::init()->remove_coupon( $coupon_code );
			}

			if ( wp_get_referer() ) {
				wp_safe_redirect( remove_query_arg( [ 'remove_coupon', '_wpnonce' ], wp_get_referer() ) );
				exit;
			}
		}
	}

	// Totals

	/**
	 * Sets the array of calculated coupon totals.
	 *
	 * @param array $value Value to set.
	 */
	public function set_coupon_discount_totals( array $value = [] ) {
		$this->coupon_discount_totals = $value;
	}

	/**
	 * Sets the array of calculated coupon tax totals.
	 *
	 * @param array $value Value to set.
	 */
	public function set_coupon_discount_tax_totals( array $value = [] ) {
		$this->coupon_discount_tax_totals = $value;
	}

	/**
	 * Sets the array of applied coupon codes.
	 *
	 * @param array $value List of applied coupon codes.
	 */
	public function set_applied_coupons( array $value = [] ) {
		$this->applied_coupons = $value;
	}

	/**
	 * Set all calculated totals.
	 *
	 * @param array $value Value to set.
	 */
	public function set_totals( array $value = [] ) {
		$this->totals = wp_parse_args( $value, $this->default_totals );
	}

	/**
	 * Set subtotal.
	 *
	 * @param float|string $value Value to set.
	 */
	public function set_subtotal( $value ) {
		$this->totals['subtotal'] = Formatting::format_decimal( $value );
	}

	/**
	 * Set subtotal.
	 *
	 * @param float $value Value to set.
	 */
	public function set_subtotal_tax( float $value ) {
		$this->totals['subtotal_tax'] = $value;
	}

	/**
	 * Set discount_total.
	 *
	 * @param float $value Value to set.
	 */
	public function set_discount_total( float $value ) {
		$this->totals['discount_total'] = $value;
	}

	/**
	 * Set discount_tax.
	 *
	 * @param float $value Value to set.
	 */
	public function set_discount_tax( float $value ) {
		$this->totals['discount_tax'] = $value;
	}

	/**
	 * Set shipping_total.
	 *
	 * @param float $value Value to set.
	 */
	public function set_shipping_total( float $value ) {
		$this->totals['shipping_total'] = Formatting::format_decimal( $value );
	}

	/**
	 * Set shipping_tax.
	 *
	 * @param float $value Value to set.
	 */
	public function set_shipping_tax( float $value ) {
		$this->totals['shipping_tax'] = $value;
	}

	/**
	 * Set cart_contents_total.
	 *
	 * @param float $value Value to set.
	 */
	public function set_cart_contents_total( float $value ) {
		$this->totals['cart_contents_total'] = Formatting::format_decimal( $value );
	}

	/**
	 * Set cart tax amount.
	 *
	 * @param float $value Value to set.
	 */
	public function set_cart_contents_tax( float $value ) {
		$this->totals['cart_contents_tax'] = $value;
	}

	/**
	 * Set cart total.
	 *
	 * @param float $value Value to set.
	 */
	public function set_total( float $value ) {
		$this->totals['total'] = Formatting::format_decimal( $value, Formatting::get_price_decimals() );
	}

	/**
	 * Set total tax amount.
	 *
	 * @param float $value Value to set.
	 */
	public function set_total_tax( float $value ) {
		// We round here because this is a total entry, as opposed to line items in other setters.
		$this->totals['total_tax'] = Formatting::round_tax_total( $value );
	}

	/**
	 * Set fee amount.
	 *
	 * @param float $value Value to set.
	 */
	public function set_fee_total( float $value ) {
		$this->totals['fee_total'] = Formatting::format_decimal( $value );
	}

	/**
	 * Set fee tax.
	 *
	 * @param float $value Value to set.
	 */
	public function set_fee_tax( float $value ) {
		$this->totals['fee_tax'] = $value;
	}

	/**
	 * Set taxes.
	 *
	 * @param array $value Tax values.
	 */
	public function set_shipping_taxes( array $value ) {
		$this->totals['shipping_taxes'] = $value;
	}

	/**
	 * Set taxes.
	 *
	 * @param array $value Tax values.
	 */
	public function set_cart_contents_taxes( array $value ) {
		$this->totals['cart_contents_taxes'] = $value;
	}

	/**
	 * Set taxes.
	 *
	 * @param array $value Tax values.
	 */
	public function set_fee_taxes( array $value ) {
		$this->totals['fee_taxes'] = $value;
	}

	/**
	 * Return all calculated coupon totals.
	 *
	 * @return array
	 */
	public function get_coupon_discount_totals(): array {
		return $this->coupon_discount_totals;
	}

	/**
	 * Return all calculated coupon tax totals.
	 *
	 * @return array
	 */
	public function get_coupon_discount_tax_totals(): array {
		return $this->coupon_discount_tax_totals;
	}

	/**
	 * Return all calculated totals.
	 *
	 * @return array
	 */
	public function get_totals(): array {
		return empty( $this->totals ) ? $this->default_totals : $this->totals;
	}

	/**
	 * Get a total.
	 *
	 * @param string $key Key of element in $totals array.
	 *
	 * @return mixed|float|int
	 */
	protected function get_totals_var( string $key ) {
		return $this->totals[ $key ] ?? $this->default_totals[ $key ];
	}

	/**
	 * Get subtotal.
	 *
	 * @return float|string
	 */
	public function get_subtotal() {
		$subtotal = $this->get_totals_var( 'subtotal' );

		/**
		 * Filter cart subtotal.
		 *
		 * @param float|string $subtotal Subtotal.
		 *
		 * @return float|string
		 */
		return apply_filters( 'storeengine/cart/get_subtotal', $subtotal );
	}

	/**
	 * Get subtotal_tax.
	 *
	 * @return float
	 */
	public function get_subtotal_tax(): float {
		$subtotal_tax = $this->get_totals_var( 'subtotal_tax' );

		/**
		 * Filter cart subtotal tax.
		 *
		 * @param float $subtotal_tax Subtotal tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_subtotal_tax', $subtotal_tax );
	}

	/**
	 * Get discount_total.
	 *
	 * @return float
	 */
	public function get_discount_total(): float {
		$discount_total = $this->get_totals_var( 'discount_total' );

		/**
		 * Filter cart discount total.
		 *
		 * @param float $discount_total Discount total.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_discount_total', $discount_total );
	}

	/**
	 * Get discount_tax.
	 *
	 * @return float
	 */
	public function get_discount_tax(): float {
		$discount_tax = $this->get_totals_var( 'discount_tax' );

		/**
		 * Filter cart discount tax.
		 *
		 * @param float $discount_tax Discount tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_discount_tax', $discount_tax );
	}

	/**
	 * Get shipping_total.
	 *
	 * @return float|string
	 */
	public function get_shipping_total() {
		$shipping_total = $this->get_totals_var( 'shipping_total' );

		/**
		 * Filter cart shipping total.
		 *
		 * @param float|string $shipping_total Shipping total.
		 *
		 * @return float|string
		 */
		return apply_filters( 'storeengine/cart/get_shipping_total', $shipping_total );
	}

	/**
	 * Get shipping_tax.
	 *
	 * @return float
	 */
	public function get_shipping_tax(): float {
		$shipping_tax = $this->get_totals_var( 'shipping_tax' );

		/**
		 * Filter cart shipping tax.
		 *
		 * @param float $shipping_tax Shipping tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_shipping_tax', $shipping_tax );
	}

	public function get_shipping_tax_total(): float {
		return $this->get_shipping_tax();
	}

	/**
	 * Gets cart total. This is the total of items in the cart, but after discounts. Subtotal is before discounts.
	 *
	 * @return float|string (can be string due to Formatting::format_decimal())
	 */
	public function get_cart_contents_total() {
		$cart_contents_total = $this->get_totals_var( 'cart_contents_total' );

		/**
		 * Filter cart contents total.
		 *
		 * @param float $cart_contents_total Cart content total.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_cart_contents_total', $cart_contents_total );
	}

	/**
	 * Gets cart tax amount.
	 *
	 * @return float
	 */
	public function get_cart_contents_tax(): float {
		$cart_contents_tax = $this->get_totals_var( 'cart_contents_tax' );

		/**
		 * Filter cart contents total tax.
		 *
		 * @param float $cart_contents_tax Contents total tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_cart_contents_tax', $cart_contents_tax );
	}

	/**
	 * Gets cart total after calculation.
	 *
	 * @param string $context If the context is view, the value will be formatted for display. This keeps it compatible with pre-3.2 versions.
	 *
	 * @return float|string
	 */
	public function get_total( string $context = 'view' ) {
		/**
		 * Filter get cart total.
		 *
		 * @param float $total Cart total.
		 *
		 * @return float
		 */
		$total = apply_filters( 'storeengine/cart/get_total', $this->get_totals_var( 'total' ) );

		if ( 'view' === $context ) {
			/**
			 * Filter cart total.
			 *
			 * @param string $formatted_total Formatted Cart total.
			 *
			 * @return string
			 */
			return apply_filters( 'storeengine/cart/total', Formatting::price( $total ) );
		}

		return $total;
	}

	/**
	 * Get total tax amount.
	 *
	 * @return float
	 */
	public function get_total_tax(): float {
		$total_tax = $this->get_totals_var( 'total_tax' );

		/**
		 * Filter cart total tax.
		 *
		 * @param float $total_tax Cart total tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_total_tax', $total_tax );
	}

	/**
	 * Get total fee amount.
	 *
	 * @return float
	 */
	public function get_fee_total(): float {
		$fee_total = $this->get_totals_var( 'fee_total' );

		/**
		 * Filter cart fee total.
		 *
		 * @param float $fee_total Cart fee total.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_fee_total', $fee_total );
	}

	/**
	 * Get total fee tax amount.
	 *
	 * @return float
	 */
	public function get_fee_tax(): float {
		$fee_total_tax = $this->get_totals_var( 'fee_tax' );

		/**
		 * Filter cart fee total tax.
		 *
		 * @param float $fee_total_tax Cart fee total tax.
		 *
		 * @return float
		 */
		return apply_filters( 'storeengine/cart/get_fee_tax', $fee_total_tax );
	}


	/**
	 * Get taxes.
	 */
	public function get_shipping_taxes(): array {
		$shipping_taxes = $this->get_totals_var( 'shipping_taxes' );

		/**
		 * Filter shipping taxes.
		 *
		 * @param array $shipping_taxes Cart shipping taxes.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/cart/get_shipping_taxes', $shipping_taxes );
	}

	/**
	 * Get taxes.
	 */
	public function get_cart_contents_taxes(): array {
		$cart_contents_taxes = $this->get_totals_var( 'cart_contents_taxes' );

		/**
		 * Filter cart contents taxes.
		 *
		 * @param array $cart_contents_taxes Cart contents taxes.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/cart/get_cart_contents_taxes', $cart_contents_taxes );
	}

	/**
	 * Get taxes.
	 */
	public function get_fee_taxes(): array {
		$fee_taxes = $this->get_totals_var( 'fee_taxes' );

		/**
		 * Filter cart fee taxes.
		 *
		 * @param array $fee_taxes Cart fee taxes.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/cart/get_fee_taxes', $fee_taxes );
	}

	/**
	 * Returns the cart and shipping taxes, merged.
	 *
	 * @return array merged taxes
	 */
	public function get_taxes(): array {
		$taxes = Formatting::array_merge_recursive_numeric( $this->get_shipping_taxes(), $this->get_cart_contents_taxes(), $this->get_fee_taxes() );

		/**
		 * Filter cart taxes.
		 *
		 * @param array $taxes Cart taxes.
		 * @param Cart $this Cart instance.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/cart/get_taxes', $taxes, $this );
	}

	/**
	 * Determines the value that the customer spent and the subtotal
	 * displayed, used for things like coupon validation.
	 *
	 * Since the coupon lines are displayed based on the TAX DISPLAY value
	 * of cart, this is used to determine the spend.
	 *
	 * If cart totals are shown including tax, use the subtotal.
	 * If cart totals are shown excluding tax, use the subtotal ex tax
	 * (tax is shown after coupons).
	 *
	 * @return float
	 */
	public function get_displayed_subtotal(): float {
		return $this->display_prices_including_tax() ? $this->get_subtotal() + $this->get_subtotal_tax() : $this->get_subtotal();
	}

	public function get_tax_totals() {
		$shipping_taxes = $this->get_shipping_taxes(); // Shipping taxes are rounded differently, so we will subtract from all taxes, then round and then add them back.
		$taxes          = $this->get_taxes();
		$tax_totals     = [];

		foreach ( $taxes as $key => $tax ) {
			$code = Tax::get_rate_code( $key );

			if ( $code || apply_filters( 'storeengine/cart/remove_taxes_zero_rate_id', 'zero-rated' ) === $key ) {
				if ( ! isset( $tax_totals[ $code ] ) ) {
					$tax_totals[ $code ]         = new stdClass();
					$tax_totals[ $code ]->amount = 0;
				}

				$tax_totals[ $code ]->tax_rate_id = $key;
				$tax_totals[ $code ]->is_compound = Tax::is_compound( $key );
				$tax_totals[ $code ]->label       = Tax::get_rate_label( $key );

				if ( isset( $shipping_taxes[ $key ] ) ) {
					$tax -= $shipping_taxes[ $key ];
					// Round tax total.
					$tax = Formatting::round_tax_total( $tax );
					// Add to total amount.
					$tax += NumberUtil::round( $shipping_taxes[ $key ], Formatting::get_price_decimals() );
					unset( $shipping_taxes[ $key ] );
				}

				$tax_totals[ $code ]->amount += Formatting::round_tax_total( $tax );
				// Set formatted amount.
				$tax_totals[ $code ]->formatted_amount = Formatting::price( $tax_totals[ $code ]->amount );
			}
		}

		if ( apply_filters( 'storeengine/cart/hide_zero_taxes', true ) ) {
			$amounts    = array_filter( wp_list_pluck( $tax_totals, 'amount' ) );
			$tax_totals = array_intersect_key( $tax_totals, $amounts );
		}

		/**
		 * Filter get cart tax totals.
		 *
		 * @param array $tax_totals Cart Tax totals.
		 * @param Cart $this Cart instance.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/cart/tax_totals', $tax_totals, $this );
	}

	public function get_tax_total(): float {
		return $this->get_fee_tax() + $this->get_cart_contents_tax();
	}

	/**
	 * Get all tax classes for items in the cart.
	 *
	 * @return array
	 */
	public function get_cart_item_tax_classes(): array {
		$found_tax_classes = [];

		foreach ( $this->get_cart_items() as $item ) {
			$product = Helper::get_product( $item->product_id );
			if ( $product && ( $product->is_taxable() || $product->is_shipping_taxable() ) ) {
				$found_tax_classes[] = $product->get_tax_class();
			}
		}

		return array_unique( $found_tax_classes );
	}

	/**
	 * Get all tax classes for shipping based on the items in the cart.
	 *
	 * @return array
	 */
	public function get_cart_item_tax_classes_for_shipping(): array {
		$found_tax_classes = [];

		foreach ( $this->get_cart_items() as $item ) {
			$product = Helper::get_product( $item->product_id );
			if ( $product && $product->is_shipping_taxable() ) {
				$found_tax_classes[] = $product->get_tax_class();
			}
		}

		return array_unique( $found_tax_classes );
	}

	/**
	 * Gets the cart tax (after calculation).
	 *
	 * @return string formatted price
	 */
	public function get_cart_tax(): string {
		$cart_total_tax = Formatting::round_tax_total( $this->get_cart_contents_tax() + $this->get_shipping_tax() + $this->get_fee_tax() );
		$cart_total_tax = $cart_total_tax ? Formatting::price( $cart_total_tax ) : '';

		/**
		 * Filter get cart tax.
		 *
		 * @param string $cart_total_tax Cart total tax.
		 *
		 * @return string
		 */
		return apply_filters( 'storeengine/cart/get_tax', $cart_total_tax );
	}

	/**
	 * Get a tax amount.
	 *
	 * @param string $tax_rate_id ID of the tax rate to get taxes for.
	 *
	 * @return float amount
	 */
	public function get_tax_amount( string $tax_rate_id ) {
		$taxes = Formatting::array_merge_recursive_numeric( $this->get_cart_contents_taxes(), $this->get_fee_taxes() );

		return $taxes[ $tax_rate_id ] ?? 0;
	}

	/**
	 * Get a tax amount.
	 *
	 * @param string $tax_rate_id ID of the tax rate to get taxes for.
	 *
	 * @return float amount
	 */
	public function get_shipping_tax_amount( string $tax_rate_id ) {
		$taxes = $this->get_shipping_taxes();

		return $taxes[ $tax_rate_id ] ?? 0;
	}

	/**
	 * Get tax row amounts with or without compound taxes includes.
	 *
	 * @param bool $compound True if getting compound taxes.
	 * @param bool $display True if getting total to display.
	 *
	 * @return float|string total tax amount, decimal formated if display is true.
	 */
	public function get_taxes_total( bool $compound = true, bool $display = true ) {
		$total = 0;
		$taxes = $this->get_taxes();
		foreach ( $taxes as $key => $tax ) {
			if ( ! $compound && Tax::is_compound( $key ) ) {
				continue;
			}
			$total += $tax;
		}

		if ( $display ) {
			$total = Formatting::format_decimal( $total, Formatting::get_price_decimals() );
		}

		/**
		 * Filter cart taxes total.
		 *
		 * @param float|int|mixed|string $total
		 * @param bool $compound
		 * @param bool $display
		 * @param Cart $this
		 *
		 * @return mixed
		 */
		return apply_filters( 'storeengine/cart/taxes_total', $total, $compound, $display, $this );
	}

	/**
	 * Given a set of packages with rates, get the chosen ones only.
	 *
	 * @param array $calculated_shipping_packages Array of packages.
	 *
	 * @return array
	 */
	protected function get_chosen_shipping_methods( array $calculated_shipping_packages = [] ): array {
		$chosen_methods = [];
		// Get chosen methods for each package to get our totals.
		foreach ( $calculated_shipping_packages as $key => $package ) {
			$chosen_method = $this->get_chosen_shipping_method_for_package( $key, $package );
			if ( $chosen_method ) {
				$chosen_methods[ $key ] = $package['rates'][ $chosen_method ];
			}
		}

		return $chosen_methods;
	}

	/**
	 * Get chosen method for package from session.
	 *
	 * @param int|string $key Key of package.
	 * @param array $package Package data array.
	 *
	 * @return string|bool Either the chosen method ID or false if nothing is chosen yet.
	 */
	public function get_chosen_shipping_method_for_package( $key, array $package ) {
		$chosen_methods = $this->get_meta( 'chosen_shipping_methods' );
		$chosen_methods = is_array( $chosen_methods ) ? $chosen_methods : [];
		$chosen_method  = $chosen_methods[ $key ] ?? false;
		$changed        = $this->shipping_methods_have_changed( $key, $package );


		if ( ! isset( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
			$package['rates'] = [];
		}

		// If not set, not available, or available methods have changed, set to the DEFAULT option.
		if ( ! $chosen_method || $changed || ! isset( $package['rates'][ $chosen_method ] ) ) {
			$chosen_method = $this->get_default_shipping_method_for_package( $key, $package, $chosen_method );

			if ( ! empty( $chosen_method ) ) {
				$chosen_methods[ $key ] = $chosen_method;
			}

			$this->set_meta( 'chosen_shipping_methods', $chosen_methods );

			/**
			 * Fires when a shipping method is chosen.
			 *
			 * @param string $chosen_method Chosen shipping method. E.g. flat_rate:1.
			 */
			do_action( 'storeengine/shipping/method_chosen', $chosen_method );
		}

		return $chosen_method;
	}

	/**
	 * See if the methods have changed since the last request.
	 *
	 * @param int|string $key
	 * @param array $package
	 *
	 * @return bool
	 */
	public function shipping_methods_have_changed( $key, array $package ): bool {
		$previous_shipping_methods = $this->get_meta( 'previous_shipping_methods' );
		// Get new and old rates.
		$new_rates  = array_keys( $package['rates'] );
		$prev_rates = $previous_shipping_methods[ $key ] ?? false;
		// Update session.
		$previous_shipping_methods[ $key ] = $new_rates;
		$this->set_meta( 'previous_shipping_methods', $previous_shipping_methods );

		return $new_rates !== $prev_rates;
	}

	/**
	 * Choose the default method for a package.
	 *
	 * @param int|string $key Key of package.
	 * @param array $package Package data array.
	 * @param string $chosen_method Chosen shipping method. e.g. flat_rate:1.
	 *
	 * @return string
	 */
	public function get_default_shipping_method_for_package( $key, array $package, string $chosen_method ): string {
		$rate_keys = array_keys( $package['rates'] );


		// Default to the first method in the package. This can be sorted in the backend by the merchant.
		$default = current( $rate_keys );

		// @TODO: Check coupons to see if free shipping is available. If it is, we'll use that method as the default.


		/**
		 * Filters the default shipping method for a package.
		 *
		 * @param string $default Default shipping method.
		 * @param array $rates Shipping rates.
		 * @param string $chosen_method Chosen method id.
		 */
		return (string) apply_filters( 'storeengine/shipping/chosen_method', $default, $package['rates'], $chosen_method );
	}

	public function calculate_shipping(): array {
		// Reset totals.
		$this->set_shipping_total( 0 );
		$this->set_shipping_tax( 0 );
		$this->set_shipping_taxes( [] );
		$this->shipping_methods        = [];
		$this->has_calculated_shipping = false;

		if ( ! $this->needs_shipping() || ! $this->show_shipping() ) {
			return $this->shipping_methods;
		}

		$this->has_calculated_shipping = true;
		$this->shipping_methods        = $this->get_chosen_shipping_methods( Shipping::init()->calculate_shipping( $this->get_shipping_packages(), $this ) );

		$shipping_costs = wp_list_pluck( $this->shipping_methods, 'cost' );
		$shipping_taxes = wp_list_pluck( $this->shipping_methods, 'taxes' );
		$merged_taxes   = [];
		foreach ( $shipping_taxes as $taxes ) {
			foreach ( $taxes as $tax_id => $tax_amount ) {
				$merged_taxes[ $tax_id ] = ( $merged_taxes[ $tax_id ] ?? 0 ) + $tax_amount;
			}
		}

		$this->set_shipping_total( array_sum( $shipping_costs ) );
		$this->set_shipping_tax( array_sum( $merged_taxes ) );
		$this->set_shipping_taxes( $merged_taxes );

		return $this->shipping_methods;
	}

	/**
	 * Will set cart cookies if needed and when possible.
	 *
	 * Headers are only updated if headers have not yet been sent.
	 */
	public function maybe_set_cart_cookies() {
		if ( headers_sent() || ! did_action( 'wp_loaded' ) || isset( $this->logginout ) ) {
			return;
		}

		if ( $this->has_items() ) {
			$this->set_cart_cookies( true );
		} elseif ( isset( $_COOKIE['storeengine_items_in_cart'] ) ) { // WPCS: input var ok.
			$this->set_cart_cookies( false );
		}

		$this->dedupe_cookies();
	}

	public function remove_cart_cookies() {
		$this->logginout = true;
		$this->set_cart_cookies( false );
	}

	/**
	 * Set cart hash cookie and items in cart if not already set.
	 *
	 * @param bool $set Should cookies be set (true) or unset.
	 */
	private function set_cart_cookies( $set = true ) {
		if ( $set ) {
			if ( ! $this->cart_hash ) {
				$this->cart_hash = wp_generate_uuid4();
			}

			$setcookies = [
				'storeengine_items_in_cart' => '1',
				'storeengine_cart_hash'     => $this->cart_hash,
			];

			foreach ( $setcookies as $name => $value ) {
				if ( ! isset( $_COOKIE[ $name ] ) || $_COOKIE[ $name ] !== $value ) {
					Helper::setcookie( $name, $value );
					$_COOKIE[ $name ] = $value;
				}
			}
		} else {
			$unsetcookies = [ 'storeengine_items_in_cart', 'storeengine_cart_hash' ];

			foreach ( $unsetcookies as $name ) {
				if ( isset( $_COOKIE[ $name ] ) ) {
					Helper::setcookie( $name, 0, time() - HOUR_IN_SECONDS );
					unset( $_COOKIE[ $name ] );
				}
			}
		}

		do_action( 'storeengine/cart/set_cart_cookies', $set );
	}

	/**
	 * Remove duplicate cookies from the response.
	 */
	private function dedupe_cookies() {
		$all_cookies    = array_filter( headers_list(), fn ( $header ) => stripos( $header, 'Set-Cookie:' ) !== false );
		$final_cookies  = [];
		$update_cookies = false;

		foreach ( $all_cookies as $cookie ) {
			list( , $cookie_value )             = explode( ':', $cookie, 2 );
			list( $cookie_name, $cookie_value ) = explode( '=', trim( $cookie_value ), 2 );

			if ( stripos( $cookie_name, 'storeengine_' ) !== false ) {
				$key = $this->find_cookie_by_name( $cookie_name, $final_cookies );
				if ( false !== $key ) {
					$update_cookies = true;
					unset( $final_cookies[ $key ] );
				}
			}

			$final_cookies[] = $cookie;
		}

		if ( $update_cookies ) {
			header_remove( 'Set-Cookie' );
			foreach ( $final_cookies as $cookie ) {
				// Using header here preserves previous cookie args.
				header( $cookie, false );
			}
		}
	}

	/**
	 * Find a cookie by name in an array of cookies.
	 *
	 * @param string $cookie_name Name of the cookie to find.
	 * @param array $cookies Array of cookies to search.
	 *
	 * @return int|string Key of the cookie if found, false if not.
	 */
	private function find_cookie_by_name( string $cookie_name, array $cookies ) {
		foreach ( $cookies as $key => $cookie ) {
			if ( strpos( $cookie, $cookie_name ) !== false ) {
				return $key;
			}
		}

		return false;
	}
}

// End of file cart.php
