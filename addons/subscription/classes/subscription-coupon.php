<?php

namespace StoreEngine\Addons\Subscription\Classes;

use StoreEngine\Addons\Subscription\Hooks;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Discounts;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\AbstractOrderItem;
use StoreEngine\Traits\Singleton;

class SubscriptionCoupon {
	use Singleton;

	/**
	 * Subscription coupon types.
	 *
	 * @var array
	 */
	private static array $recurring_coupons = [
		'recurring_fee'     => 1,
		'recurring_percent' => 1,
	];

	protected function __construct() {
		// Validate subscription coupons
		add_filter( 'storeengine/coupon_is_valid', [ __CLASS__, 'validate_subscription_coupon' ], 10, 3 );
	}

	public static function remove_coupons( Cart $cart ) {
		$calculation_type = Hooks::get_calculation_type();
		// Only hook when totals are being calculated completely (on cart & checkout pages)
		if ( 'none' === $calculation_type || ! Hooks::cart_contains_subscription() || empty( $cart->recurring_cart_key ) ) {
			return;
		}

		$applied_coupons = $cart->get_coupons();
		if ( empty( $applied_coupons ) ) {
			return;
		}

		// If we're calculating a sign-up fee or recurring fee only amount, remove irrelevant coupons
		foreach ( $applied_coupons as $coupon ) {
			$coupon_type = $coupon->get_discount_type();

			/**
			 * Filters whether the coupon should be allowed to be removed.
			 *
			 * @param bool $bypass_removal Whether to bypass removing the coupon.
			 * @param Coupon $coupon The coupon object.
			 * @param string $coupon_type The coupon's discount_type property.
			 * @param string $calculation_type The current calculation type.
			 */
			if ( apply_filters( 'storeengine/subscription/bypass_coupon_removal', false, $coupon, $coupon_type, $calculation_type, $cart ) ) {
				continue;
			}

			if ( ! isset( self::$recurring_coupons[ $coupon_type ] ) ) {
				$cart->remove_coupon( $coupon->get_code() );
				continue;
			}

			if ( 'recurring_total' === $calculation_type || ! Hooks::all_cart_items_have_free_trial( $cart ) ) {
				continue;
			}

			$cart->remove_coupon( $coupon->get_code() );
		}
	}

	public static function validate_subscription_coupon( bool $valid, Coupon $coupon, Discounts $discount ): bool {
		if ( ! apply_filters( 'storeengine/subscription/validate_coupon_type', true, $coupon, $valid ) ) {
			return $valid;
		}

		$discount_items = $discount->get_items();

		if ( ! empty( $discount_items ) ) {
			$item = reset( $discount_items );

			if ( isset( $item->object ) && is_a( $item->object, AbstractOrderItem::class ) ) {
				$valid = self::validate_subscription_coupon_for_order( $valid, $coupon, $item->object->get_order() );
			} else {
				$valid = self::validate_subscription_coupon_for_cart( $valid, $coupon );
			}
		}

		return $valid;
	}

