<?php

namespace StoreEngine\Addons\Subscription\Classes;

use DateTimeZone;
use Exception;
use StoreEngine\Addons\Subscription\Events\CreateSubscription;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order\AbstractOrderItem;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\Constants;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscription extends Order {

	public static array $whitelisted_status_list = [
		Constants::SUBSCRIPTION_STATUS_ACTIVE,
		Constants::SUBSCRIPTION_STATUS_PENDING,
		Constants::SUBSCRIPTION_STATUS_PENDING_PAYMENT,
		Constants::SUBSCRIPTION_STATUS_RENEWED,
		Constants::SUBSCRIPTION_STATUS_EXPIRED,
		Constants::SUBSCRIPTION_STATUS_ON_HOLD,
		Constants::SUBSCRIPTION_STATUS_DRAFT,
		Constants::SUBSCRIPTION_STATUS_ARCHIVE,
		Constants::SUBSCRIPTION_STATUS_CANCELLED,
	];

	private ?array $cached_payment_count = null;

	protected string $object_type = 'subscription';

	public function __construct( $read = 0 ) {
		$this->extra_data         = array_merge( $this->extra_data, [
			'next_payment_date'       => null,
			'last_payment_date'       => null,
			'start_date'              => null,
			'end_date'                => null,
			'trial'                   => false,
			'trial_days'              => 0,
			'trial_end_date'          => null,
			'cancelled_date'          => null,
			'payment_retry_date'      => null,
			'payment_duration'        => 1,
			'payment_duration_type'   => 'month',
			'initial_order_id'        => 0,
			'requires_manual_renewal' => false,
			'suspension_count'        => 0,
			'related_order_ids'       => [],
		] );
		$this->internal_meta_keys = array_merge( $this->internal_meta_keys, [
			'_next_payment_date',
			'_last_payment_date',
			'_start_date',
			'_end_date',
			'_trial',
			'_trial_days',
			'_trial_end_date',
			'_cancelled_date',
			'_payment_retry_date',
			'_payment_duration',
			'_payment_duration_type',
			'_initial_order_id',
			'_requires_manual_renewal',
			'_suspension_count',
			'_related_order_ids',
		] );
		$this->meta_key_to_props  = array_merge( $this->meta_key_to_props, [
			'_next_payment_date'       => 'next_payment_date',
			'_last_payment_date'       => 'last_payment_date',
			'_start_date'              => 'start_date',
			'_end_date'                => 'end_date',
			'_trial'                   => 'trial',
			'_trial_days'              => 'trial_days',
			'_trial_end_date'          => 'trial_end_date',
			'_cancelled_date'          => 'cancelled_date',
			'_payment_retry_date'      => 'payment_retry_date',
			'_payment_duration'        => 'payment_duration',
			'_payment_duration_type'   => 'payment_duration_type',
			'_initial_order_id'        => 'initial_order_id',
			'_requires_manual_renewal' => 'requires_manual_renewal',
			'_suspension_count'        => 'suspension_count',
			'_related_order_ids'       => 'related_order_ids',
		] );

		parent::__construct( $read );
	}

	protected function read_data(): array {
		return array_merge(
			parent::read_data(),
			[
				'next_payment_date'       => $this->get_metadata( '_next_payment_date' ),
				'last_payment_date'       => $this->get_metadata( '_last_payment_date' ),
				'start_date'              => $this->get_metadata( '_start_date' ),
				'end_date'                => $this->get_metadata( '_end_date' ),
				'trial_end_date'          => $this->get_metadata( '_trial_end_date' ),
				'trial'                   => $this->get_metadata( '_trial' ),
				'trial_days'              => $this->get_metadata( '_trial_days' ),
				'cancelled_date'          => $this->get_metadata( '_cancelled_date' ),
				'payment_retry_date'      => $this->get_metadata( '_payment_retry_date' ),
				'payment_duration'        => $this->get_metadata( '_payment_duration' ),
				'payment_duration_type'   => $this->get_metadata( '_payment_duration_type' ),
				'initial_order_id'        => $this->get_metadata( '_initial_order_id' ),
				'requires_manual_renewal' => $this->get_metadata( '_requires_manual_renewal' ),
				'suspension_count'        => $this->get_metadata( '_suspension_count' ),
				'related_order_ids'       => $this->get_metadata( '_related_order_ids' ),
			]
		);
	}

	/**
	 * @param string $context
	 *
	 * @return StoreEngineDateTime|NULL
	 */
	public function get_next_payment_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'next_payment_date', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return StoreEngineDateTime|NULL
	 */
	public function get_last_payment_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'last_payment_date', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return StoreEngineDateTime|NULL
	 */
	public function get_start_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'start_date', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return StoreEngineDateTime|NULL
	 */
	public function get_end_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'end_date', $context );
	}

	public function get_payment_duration( string $context = 'view' ) {
		return $this->get_prop( 'payment_duration', $context );
	}

	public function get_payment_duration_type( string $context = 'view' ) {
		$value = $this->get_prop( 'payment_duration_type', $context );
		if ( ! in_array( $value, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$old_values = [
				'days'   => 'day',
				'weeks'  => 'week',
				'months' => 'month',
				'years'  => 'year',
			];
			$value      = $old_values[ $value ] ?? 'month';
		}

		return $value;
	}

	/**
	 * Get suspension count.
	 */
	public function get_suspension_count( string $context = 'view' ): int {
		return absint( $this->get_prop( 'suspension_count', $context ) );
	}

	public function get_trial( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'trial', $context ) );
	}

	public function get_trial_days( string $context = 'view' ) {
		return $this->get_prop( 'trial_days', $context );
	}

	public function get_trial_end_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'trial_end_date', $context );
	}

	public function get_cancelled_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'cancelled_date', $context );
	}

	public function get_payment_retry_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'payment_retry_date', $context );
	}

	public function get_initial_order_id( string $context = 'view' ) {
		return $this->get_prop( 'initial_order_id', $context );
	}

	/**
	 * @param string $order_type
	 *
	 * @return int[]
	 */
	public function get_related_order_ids( string $order_type = 'any' ): array {
		// @XXX maybe use related_order_ids prop or update tha prop.
		$related_order_ids = [];

		if ( in_array( $order_type, [ 'any', 'parent' ], true ) && $this->get_parent_id() ) {
			$parent_order = Helper::get_order( $this->get_parent_id() );

			if ( $parent_order && ! is_wp_error( $parent_order ) ) {
				$related_order_ids[ $this->get_parent_id() ] = $parent_order->get_id();
			}
		}

		if ( 'parent' !== $order_type ) {
			$relation_types = ( 'any' === $order_type ) ? [ 'renewal', 'resubscribe', 'switch' ] : [ $order_type ];
			foreach ( $relation_types as $relation_type ) {
				$ids = $this->get_meta( 'related_' . $relation_type . '_orders' );
				if ( empty( $ids ) || ! is_array( $ids ) ) {
					$query = new OrderCollection( [
						'fields'     => 'ids',
						'per_page'   => - 1,
						'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							[
								'key'   => '_subscription_' . $relation_type,
								'value' => $this->get_id(),
							],
						],
					] );

					$ids = $query->get_results();
				}

				$related_order_ids = array_merge( $related_order_ids, $ids );
			}
		}


		return array_map( 'absint', $related_order_ids );
	}

	/**
	 * Sets a date prop whilst handling formatting and datetime objects.
	 *
	 * @param string $prop Name of prop to set.
	 * @param string|int $value Value of the prop.
	 */
	protected function set_date_prop( string $prop, $value ) {
		try {
			if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
				$this->set_prop( $prop, null );

				if ( true === $this->object_read ) {
					$this->save_dates( $prop );
					/**
					 * Triggers when removed.
					 *
					 * @param Subscription $this Subscription
					 * @param string $prop Date type.
					 */
					do_action( 'storeengine/subscription/date_deleted', $this, $prop );
				}

				return;
			}

			$this->set_prop( $prop, Formatting::string_to_datetime( $value ) );

			if ( true === $this->object_read ) {
				$this->save_dates( $prop );
				/**
				 * Triggers when changed.
				 *
				 * @param Subscription $this Subscription
				 * @param string $prop Date type.
				 */
				do_action( 'storeengine/subscription/date_updated', $this, $prop, $value );
			}
		} catch ( Exception $e ) { // @codingStandardsIgnoreLine.
			// @TODO implement error logger
			Helper::log_error( $e );
		}
	}

	protected function save_dates( $prop ) {
		if ( ! $this->get_id() ) {
			return;
		}

		$value = $this->get_formatted_date_prop( $prop, 'mysql', true, 'update' );


		if ( ! $value ) {
			$value = null;
		}

		if ( 'date_created_gmt' === $prop || 'date_updated_gmt' === $prop ) {
			$this->wpdb->update( $this->table, [ $prop => $value ], [ $this->primary_key => $this->get_id() ], [ '%s' ], [ '%d' ] );

			return;
		}

		$meta_key = '_' . $prop;
		if ( ! array_key_exists( $meta_key, $this->meta_key_to_props ) ) {
			return;
		}

		if ( null === $value ) {
			delete_metadata( 'order', $this->get_id(), $meta_key );

			return;
		}

		update_metadata( 'order', $this->get_id(), $meta_key, $value );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_next_payment_date( $value ) {
		$this->set_date_prop( 'next_payment_date', $value );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_last_payment_date( $value ) {
		$this->set_date_prop( 'last_payment_date', $value );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_start_date( $value ) {
		$this->set_date_prop( 'start_date', $value );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_end_date( $value ) {
		$this->set_date_prop( 'end_date', $value );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_payment_duration( $value ) {
		$this->set_prop( 'payment_duration', absint( $value ) );
	}

	public function set_payment_duration_type( $value ) {
		if ( ! in_array( $value, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$old_values = [
				'days'   => 'day',
				'weeks'  => 'week',
				'months' => 'month',
				'years'  => 'year',
			];
			$value      = $old_values[ $value ] ?? 'month';
		}

		$this->set_prop( 'payment_duration_type', $value );
	}

	/**
	 * Set suspension count.
	 *
	 * @param int|string $value
	 */
	public function set_suspension_count( $value ) {
		$this->set_prop( 'suspension_count', absint( $value ) );
	}

	/**
	 * @param string|int|bool $value
	 */
	public function set_trial( $value ) {
		$this->set_prop( 'trial', Formatting::bool_to_string( $value ) );
	}

	/**
	 * @param string|int $value
	 *
	 * @return void
	 */
	public function set_trial_days( $value ) {
		$this->set_prop( 'trial_days', absint( $value ) );
	}

	/**
	 * @param string|integer|null $value
	 */
	public function set_trial_end_date( $value ) {
		$this->set_date_prop( 'trial_end_date', $value );
	}

	public function set_cancelled_date( $value ) {
		$this->set_date_prop( 'cancelled_date', $value );
	}

	public function set_payment_retry_date( $value ) {
		$this->set_date_prop( 'payment_retry_date', $value );
	}

	/**
	 * @param string|int $value
	 *
	 * @return void
	 */
	public function set_initial_order_id( $value ) {
		$this->set_prop( 'initial_order_id', absint( $value ) );
	}

	/**
	 * @param ?array|?string $value
	 *
	 * @return void
	 */
	public function set_related_order_ids( $value ) {
		$this->set_prop( 'related_order_ids', maybe_unserialize( $value ?? '' ) );
	}

	public function set_status( string $new_status, string $note = '', bool $manual_update = false ): array {
		if ( ! $this->object_read && in_array( $new_status, [ 'draft', 'auto-draft' ], true ) ) {
			$new_status = apply_filters( 'storeengine/subscription/default_status', 'pending' );
		}

		if ( 'pending_payment' === $new_status ) {
			$new_status = 'pending';
		}

		return parent::set_status( $new_status, $note, $manual_update );
	}

	/**
	 * Get all valid statuses for this subscription
	 *
	 * @return array Internal status keys e.g. 'pending, on_hold, active, cancelled, pending-cancel'
	 */
	public function get_valid_statuses(): array {
		return array_keys( SubscriptionCollection::get_subscription_statuses() );
	}

	// Helpers...

	/**
	 * @throws StoreEngineException
	 */
	public static function get_subscriptions_by_order_id( int $id ): array {
		global $wpdb;

		$results = [];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders o WHERE o.parent_order_id = %d AND o.type = %s GROUP BY o.id;", $id, 'subscription' ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $ids as $id ) {
			$results[ $id ] = new self( absint( $id ) );
		}

		return $results;
	}

	/**
	 * @param int $id
	 *
	 * @return self|null
	 * @throws StoreEngineException
	 */
	public static function get_subscription( int $id ): ?self {
		return new self( $id );
	}

	public static function get_subscriptions_by_status( ?string $status = null ): array {
		global $wpdb;

		$results = [];

		if ( empty( $status ) ) {
			$status = Constants::SUBSCRIPTION_STATUS_ACTIVE;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders o WHERE o.status = %s AND o.type = %s GROUP BY o.id;", $status, 'subscription' ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $ids as $id ) {
			$results[ $id ] = new self( absint( $id ) );
		}

		return $results;
	}

	public static function get_renewal_subscriptions( int $current_time ): array {
		global $wpdb;
		$query = ( new self() )->query();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"{$query}
						WHERE m.meta_key = 'next_payment_date'
							AND m.meta_value < %d
							AND o.type = %s
							AND o.status = %s;",
				$current_time,
				'subscription',
				Constants::SUBSCRIPTION_STATUS_ACTIVE
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$output = [];
		foreach ( $results as $result ) {
			$output[ $result['o_id'] ] = new self( absint( $result['o_id'] ) );
		}

		return $output;
	}


	public function get_related_orders( $order_types = [ 'parent', 'renewal', 'switch' ] ) {
		if ( ! is_array( $order_types ) ) {
			// Accept either an array or string (to make it more convenient for singular types, like 'parent' or 'any')
			$order_types = [ $order_types ];
		}

		$related_orders = [];
		foreach ( $order_types as $order_type ) {
			$related_orders += apply_filters(
				'storeengine/subscription/related_orders',
				$this->get_related_order_ids( $order_type ),
				$this,
				$order_type
			);
		}

		arsort( $related_orders );

		return $related_orders;
	}

	/**
	 * @param int $page
	 * @param int $per_page
	 * @param callable|null $cb
	 *
	 * @return array
	 * @deprecated
	 */
	public function _get_related_orders( int $page = 1, int $per_page = 10, ?callable $cb = null ): array {
		global $wpdb;

		if ( $per_page <= 0 ) {
			$per_page = 10;
		}

		if ( $page <= 0 ) {
			$page = 10;
		}

		if ( empty( $this->id ) ) {
			return [
				'total'  => 0,
				'orders' => [],
			];
		}

		$related_orders   = $this->get_related_order_ids();
		$initial_order_id = $this->get_initial_order_id();

		if ( $initial_order_id ) {
			$related_orders[] = $initial_order_id;
		}

		$related_orders = array_unique( array_filter( array_map( 'absint', $related_orders ) ) );

		if ( empty( $related_orders ) ) {
			return [
				'total'  => 0,
				'orders' => [],
			];
		}

		$count = count( $related_orders );

		if ( $count > $per_page ) {
			$related_orders = array_slice( $related_orders, $page * $per_page, $per_page );
		}

		$orders = [];
		foreach ( $related_orders as $id ) {
			try {
				if ( is_callable( $cb ) ) {
					$orders[ $id ] = $cb( new Order( $id ) );
				} else {
					$orders[ $id ] = new Order( $id );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// NO Op.
			}
		}

		return [
			'total'  => $count,
			'orders' => $orders,
		];
	}

	public static function validate_status( string $status, string $default = Constants::SUBSCRIPTION_STATUS_ON_HOLD ): string {
		return in_array( $status, self::$whitelisted_status_list, true ) ? $status : $default;
	}

	public function update_date(): void {
		$end_date = $this->get_end_date();

		if ( $end_date && $end_date->getTimestamp() >= time() ) {
			return;
		}

		$trial_end_timestamp = null;
		if ( $this->get_trial() ) {
			$trial_end_date = gmdate( 'c', $trial_end_timestamp = strtotime( "+{$this->get_trial_days()} day" ) );
			$this->set_trial_end_date( $trial_end_date );
		}

		$end_date = gmdate( 'c', strtotime( "+{$this->get_payment_duration()} {$this->get_payment_duration_type()}", $trial_end_timestamp ) );

		$this->set_start_date( gmdate( 'c' ) );
		$this->set_next_payment_date( $end_date );
		$this->set_end_date( $end_date );
	}

	/**
	 * Checks if the subscription requires manual renewal payments.
	 *
	 * @access public
	 *
	 * @param string $context
	 *
	 * @return bool
	 */
	public function get_requires_manual_renewal( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'requires_manual_renewal', $context ) );
	}

	/**
	 * Set the manual renewal flag on the subscription.
	 *
	 * The manual renewal flag is stored in database as string 'true' or 'false' when set, and empty string when not set
	 * (which means it doesn't require manual renewal), but we want to consistently use it via get/set as a boolean,
	 * for sanity's sake.
	 *
	 * @param string|bool $value
	 */
	public function set_requires_manual_renewal( $value ) {
		$this->set_prop( 'requires_manual_renewal', Formatting::bool_to_string( $value ) );
	}


	/**
	 * Checks if the subscription requires manual renewal payments.
	 *
	 * This differs to the @return bool
	 *
	 * @see self::get_requires_manual_renewal() method in that it also conditions outside
	 * of the 'requires_manual_renewal' property which would force a subscription to require manual renewal
	 * payments, like an inactive payment gateway or a site in staging mode.
	 *
	 * @access public
	 */
	public function is_manual(): bool {

		// @TODO check if duplicate site..
		if ( true === $this->get_requires_manual_renewal() || ! Helper::get_payment_gateway( $this->get_payment_method() ) ) {
			$is_manual = true;
		} else {
			$is_manual = false;
		}

		return apply_filters( 'storeengine/subscription/is_manual', $is_manual, $this );
	}

	/**
	 * Get the MySQL formatted date for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end'
	 * @param string $timezone The timezone of the $datetime param, either 'gmt' or 'site'. Default 'gmt'.
	 */
	public function get_date( string $date_type, string $timezone = 'gmt' ) {
		switch ( $date_type ) {
			case 'date_created':
				$date = $this->get_date_created_gmt();
				break;
			case 'date_modified':
				$date = $this->get_date_updated_gmt();
				break;
			case 'date_paid':
				$date = $this->get_date_paid_gmt();
				break;
			case 'date_completed':
				$date = $this->get_date_completed_gmt();
				break;
			case 'last_order_date_created':
				$date = $this->get_related_orders_date( 'date_created', 'last' );
				break;
			case 'last_order_date_paid':
				$date = $this->get_related_orders_date( 'date_paid', 'last' );
				break;
			case 'last_order_date_completed':
				$date = $this->get_related_orders_date( 'date_completed', 'last' );
				break;
			default:
				$method = 'get_' . $date_type . '_date';
				$date   = method_exists( $this, $method ) ? $this->$method() : null;
				break;
		}

		if ( is_null( $date ) ) {
			$date = 0;
		}

		if ( is_a( $date, 'DateTime' ) ) {
			// Don't change the original date object's timezone as this may affect the prop stored on the subscription
			$date = clone $date;

			if ( 'gmt' === strtolower( $timezone ) ) {
				$date->setTimezone( new DateTimeZone( 'UTC' ) );
			}

			$date = $date->date( 'Y-m-d H:i:s' );
		}

		return apply_filters( 'storeengine/subscription/get_' . $date_type . '_date', $date, $this, $timezone );
	}

	/**
	 * Get the timestamp for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created', 'end' or 'end_of_prepaid_term'
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 */
	public function get_time( string $date_type, string $timezone = 'gmt' ): int {
		return CreateSubscription::date_to_time( $this->get_date( $date_type, $timezone ) );
	}

	/**
	 * Check if the subscription's payment method supports a certain feature, like date changes.
	 *
	 * If the subscription uses manual renewals as the payment method, it supports all features.
	 * Otherwise, the feature will only be supported if the payment gateway set as the payment
	 * method supports for the feature.
	 *
	 * @param string $payment_gateway_feature one of:
	 *    'subscription_suspension'
	 *    'subscription_reactivation'
	 *    'subscription_cancellation'
	 *    'subscription_date_changes'
	 *    'subscription_amount_changes'
	 */
	public function payment_method_supports( string $payment_gateway_feature ) {
		$payment_gateway = Helper::get_payment_gateway( $this->get_payment_method() );
		if ( $this->is_manual() || ( null !== $payment_gateway && $payment_gateway->supports( $payment_gateway_feature ) ) ) {
			$payment_gateway_supports = true;
		} else {
			$payment_gateway_supports = false;
		}

		return apply_filters( 'storeengine/subscription/payment_gateway_supports', $payment_gateway_supports, $payment_gateway_feature, $this );
	}

	/**
	 * Check if a subscription can be changed to a new status or date
	 */
	public function can_be_updated_to( $new_status ) {
		switch ( $new_status ) {
			case 'pending':
				if ( $this->has_status( array( 'auto-draft', 'draft' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'completed': // core WC order status mapped internally to avoid exceptions
			case 'active':
				if ( $this->payment_method_supports( 'subscription_reactivation' ) && $this->has_status( 'on_hold' ) ) {
					$can_be_updated = true;
				} elseif ( $this->has_status( 'pending' ) ) {
					$can_be_updated = true;
				} elseif ( $this->has_status( 'pending_cancel' ) && ( $this->get_end_date() && $this->get_end_date()->getTimestamp() > gmdate( 'U' ) ) && ( $this->is_manual() || ( false === $this->payment_method_supports( 'gateway_scheduled_payments' ) && $this->payment_method_supports( 'subscription_date_changes' ) && $this->payment_method_supports( 'subscription_reactivation' ) ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'failed': // core WC order status mapped internally to avoid exceptions
			case 'on_hold':
				if ( $this->payment_method_supports( 'subscription_suspension' ) && $this->has_status( [
					'active',
					'pending',
				] ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'cancelled':
				if ( $this->payment_method_supports( 'subscription_cancellation' ) && ( $this->has_status( 'pending_cancel' ) || ! $this->has_status( SubscriptionCollection::get_ended_statuses() ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'pending_cancel':
				// Only active subscriptions can be given the "pending cancellation" status, because it is used to account for a prepaid term
				if ( $this->payment_method_supports( 'subscription_cancellation' ) ) {
					if ( $this->has_status( 'active' ) ) {
						$can_be_updated = true;
					} elseif ( ! $this->needs_payment() && $this->has_status( [ 'cancelled', 'on_hold' ] ) ) {
						// Payment completed and subscription is cancelled
						$can_be_updated = true;
					} else {
						$can_be_updated = false;
					}
				} else {
					$can_be_updated = false;
				}
				break;
			case 'expired':
				if ( ! $this->has_status( array( 'cancelled', 'trash', 'switched' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'trash':
				$cancelled_statuses = [
					'cancelled',
					'trash',
					'expired',
					'switched',
					'pending_cancel',
				];
				if ( $this->has_status( $cancelled_statuses ) || $this->can_be_updated_to( 'cancelled' ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'deleted':
				if ( 'trash' === $this->get_status() ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			default:
				$can_be_updated = apply_filters( 'storeengine/subscription/can_be_updated_to', false, $new_status, $this );
				break;
		}

		return apply_filters( 'storeengine/subscription/can_be_updated_to_' . $new_status, $can_be_updated, $this );
	}

	/**
	 * Updates status of the subscription
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @param string $note (default: '') Optional note to add
	 *
	 * @throws StoreEngineException
	 */
	public function update_status( string $new_status, string $note = '', bool $manual = false ): bool {
		if ( ! $this->get_id() ) {
			return false;
		}

		$old_status = $this->get_status();

		if ( $new_status !== $old_status || ! in_array( $old_status, SubscriptionCollection::get_subscription_status_keys(), true ) ) {
			do_action( 'storeengine/subscription/pre_update_status', $old_status, $new_status, $this );

			// Only update if possible
			if ( ! $this->can_be_updated_to( $new_status ) ) {

				// translators: %s: subscription status.
				$message = sprintf( __( 'Unable to change subscription status to "%s".', 'storeengine' ), $new_status );

				$this->add_order_note( $message );

				do_action( 'storeengine/subscription/unable_to_update_status', $this, $new_status, $old_status );

				// Let plugins handle it if they tried to change to an invalid status
				throw new StoreEngineException( $message, 'unable-to-update-subscription-status' );
			}

			try {
				$this->set_status( $new_status, $note, $manual );

				switch ( $new_status ) {
					case 'pending':
						// Nothing to do here
						break;

					case 'pending_cancel':
						// Store the subscription's end date and trial end date before overriding/deleting them.
						// Used for restoring the dates if the customer reactivates the subscription.
						$this->update_meta_data( 'end_date_pre_cancellation', $this->get_end_date() );
						$this->update_meta_data( 'trial_end_pre_cancellation', $this->get_trial_end_date() );

						$end_date = $this->calculate_date( 'end_of_prepaid_term_date' );

						// If there is no future payment and no expiration date set, or the end date is before now, the customer has no prepaid term (this shouldn't be possible as only active subscriptions can be set to pending cancellation and an active subscription always has either an end date or next payment), so set the end date and cancellation date to now
						if ( ! $end_date || Formatting::string_to_datetime_utc( $end_date ) < time() ) {
							$cancelled_date = current_time( 'mysql', true );
							$end_date       = $cancelled_date;
						} else {
							// the cancellation date is now, and the end date is the end of prepaid term date
							$cancelled_date = current_time( 'mysql', true );
						}

						$this->set_trial_end_date( 0 );
						$this->set_next_payment_date( 0 );
						$this->set_cancelled_date( $cancelled_date );
						$this->set_end_date( $end_date );

						break;

					case 'completed': // core order status mapped internally to avoid exceptions
					case 'active':
						if ( 'pending_cancel' === $old_status ) {
							$this->set_cancelled_date( 0 );
							$this->set_end_date( $this->meta_exists( 'end_date_pre_cancellation' ) ? $this->get_meta( 'end_date_pre_cancellation' ) : 0 );
							$this->set_trial_end_date( $this->meta_exists( 'trial_end_pre_cancellation' ) ? $this->get_meta( 'trial_end_pre_cancellation' ) : 0 );
							$this->set_next_payment_date( $this->get_end_date( 'edit' ) );
						} else {
							// Recalculate and set next payment date
							$stored_next_payment = $this->get_next_payment_date() ? $this->get_next_payment_date()->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp() : 0;

							// Make sure the next payment date is more than 2 hours in the future by default
							if ( $stored_next_payment < ( gmdate( 'U' ) + apply_filters( 'storeengine/subscription/activation_next_payment_date_threshold', 2 * HOUR_IN_SECONDS, $stored_next_payment, $old_status, $this ) ) ) { // also accounts for a $stored_next_payment of 0, meaning it's not set

								$calculated_next_payment = $this->calculate_date( 'next_payment_date' );

								if ( $calculated_next_payment > 0 ) {
									$this->set_next_payment_date( $calculated_next_payment );
								} elseif ( $stored_next_payment < gmdate( 'U' ) ) { // delete the stored date if it's in the past as we're not updating it (the calculated next payment date is 0 or none)
									$this->set_next_payment_date( 0 );
								}
							} else {
								// In case plugins want to run some code when the subscription was reactivated, but the next payment date was not recalculated.
								do_action( 'storeengine/subscription/activation_next_payment_not_recalculated', $stored_next_payment, $old_status, $this );
							}
						}

						break;

					case 'failed': // core order status mapped internally to avoid exceptions
					case 'on_hold':
						// Record date of suspension - 'post_modified' column?
						$this->set_suspension_count( $this->get_suspension_count() + 1 );
						break;
					case 'cancelled':
					case 'switched':
					case 'expired':
						$this->set_trial_end_date( 0 );
						$this->set_next_payment_date( 0 );
						$this->set_end_date( current_time( 'mysql', true ) );

						// Also set the cancelled date to now if it wasn't set previously (when the status was changed to pending-cancellation)
						if ( 'cancelled' === $new_status && ! $this->get_cancelled_date( 'cancelled' ) ) {
							$this->set_cancelled_date( $this->get_end_date() );
						}

						break;
				}

				// Make sure status is saved when WC 3.0+ is active, similar to Order::update_status() with WC 3.0+ - set_status() can be used to avoid saving.
				$this->save();
			} catch ( StoreEngineException $e ) {
				// @TODO implement logger

				// Make sure the old status is restored
				$this->set_status( $old_status, $note, $manual );

				// There is no status transition
				$this->status_transition = false;

				// translators: 1: subscription status, 2: error message.
				$this->add_order_note( sprintf( __( 'Unable to change subscription status to "%1$s". Exception: %2$s', 'storeengine' ), $new_status, $e->getMessage() ) );

				$this->save();

				do_action( 'storeengine/subscription/unable_to_update_status', $this, $new_status, $old_status );

				throw $e;
			}
		}

		return true;
	}

	/**
	 * Handle the status transition.
	 */
	protected function status_transition() {
		// Use local copy of status transition value.
		$status_transition = $this->status_transition;

		// If we're not currently in the midst of a status transition, bail early.
		if ( ! $status_transition ) {
			return;
		}

		try {
			do_action( "storeengine/subscription/status_{$status_transition['to']}", $this );

			if ( ! empty( $status_transition['from'] ) ) {
				$transition_note = sprintf(
				/* translators: 1: old subscription status 2: new subscription status */
					__( 'Status changed from %1$s to %2$s.', 'storeengine' ),
					SubscriptionCollection::get_subscription_status_name( $status_transition['from'] ),
					SubscriptionCollection::get_subscription_status_name( $status_transition['to'] )
				);

				do_action( "storeengine/subscription/status_{$status_transition['from']}_to_{$status_transition['to']}", $this );

				// Trigger a hook with params we want.
				do_action( 'storeengine/subscription/status_updated', $this, $status_transition['to'], $status_transition['from'] );

				// Trigger a hook with params matching WooCommerce's 'woocommerce_order_status_changed' hook so functions attached to it can be attached easily to subscription status changes.
				do_action( 'storeengine/subscription/status_changed', $this->get_id(), $status_transition['from'], $status_transition['to'], $this );
			} else {
				/* translators: %s: new order status */
				$transition_note = sprintf( __( 'Status set to %s.', 'storeengine' ), SubscriptionCollection::get_subscription_status_name( $status_transition['to'] ) );
			}

			// Note the transition occurred.
			$this->add_order_note( trim( "{$status_transition['note']} {$transition_note}" ), 0, $status_transition['manual'] );
		} catch ( Exception $e ) {
			// @TODO implement error logger.

			$this->add_order_note( __( 'Error during subscription status transition.', 'storeengine' ) . ' ' . $e->getMessage() );
		}

		// This has run, so reset status transition variable
		$this->status_transition = false;
	}

	public function payment_complete( $transaction_id = '' ): bool {
		// Clear the cached renewal payment counts, kept here for backward compat even though it's also reset in $this->process_payment_complete()
		if ( isset( $this->cached_payment_count['completed'] ) ) {
			$this->cached_payment_count = null;
		}

		// Make sure the last order's status is updated
		$last_order = $this->get_last_order( 'all', 'any' );

		if ( false !== $last_order && $last_order->needs_payment() ) {
			$last_order->payment_complete( $transaction_id );
		}

		$this->payment_complete_for_order( $last_order );

		return true;
	}

	/**
	 * When payment is completed for a related order, reset any renewal related counters and reactive the subscription.
	 *
	 * @param Order $order
	 */
	public function payment_complete_for_order( $last_order ) {

		// Clear the cached renewal payment counts
		if ( isset( $this->cached_payment_count['completed'] ) ) {
			$this->cached_payment_count = null;
		}

		// Reset suspension count
		$this->set_suspension_count( 0 );

		// Make sure subscriber has default role
		//wcs_update_users_role( $this->get_user_id(), 'default_subscriber_role' );

		// Add order note depending on initial payment
		$this->add_order_note( __( 'Payment status marked complete.', 'storeengine' ) );

		$this->update_status( 'active' ); // also saves the subscription

		do_action( 'storeengine/subscription/payment_complete', $this );

		if ( false !== $last_order && SubscriptionCollection::order_contains_renewal( $last_order->get_id() ) ) {
			do_action( 'storeengine/subscription/renewal_payment_complete', $this, $last_order );
		}
	}

	/**
	 * When a payment fails, either for the original purchase or a renewal payment, this function processes it.
	 */
	public function payment_failed( $new_status = 'on_hold' ) {

		// Make sure the last order's status is set to failed
		$last_order = $this->get_last_order( 'all', 'any' );

		if ( false !== $last_order && false === $last_order->has_status( 'failed' ) ) {
			remove_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment' );
			$last_order->update_status( 'failed' );
			add_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10, 3 );
		}

		// Log payment failure on order
		$this->add_order_note( __( 'Payment failed.', 'storeengine' ) );

		// Allow a short circuit for plugins & payment gateways to force max failed payments exceeded
		if ( 'cancelled' === $new_status || apply_filters( 'storeengine/subscription/max_failed_payments_exceeded', false, $this ) ) {
			if ( $this->can_be_updated_to( 'cancelled' ) ) {
				$this->update_status( 'cancelled', __( 'Subscription Cancelled: maximum number of failed payments reached.', 'storeengine' ) );
			}
		} elseif ( $this->can_be_updated_to( $new_status ) ) {
			$this->update_status( $new_status );
		}

		do_action( 'storeengine/subscription/payment_failed', $this, $new_status );

		if ( SubscriptionCollection::order_contains_subscription( $last_order->get_id(), [ 'renewal' ] ) ) {
			do_action( 'storeengine/subscription/renewal_payment_failed', $this, $last_order );
			// maybe reschedule retry payment.
		}
	}

	public function set_payment_method( $payment_method = '' ) {
		if ( empty( $payment_method ) ) {
			$this->set_requires_manual_renewal( true );
			$this->set_prop( 'payment_method', '' );
			$this->set_prop( 'payment_method_title', '' );
		} else {
			$payment_method_id = is_object( $payment_method ) ? $payment_method->id : $payment_method;
			if ( $this->get_payment_method() !== $payment_method_id ) {
				if ( $this->object_read ) {
					if ( is_a( $payment_method, PaymentGateway::class ) ) {
						$payment_gateway = $payment_method;
					} else {
						$payment_gateway = Helper::get_payment_gateway( $payment_method_id );
					}

					if ( Helper::get_settings( 'turn_off_automatic_payments', false ) ) {
						$this->set_requires_manual_renewal( true );
					} elseif ( is_null( $payment_gateway ) || ! $payment_gateway->supports( 'subscriptions' ) ) {
						$this->set_requires_manual_renewal( true );
					} else {
						$this->set_requires_manual_renewal( false );
					}

					$this->set_prop( 'payment_method_title', is_null( $payment_gateway ) ? '' : $payment_gateway->get_title() );
				}

				$this->set_prop( 'payment_method', $payment_method_id );
			}
		}
	}

	/**
	 * Gets the most recent order that relates to a subscription, including renewal orders and the initial order (if any).
	 *
	 * @param string $return_fields The columns to return, either 'all' or 'ids'
	 * @param array|string $order_types Can include any combination of 'parent', 'renewal', 'switch' or 'any' which will return the latest renewal order of any type. Defaults to 'parent' and 'renewal'.
	 *
	 * @return false|Order
	 */
	public function get_last_order( string $return_fields = 'ids', $order_types = [ 'parent', 'renewal' ] ) {
		$return_fields  = ( 'ids' === $return_fields ) ? $return_fields : 'all';
		$order_types    = ( 'any' === $order_types ) ? [ 'parent', 'renewal', 'switch' ] : (array) $order_types;
		$related_orders = [];

		foreach ( $order_types as $order_type ) {
			switch ( $order_type ) {
				case 'parent':
					if ( $this->get_parent_id() ) {
						$related_orders[] = $this->get_parent_id();
					}
					break;
				default:
					$related_orders = array_merge( $related_orders, $this->get_related_order_ids( $order_type ) );
					break;
			}
		}

		if ( empty( $related_orders ) ) {
			$last_order = false;
		} else {
			$last_order = max( $related_orders );

			if ( 'all' === $return_fields ) {
				if ( $this->get_parent_id() && $last_order === $this->get_parent_id() ) {
					$last_order = $this->get_parent();
				} else {
					$last_order = Helper::get_order( $last_order );

					if ( is_wp_error( $last_order ) ) {
						$last_order = false;
					}
				}
			}
		}

		return apply_filters( 'storeengine/subscription/last_order', $last_order, $this );
	}

	/**
	 * Get parent order object.
	 *
	 * @return Order|bool
	 */
	public function get_parent() {
		$parent_id = $this->get_parent_id();
		$order     = false;

		if ( $parent_id > 0 ) {
			$order = Helper::get_order( $parent_id );
		}

		if ( is_wp_error( $order ) ) {
			$order = false;
		}

		return $order;
	}

	/**
	 * Get the number of payments for a subscription.
	 *
	 * Default payment count includes all renewal orders and potentially an initial order
	 * (if the subscription was created as a result of a purchase from the front end
	 * rather than manually by the store manager).
	 *
	 * @param string $payment_type Type of count (completed|refunded|net). Optional. Default completed.
	 * @param string|array $order_types Type of order relation(s) to count. Optional. Default array(parent,renewal).
	 *
	 * @return integer Count.
	 */
	public function get_payment_count( string $payment_type = 'completed', $order_types = '' ) {
		if ( empty( $order_types ) ) {
			$order_types = [ 'parent', 'renewal' ];
		} elseif ( ! is_array( $order_types ) ) {
			$order_types = [ $order_types ];
		}

		// Replace 'any' to prevent counting orders twice.
		$any_key = array_search( 'any', $order_types, true );
		if ( false !== $any_key ) {
			unset( $order_types[ $any_key ] );
			$order_types = array_merge( $order_types, [ 'parent', 'renewal', 'resubscribe', 'switch' ] );
		}

		// Ensure orders are only counted once and parent is counted before renewal for deprecated filter.
		$order_types = array_unique( $order_types );
		sort( $order_types );

		if ( ! is_array( $this->cached_payment_count ) ) {
			$this->cached_payment_count = [
				'completed' => [],
				'refunded'  => [],
			];
		}

		// Keep a tally of the counts of all requested order types
		$total_completed_payment_count = 0;
		$total_refunded_payment_count  = 0;

		foreach ( $order_types as $order_type ) {
			// If not cached, calculate the payment counts otherwise use the cached version.
			if ( ! isset( $this->cached_payment_count['completed'][ $order_type ] ) ) {
				$completed_payment_count = 0;
				$refunded_payment_count  = 0;

				// Looping over the known orders is faster than database queries on large sites
				foreach ( $this->get_related_orders( 'all', $order_type ) as $related_order ) {
					if ( null !== $related_order->get_date_paid() ) {
						$completed_payment_count ++;

						if ( $related_order->has_status( 'refunded' ) ) {
							$refunded_payment_count ++;
						}
					}
				}
			} else {
				$completed_payment_count = $this->cached_payment_count['completed'][ $order_type ];
				$refunded_payment_count  = $this->cached_payment_count['refunded'][ $order_type ];
			}

			// Store the payment counts to avoid hitting the database again
			$this->cached_payment_count['completed'][ $order_type ] = apply_filters( "storeengine/subscription/{$order_type}_payment_completed_count", $completed_payment_count, $this, $order_type );
			$this->cached_payment_count['refunded'][ $order_type ]  = apply_filters( "storeengine/subscription/{$order_type}_payment_refunded_count", $refunded_payment_count, $this, $order_type );

			$total_completed_payment_count += $this->cached_payment_count['completed'][ $order_type ];
			$total_refunded_payment_count  += $this->cached_payment_count['refunded'][ $order_type ];
		}

		switch ( $payment_type ) {
			case 'completed':
				$count = $total_completed_payment_count;
				break;
			case 'refunded':
				$count = $total_refunded_payment_count;
				break;
			case 'net':
				$count = $total_completed_payment_count - $total_refunded_payment_count;
				break;
			default:
				$count = 0;
				break;
		}

		return $count;
	}

	/**
	 * Calculates the next payment date for a subscription.
	 *
	 * Although an inactive subscription does not have a next payment date, this function will still calculate the date
	 * so that it can be used to determine the date the next payment should be charged for inactive subscriptions.
	 *
	 * @return int | string Zero if the subscription has no next payment date, or a MySQL formatted date time if there is a next payment date
	 */
	protected function calculate_next_payment_date() {
		$next_payment_date = 0;

		// If the subscription is not active, there is no next payment date
		$start_time        = $this->get_start_date()->getTimestamp();
		$next_payment_time = $this->get_next_payment_date() ? $this->get_next_payment_date()->getTimestamp() : 0;
		$trial_end_time    = $this->get_trial_end_date() ? $this->get_trial_end_date()->getTimestamp() : 0;
		$end_time          = $this->get_end_date() ? $this->get_end_date()->getTimestamp() : 0;
		$last_order        = $this->get_last_order( 'last' );

		if ( $last_order && $last_order->get_date_created_gmt() && $last_order->get_date_paid_gmt() ) {
			$last_payment_time = max( $last_order->get_date_created_gmt()->getTimestamp(), $last_order->get_date_paid_gmt()->getTimestamp() );
		} elseif ( $last_order && $last_order->get_date_created_gmt() ) {
			$last_payment_time = $last_order->get_date_created_gmt()->getTimestamp();
		} else {
			$last_payment_time = 0;
		}

		// If the subscription has a free trial period, and we're still in the free trial period, the next payment is due at the end of the free trial
		if ( $trial_end_time > time() ) {
			$next_payment_timestamp = $trial_end_time;
		} else {
			// The next payment date is {interval} billing periods from the start date, trial end date or last payment date
			if ( 0 !== $next_payment_time && $next_payment_time < gmdate( 'U' ) && ( 0 !== $trial_end_time && 1 >= $this->get_payment_count() ) ) {
				$from_timestamp = $next_payment_time;
			} elseif ( $last_payment_time >= $start_time && apply_filters( 'storeengine/subscription/calculate_next_payment_from_last_payment', true, $this ) ) {
				$from_timestamp = $last_payment_time;
			} elseif ( $next_payment_time > $start_time ) { // Use the currently scheduled next payment to preserve synchronisation
				$from_timestamp = $next_payment_time;
			} else {
				$from_timestamp = $start_time;
			}

			$next_payment_timestamp = CreateSubscription::add_time( $this->get_payment_duration(), $this->get_payment_duration_type(), $from_timestamp, 'offset_site_time' );

			// Make sure the next payment is more than 2 hours in the future, this ensures changes to the site's timezone because of daylight savings will never cause a 2nd renewal payment to be processed on the same day
			$i = 1;
			while ( $next_payment_timestamp < ( time() + 2 * HOUR_IN_SECONDS ) && $i < 3000 ) {
				$next_payment_timestamp = CreateSubscription::add_time( $this->get_payment_duration(), $this->get_payment_duration_type(), $next_payment_timestamp, 'offset_site_time' );
				++ $i;
			}
		}

		// If the subscription has an end date and the next billing period comes after that, return 0
		if ( 0 !== $end_time && ( $next_payment_timestamp + 23 * HOUR_IN_SECONDS ) > $end_time ) {
			$next_payment_timestamp = 0;
		}

		if ( $next_payment_timestamp > 0 ) {
			$next_payment_date = gmdate( 'Y-m-d H:i:s', $next_payment_timestamp );
		}

		return $next_payment_date;
	}

	/**
	 * Calculate a given date for the subscription in GMT/UTC.
	 *
	 * @param string $date_type 'trial_end_date', 'next_payment_date', 'end_of_prepaid_term_date' or 'end_date'
	 */
	public function calculate_date( string $date_type ) {
		switch ( $date_type ) {
			case 'next_payment_date':
				$date = $this->calculate_next_payment_date();
				break;
			case 'trial_end_date':
				if ( $this->get_payment_count() >= 2 ) {
					$date = 0;
				} else {
					// By default, trial end is the same as the next payment date
					$date = $this->calculate_next_payment_date();
				}
				break;
			case 'end_of_prepaid_term_date':
				if ( ! $this->get_next_payment_date() || ! $this->get_end_date() ) {
					$date = 0;
				} else {
					$next_payment_time = $this->get_next_payment_date()->getTimestamp();
					$end_time          = $this->get_end_date()->getTimestamp();

					// If there was a future payment, the customer has paid up until that payment date
					if ( $next_payment_time >= time() ) {
						$date = $next_payment_time;
						// If there is no future payment and no expiration date set, the customer has no prepaid term (this shouldn't be possible as only active subscriptions can be set to pending cancellation and an active subscription always has either an end date or next payment)
					} elseif ( 0 === $next_payment_time || $end_time <= time() ) {
						$date = current_time( 'mysql', true );
					} else {
						$date = $end_time;
					}
				}
				break;
			default:
				$date = 0;
				break;
		}

		return apply_filters( 'storeengine/subscription/calculated_' . $date_type . '_date', $date, $this );
	}

	public function get_related_orders_date( $date_type, $order_type = 'any' ) {
		$date   = null;
		$getter = 'get_' . $date_type;
		if ( 'last' === $order_type ) {
			$last_order = $this->get_last_order( 'all' );
			$date       = method_exists( $last_order, $getter ) ? $last_order->$getter() : null;
		} else {
			// Loop over orders until we find a valid date of this type or run out of related orders
			foreach ( $this->get_related_orders( $order_type ) as $related_order_id ) {
				$related_order = Helper::get_order( $related_order_id );
				$date          = method_exists( $related_order, $getter ) ? $related_order->$getter() : null;
				if ( is_a( $date, StoreengineDatetime::class ) ) {
					break;
				}
			}
		}

		return $date;
	}

	public function get_last_order_date_created_date() {
		return $this->get_related_orders_date( 'date_created_gmt', 'last' );
	}

	public function get_last_order_date_paid_date() {
		return $this->get_related_orders_date( 'date_paid_gmt', 'last' );
	}

	public function get_last_order_date_completed_date() {
		return $this->get_related_orders_date( 'date_completed_gmt', 'last' );
	}

	/**
	 * Returns a string representation of a subscription date in the site's time (i.e. not GMT/UTC timezone).
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created', 'end' or 'end_of_prepaid_term'
	 */
	public function get_date_to_display( string $date_type = 'next_payment' ) {
		$timestamp_gmt = $this->get_time( $date_type );

		// Don't display next payment date when the subscription is inactive
		if ( 'next_payment' == $date_type && ! $this->has_status( 'active' ) ) {
			$timestamp_gmt = 0;
		}

		if ( $timestamp_gmt > 0 ) {
			$time_diff = $timestamp_gmt - time();

			if ( $time_diff > 0 && $time_diff < WEEK_IN_SECONDS ) {
				// translators: placeholder is human time diff (e.g. "3 weeks")
				$date_to_display = sprintf( __( 'In %s', 'storeengine' ), human_time_diff( time(), $timestamp_gmt ) );
			} elseif ( $time_diff < 0 && absint( $time_diff ) < WEEK_IN_SECONDS ) {
				// translators: placeholder is human time diff (e.g. "3 weeks")
				$date_to_display = sprintf( __( '%s ago', 'storeengine' ), human_time_diff( time(), $timestamp_gmt ) );
			} else {
				$date_to_display = date_i18n( Formatting::date_format(), $timestamp_gmt );
			}
		} else {
			switch ( $date_type ) {
				case 'end':
					$date_to_display = __( 'Not yet ended', 'storeengine' );
					break;
				case 'cancelled':
					$date_to_display = __( 'Not cancelled', 'storeengine' );
					break;
				case 'next_payment':
				case 'trial_end':
				default:
					$date_to_display = _x( '-', 'original denotes there is no date to display', 'storeengine' );
					break;
			}
		}

		return apply_filters( 'storeengine/subscription/date_to_display', $date_to_display, $date_type, $this );
	}

	/**
	 * The total sign-up fee for the subscription if any.
	 *
	 * @return float
	 */
	public function get_sign_up_fee(): float {
		$sign_up_fee = 0;

		foreach ( $this->get_items() as $line_item ) {
			try {
				$sign_up_fee += $this->get_items_sign_up_fee( $line_item );
			} catch ( Exception $e ) {
				$sign_up_fee += 0;
			}
		}

		return (float) apply_filters( 'storeengine/subscription/sign_up_fee', $sign_up_fee, $this );
	}

	/**
	 * Check if a given line item on the subscription had a sign-up fee, and if so, return the value of the sign-up fee.
	 *
	 * The single quantity sign-up fee will be returned instead of the total sign-up fee paid. For example, if 3 x a product
	 * with a 10 BTC sign-up fee was purchased, a total 30 BTC was paid as the sign-up fee but this function will return 10 BTC.
	 *
	 * @param OrderItemProduct|AbstractOrderItem $line_item Either an order item (in the array format returned by self::get_items()) or the ID of an order item.
	 * @param string $tax_inclusive_or_exclusive Whether or not to adjust sign up fee if prices inc tax - ensures that the sign up fee paid amount includes the paid tax if inc
	 *
	 * @return float
	 */
	public function get_items_sign_up_fee( $line_item, $tax_inclusive_or_exclusive = 'exclusive_of_tax' ): float {
		$parent_order = $this->get_parent();

		// If there was no original order, nothing was paid up-front which means no sign-up fee
		if ( ! $parent_order ) {
			$sign_up_fee = 0;
		} else {
			$original_order_item = '';

			// Find the matching item on the order
			foreach ( $parent_order->get_items() as $order_item ) {
				$line_item_pro_id  = $line_item->get_variation_id() ? $line_item->get_variation_id() : $line_item->get_product_id();
				$order_item_pro_id = $order_item->get_variation_id() ? $order_item->get_variation_id() : $order_item->get_product_id();
				if ( $line_item_pro_id == $order_item_pro_id ) {
					$original_order_item = $order_item;
					break;
				}
			}

			// No matching order item, so this item wasn't purchased in the original order
			if ( empty( $original_order_item ) ) {
				$sign_up_fee = 0;
			} elseif ( $line_item->is_trial() ) {
				$sign_up_fee = ( (float) $original_order_item->get_total( 'edit' ) ) / $original_order_item->get_quantity( 'edit' );
			} elseif ( $original_order_item->meta_exists( '_synced_sign_up_fee' ) ) {
				$sign_up_fee = ( (float) $original_order_item->get_meta( '_synced_sign_up_fee' ) ) / $original_order_item->get_quantity( 'edit' );

				// The synced sign up fee meta contains the raw product sign up fee, if the subscription totals are inclusive of tax, we need to adjust the synced sign up fee to match tax inclusivity.
				if ( $this->get_prices_include_tax() ) {
					$line_item_total    = (float) $original_order_item->get_total( 'edit' ) + $original_order_item->get_total_tax( 'edit' );
					$signup_fee_portion = $sign_up_fee / $line_item_total;
					$sign_up_fee        = (float) $original_order_item->get_total( 'edit' ) * $signup_fee_portion;
				}
			} else {
				// Sign-up fee is any amount on top of recurring amount
				$order_line_total        = ( (float) $original_order_item->get_total( 'edit' ) ) / $original_order_item->get_quantity( 'edit' );
				$subscription_line_total = ( (float) $line_item->get_total( 'edit' ) ) / $line_item->get_quantity( 'edit' );

				$sign_up_fee = max( $order_line_total - $subscription_line_total, 0 );
			}

			// If prices don't inc tax, ensure that the sign up fee amount includes the tax.
			if ( 'inclusive_of_tax' === $tax_inclusive_or_exclusive && ! empty( $original_order_item ) && ! empty( $sign_up_fee ) ) {
				$sign_up_fee_proportion = $sign_up_fee / ( $original_order_item->get_total( 'edit' ) / $original_order_item->get_quantity( 'edit' ) );
				$sign_up_fee_tax        = $original_order_item->get_total_tax( 'edit' ) * $sign_up_fee_proportion;

				$sign_up_fee += $sign_up_fee_tax;
				$sign_up_fee  = Formatting::format_decimal( $sign_up_fee, Formatting::get_price_decimals() );
			}
		}

		return (float) apply_filters( 'storeengine/subscription/items_sign_up_fee', $sign_up_fee, $line_item, $this, $tax_inclusive_or_exclusive );
	}

	/**
	 * Determine how the payment method should be displayed for a subscription.
	 *
	 * @param string $context The context the payment method is being displayed in. Can be 'admin' or 'customer'. Default 'admin'.
	 */
	public function get_payment_method_to_display( string $context = 'admin' ) {
		$payment_method_to_display = 'N/A';
		if ( $this->is_manual() ) {
			$payment_method_to_display = __( 'Manual Renewal', 'storeengine' );
			// Use the current title of the payment gateway when available
		} elseif ( false !== ( $payment_gateway = Helper::get_payment_gateway_by_order( $this ) ) ) {
			$payment_method_to_display = $payment_gateway->get_title();
			// Fallback to the title of the payment method when the subscription was created
		} else {
			$payment_method_to_display = $this->get_payment_method_title();
		}

		$payment_method_to_display = apply_filters( 'storeengine/subscription/payment_method_to_display', $payment_method_to_display, $this, $context );

		if ( 'customer' === $context ) {
			// translators: %s: payment method.
			$payment_method_to_display = sprintf( __( 'Via %s', 'storeengine' ), $payment_method_to_display );

			// Only filter the result for non-manual subscriptions.
			if ( ! $this->is_manual() ) {
				$payment_method_to_display = apply_filters( 'storeengine/subscription/my_payment_method', $payment_method_to_display, $this );
			}
		}

		return $payment_method_to_display;
	}

	/**
	 *  Determine if the subscription is for one payment only.
	 *
	 * @return bool whether the subscription is for only one payment
	 */
	public function is_one_payment(): bool {
		$is_one_payment = false;
		$end_time       = $this->get_end_date();
		$end_time       = $end_time ? $end_time->getTimestamp() : 0;

		if ( $end_time ) {
			$from_timestamp = $this->get_start_date();
			$from_timestamp = $from_timestamp ? $from_timestamp->getTimestamp() : 0;
			$trial_end      = $this->get_trial_end_date();
			$trial_end      = $trial_end ? $trial_end->getTimestamp() : 0;

			if ( $trial_end /*|| WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $this )*/ ) {
				$subscription_order_count = count( $this->get_related_orders() );
				$next_payment_timestamp   = $this->get_next_payment_date();
				$next_payment_timestamp   = $next_payment_timestamp ? $next_payment_timestamp->getTimestamp() : 0;

				$last_payment_timestamp = $this->get_last_order_date_created_date();
				$last_payment_timestamp = $last_payment_timestamp ? $last_payment_timestamp->getTimestamp() : 0;

				// when we have a sync'd subscription before its 1st payment, we need to base the calculations for the next payment on the first/next payment timestamp.
				if ( $subscription_order_count < 2 && $next_payment_timestamp ) {
					$from_timestamp = $next_payment_timestamp;

					// when we have a sync'd subscription after its 1st payment, we need to base the calculations for the next payment on the last payment timestamp.
				} elseif ( ! ( $subscription_order_count > 2 ) && $last_payment_timestamp ) {
					$from_timestamp = $last_payment_timestamp;
				}
			}

			$next_payment_timestamp = CreateSubscription::add_time( $this->get_payment_duration(), $this->get_payment_duration_type(), $from_timestamp );

			if ( ( $next_payment_timestamp + DAY_IN_SECONDS - 1 ) > $end_time ) {
				$is_one_payment = true;
			}
		}

		return apply_filters( 'storeengine/subscription/is_one_payment', $is_one_payment, $this );
	}

	/**
	 * Cancel the order and restore the cart (before payment)
	 *
	 * @param string $note (default: '') Optional note to add
	 *
	 * @throws StoreEngineException
	 */
	public function cancel_order( string $note = '' ) {

		// If the customer hasn't been through the pending cancellation period yet set the subscription to be pending cancellation unless there is a pending renewal order.
		if ( apply_filters( 'storeengine/subscription/use_pending_cancel', true ) && $this->calculate_date( 'end_of_prepaid_term' ) > current_time( 'mysql', true ) && ( $this->has_status( 'active' ) || $this->has_status( 'on_hold' ) && ! $this->needs_payment() ) ) {
			$this->update_status( 'pending_cancel', $note );
			// If the subscription has already ended or can't be cancelled for some other reason, just record the note.
		} elseif ( ! $this->can_be_updated_to( 'cancelled' ) ) {
			$this->add_order_note( $note );
			// Cancel for real if we're already pending cancellation
		} else {
			$this->update_status( 'cancelled', $note );
		}
	}

	/**
	 * Allow subscription amounts/items to bed edited if the gateway supports it.
	 *
	 * @access public
	 * @return bool
	 */
	public function is_editable(): bool {
		if ( ! isset( $this->editable ) ) {
			if ( $this->has_status( [ 'pending', 'draft', 'auto-draft' ] ) ) {
				$this->editable = true;
			} elseif ( $this->is_manual() || $this->payment_method_supports( 'subscription_amount_changes' ) ) {
				$this->editable = true;
			} else {
				$this->editable = false;
			}
		}

		return apply_filters( 'storeengine/order_is_editable', $this->editable, $this );
	}

	/**
	 * Generates a URL to view an order from the myaccount page.
	 *
	 * @return string
	 */
	public function get_view_order_url(): string {
		return apply_filters( 'storeengine/subscription/get_view_url', Helper::get_account_endpoint_url( 'plans', $this->get_id() ), $this );
	}
}
