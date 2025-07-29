<?php

namespace StoreEngine\Utils;

class Constants {
	const ORDER_STATUS_PROCESSING = 'processing';

	const ORDER_STATUS_COMPLETED = 'completed';

	const ORDER_STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';

	const ORDER_STATUS_DRAFT = 'draft';

	const ORDER_STATUS_PENDING_PAYMENT = 'pending_payment';

	const ORDER_STATUS_PAID = 'paid';

	const ORDER_STATUS_ON_HOLD = 'on_hold';

	const ORDER_STATUS_CANCELED = 'canceled';

	const ORDER_STATUS_REFUNDED = 'refunded';

	const ORDER_STATUS_FAILED = 'failed';

	const ORDER_STATUS_TRASH = 'trash';

	const BILLING_ADDRESS_TYPE = 'billing_address';

	const SHIPPING_ADDRESS_TYPE = 'shipping_address';

	const ORDER_STATUS_ACTIVE = 'active';

	const ORDER_STATUS_CANCELLED = 'cancelled';

	const ORDER_STATUS_EXPIRED = 'expired';

	const ORDER_STATUS_RENEWED = 'renewed';

	// Shipping statuses
	const READY_FOR_SHIP = 'ready_for_ship';

	const AWAITING_SHIPMENT = 'awaiting_shipment';

	const SHIPPED = 'shipped';

	const ON_THE_WAY = 'on_the_way';

	const OUT_FOR_DELIVERY = 'out_for_delivery';

	const DELIVERED = 'delivered';

	const RETURNED = 'returned';

	// @TODO Need implementation.
	const SUBSCRIPTION_STATUS_ACTIVE = 'active';

	const SUBSCRIPTION_STATUS_PENDING = 'pending';

	const SUBSCRIPTION_STATUS_PENDING_PAYMENT = 'pending_payment';

	const SUBSCRIPTION_STATUS_RENEWED = 'renewed';

	const SUBSCRIPTION_STATUS_EXPIRED = 'expired';

	const SUBSCRIPTION_STATUS_ON_HOLD = 'on_hold';

	const SUBSCRIPTION_STATUS_DRAFT = 'draft';

	const SUBSCRIPTION_STATUS_ARCHIVE = 'archive';

	const SUBSCRIPTION_STATUS_CANCELLED = 'cancelled';

	/**
	 * A container for all defined constants.
	 *
	 * @access public
	 * @static
	 *
	 * @var array
	 */
	public static array $set_constants = [];

	/**
	 * Checks if a "constant" has been set in constants Manager
	 * and has a truthy value (e.g. not null, not false, not 0, any string).
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool
	 */
	public static function is_true( string $name ): bool {
		return self::is_defined( $name ) && self::get_constant( $name );
	}

	/**
	 * Checks if a "constant" has been set in constants Manager, and if not,
	 * checks if the constant was defined with define( 'name', 'value ).
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool
	 */
	public static function is_defined( string $name ): bool {
		return array_key_exists( $name, self::$set_constants ) || defined( $name );
	}

	/**
	 * Attempts to retrieve the "constant" from constants Manager, and if it hasn't been set,
	 * then attempts to get the constant with the constant() function. If that also hasn't
	 * been set, attempts to get a value from filters.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return mixed null if the constant does not exist or the value of the constant.
	 */
	public static function get_constant( string $name ) {
		if ( array_key_exists( $name, self::$set_constants ) ) {
			return self::$set_constants[ $name ];
		}

		if ( defined( $name ) ) {
			return constant( $name );
		}

		/**
		 * Filters the value of the constant.
		 *
		 * @param null The constant value to be filtered. The default is null.
		 * @param String $name The constant name.
		 */
		return apply_filters( 'storeengine/constant/default_value', null, $name );
	}

	/**
	 * Sets the value of the "constant" within constants Manager.
	 *
	 * @param string $name The name of the constant.
	 * @param int|float|string|bool|array|null $value The value of the constant.
	 */
	public static function set_constant( string $name, $value ) {
		self::$set_constants[ $name ] = $value;
	}

	/**
	 * Will unset a "constant" from constants Manager if the constant exists.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool Whether the constant was removed.
	 */
	public static function clear_single_constant( string $name ): bool {
		if ( ! array_key_exists( $name, self::$set_constants ) ) {
			return false;
		}

		unset( self::$set_constants[ $name ] );

		return true;
	}

	/**
	 * Resets all the constants within constants Manager.
	 */
	public static function clear_constants() {
		self::$set_constants = [];
	}
}