	/**
	 * Check if a subscription coupon is valid for the cart.
	 *
	 * @param boolean $valid
	 * @param Coupon $coupon
	 *
	 * @return bool whether the coupon is valid
	 * @throws StoreEngineException
	 */
	public static function validate_subscription_coupon_for_cart( bool $valid, Coupon $coupon ): bool {
		$coupon_error = '';
		$error_code   = '';
		$coupon_type  = $coupon->get_discount_type();

		// ignore non-subscription coupons
		if ( ! in_array( $coupon_type, [ 'recurring_fee', 'sign_up_fee', 'recurring_percent', 'sign_up_fee_percent', 'renewal_fee', 'renewal_percent', 'renewal_cart', 'initial_cart' ] ) ) {
			// but make sure there is actually something for the coupon to be applied to (i.e. not a free trial)
			if ( ( Utils::cart_contains_renewal() || Hooks::cart_contains_subscription() ) && 0 == \StoreEngine::init()->get_cart()->get_subtotal( 'edit' ) ) {
				$coupon_error = __( 'Sorry, this coupon is only valid for an initial payment and the cart does not require an initial payment.', 'storeengine' );
				$error_code   = 'only-valid-for-initial-payment';
				$error_code   = '';
			}
		} else {
			// prevent subscription coupons from being applied to renewal payments
			if ( Utils::cart_contains_renewal() && ! in_array( $coupon_type, [ 'renewal_fee', 'renewal_percent', 'renewal_cart' ] ) ) {
				$coupon_error = __( 'Sorry, this coupon is only valid for new subscriptions.', 'storeengine' );
				$error_code   = 'only-valid-for-new-subscriptions';
			}

			// prevent subscription coupons from being applied to non-subscription products
			if ( ! Utils::cart_contains_renewal() && ! Hooks::cart_contains_subscription() ) {
				$coupon_error = __( 'Sorry, this coupon is only valid for subscription products.', 'storeengine' );
				$error_code   = 'only-valid-for-subscriptions';
			}

			// prevent subscription renewal coupons from being applied to non renewal payments
			if ( ! Utils::cart_contains_renewal() && in_array( $coupon_type, [ 'renewal_fee', 'renewal_percent', 'renewal_cart' ] ) ) {
				// translators: 1$: coupon code that is being removed
				$coupon_error = sprintf( __( 'Sorry, the "%1$s" coupon is only valid for renewals.', 'storeengine' ), $coupon->get_code() );
				$error_code   = 'only-valid-for-renewal';
			}

			// prevent sign up fee coupons from being applied to subscriptions without a sign-up fee
			if ( 0 == Hooks::get_cart_subscription_sign_up_fee() && in_array( $coupon_type, [ 'sign_up_fee', 'sign_up_fee_percent' ] ) ) {
				$coupon_error = __( 'Sorry, this coupon is only valid for subscription products with a sign-up fee.', 'storeengine' );
				$error_code   = 'only-valid-for-subscription-sign-up-fee';
			}
		}

		if ( ! empty( $coupon_error ) ) {
			throw new StoreEngineException( $coupon_error, $error_code );
		}

		return $valid;
	}

	/**
	 * Check if a subscription coupon is valid for an order/subscription.
	 *
	 * @param Coupon $coupon The subscription coupon being validated. Can accept recurring_fee, recurring_percent, sign_up_fee or sign_up_fee_percent coupon types.
	 * @param Order|Subscription $order The order or subscription object to which the coupon is being applied
	 *
	 * @return bool whether the coupon is valid
	 * @throws StoreEngineException
	 */
	public static function validate_subscription_coupon_for_order( bool $valid, Coupon $coupon, $order ): bool {
		$error_message = '';
		$error_code    = '';
		$coupon_type   = $coupon->get_discount_type();

		// Recurring coupons can be applied to subscriptions and renewal orders
		if ( in_array( $coupon_type, [ 'recurring_fee', 'recurring_percent' ] ) && ! ( ( $order instanceof Subscription || $order->is_type( 'subscription' ) ) || SubscriptionCollection::order_contains_subscription( $order->get_id(), 'any' ) ) ) {
			$error_message = __( 'Sorry, recurring coupons can only be applied to subscriptions or subscription orders.', 'storeengine' );
			// Sign-up fee coupons can be applied to parent orders which contain subscription products with at least one sign up fee
		} elseif ( in_array( $coupon_type, [ 'sign_up_fee', 'sign_up_fee_percent' ] ) && ! ( SubscriptionCollection::order_contains_subscription( $order->get_id(), 'parent' ) || 0 != Utils::get_sign_up_fee_from_order( $order ) ) ) {
			// translators: placeholder is coupon code
			$error_message = sprintf( __( 'Sorry, "%s" can only be applied to subscription parent orders which contain a product with signup fees.', 'storeengine' ), $coupon->get_code() );
			// Only recurring coupons can be applied to subscriptions
		} elseif ( ! in_array( $coupon_type, [ 'recurring_fee', 'recurring_percent' ] ) && ( $order instanceof Subscription || $order->is_type( 'subscription' ) ) ) {
			$error_message = __( 'Sorry, only recurring coupons can be applied to subscriptions.', 'storeengine' );
		}

		if ( ! empty( $error_message ) ) {
			throw new StoreEngineException( $error_message, $error_code );
		}

		return $valid;
	}
}

// End of file subscription-coupon.php
