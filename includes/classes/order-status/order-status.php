<?php

declare( strict_types=1 );

namespace StoreEngine\Classes\OrderStatus;

/**
 * Enum class for all the order statuses.
 *
 * For a full documentation on the public order statuses, please refer to the following link:
 * https://woocommerce.com/document/managing-orders/order-statuses/
 */
final class OrderStatus {

	/**
	 * The order is an automatically generated draft.
	 *
	 * @var string
	 */
	const AUTO_DRAFT = 'auto-draft';

	/**
	 * Draft orders are created when customers start the checkout process while the block version of the checkout is in place.
	 *
	 * @var string
	 */
	const DRAFT = 'draft';

	/**
	 * The order has been received, but no payment has been made.
	 *
	 * @var string
	 */
	const PAYMENT_PENDING = 'pending_payment';

	/**
	 * Payment has been received or confirmed by gateway.
	 *
	 * @var string
	 */
	const PAYMENT_CONFIRMED = 'payment_confirmed';

	/**
	 * The customer’s payment failed or was declined, and no payment has been successfully made.
	 *
	 * @var string
	 */
	const PAYMENT_FAILED = 'payment_failed';

	/**
	 * Payment has been received (paid), and the stock has been reduced.
	 *
	 * @var string
	 */
	const PROCESSING = 'processing';

	/**
	 * The order is awaiting payment confirmation.
	 *
	 * @var string
	 */
	const ON_HOLD = 'on_hold';

	/**
	 * Order fulfilled and complete.
	 *
	 * @var string
	 */
	const COMPLETED = 'completed';

	/**
	 * Orders are automatically put in the Refunded status when an admin or shop manager has fully refunded the order’s value after payment.
	 *
	 * @var string
	 */
	const REFUNDED = 'refunded';

	/**
	 * The order was canceled by an admin or the customer.
	 *
	 * @var string
	 */
	const CANCELLED = 'cancelled';

	/**
	 * The order is in the trash.
	 *
	 * @var string
	 */
	const TRASH = 'trash';

	/**
	 * Get all order statuses.
	 *
	 * @used-by Order::set_status
	 * @return array
	 */
	public static function get_order_statuses(): array {
		$order_statuses = [
			self::AUTO_DRAFT        => _x( 'Auto Draft', 'Order status', 'storeengine' ),
			self::DRAFT             => _x( 'Draft', 'Order status', 'storeengine' ),
			self::PAYMENT_PENDING   => _x( 'Pending payment', 'Order status', 'storeengine' ),
			self::PAYMENT_CONFIRMED => _x( 'Payment confirmed', 'Order status', 'storeengine' ),
			self::PROCESSING        => _x( 'Processing', 'Order status', 'storeengine' ),
			self::ON_HOLD           => _x( 'On hold', 'Order status', 'storeengine' ),
			self::COMPLETED         => _x( 'Completed', 'Order status', 'storeengine' ),
			self::CANCELLED         => _x( 'Cancelled', 'Order status', 'storeengine' ),
			self::REFUNDED          => _x( 'Refunded', 'Order status', 'storeengine' ),
			self::PAYMENT_FAILED    => _x( 'Failed', 'Order status', 'storeengine' ),
			self::TRASH             => _x( 'Trash', 'Order status', 'storeengine' ),
		];

		return apply_filters( 'storeengine/order_statuses', $order_statuses );
	}


	/**
	 * See if a string is an order status.
	 *
	 * @param string $maybe_status Status, including any wc- prefix.
	 *
	 * @return bool
	 */
	public static function is_order_status( string $maybe_status ): bool {
		$order_statuses = self::get_order_statuses();

		return isset( $order_statuses[ $maybe_status ] );
	}

	/**
	 * Get list of statuses which are consider 'paid'.
	 *
	 * @return array
	 */
	public static function get_is_paid_statuses(): array {
		/**
		 * Filter the list of statuses which are considered 'paid'.
		 *
		 * @param array $statuses List of statuses.
		 */
		return apply_filters( 'storeengine/order_is_paid_statuses', [ self::PROCESSING, self::COMPLETED ] );
	}

	/**
	 * Get list of statuses which are consider 'pending payment'.
	 *
	 * @return array
	 */
	public static function get_is_pending_statuses(): array {
		/**
		 * Filter the list of statuses which are considered 'pending payment'.
		 *
		 * @param array $statuses List of statuses.
		 */
		return apply_filters( 'storeengine/order_is_pending_statuses', [ self::PAYMENT_PENDING ] );
	}


	/**
	 * Get the nice name for an order status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	public static function get_order_status_name( string $status ): string {
		// "Special statuses": these are in common usage across WooCommerce, but are not normally returned by
		/** @see wc_get_order_statuses(). */
		$special_statuses = [
			self::DRAFT      => __( 'Draft', 'storeengine' ),
			self::AUTO_DRAFT => __( 'Auto Draft', 'storeengine' ),
			self::TRASH      => __( 'Trash', 'storeengine' ),
		];

		// Merge order is important. If the special statuses are ever returned by wc_get_order_statuses(), those definitions
		// should take priority.
		$statuses = array_merge( $special_statuses, self::get_order_statuses() );

		return $statuses[ $status ] ?? $status;
	}
}
