<?php

namespace StoreEngine\Addons\Subscription;

use StoreEngine;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCoupon;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\CartItem;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemShipping;
use StoreEngine\Classes\Price;
use StoreEngine\Shipping\Shipping;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	/**
	 * A flag to control how to modify the calculation of totals by Cart::calculate_cart_totals()
	 *
	 * Can take any one of these values:
	 * - 'none' used to calculate the initial total.
	 * - 'combined_total' used to calculate the total of sign-up fee + recurring amount.
	 * - '`sign_up_fee_total`' used to calculate the initial amount when there is a free trial period and a sign-up fee. Different to 'combined_total' because shipping is not charged on a sign-up fee.
	 * - 'recurring_total' used to calculate the totals for the recurring amount when the recurring amount differs to to 'combined_total' because of coupons or sign-up fees.
	 * - 'free_trial_total' used to calculate the initial total when there is a free trial period and no sign-up fee. Different to 'combined_total' because shipping is not charged up-front when there is a free trial.
	 */
	private static string $calculation_type = 'none';

	/**
	 * An internal pointer to the current recurring cart calculation (if any)
	 */
	private static string $recurring_cart_key = 'none';

	/**
	 * A cache of the calculated recurring shipping packages
	 */
	private static array $recurring_shipping_packages = [];

	/**
	 * A cache of the current recurring cart being calculated
	 */
	private static ?Cart $cached_recurring_cart = null;

	protected function __construct() {
		add_filter( 'storeengine/get_price_html', [ __CLASS__, 'display_subscription_price' ], 10, 2 );
		add_filter( 'storeengine/get_price_summery_html', [ __CLASS__, 'display_subscription_period' ], 10, 2 );

		add_action( 'storeengine/cart/before_calculate_totals', [ __CLASS__, 'add_calculation_price_filter' ], 10 );
		add_action( 'storeengine/calculate_totals', [ __CLASS__, 'remove_calculation_price_filter' ], 10 );
		add_action( 'storeengine/cart/after_calculate_totals', [ __CLASS__, 'remove_calculation_price_filter' ], 10 );

		add_filter( 'storeengine/calculated_total', [ __CLASS__, 'calculate_subscription_totals' ], 1000, 2 );

		// Display Formatted Totals
		add_filter( 'storeengine/cart/product_subtotal', [ __CLASS__, 'get_formatted_product_subtotal' ], 11, 4 );
		// Make sure cart product prices correctly include/exclude taxes
		add_filter( 'storeengine/cart/product_price', [ __CLASS__, 'cart_product_price' ], 10, 2 );

		// Sometimes, even if the order total is $0, the cart still needs payment
		add_filter( 'storeengine/cart/needs_payment', [ __CLASS__, 'cart_needs_payment' ], 10, 2 );

		add_action( 'storeengine/cart/cart_totals_after_order_total', [ __CLASS__, 'display_recurring_totals' ] );

		add_action( 'storeengine/thankyou/order_info', [ __CLASS__, 'order_subscription_info' ] );
		add_action( 'storeengine/thankyou/after_order_details', [ __CLASS__, 'order_subscription_details' ] );

		add_filter( 'storeengine/api/customer/customer_data', [ __CLASS__, 'customer_api_add_subscription_data' ], 10, 2 );

		add_action( 'storeengine/order_details/after_order_table', [ __CLASS__, 'add_subscriptions_to_view_order' ] );
		add_action( 'storeengine/subscription/after_subscription_details', [ __CLASS__, 'add_related_orders_to_view_subscription' ] );

		add_action( 'wp_loaded', [ __CLASS__, 'maybe_change_users_subscription' ], 100 );
	}

	/**
	 * Checks if the current request is by a user to change the status of their subscription, and if it is,
	 * validate the request and proceed to change to the subscription.
	 */
	public static function maybe_change_users_subscription() {
		if ( isset( $_GET['change_subscription_to'], $_GET['subscription_id'] ) && ! empty( $_GET['_wpnonce'] ) ) {
			Caching::nocache_headers();
			$new_status = sanitize_text_field( $_GET['change_subscription_to'] );
			$wpnonce    = sanitize_text_field( $_GET['_wpnonce'] );

			try {
				$subscription = Subscription::get_subscription( absint( $_GET['subscription_id'] ) );
			} catch ( StoreEngineException $e ) {
				wp_die(
					__( 'That subscription does not exist. Please contact us if needed.', 'storeengine' ),
					__( 'Error: Subscription not found', 'storeengine' ),
					[
						'response'  => 404,
						'back_link' => true,
					]
				);
			}

			if ( ! wp_verify_nonce( $wpnonce, $subscription->get_id() . $subscription->get_status() ) ) {
				wp_die(
					__( 'Security error. Please contact us if needed.', 'storeengine' ),
					__( 'Error: Invalid request', 'storeengine' ),
					[
						'response'  => 404,
						'back_link' => true,
					]
				);
			}

			if ( get_current_user_id() !== $subscription->get_customer_id() ) {
				wp_die(
					__( 'That doesn\'t appear to be one of your subscriptions.', 'storeengine' ),
					__( 'Error: Invalid request', 'storeengine' ),
					[
						'response'  => 404,
						'back_link' => true,
					]
				);
			}

			if ( ! $subscription->can_be_updated_to( $new_status ) ) {
				wp_die(
					sprintf(
					// translators: placeholder is subscription's new status, translated
						__( 'That subscription can not be changed to %s. Please contact us if needed.', 'storeengine' ),
						SubscriptionCollection::get_subscription_status_name( $new_status )
					),
					__( 'Error: Can not update subscription status', 'storeengine' ),
					[
						'response'  => 404,
						'back_link' => true,
					]
				);
			}

			try {
				Utils::change_users_subscription( $subscription, $new_status );

				wp_safe_redirect( $subscription->get_view_order_url() );
				exit;
			} catch ( StoreEngineException $e ) {
				wp_die(
					$e->getMessage(),
					__( 'Error: Failed to update subscription status', 'storeengine' ),
					[
						'response'  => 404,
						'back_link' => true,
					]
				);
			}
		}
	}



	public static function add_related_orders_to_view_subscription( Subscription $subscription ) {
		$subscription_orders = $subscription->get_related_orders();

		if ( ! empty( $subscription_orders ) ) {
			Template::get_template(
				'frontend-dashboard/pages/partials/related-orders.php',
				[
					'subscription'        => $subscription,
					'subscription_orders' => $subscription_orders,
				]
			);
		}
	}

	public static function add_subscriptions_to_view_order( Order $order ) {
		$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id(), [ 'parent' ] );

		if ( ! empty( $subscriptions ) ) {
			Template::get_template(
				'frontend-dashboard/pages/partials/related-plans.php',
				[
					'order'         => $order,
					'subscriptions' => $subscriptions,
				]
			);
		}
	}

	public static function customer_api_add_subscription_data( $data, StoreEngine\Classes\Customer $customer ): array {
		$query = new SubscriptionCollection( [
			'per_page' => - 1,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'DESC',
			'where'    => [
				[
					'key'   => 'customer_id',
					'value' => $customer->get_id(),
				],
			],
		] );

		foreach ( $query->get_results() as $subscription ) {
			$product                 = current( $subscription->get_items() );
			$data['subscriptions'][] = [
				'subscription_id'   => $subscription->get_id(),
				'status'            => $subscription->get_status(),
				'status_title'      => $subscription->get_status_title(),
				'product'           => $product ? $product->get_name() : __( 'N/A', 'storeengine' ),
				'payment_method'    => $subscription->get_payment_method_title(),
				'start_date'        => $subscription->get_start_date() ? $subscription->get_start_date()->date( 'Y-m-d H:i:s' ) : null,
				'next_payment_date' => $subscription->get_next_payment_date() ? $subscription->get_next_payment_date()->date( 'Y-m-d H:i:s' ) : null,
			];
		}

		return $data;
	}

	public static function order_subscription_details( Order $order ) {
		$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id(), [ 'any' ] );

		if ( ! empty( $subscriptions ) ) {
			Template::get_template( 'shortcode/subscription-details.php', [
				'order'         => $order,
				'subscriptions' => $subscriptions,
			] );
		}
	}

	public static function order_subscription_info( Order $order ) {
		if ( SubscriptionCollection::order_contains_subscription( $order->get_id(), [ 'any' ] ) ) {
			$subscriptions                = SubscriptionCollection::get_subscriptions_for_order( $order->get_id(), [ 'any' ] );
			$subscription_count           = count( $subscriptions );
			$thank_you_message            = '<div class="storeengine-thankyou-order-info-success__content storeengine-thankyou-order-info-success__subscription">';
			$my_account_subscriptions_url = StoreEngine\Utils\Helper::get_account_endpoint_url( 'dashboard' );

			if ( $subscription_count ) {
				foreach ( $subscriptions as $subscription ) {
					if ( ! $subscription->has_status( 'active' ) ) {
						$thank_you_message = '<p>' . _n( 'Your subscription will be activated when payment clears.', 'Your subscriptions will be activated when payment clears.', $subscription_count, 'storeengine' ) . '</p>';
						break;
					}
				}
			}

			// translators: placeholders are opening and closing link tags
			$thank_you_message .= '<p>' . sprintf( _n( 'View the status of your subscription in %1$syour account%2$s.', 'View the status of your subscriptions in %1$syour account%2$s.', $subscription_count, 'storeengine' ), '<a href="' . $my_account_subscriptions_url . '">', '</a>' ) . '</p>';
			$thank_you_message .= '</div>';

			echo wp_kses_post( apply_filters( 'storeengine/subscription/order_subscription_info', $thank_you_message, $order ) );
		}
	}

	public static function display_recurring_totals() {
		if ( self::cart_contains_subscription() && ! empty( StoreEngine::init()->get_cart()->recurring_carts ) ) {
			// We only want shipping for recurring amounts, and they need to be calculated again here.
			self::$calculation_type       = 'recurring_total';
			$carts_with_multiple_payments = 0;

			foreach ( StoreEngine::init()->get_cart()->recurring_carts as $recurring_cart ) {
				// Cart contains more than one payment.
				if ( 0 != $recurring_cart->next_payment_date ) {
					$carts_with_multiple_payments ++;
				}
			}

			if ( apply_filters( 'storeengine/subscription/display_recurring_totals', $carts_with_multiple_payments >= 1 ) ) {
				Template::get_template(
					'cart/cart-recurring-total.php',
					[
						'shipping_methods'             => [],
						'recurring_carts'              => StoreEngine::init()->get_cart()->recurring_carts,
						'carts_with_multiple_payments' => $carts_with_multiple_payments,
					]
				);
			}

			self::$calculation_type = 'none';
		}
	}

	public static function display_subscription_price( string $price_html, Price $price ) {
		if ( $price->is_subscription() ) {
			return Utils::subscription_price_html( $price->get_id(), [ 'price' => $price_html ] );
		}

		return $price_html;
	}

	public static function display_subscription_period( string $price_html, Price $price ): string {
		if ( $price->is_subscription() ) {
			return sprintf(
			// translators: 1$: recurring amount, 2$: subscription shorthand period (e.g. "mo" or "3 mo") (e.g. "$15 / mo" or "$10 / 3 mo").
				__( '%1$s / %2$s', 'storeengine' ),
				$price_html,
				Utils::get_subscription_period_short_strings( $price->get_payment_duration(), $price->get_payment_duration_type() )
			);
		}

		return $price_html;
	}

	public static function add_calculation_price_filter( Cart $cart ) {
		$cart->recurring_carts = [];

		// Only hook when cart contains a subscription
		if ( ! $cart->has_subscription_product() ) {
			return;
		}

		// Set which price should be used for calculation
		add_filter( 'storeengine/cart/item_price', [ __CLASS__, 'set_subscription_prices_for_calculation' ], 100, 2 );
	}

	public static function remove_calculation_price_filter() {
		remove_filter( 'storeengine/cart/item_price', [ __CLASS__, 'set_subscription_prices_for_calculation' ], 100 );
	}

	public static function set_subscription_prices_for_calculation( $price, CartItem $cart_item ) {
		if ( 'subscription' === $cart_item->price_type ) {
			if ( 'none' == self::$calculation_type ) {
				if ( $cart_item->setup_fee && $cart_item->setup_fee_price /*&& 'fee' === $cart_item->setup_fee_type*/ ) {
					return $price + $cart_item->setup_fee_price;
				}

				return $price;
			}
		} elseif ( 'recurring_total' == self::$calculation_type ) {
			return 0;
		}

		return $price;
	}

	/**
	 * Calculate the initial and recurring totals for all subscription products in the cart.
	 *
	 * We need to group subscriptions by billing schedule to make the display and creation of recurring totals sane,
	 * when there are multiple subscriptions in the cart. To do that, we use an array with keys of the form:
	 * '{billing_interval}_{billing_period}_{trial_interval}_{trial_period}_{length}_{billing_period}'. This key
	 * is used to reference WC_Cart objects for each recurring billing schedule and these are stored in the master
	 * cart with the billing schedule key.
	 *
	 * After we have calculated and grouped all recurring totals, we need to checks the structure of the subscription
	 * product prices to see whether they include sign-up fees and/or free trial periods and then recalculates the
	 * appropriate totals by using the @see self::$calculation_type flag and cloning the cart to run @see WC_Cart::calculate_totals()
	 */
	public static function calculate_subscription_totals( $total, Cart $cart ) {
		if ( ! self::cart_contains_subscription( $cart ) && ! self::cart_contains_resubscribe( $cart ) ) { // cart doesn't contain subscription
			return $total;
		} elseif ( 'none' != self::$calculation_type ) { // We're in the middle of a recalculation, let it run
			return $total;
		}

		// Save the original cart values/totals, as we'll use this when there is no sign-up fee
		$cart->set_total( max( $total, 0 ) );

		do_action( 'storeengine/subscription/cart/before_grouping' );

		$subscription_groups = [];

		// Group the subscription items by their cart item key based on billing schedule
		foreach ( $cart->get_cart_items() as $cart_item_key => $cart_item ) {
			/** @var CartItem $cart_item */
			if ( 'subscription' === $cart_item->price_type ) {
				$subscription_groups[ self::generate_recurring_cart_key( $cart_item ) ][] = $cart_item_key;
			}
		}

		do_action( 'storeengine/subscription/cart/after_grouping' );

		$recurring_carts  = [];
		$shipping_methods = $cart->get_meta( 'chosen_shipping_methods' ) ?? [];

		$cart->set_meta( 'chosen_shipping_methods', $shipping_methods );

		// Now let's calculate the totals for each group of subscriptions
		self::$calculation_type = 'recurring_total';

		foreach ( $subscription_groups as $recurring_cart_key => $subscription_group ) {
			// Create a clone cart to calculate and store totals for this group of subscriptions
			$recurring_cart = clone $cart;
			$price          = null;

			// Set the current recurring key flag on this class, and store the recurring_cart_key to the new cart instance.
			self::$recurring_cart_key           = $recurring_cart_key;
			$recurring_cart->recurring_cart_key = $recurring_cart_key;

			// Remove any items not in this subscription group
			foreach ( $recurring_cart->get_cart_items() as $cart_item_key => $cart_item ) {
				if ( ! in_array( $cart_item_key, $subscription_group, true ) ) {
					unset( $recurring_cart->cart_items[ $cart_item_key ] );
					continue;
				}

				if ( null === $price ) {
					$price = new Price( $cart_item->price_id );
				}
			}

			$recurring_cart->start_date        = apply_filters( 'storeengine/subscription/recurring_cart_start_date', gmdate( 'Y-m-d H:i:s' ), $recurring_cart );
			$recurring_cart->trial_end_date    = apply_filters( 'storeengine/subscription/recurring_cart_trial_end_date', 0, $recurring_cart, $price );
			$recurring_cart->next_payment_date = apply_filters( 'storeengine/subscription/recurring_cart_next_payment_date', Utils::get_first_renewal_payment_date( $price, $recurring_cart->start_date ), $recurring_cart, $price );
			$recurring_cart->end_date          = apply_filters( 'storeengine/subscription/recurring_cart_end_date', Utils::get_expiration_date( $price, $recurring_cart->start_date ), $recurring_cart, $price );

			// Before calculating recurring cart totals, store this recurring cart object
			self::set_cached_recurring_cart( $recurring_cart );

			// No fees recur (yet)
			$recurring_cart->fees_api()->remove_all_fees();

			$recurring_cart->fee_total = 0;

			/**
			 * Remove coupons from recurring cart.
			 *
			 * @see SubscriptionCoupon::remove_coupons();
			 * Adding action via `storeengine/cart/before_calculate_totals` hook doesn't working.
			 * @TODO implement coupon removal logic here.
			 */
			$recurring_cart->remove_coupons();

			$recurring_cart->calculate_cart_totals();

			// Store this groups cart details
			$recurring_carts[ $recurring_cart_key ] = clone $recurring_cart;

			// And remove some other floatsam
			$recurring_carts[ $recurring_cart_key ]->removed_cart_contents = [];
			$recurring_carts[ $recurring_cart_key ]->cart_session_data     = [];

			// Keep a record of the shipping packages so we can add them to the global packages later
			self::$recurring_shipping_packages[ $recurring_cart_key ] = Shipping::init()->get_packages();
		}

		// Reset flags when we're done processing recurring carts.
		self::$calculation_type = self::$recurring_cart_key = 'none';

		// We need to reset the packages and totals stored in Shipping::init() too

		// Only calculate the initial order cart shipping if we need to show shipping.
		if ( $cart->show_shipping() ) {
			$cart->calculate_shipping();
		}

		// We no longer need our backup of shipping methods

		// If there is no sign-up fee and a free trial, and no products being purchased with the subscription, we need to zero the fees for the first billing period
		$remove_fees_from_cart = ( 0 == self::get_cart_subscription_sign_up_fee( $cart ) && self::all_cart_items_have_free_trial( $cart ) );

		/**
		 * Allow third-parties to override whether the fees will be removed from the initial order cart.
		 *
		 * @param bool $remove_fees_from_cart Whether the fees will be removed. By default fees will be removed if there is no signup fee and all cart items have a trial.
		 * @param Cart $cart The standard WC cart object.
		 * @param array $recurring_carts All the recurring cart objects.
		 */
		if ( apply_filters( 'storeengine/subscription/remove_fees_from_initial_cart', $remove_fees_from_cart, $cart, $recurring_carts ) ) {
			$cart_fees = $cart->get_fees();
			foreach ( $cart_fees as $fee ) {
				$fee->amount = 0;
				$fee->tax    = 0;
				$fee->total  = 0;
			}

			$cart->fees_api()->set_fees( $cart_fees );
			$cart->fee_total = 0;
		}

		$cart->recurring_carts = $recurring_carts;

		$total = max( 0, round( $cart->get_cart_contents_total() + $cart->get_tax_total() + $cart->get_shipping_tax_total() + $cart->get_shipping_total() + $cart->get_fee_total(), Formatting::get_price_decimals() ) );

		if ( ! self::charge_shipping_up_front( $cart ) ) {
			$total                    = max( 0, $total - $cart->get_shipping_tax_total() - $cart->get_shipping_total() );
			$cart->shipping_taxes     = [];
			$cart->shipping_tax_total = 0;
			$cart->shipping_total     = 0;
		}

		return apply_filters( 'storeengine/subscription/calculated_total', $total );
	}

	public static function get_sign_up_fee_filter( $value, Price $price ) {
		return $price->has_setup_fee() && $price->get_setup_fee() ? $price->get_setup_fee_price() : 0;
	}

	/**
	 * Returns the subtotal for a cart item including the subscription period and duration details
	 */
	public static function get_formatted_product_subtotal( $product_subtotal, Price $price, int $quantity, Cart $cart ) {
		if ( $price->is_subscription()/* && ! wcs_cart_contains_renewal()*/ ) {
			// Avoid infinite loop
			remove_filter( 'storeengine/cart/product_subtotal', [ __CLASS__, 'get_formatted_product_subtotal' ], 11 );

			add_filter( 'storeengine/price/get/price', [ __CLASS__, 'get_sign_up_fee_filter' ], 100, 2 );

			// And get the appropriate sign up fee string
			$sign_up_fee_string = $cart->get_product_subtotal( $price->get_price(), $price->get_id(), $price->get_product_id(), $quantity );

			remove_filter( 'storeengine/price/get/price', [ __CLASS__, 'get_sign_up_fee_filter' ], 100 );

			add_filter( 'storeengine/cart/product_subtotal', [ __CLASS__, 'get_formatted_product_subtotal' ], 11, 4 );

			$product_subtotal = Utils::subscription_price_html(
				$price->get_id(),
				[
					'price'           => $product_subtotal,
					'sign_up_fee'     => $sign_up_fee_string,
					'tax_calculation' => $cart->get_tax_price_display_mode(),
				]
			);

			$inc_tax_or_vat_string = Countries::init()->inc_tax_or_vat();
			$ex_tax_or_vat_string  = Countries::init()->ex_tax_or_vat();

			if ( ! empty( $inc_tax_or_vat_string ) && false !== strpos( $product_subtotal, $inc_tax_or_vat_string ) ) {
				$product_subtotal = str_replace( Countries::init()->inc_tax_or_vat(), '', $product_subtotal ) . ' <small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>';
			}
			if ( ! empty( $ex_tax_or_vat_string ) && false !== strpos( $product_subtotal, $ex_tax_or_vat_string ) ) {
				$product_subtotal = str_replace( Countries::init()->ex_tax_or_vat(), '', $product_subtotal ) . ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
			}

			$product_subtotal = '<span class="subscription-price">' . $product_subtotal . '</span>';
		}

		return $product_subtotal;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 */
	public static function cart_contains_resubscribe( $cart = null ) {
		$contains_resubscribe = false;

		if ( empty( $cart ) ) {
			$cart = StoreEngine::init()->get_cart();
		}

		if ( ! empty( $cart->get_cart_items() ) ) {
			foreach ( $cart->get_cart_items() as $cart_item ) {
				if ( isset( $cart_item->subscription_resubscribe ) ) {
					$contains_resubscribe = $cart_item;
					break;
				}
			}
		}

		return apply_filters( 'storeengine/subscription/cart/contains_resubscribe', $contains_resubscribe, $cart );
	}

	/**
	 * Create a shipping package index for a given shipping package on a recurring cart.
	 *
	 * @param string $recurring_cart_key a cart key of the form returned by @see self::generate_recurring_cart_key()
	 * @param int $package_index the index of a package
	 */
	public static function get_recurring_shipping_package_key( string $recurring_cart_key, int $package_index ): string {
		return $recurring_cart_key . '_' . $package_index;
	}

	/**
	 * Construct a cart key based on the billing schedule of a subscription product.
	 *
	 * Subscriptions groups products by billing schedule when calculating cart totals, so that shipping and other "per order" amounts
	 * can be calculated for each group of items for each renewal. This method constructs a cart key based on the billing schedule
	 * to allow products on the same billing schedule to be grouped together - free trials and synchronisation is accounted for by
	 * using the first renewal date (if any) for the susbcription.
	 */
	public static function generate_recurring_cart_key( CartItem $cart_item, $renewal_time = '' ) {
		$cart_key     = '';
		$price        = new Price( $cart_item->price_id );
		$renewal_time = ! empty( $renewal_time ) ? $renewal_time : Utils::get_first_renewal_payment_time( $price );
		$interval     = $cart_item->payment_duration;
		$period       = $cart_item->payment_duration_type;
		$length       = 0;

		if ( $renewal_time > 0 ) {
			$cart_key .= gmdate( 'Y_m_d_', $renewal_time );
		}

		// First start with the billing interval and period
		switch ( $interval ) {
			case 1:
				if ( 'day' == $period ) {
					$cart_key .= 'daily'; // always gotta be one exception
				} else {
					$cart_key .= sprintf( '%sly', $period );
				}
				break;
			case 2:
				$cart_key .= sprintf( 'every_2nd_%s', $period );
				break;
			case 3:
				$cart_key .= sprintf( 'every_3rd_%s', $period ); // or sometimes two exceptions it would seem
				break;
			default:
				$cart_key .= sprintf( 'every_%dth_%s', $interval, $period );
				break;
		}

		if ( $length > 0 ) {
			$cart_key .= '_for_';
			$cart_key .= sprintf( '%d_%s', $length, $period );
			if ( $length > 1 ) {
				$cart_key .= 's';
			}
		}

		return apply_filters( 'storeengine/subscription/recurring_cart_key', $cart_key, $cart_item );
	}

	/**
	 * Checks the cart to see if it contains a subscription product.
	 *
	 * @param Cart|null $cart
	 *
	 * @return bool
	 */
	public static function cart_contains_subscription( ?Cart $cart = null ): bool {
		$cart = StoreEngine::init()->get_cart() ?? $cart;

		return $cart && $cart->has_subscription_product();
	}

	/**
	 * Gets the cart calculation type flag
	 */
	public static function get_calculation_type(): string {
		return self::$calculation_type;
	}

	/**
	 * Sets the cart calculation type flag
	 */
	public static function set_calculation_type( $calculation_type ) {
		self::$calculation_type = $calculation_type;

		return $calculation_type;
	}

	/**
	 * Sets the recurring cart key flag.
	 *
	 * @param string $recurring_cart_key Recurring cart key used to identify the current recurring cart being processed.
	 *
	 * @internal While this is indeed stored to the cart object, some hooks such as woocommerce_cart_shipping_packages
	 *           do not have access to this property. So we can properly set package IDs we make use of this flag.
	 */
	public static function set_recurring_cart_key( string $recurring_cart_key ): string {
		self::$recurring_cart_key = $recurring_cart_key;

		return $recurring_cart_key;
	}

	public static function get_recurring_cart_key(): string {
		return self::$recurring_cart_key;
	}

	/**
	 * Update the cached recurring cart.
	 *
	 * @param Cart $recurring_cart Cart object.
	 */
	public static function set_cached_recurring_cart( $recurring_cart ) {
		self::$cached_recurring_cart = $recurring_cart;
	}

	/**
	 * Gets the subscription sign up fee for the cart and returns it
	 *
	 * Currently short-circuits to return just the sign-up fee of the first subscription, because only
	 * one subscription can be purchased at a time.
	 */
	public static function get_cart_subscription_sign_up_fee( ?Cart $cart = null ): float {
		$sign_up_fee = 0;

		if ( ! $cart ) {
			$cart = StoreEngine::init()->get_cart();
		}

		if ( $cart && ( self::cart_contains_subscription( $cart ) || Utils::cart_contains_renewal( $cart ) ) ) {
			$renewal_item = Utils::cart_contains_renewal( $cart );

			foreach ( $cart->get_cart_items() as $cart_item ) {
				// Renewal items do not have sign-up fees
				if ( $renewal_item === $cart_item ) {
					continue;
				}

				if ( $cart_item->setup_fee && $cart_item->setup_fee_price > 0 /*&& 'fee' === $cart_item->setup_fee_type*/ ) {
					$sign_up_fee += $cart_item->setup_fee_price;
				}
			}
		}

		return (float) apply_filters( 'storeengine/subscription/cart/sign_up_fee', $sign_up_fee );
	}

	/**
	 * Check whether the cart needs payment even if the order total is $0
	 *
	 * @param bool $needs_payment The existing flag for whether the cart needs payment or not.
	 * @param Cart $cart The WooCommerce cart object.
	 *
	 * @return bool
	 */
	public static function cart_needs_payment( bool $needs_payment, Cart $cart ): bool {

		// Skip checks if `needs_payment` is already set or cart total not 0.
		if ( false !== $needs_payment || 0 != (float) $cart->get_total( 'edit' ) ) {
			return $needs_payment;
		}

		// Skip checks if cart has no subscriptions.
		if ( ! self::cart_contains_subscription( $cart ) ) {
			return $needs_payment;
		}

		return apply_filters( 'storeengine/subscription/cart/needs_payment', $needs_payment, $cart );
	}

	public static function cart_product_price( $formatted_price, Price $price ) {
		if ( $price->is_subscription() ) {
			$formatted_price = Utils::subscription_price_html(
				$price->get_id(),
				[
					'price'           => $formatted_price,
					'tax_calculation' => StoreEngine::init()->get_cart()->get_tax_price_display_mode(),
				]
			);
		}

		return $formatted_price;
	}

	/**
	 * Check whether shipping should be charged on the initial order.
	 *
	 * When the cart contains a physical subscription with a free trial and no other physical items, shipping
	 * should not be charged up-front.
	 *
	 * @internal self::all_cart_items_have_free_trial() is false if non-subscription products are in the cart.
	 */
	public static function charge_shipping_up_front( $cart ) {
		return apply_filters( 'storeengine/subscription/cart/shipping_up_front', ! self::all_cart_items_have_free_trial( $cart ) );
	}

	/**
	 * Check whether all the subscription product items in the cart have a free trial.
	 *
	 * Useful for determining if certain up-front amounts should be charged.
	 */
	public static function all_cart_items_have_free_trial( $cart ): bool {
		return (bool) apply_filters( 'storeengine/subscription/all_cart_items_have_free_trial', true, $cart );
	}

	/**
	 * Stores shipping info on the subscription
	 *
	 * @param Subscription $subscription instance of a subscriptions object
	 * @param Cart $cart A cart with recurring items in it
	 *
	 * @throws StoreEngineException
	 */
	public static function add_shipping( Subscription $subscription, Cart $cart ) {

		// We need to make sure we only get recurring shipping packages
		self::set_calculation_type( 'recurring_total' );
		self::set_recurring_cart_key( $cart->recurring_cart_key );
		$chosen_methods = $cart->get_meta( 'chosen_shipping_methods' );

		if ( $cart->needs_shipping() ) {
			foreach ( $cart->get_shipping_packages() as $recurring_cart_package_key => $recurring_cart_package ) {
				$package_index      = $recurring_cart_package['package_index'] ?? 0;
				$package            = Shipping::init()->calculate_shipping_for_package( $recurring_cart_package );
				$shipping_method_id = $chosen_methods[ $package_index ] ?? '';

				if ( isset( $chosen_methods[ $recurring_cart_package_key ] ) ) {
					$shipping_method_id = $chosen_methods[ $recurring_cart_package_key ];
					$package_key        = $recurring_cart_package_key;
				} else {
					$package_key = $package_index;
				}

				if ( isset( $package['rates'][ $shipping_method_id ] ) ) {
					$shipping_rate = $package['rates'][ $shipping_method_id ];
					$item          = new OrderItemShipping();
					$item->set_props( [
						'method_title' => $shipping_rate->label,
						'total'        => Formatting::format_decimal( $shipping_rate->cost ),
						'taxes'        => [ 'total' => $shipping_rate->taxes ],
						'order_id'     => $subscription->get_id(),
					] );
					$item->set_method_id( $shipping_rate->method_id );
					$item->set_instance_id( $shipping_rate->instance_id );

					foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
						$item->add_meta_data( $key, $value, true );
					}

					$subscription->add_item( $item );

					$item->save();

					//do_action( 'storeengine/frontend/checkout/order/create_order_shipping_item', $item, $recurring_cart_package, $subscription );
					do_action( 'storeengine/subscription/checkout/create_subscription_shipping_item', $item, $package_key, $package, $subscription );
				}
			}
		}

		self::set_calculation_type( 'none' );
		self::set_recurring_cart_key( 'none' );
	}
}

// End of file hooks.php
