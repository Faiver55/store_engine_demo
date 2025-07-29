<?php

namespace StoreEngine\Addons\Subscription\Classes;

use StoreEngine\Classes\AbstractCollection;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @method array<Subscription> get_results()
 */
class SubscriptionCollection extends AbstractCollection {
	protected string $table       = 'storeengine_orders';
	protected string $object_type = 'subscription';
	protected string $meta_type   = 'order';
	protected string $primary_key = 'id';
	protected string $returnType  = Subscription::class; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected array $must_where = [
		'relation' => 'AND',
		[
			'key'     => 'type',
			'value'   => 'subscription',
			'compare' => '=',
		],
	];

	public static function order_has_subscription( int $order_id ): bool {
		try {
			$order = Helper::get_order( $order_id );
			foreach ( $order->get_items() as $item ) {
				/** @var OrderItemProduct $order_item */
				if ( 'subscription' === $item->get_price_type() ) {
					return true;
				}
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// No op.
		}

		try {
			$subscription = new Subscription( $order_id );
			if ( $subscription->is_type( 'subscription' ) ) {
				return true;
			}
		} catch ( StoreEngineException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// No op.
		}

		return self::order_contains_subscription( $order_id );
	}

	public static function is_subscription( $order ): bool {
		try {
			$subscription = new Subscription( $order );

			return $subscription->is_type( 'subscription' );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public static function order_contains_subscription( int $order_id, $order_type = [ 'parent', 'resubscribe', 'switch' ] ): bool {
		$order = Helper::get_order( $order_id );

		if ( is_string( $order_type ) ) {
			$order_type = [ $order_type ];
		}

		if ( ! $order || is_wp_error( $order ) ) {
			return false;
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'parent', $order_type, true ) ) {
			// if order contains subscription.
			$query = new self( [
				'fields'   => 'ids',
				'per_page' => - 1,
				'where'    => [
					[
						'relation' => 'AND',
						[
							'key'     => 'parent_order_id',
							'value'   => $order_id,
							'compare' => '=',
							'type'    => 'NUMERIC',
						],
					],
				],
			] );

			if ( $query->have_results() ) {
				return true;
			}

			return false;
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'renewal', $order_type, true ) ) {
			return (bool) $order->get_meta( '_subscription_renewal' );
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'resubscribe', $order_type, true ) ) {
			return (bool) $order->get_meta( '_subscription_resubscribe' );
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'switch', $order_type, true ) ) {
			return (bool) $order->get_meta( '_subscription_switch' );
		}

		return false;
	}

	/**
	 * @param $order_id
	 * @param array|string $order_type
	 *
	 * @return Subscription[]
	 * @throws StoreEngineException
	 */
	public static function get_subscriptions_for_order( $order_id, $order_type = [ 'parent', 'resubscribe', 'switch' ] ): array {
		$subscriptions = [];

		if ( is_string( $order_type ) ) {
			$order_type = [ $order_type ];
		}

		$order = Helper::get_order( $order_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return $subscriptions;
		}

		$query = new self( [
			'per_page' => - 1,
			'where'    => [
				'relation' => 'AND',
				[
					'key'     => 'parent_order_id',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		if ( in_array( 'any', $order_type, true ) || in_array( 'parent', $order_type, true ) ) {
			$subscriptions = array_merge( $subscriptions, $query->get_results() );
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'renewal', $order_type, true ) ) {
			$renewal = $order->get_meta( '_subscription_renewal' );
			if ( $renewal ) {
				$subscriptions[ $renewal ] = new Subscription( $renewal );
			}
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'resubscribe', $order_type, true ) ) {
			$resubscribe = $order->get_meta( '_subscription_resubscribe' );
			if ( $resubscribe ) {
				$subscriptions[ $resubscribe ] = new Subscription( $resubscribe );
			}
		}

		if ( in_array( 'any', $order_type, true ) || in_array( 'switch', $order_type, true ) ) {
			$switch = $order->get_meta( '_subscription_switch' );
			if ( $switch ) {
				$subscriptions[ $switch ] = new Subscription( $switch );
			}
		}

		return $subscriptions;
	}

	public static function order_contains_renewal( $order_id ): bool {
		return $order_id && ! empty( self::get_subscriptions_for_renewal_order( $order_id ) );
	}

	public static function get_subscriptions_for_renewal_order( $order_id ): array {
		$query = new OrderCollection( [
			'per_page'   => - 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'     => '_subscription_renewal',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		], 'order' );

		$subscriptions = [];

		if ( $query->have_results() ) {
			foreach ( $query->get_results() as $order ) {
				if ( $order->get_meta( '_subscription_renewal' ) ) {
					$subscriptions[] = new Subscription( $order->get_meta( '_subscription_renewal' ) );
				}
			}
		}

		return $subscriptions;
	}

	public static function get_subscriptions_for_resubscribe_order( $order_id ): array {
		$query = new OrderCollection( [
			'per_page'   => - 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'     => '_subscription_resubscribe',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		], 'order' );

		$subscriptions = [];

		if ( $query->have_results() ) {
			foreach ( $query->get_results() as $order ) {
				$subscriptions[] = new Subscription( $order->get_meta( '_subscription_resubscribe' ) );
			}
		}

		return $subscriptions;
	}

	public static function get_subscriptions_for_switch_order( $order_id ): array {
		$query = new OrderCollection( [
			'per_page'   => - 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'     => '_subscription_switch',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		], 'order' );

		$subscriptions = [];

		if ( $query->have_results() ) {
			foreach ( $query->get_results() as $order ) {
				$subscriptions[] = new Subscription( $order->get_meta( '_subscription_switch' ) );
			}
		}

		return $subscriptions;
	}

	protected static ?array $statuses = null;

	/**
	 * Return an array of subscription status types, similar to @see wc_get_order_statuses()
	 *
	 * @return array
	 */
	public static function get_subscription_statuses(): array {
		if ( null === self::$statuses ) {
			self::$statuses = [
				'pending'        => _x( 'Pending', 'Subscription status', 'storeengine' ),
				'active'         => _x( 'Active', 'Subscription status', 'storeengine' ),
				'on_hold'        => _x( 'On hold', 'Subscription status', 'storeengine' ),
				'cancelled'      => _x( 'Cancelled', 'Subscription status', 'storeengine' ),
				'switched'       => _x( 'Switched', 'Subscription status', 'storeengine' ),
				'expired'        => _x( 'Expired', 'Subscription status', 'storeengine' ),
				'pending_cancel' => _x( 'Pending Cancellation', 'Subscription status', 'storeengine' ),
			];
		}

		return apply_filters( 'storeengine/subscription/statuses', self::$statuses );
	}

	public static function get_subscription_status_keys(): array {
		return array_keys( self::get_subscription_statuses() );
	}

	/**
	 * Get the nice name for a subscription's status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function get_subscription_status_name( string $status ): string {
		$statuses = self::get_subscription_statuses();

		// if the sanitized status key is not in the list of filtered subscription names, return the
		$status_name = $statuses[ $status ] ?? $status;

		return apply_filters( 'storeengine/subscription/status_name', $status_name, $status );
	}

	/**
	 * Return an array statuses used to describe when a subscriptions has been marked as ending or has ended.
	 *
	 * @return array
	 */
	public static function get_ended_statuses(): array {
		return apply_filters( 'storeengine/subscription/ended_statuses', [ 'cancelled', 'trash', 'expired', 'switched', 'pending_cancel' ] );
	}
}

// End of file subscription-collection.php.
