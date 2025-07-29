<?php

namespace StoreEngine\Frontend;

use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

class Coupon {

	public static function init() {
		add_action( 'storeengine/validate_coupon', array( __CLASS__, 'check_cart_minimum_requirement' ) );
	}

	/**
	 * @throws StoreEngineException
	 */
	public static function check_cart_minimum_requirement( \StoreEngine\classes\Coupon $coupon ) {
		if ( 'none' !== $coupon->settings['coupon_type_of_min_requirement'] ) {
			$cart          = Helper::cart();
			$cart_subtotal = (float) $cart->get_cart_subtotal();

			self::check_minimum_requirements( $coupon, $cart->get_count(), $cart_subtotal);
		}
	}


	/**
	 * @param \StoreEngine\Classes\Coupon $coupon
	 * @param $total_quantity
	 * @param $subtotal
	 * @return void
	 * @throws StoreEngineException
	 */
	public static function check_minimum_requirements( \StoreEngine\Classes\Coupon $coupon, $total_quantity, $subtotal ) {
		$minimum_purchase_quantity = $coupon->settings['coupon_min_purchase_quantity'];
		if ( 'quantity' === $coupon->settings['coupon_type_of_min_requirement'] && $total_quantity < $minimum_purchase_quantity ) {
			throw new StoreEngineException( esc_html__( 'Sorry, Coupon has minimum purchase quantity', 'storeengine' ), 'min-purchase-qty', null, 400 );
		}

		$minimum_purchase_amount = $coupon->settings['coupon_min_purchase_amount'] ?? 0;
		if ( 'amount' === $coupon->settings['coupon_type_of_min_requirement'] && $subtotal < (float) $minimum_purchase_amount ) {
			throw new StoreEngineException( esc_html__( 'Sorry, Coupon has minimum purchase amount', 'storeengine' ), 'min-purchase-amount', null, 400 );
		}
	}

}
