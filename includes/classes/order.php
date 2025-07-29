<?php

namespace StoreEngine\Classes;

use Exception;
use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Hooks;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use WP_Comment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Order
 * @see \WC_Order_Data_Store_CPT
 * @see \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore
 */
class Order extends AbstractOrder {

	protected array $extra_data = [
		'order_placed_date_gmt'        => null,
		'order_placed_date'            => null,
		'paid_status'                  => null,
		// Addresses
		'billing'                      => [
			'first_name'   => '',
			'last_name'    => '',
			'company'      => '',
			'address_1'    => '',
			'address_2'    => '',
			'city'         => '',
			'state'        => '',
			'postcode'     => '',
			'country'      => '',
			'email'        => '',
			'phone'        => '',
			'address_type' => 'billing',
		],
		'shipping'                     => [
			'first_name'   => '',
			'last_name'    => '',
			'company'      => '',
			'address_1'    => '',
			'address_2'    => '',
			'city'         => '',
			'state'        => '',
			'postcode'     => '',
			'country'      => '',
			'email'        => '',
			'phone'        => '',
			'address_type' => 'shipping',
		],
		'download_permissions_granted' => false,
		'auto_complete_digital_order'  => false,
	];

	/**
	 * Stores data about status changes so relevant hooks can be fired.
	 *
	 * @var bool|array
	 */
	protected $status_transition = false;

	public function __construct( $read = 0 ) {
		$this->internal_meta_keys[]                               = '_order_placed_date_gmt';
		$this->internal_meta_keys[]                               = '_order_placed_date';
		$this->internal_meta_keys[]                               = '_download_permissions_granted';
		$this->internal_meta_keys[]                               = '_auto_complete_digital_order';
		$this->meta_key_to_props['_order_placed_date_gmt']        = 'order_placed_date_gmt';
		$this->meta_key_to_props['_order_placed_date']            = 'order_placed_date';
		$this->meta_key_to_props['_paid_status']                  = 'paid_status';
		$this->meta_key_to_props['_download_permissions_granted'] = 'download_permissions_granted';
		$this->meta_key_to_props['_auto_complete_digital_order']  = 'auto_complete_digital_order';
		parent::__construct( $read );
	}

	protected function read_db_data( $value, string $field = 'id' ): array {
		return array_merge(
			parent::read_db_data( $value, $field ),
			[
				'order_placed_date_gmt'        => $this->get_metadata( '_order_placed_date_gmt' ),
				'order_placed_date'            => $this->get_metadata( '_order_placed_date' ),
				'paid_status'                  => $this->get_metadata( '_paid_status' ),
				'download_permissions_granted' => $this->get_metadata( '_download_permissions_granted' ),
				'auto_complete_digital_order'  => $this->get_metadata( '_auto_complete_digital_order' ),
			]
		);
	}

	public function save() {
		$this->maybe_set_user_billing_email();

		$saved = parent::save();

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->status_transition();

		return $this->get_id();
	}

	public function create() {
		parent::create();

		if ( ! $this->is_type( 'refund' ) && ( array_key_exists( 'billing', $this->data ) || array_key_exists( 'shipping', $this->data ) ) ) {
			foreach ( [ 'billing', 'shipping' ] as $type ) {
				$address = $this->get_address( $type );

				if ( ! $this->{'has_' . $type . '_address'}( 'edit' ) ) {
					continue;
				}

				$address['order_id'] = $this->get_id();
				$formats             = array_fill( 0, count( $address ), '%s' );

				$this->wpdb->insert( "{$this->wpdb->prefix}storeengine_order_addresses", $address, $formats );

				if ( $this->wpdb->last_error ) {
					throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
				}
			}
		}
	}

	public function update() {
		parent::update();

		foreach ( [ 'billing', 'shipping' ] as $type ) {
			$address = $this->get_address( $type );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query prepared.
			$this->wpdb->query(
				$this->wpdb->prepare(
					"
				INSERT INTO `{$this->wpdb->prefix}storeengine_order_addresses`
				    (`order_id`, `address_type`, `first_name`, `last_name`, `company`, `address_1`, `address_2`, `city`, `state`, `postcode`, `country`, `email`, `phone`)
				VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
					`order_id` = VALUES(`order_id`),
					`first_name` = VALUES(`first_name`),
					`last_name` = VALUES(`last_name`),
					`company` = VALUES(`company`),
					`address_1` = VALUES(`address_1`),
					`address_2` = VALUES(`address_2`),
					`city` = VALUES(`city`),
					`state` = VALUES(`state`),
					`postcode` = VALUES(`postcode`),
					`country` = VALUES(`country`),
					`email` = VALUES(`email`),
					`phone` = VALUES(`phone`);
				",
					$this->get_id(),
					$type,
					$address['first_name'],
					$address['last_name'],
					$address['company'],
					$address['address_1'],
					$address['address_2'],
					$address['city'],
					$address['state'],
					$address['postcode'],
					$address['country'],
					$address['email'],
					$address['phone']
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query prepared.

			if ( $this->wpdb->last_error ) {
				throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
			}
		}
	}

	public function delete( bool $force_delete = false ): bool {
		if ( ! $force_delete && $this->is_trashable() ) {
			$this->set_status( 'trash' );
			$this->save();

			return true;
		}

		$refunds = $this->get_refunds();

		if ( ! empty( $refunds ) ) {
			foreach ( $refunds as $refund ) {
				$refund->delete( true );
			}
		}

		if ( $this->has_address( 'edit' ) ) {
			$this->wpdb->delete( "{$this->wpdb->prefix}storeengine_order_addresses", [ 'order_id' => $this->get_id() ], [ '%d' ] );

			if ( $this->wpdb->last_error ) {
				throw new StoreEngineException( $this->wpdb->last_error, 'db-error-delete-order_addresses' );
			}
		}

		return parent::delete( true );
	}

	/**
	 * Log an error about this order is exception is encountered.
	 *
	 * @param StoreEngineException $e Exception object.
	 * @param string $message Message regarding exception thrown.
	 */
	protected function handle_exception( StoreEngineException $e, string $message = 'Error' ) {
		$this->add_order_note( $message . ' ' . $e->getMessage() );
	}

	/**
	 * When a payment is complete this function is called.
	 *
	 * Most of the time this should mark an order as 'processing' so that admin can process/post the items.
	 * If the cart contains only downloadable items then the order is 'completed' since the admin needs to take no action.
	 * Stock levels are reduced at this point.
	 * Sales are also recorded for products.
	 * Finally, record the date of payment.
	 *
	 * @param string $transaction_id Optional transaction id to store in post meta.
	 *
	 * @return bool success
	 */
	public function payment_complete( string $transaction_id = '' ): bool {
		if ( ! $this->get_id() ) { // Order must exist.
			return false;
		}

		try {
			$order_id = $this->get_id();
			/**
			 * Fires before payment complete process of an order.
			 *
			 * @param int $order_id Order id.
			 * @param string $transaction_id Transaction id.
			 */
			do_action( 'storeengine/pre_payment_complete', $order_id, $transaction_id );

			/**
			 * Filters the valid order statuses for payment complete.
			 *
			 * @param array $valid_completed_statuses Array of valid order statuses for payment complete.
			 * @param Order $this Order object.
			 */
			$valid_completed_statuses = apply_filters( 'storeengine/valid_order_statuses_for_payment_complete', [
				OrderStatus::ON_HOLD,
				OrderStatus::PAYMENT_PENDING,
				OrderStatus::PAYMENT_FAILED,
				OrderStatus::CANCELLED,
			], $this );

			if ( $this->has_status( $valid_completed_statuses ) ) {
				if ( ! empty( $transaction_id ) ) {
					$this->set_transaction_id( $transaction_id );
				}

				if ( ! $this->get_date_paid_gmt( 'edit' ) ) {
					$this->set_date_paid_gmt( time() );
				}

				/**
				 * Filters the order status to set after payment complete.
				 *
				 * @param string $status Order status.
				 * @param int $order_id Order ID.
				 * @param Order $this Order object.
				 */
				$this->set_status( apply_filters( 'storeengine/payment_complete_order_status', $this->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED, $this->get_id(), $this ) );
				$this->save();

				/**
				 * Fires after payment complete process of an order.
				 *
				 * @param int $order_id Order id.
				 * @param string $transaction_id Transaction id.
				 */
				do_action( 'storeengine/payment_complete', $order_id, $transaction_id );
			} else {
				$status = $this->get_status();
				/**
				 * If order status isn't valid for mark order as processing/completed, then fire this hook.
				 *
				 * @param int $order_id Order ID.
				 * @param array $transaction_id Transaction ID.
				 */
				do_action( "storeengine/payment_complete_order_status_{$status}", $order_id, $transaction_id );
			}
		} catch ( Exception $e ) {
			// @TODO implement error logger.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				/**
				 * If there was an error completing the payment, log to a file and add an order note so the admin can take action.
				 */
				error_log( sprintf( 'Error completing payment for order #%d. Error: %s', $this->get_id(), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			$this->add_order_note( __( 'Payment complete event failed.', 'storeengine' ) . ' ' . $e->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Gets order total - formatted for display.
	 *
	 * @param string $tax_display Type of tax display.
	 * @param bool $display_refunded If should include refunded value.
	 *
	 * @return string
	 */
	public function get_formatted_order_total( string $tax_display = '', bool $display_refunded = true ): string {
		$formatted_total = Formatting::price( $this->get_total(), [ 'currency' => $this->get_currency() ] );
		$order_total     = $this->get_total();
		$total_refunded  = $this->get_total_refunded();
		$tax_string      = '';

		// Tax for inclusive prices.
		if ( TaxUtil::is_tax_enabled() && 'incl' === $tax_display ) {
			$tax_string_array = [];
			$tax_totals       = $this->get_tax_totals();

			if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
				foreach ( $tax_totals as $code => $tax ) {
					$tax_amount         = ( $total_refunded && $display_refunded ) ? Formatting::price( Tax::round( $tax->amount - $this->get_total_tax_refunded_by_rate_id( $tax->rate_id ) ), [ 'currency' => $this->get_currency() ] ) : $tax->formatted_amount;
					$tax_string_array[] = sprintf( '%s %s', $tax_amount, $tax->label );
				}
			} elseif ( ! empty( $tax_totals ) ) {
				$tax_amount         = ( $total_refunded && $display_refunded ) ? $this->get_total_tax() - $this->get_total_tax_refunded() : $this->get_total_tax();
				$tax_string_array[] = sprintf( '%s %s', Formatting::price( $tax_amount, [ 'currency' => $this->get_currency() ] ), Countries::init()->tax_or_vat() );
			}

			if ( ! empty( $tax_string_array ) ) {
				/* translators: %s: tax amounts */
				$tax_string = ' <small class="includes_tax">' . sprintf( __( '(includes %s)', 'storeengine' ), implode( ', ', $tax_string_array ) ) . '</small>';
			}
		}

		if ( $total_refunded && $display_refunded ) {
			$current_total = Formatting::price( $order_total - $total_refunded, [ 'currency' => $this->get_currency() ] );
			// Strikethrough pricing.
			$formatted_total = '<del aria-hidden="true">' . $formatted_total . '</del> ';

			// For accessibility (a11y) we'll also display that information to screen readers.
			$formatted_total .= '<span class="screen-reader-text"> ';
			// translators: %s is total order amount without refund.
			$formatted_total .= esc_html( sprintf( __( 'Original amount was: %s.', 'storeengine' ), wp_strip_all_tags( $formatted_total ) ) );
			$formatted_total .= '</span>';

			// Add the sale price.
			$formatted_total .= ' <ins aria-hidden="true">' . $current_total . $tax_string . '</ins> ';

			// For accessibility (a11y) we'll also display that information to screen readers.
			$formatted_total .= '<span class="screen-reader-text"> ';
			// translators: %s is total order amount after refund.
			$formatted_total .= esc_html( sprintf( __( 'Current amount is: %s.', 'storeengine' ), wp_strip_all_tags( $current_total ) ) );
			$formatted_total .= '</span>';
		} else {
			$formatted_total .= $tax_string;
		}

		/**
		 * Filter WooCommerce formatted order total.
		 *
		 * @param string $formatted_total Total to display.
		 * @param Order $order Order data.
		 * @param string $tax_display Type of tax display.
		 * @param bool $display_refunded If should include refunded value.
		 */
		return apply_filters( 'storeengine/get_formatted_order_total', $formatted_total, $this, $tax_display, $display_refunded );
	}

	/**
	 * Set order status.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @param string $note
	 * @param bool $manual_update
	 *
	 * @return array
	 */
	public function set_status( string $new_status, string $note = '', bool $manual_update = false ): array {
		$result = parent::set_status( $new_status );

		if ( true === $this->object_read && ! empty( $result['from'] ) && $result['from'] !== $result['to'] ) {
			$this->status_transition = [
				'from'   => ! empty( $this->status_transition['from'] ) ? $this->status_transition['from'] : $result['from'],
				'to'     => $result['to'],
				'note'   => $note,
				'manual' => $manual_update,
			];

			if ( $manual_update ) {
				$order_id         = $this->get_id();
				$new_order_status = $result['to'];
				/**
				 * Fires during set new status when manual update is set to true.
				 *
				 * @param int $order_id Order ID.
				 * @param string $new_order_status New Order Status.
				 */
				do_action( 'storeengine/order_edit_status', $order_id, $new_order_status );
			}

			if ( $this->is_type( 'order' ) ) {
				// Maybe set paid status.
				if ( ! $this->is_paid( 'edit' ) && $this->has_status( OrderStatus::COMPLETED ) && 'paid' !== $this->get_paid_status( 'edit' ) ) {
					$this->set_paid_status( 'paid' );
					$this->add_order_note( __( 'Order marked as paid automatically as order is completed.', 'storeengine' ) );
				}

				if ( ! $this->is_paid( 'edit' ) && $this->has_status( OrderStatus::PAYMENT_CONFIRMED ) && 'paid' !== $this->get_paid_status( 'edit' ) ) {
					$this->set_paid_status( 'paid' );
					$this->add_order_note( __( 'Order marked as paid as order status set to payment confirmed.', 'storeengine' ) );
				}

				if ( 'paid' === $this->get_paid_status( 'edit' ) && $this->has_status( OrderStatus::PAYMENT_PENDING ) ) {
					$this->set_paid_status( 'unpaid' );
					$this->set_date_paid_gmt( 0 );
					$this->add_order_note( __( 'Order marked as unpaid as order status set to payment pending.', 'storeengine' ) );
				}

				if ( 'paid' === $this->get_paid_status( 'edit' ) && $this->has_status( OrderStatus::ON_HOLD ) ) {
					$this->set_paid_status( 'on_hold' );
					$this->set_date_paid_gmt( 0 );
					$this->add_order_note( __( 'Order marked as oh-hold as order status set to payment on-hold.', 'storeengine' ) );
				}

				$this->maybe_set_date_completed();
			}
		}

		return $result;
	}

	/**
	 * Maybe set date paid.
	 *
	 * Sets the date paid variable when transitioning to the payment complete
	 * order status. This is either processing or completed. This is not filtered
	 * to avoid infinite loops e.g. if loading an order via the filter.
	 *
	 * Date paid is set once in this manner - only when it is not already set.
	 * This ensures the data exists even if a gateway does not use the
	 * `payment_complete` method.
	 *
	 * @deprecated use paid_status
	 */
	public function maybe_set_date_paid() {
		// This logic only runs if the date_paid prop has not been set yet.
		if ( ! $this->get_date_paid_gmt( 'edit' ) ) {
			if ( $this->has_status( [
				OrderStatus::PAYMENT_CONFIRMED,
				OrderStatus::PROCESSING,
				OrderStatus::COMPLETED,
			] ) ) {
				// If payment complete status is reached, set paid now.
				$this->set_date_paid_gmt( time() );
				$this->set_prop( 'paid_status', 'paid' );
			} else {
				$this->set_date_paid_gmt( 0 );
				$this->set_prop( 'paid_status', 'unpaid' );
			}
		}

		$unpaid_statuses = [ OrderStatus::PAYMENT_PENDING, OrderStatus::AUTO_DRAFT, OrderStatus::DRAFT ];
		if ( $this->get_date_paid_gmt( 'edit' ) && $this->has_status( $unpaid_statuses ) ) {
			$this->set_date_paid_gmt( 0 );
			$this->set_prop( 'paid_status', 'unpaid' );
		}
	}

	/**
	 * @param string $status
	 * @param string|null $transaction_id
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function set_paid_status( string $status, ?string $transaction_id = null ) {
		if ( ! in_array( $status, [ 'paid', 'unpaid', 'failed', 'on_hold' ], true ) ) {
			throw new StoreEngineException( 'Invalid order paid status.' );
		}

		if ( $transaction_id ) {
			$this->set_transaction_id( $transaction_id );
		}

		$old_status = $this->get_paid_status();

		if ( 'paid' === $status ) {
			if ( ! $this->get_date_paid_gmt( 'edit' ) ) {
				$this->set_date_paid_gmt( time() );
			}
		} else {
			$this->set_date_paid_gmt( 0 );
		}

		$this->set_prop( 'paid_status', $status );

		if ( true === $this->object_read && ! empty( $old_status ) && $old_status !== $status ) {
			do_action( 'storeengine/order/payment_status_changed', $this, $status, $old_status );
		}
	}

	public function get_paid_status( string $context = 'view' ): ?string {
		$status = $this->get_prop( 'paid_status', $context );

		if ( ! $status && 'view' === $context ) {
			$status = 'unpaid';
		}

		return $status;
	}

	public function is_paid( $context = 'view' ): bool {
		return (bool) apply_filters( 'storeengine/order/is_paid', 'paid' === $this->get_paid_status( $context ) );
	}

//	/**
//	 * Returns if an order has been paid for based on the order status.
//	 *
//	 * @return bool
//	 */
//	public function is_paid(): bool {
//		return apply_filters( 'storeengine/order_is_paid', $this->has_status( OrderStatus::get_is_paid_statuses() ), $this );
//	}

	/**
	 * Maybe set date completed.
	 *
	 * Sets the date completed variable when transitioning to completed status.
	 */
	protected function maybe_set_date_completed() {
		if ( $this->has_status( OrderStatus::COMPLETED ) ) {
			$this->set_date_completed_gmt( time() );
		}
	}

	/**
	 * Updates status of order immediately.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @param string $note Optional note to add.
	 * @param bool $manual Is this a manual order status change?.
	 *
	 * @return bool
	 * @uses self::set_status()
	 */
	public function update_status( string $new_status, string $note = '', bool $manual = false ): bool {
		if ( ! $this->get_id() ) { // Order must exist.
			return false;
		}

		try {
			$this->set_status( $new_status, $note, $manual );
			$this->save();
		} catch ( Exception $e ) {
			// @TODO implement error logger.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Error updating status for order #%d. Error: %s', $this->get_id(), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$this->add_order_note( __( 'Update status event failed.', 'storeengine' ) . ' ' . $e->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Handle the status transition.
	 */
	protected function status_transition() {
		$status_transition = $this->status_transition;

		// Reset status transition variable.
		$this->status_transition = false;

		if ( $status_transition ) {
			try {
				$new_status = $status_transition['to'];
				$order_id   = $this->get_id();
				/**
				 * Fires when order status is changed.
				 *
				 * @param int $order_id Order ID.
				 * @param Order $this Order object.
				 * @param array $status_transition Status transition data.
				 */
				do_action( "storeengine/order_status_{$new_status}", $order_id, $this, $status_transition );

				if ( ! empty( $status_transition['from'] ) ) {
					/* translators: 1: old order status 2: new order status */
					$transition_note = sprintf( __( 'Order status changed from %1$s to %2$s.', 'storeengine' ), OrderStatus::get_order_status_name( $status_transition['from'] ), OrderStatus::get_order_status_name( $status_transition['to'] ) );

					// Note the transition occurred.
					$this->add_status_transition_note( $transition_note, $status_transition );

					$old_status = $status_transition['from'];

					/**
					 * Fires when order status is changed.
					 *
					 * @param int $order_id Order ID.
					 * @param Order $this Order object.
					 */
					do_action( "storeengine/order_status_{$old_status}_to_{$new_status}", $order_id, $this );

					/**
					 * Fires when order status is changed.
					 *
					 * @param int $order_id Order ID.
					 * @param string $old_status Old Status.
					 * @param string $new_status New Status.
					 * @param Order $this Order object.
					 */
					do_action( 'storeengine/order/status_changed', $order_id, $old_status, $new_status, $this );

					/**
					 * Fires when order status is changed.
					 *
					 * @param int $order_id Order ID.
					 * @param string $old_status Old Status.
					 * @param Order $this Order object.
					 */
					do_action( "storeengine/order/status_{$new_status}", $order_id, $old_status, $this );

					// Work out if this was for a payment, and trigger a payment_status hook instead.
					/**
					 * Filter the valid order statuses for payment.
					 *
					 * @param array $valid_order_statuses Array of valid order statuses for payment.
					 * @param Order $order Order object.
					 */
					$check_transition_from = in_array( $status_transition['from'], apply_filters( 'storeengine/valid_order_statuses_for_payment', [
						OrderStatus::PAYMENT_PENDING,
						OrderStatus::PAYMENT_FAILED,
					], $this ), true );
					$check_transition_to   = in_array( $status_transition['to'], OrderStatus::get_is_paid_statuses(), true );
					if ( $check_transition_from && $check_transition_to ) {
						/**
						 * Fires when the order progresses from a pending payment status to a paid one.
						 *
						 * @param int $order_id Order ID.
						 * @param Order $this Order object.
						 */
						do_action( 'storeengine/order_payment_status_changed', $order_id, $this );
					}
				} else {
					/* translators: %s: new order status */
					$transition_note = sprintf( __( 'Order status set to %s.', 'storeengine' ), OrderStatus::get_order_status_name( $status_transition['to'] ) );

					// Note the transition occurred.
					$this->add_status_transition_note( $transition_note, $status_transition );
				}
			} catch ( Exception $e ) {
				// @TODO implement error logger.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'Status transition of order #%d errored!. Error: %s', $this->get_id(), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				$this->add_order_note( __( 'Error during status transition.', 'storeengine' ) . ' ' . $e->getMessage() );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	|
	| Methods for getting data from the order object.
	|
	*/

	/**
	 * Get basic order data in array format.
	 *
	 * @return array
	 */
	public function get_base_data(): array {
		return array_merge(
			[ 'id' => $this->get_id() ],
			$this->data,
			[ 'number' => $this->get_order_number() ]
		);
	}

	/**
	 * Get all class data in array format.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return array_merge(
			$this->get_base_data(),
			[
				'meta_data'      => $this->get_meta_data(),
				'line_items'     => $this->get_items( 'line_item' ),
				'tax_lines'      => $this->get_items( 'tax' ),
				'shipping_lines' => $this->get_items( 'shipping' ),
				'fee_lines'      => $this->get_items( 'fee' ),
				'coupon_lines'   => $this->get_items( 'coupon' ),
			]
		);
	}

	/**
	 * Expands the shipping and billing information in the changes array.
	 */
	public function get_changes(): array {
		$changed_props = parent::get_changes();
		$subs          = [ 'shipping', 'billing' ];
		foreach ( $subs as $sub ) {
			if ( ! empty( $changed_props[ $sub ] ) ) {
				foreach ( $changed_props[ $sub ] as $sub_prop => $value ) {
					$changed_props[ $sub . '_' . $sub_prop ] = $value;
				}
			}
		}
		if ( isset( $changed_props['customer_note'] ) ) {
			$changed_props['post_excerpt'] = $changed_props['customer_note'];
		}

		return $changed_props;
	}

	/**
	 * Gets the order number for display (by default, order ID).
	 *
	 * @return string
	 */
	public function get_order_number(): string {
		return (string) apply_filters( 'storeengine/order_number', $this->get_id(), $this );
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * @param string $prop Name of prop to get.
	 * @param string $address_type Type of address; 'billing' or 'shipping'.
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	protected function get_address_prop( string $prop, string $address_type = 'billing', string $context = 'view' ): ?string {
		$value = null;

		if ( array_key_exists( $prop, $this->data[ $address_type ] ) ) {
			$value = $this->changes[ $address_type ][ $prop ] ?? $this->data[ $address_type ][ $prop ];

			if ( 'view' === $context ) {
				/**
				 * Filter: 'storeengine/order_get_[billing|shipping]_[prop]'
				 *
				 * Allow developers to change the returned value for any order address property.
				 *
				 * @param string $value The address property value.
				 * @param Order $order The order object being read.
				 *
				 * @ignore Ignore from HookParser.
				 */
				$value = apply_filters( $this->get_hook_prefix( $address_type . '_' . $prop ), $value, $this );
			}
		}

		return $value;
	}

	/**
	 * Get billing first name.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_first_name( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'first_name', 'billing', $context );
	}

	/**
	 * Get billing last name.
	 *
	 * @param ?string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_billing_last_name( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'last_name', 'billing', $context );
	}

	/**
	 * Get billing company.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_company( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'company', 'billing', $context );
	}

	/**
	 * Get billing address line 1.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_address_1( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'address_1', 'billing', $context );
	}

	/**
	 * Get billing address line 2.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_address_2( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'address_2', 'billing', $context );
	}

	/**
	 * Get billing city.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_city( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'city', 'billing', $context );
	}

	/**
	 * Get billing state.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_state( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'state', 'billing', $context );
	}

	/**
	 * Get billing postcode.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_postcode( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'postcode', 'billing', $context );
	}

	/**
	 * Get billing country.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_country( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'country', 'billing', $context );
	}

	/**
	 * Get billing email.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_email( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'email', 'billing', $context );
	}

	/**
	 * Get billing phone.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_billing_phone( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'phone', 'billing', $context );
	}

	/**
	 * Get shipping first name.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_first_name( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'first_name', 'shipping', $context );
	}

	/**
	 * Get shipping_last_name.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_last_name( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'last_name', 'shipping', $context );
	}

	/**
	 * Get shipping company.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_company( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'company', 'shipping', $context );
	}

	/**
	 * Get shipping address line 1.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return? string
	 */
	public function get_shipping_address_1( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'address_1', 'shipping', $context );
	}

	/**
	 * Get shipping address line 2.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_address_2( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'address_2', 'shipping', $context );
	}

	/**
	 * Get shipping city.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_shipping_city( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'city', 'shipping', $context );
	}

	/**
	 * Get shipping state.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_state( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'state', 'shipping', $context );
	}

	/**
	 * Get shipping postcode.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_postcode( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'postcode', 'shipping', $context );
	}

	/**
	 * Get shipping country.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_country( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'country', 'shipping', $context );
	}

	/**
	 * Get shipping country.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_email( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'email', 'shipping', $context );
	}

	/**
	 * Get shipping phone.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_shipping_phone( string $context = 'view' ): ?string {
		return $this->get_address_prop( 'phone', 'shipping', $context );
	}

	/**
	 * Get the payment method.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_payment_method( string $context = 'view' ) {
		return $this->get_prop( 'payment_method', $context );
	}

	/**
	 * Get payment method title.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_payment_method_title( string $context = 'view' ) {
		return $this->get_prop( 'payment_method_title', $context );
	}

	/**
	 * Get transaction id.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_transaction_id( string $context = 'view' ) {
		return $this->get_prop( 'transaction_id', $context );
	}

	/**
	 * Get customer ip address.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_ip_address( string $context = 'view' ) {
		return $this->get_prop( 'ip_address', $context );
	}

	/**
	 * Get customer user agent.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_user_agent( string $context = 'view' ) {
		return $this->get_prop( 'user_agent', $context );
	}

	/**
	 * Get created via.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string
	 */
	public function get_created_via( string $context = 'view' ) {
		return $this->get_prop( 'created_via', $context );
	}

	/**
	 * Get customer note.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_customer_note( string $context = 'view' ) {
		return $this->get_prop( 'customer_note', $context );
	}

	/**
	 * Get cart hash.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_cart_hash( string $context = 'view' ) {
		return $this->get_hash( $context );
	}

	/**
	 * Get cart hash.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_hash( string $context = 'view' ) {
		return $this->get_prop( 'hash', $context );
	}

	/**
	 * Returns the requested address in raw, non-formatted way.
	 * Note: Merges raw data with get_prop data so changes are returned too.
	 *
	 * @param string $address_type Type of address; 'billing' or 'shipping'.
	 *
	 * @return array The stored address after filter.
	 */
	public function get_address( $address_type = 'billing' ) {
		/**
		 * Filter: 'storeengine/get_order_address'
		 *
		 * Allow developers to change the returned value for an order's billing or shipping address.
		 *
		 * @param array $address_data The raw address data merged with the data from get_prop.
		 * @param string $address_type Type of address; 'billing' or 'shipping'.
		 */
		return apply_filters( 'storeengine/get_order_address', array_merge( $this->data[ $address_type ], $this->get_prop( $address_type, 'view' ) ), $address_type, $this );
	}

	/**
	 * Get a formatted shipping address for the order.
	 *
	 * @return string
	 */
	public function get_shipping_address_map_url() {
		$address = $this->get_address( 'shipping' );

		// Remove name and company before generate the Google Maps URL.
		unset( $address['first_name'], $address['last_name'], $address['company'], $address['phone'] );

		$address = apply_filters( 'storeengine/shipping_address_map_url_parts', $address, $this );

		return apply_filters( 'storeengine/shipping_address_map_url', 'https://maps.google.com/maps?&q=' . rawurlencode( implode( ', ', $address ) ) . '&z=16', $this );
	}

	/**
	 * Get a formatted billing full name.
	 *
	 * @return string
	 */
	public function get_formatted_billing_full_name() {
		/* translators: 1: first name 2: last name */
		return sprintf( _x( '%1$s %2$s', 'full name', 'storeengine' ), $this->get_billing_first_name(), $this->get_billing_last_name() );
	}

	/**
	 * Get a formatted shipping full name.
	 *
	 * @return string
	 */
	public function get_formatted_shipping_full_name() {
		/* translators: 1: first name 2: last name */
		return sprintf( _x( '%1$s %2$s', 'full name', 'storeengine' ), $this->get_shipping_first_name(), $this->get_shipping_last_name() );
	}

	/**
	 * Get a formatted billing address for the order.
	 *
	 * @param string $empty_content Content to show if no address is present. @since 3.3.0.
	 *
	 * @return string
	 */
	public function get_formatted_billing_address( $empty_content = '' ) {
		$raw_address = apply_filters( 'storeengine/order_formatted_billing_address', $this->get_address( 'billing' ), $this );
		$address     = Countries::init()->get_formatted_address( $raw_address );

		/**
		 * Filter orders formatted billing address.
		 *
		 * @param string $address Formatted billing address string.
		 * @param array $raw_address Raw billing address.
		 * @param Order $order Order data. @since 3.9.0
		 */
		return apply_filters( 'storeengine/order_get_formatted_billing_address', $address ? $address : $empty_content, $raw_address, $this );
	}

	/**
	 * Get a formatted shipping address for the order.
	 *
	 * @param string $empty_content Content to show if no address is present. @since 3.3.0.
	 *
	 * @return string
	 */
	public function get_formatted_shipping_address( $empty_content = '' ) {
		$address     = '';
		$raw_address = $this->get_address( 'shipping' );

		if ( $this->has_shipping_address() ) {
			$raw_address = apply_filters( 'storeengine/order_formatted_shipping_address', $raw_address, $this );
			$address     = Countries::init()->get_formatted_address( $raw_address );
		}

		/**
		 * Filter orders formatted shipping address.
		 *
		 * @param string $address Formatted shipping address string.
		 * @param array $raw_address Raw shipping address.
		 * @param Order $order Order data. @since 3.9.0
		 */
		return apply_filters( 'storeengine/order_get_formatted_shipping_address', $address ? $address : $empty_content, $raw_address, $this );
	}

	/**
	 * Returns true if the order has a billing address.
	 *
	 * @param string $context
	 *
	 * @return boolean
	 */
	public function has_billing_address( string $context = 'view' ): bool {
		return $this->get_billing_address_1( $context ) || $this->get_billing_address_2( $context );
	}

	/**
	 * Returns true if the order has a shipping address.
	 *
	 * @param string $context
	 *
	 * @return boolean
	 */
	public function has_shipping_address( string $context = 'view' ): bool {
		return $this->get_shipping_address_1( $context ) || $this->get_shipping_address_2( $context );
	}

	/**
	 * Gets information about whether stock was reduced.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool
	 */
	public function get_order_stock_reduced( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'order_stock_reduced', $context ) );
	}

	/**
	 * Gets information about whether permissions were generated yet.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool True if permissions were generated, false otherwise.
	 */
	public function get_download_permissions_granted( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'download_permissions_granted', $context ) );
	}

	public function get_auto_complete_digital_order( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'auto_complete_digital_order', $context ) );
	}

	/**
	 * Whether email have been sent for this order.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool
	 */
	public function get_new_order_email_sent( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'new_order_email_sent', $context ) );
	}

	/**
	 * Gets information about whether sales were recorded.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool True if sales were recorded, false otherwise.
	 */
	public function get_recorded_sales( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'recorded_sales', $context ) );
	}

	/**
	 * @param string $context
	 *
	 * @return null|StoreengineDatetime
	 */
	public function get_order_placed_date_gmt( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'order_placed_date_gmt', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return null|StoreengineDatetime
	 */
	public function get_order_placed_date( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'order_placed_date', $context );
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	|
	| Functions for setting order data. These should not update anything in the
	| database itself and should only change what is stored in the class
	| object. However, for backwards compatibility pre 3.0.0 some of these
	| setters may handle both.
	|
	*/

	/**
	 * Sets a prop for a setter method.
	 *
	 * @param string $prop Name of prop to set.
	 * @param string $address_type Type of address; 'billing' or 'shipping'.
	 * @param ?string $value Value of the prop.
	 */
	protected function set_address_prop( $prop, string $address_type, ?string $value ) {
		if ( isset( $this->data[ $address_type ] ) && array_key_exists( $prop, $this->data[ $address_type ] ) ) {
			if ( true === $this->object_read ) {
				if ( $value !== $this->data[ $address_type ][ $prop ] || ( isset( $this->changes[ $address_type ] ) && array_key_exists( $prop, $this->changes[ $address_type ] ) ) ) {
					$this->changes[ $address_type ][ $prop ] = $value;
				}
			} else {
				$this->data[ $address_type ][ $prop ] = $value;
			}
		}
	}

	/**
	 * Setter for billing address, expects the $address parameter to be key value pairs for individual address props.
	 *
	 * @param array $address Address to set.
	 *
	 * @return void
	 */
	public function set_billing_address( array $address ) {
		foreach ( $address as $key => $value ) {
			$this->set_address_prop( $key, 'billing', $value );
		}
	}

	/**
	 * Shortcut for calling set_billing_address.
	 *
	 * This is useful in scenarios where set_$prop_name is invoked, and since we store the billing address as 'billing' prop in data, it can be called directly.
	 *
	 * @param array $address Address to set.
	 *
	 * @return void
	 */
	public function set_billing( array $address ) {
		$this->set_billing_address( $address );
	}

	/**
	 * Setter for shipping address, expects the $address parameter to be key value pairs for individual address props.
	 *
	 * @param array $address Address to set.
	 *
	 * @return void
	 */
	public function set_shipping_address( array $address ) {
		foreach ( $address as $key => $value ) {
			$this->set_address_prop( $key, 'shipping', $value );
		}
	}

	/**
	 * Shortcut for calling set_shipping_address. This is useful in scenarios where set_$prop_name is invoked, and since we store the shipping address as 'shipping' prop in data, it can be called directly.
	 *
	 * @param array $address Address to set.
	 *
	 * @return void
	 */
	public function set_shipping( array $address ) {
		$this->set_shipping_address( $address );
	}

	/**
	 * Set customer id.
	 *
	 * @param int $value Customer ID.
	 */
	public function set_customer_id( $value ) {
		$this->set_prop( 'customer_id', absint( $value ) );
	}

	/**
	 * Set billing first name.
	 *
	 * @param ?string $value Billing first name.
	 */
	public function set_billing_first_name( ?string $value ) {
		$this->set_address_prop( 'first_name', 'billing', $value );
	}

	/**
	 * Set billing last name.
	 *
	 * @param ?string $value Billing last name.
	 */
	public function set_billing_last_name( ?string $value ) {
		$this->set_address_prop( 'last_name', 'billing', $value );
	}

	/**
	 * Set billing company.
	 *
	 * @param ?string $value Billing company.
	 */
	public function set_billing_company( ?string $value ) {
		$this->set_address_prop( 'company', 'billing', $value );
	}

	/**
	 * Set billing address line 1.
	 *
	 * @param ?string $value Billing address line 1.
	 */
	public function set_billing_address_1( ?string $value ) {
		$this->set_address_prop( 'address_1', 'billing', $value );
	}

	/**
	 * Set billing address line 2.
	 *
	 * @param ?string $value Billing address line 2.
	 */
	public function set_billing_address_2( ?string $value ) {
		$this->set_address_prop( 'address_2', 'billing', $value );
	}

	/**
	 * Set billing city.
	 *
	 * @param ?string $value Billing city.
	 */
	public function set_billing_city( ?string $value ) {
		$this->set_address_prop( 'city', 'billing', $value );
	}

	/**
	 * Set billing state.
	 *
	 * @param ?string $value Billing state.
	 */
	public function set_billing_state( ?string $value ) {
		$this->set_address_prop( 'state', 'billing', $value );
	}

	/**
	 * Set billing postcode.
	 *
	 * @param ?string $value Billing postcode.
	 */
	public function set_billing_postcode( ?string $value ) {
		$this->set_address_prop( 'postcode', 'billing', $value );
	}

	/**
	 * Set billing country.
	 *
	 * @param ?string $value Billing country.
	 */
	public function set_billing_country( ?string $value ) {
		$this->set_address_prop( 'country', 'billing', $value );
	}

	/**
	 * Maybe set empty billing email to that of the user who owns the order.
	 */
	protected function maybe_set_user_billing_email() {
		$user = $this->get_user();
		if ( ! $this->get_billing_email() && $user ) {
			try {
				$this->set_billing_email( $user->user_email );
			} catch ( Exception $e ) {
				unset( $e );
			}
		}
	}

	/**
	 * Set billing email.
	 *
	 * @param ?string $value Billing email.
	 *
	 * @throws StoreEngineException
	 */
	public function set_billing_email( ?string $value = '' ) {
		$value = $value ?? '';
		if ( $value && ! is_email( $value ) ) {
			$this->error( 'order_invalid_billing_email', __( 'Invalid billing email address', 'storeengine' ) );
		}

		$this->set_address_prop( 'email', 'billing', sanitize_email( $value ) );
	}

	/**
	 * Set billing phone.
	 *
	 * @param ?string $value Billing phone.
	 */
	public function set_billing_phone( ?string $value ) {
		$this->set_address_prop( 'phone', 'billing', $value );
	}

	/**
	 * Set shipping first name.
	 *
	 * @param ?string $value Shipping first name.
	 */
	public function set_shipping_first_name( ?string $value ) {
		$this->set_address_prop( 'first_name', 'shipping', $value );
	}

	/**
	 * Set shipping last name.
	 *
	 * @param ?string $value Shipping last name.
	 */
	public function set_shipping_last_name( ?string $value ) {
		$this->set_address_prop( 'last_name', 'shipping', $value );
	}

	/**
	 * Set shipping company.
	 *
	 * @param ?string $value Shipping company.
	 */
	public function set_shipping_company( ?string $value ) {
		$this->set_address_prop( 'company', 'shipping', $value );
	}

	/**
	 * Set shipping address line 1.
	 *
	 * @param ?string $value Shipping address line 1.
	 */
	public function set_shipping_address_1( ?string $value ) {
		$this->set_address_prop( 'address_1', 'shipping', $value );
	}

	/**
	 * Set shipping address line 2.
	 *
	 * @param ?string $value Shipping address line 2.
	 */
	public function set_shipping_address_2( ?string $value ) {
		$this->set_address_prop( 'address_2', 'shipping', $value );
	}

	/**
	 * Set shipping city.
	 *
	 * @param ?string $value Shipping city.
	 */
	public function set_shipping_city( ?string $value ) {
		$this->set_address_prop( 'city', 'shipping', $value );
	}

	/**
	 * Set shipping state.
	 *
	 * @param ?string $value Shipping state.
	 */
	public function set_shipping_state( ?string $value ) {
		$this->set_address_prop( 'state', 'shipping', $value );
	}

	/**
	 * Set shipping postcode.
	 *
	 * @param ?string $value Shipping postcode.
	 */
	public function set_shipping_postcode( ?string $value ) {
		$this->set_address_prop( 'postcode', 'shipping', $value );
	}

	/**
	 * Set shipping country.
	 *
	 * @param ?string $value Shipping country.
	 */
	public function set_shipping_country( ?string $value ) {
		$this->set_address_prop( 'country', 'shipping', $value );
	}

	/**
	 * Set shipping phone.
	 *
	 * @param ?string $value Shipping phone.
	 */
	public function set_shipping_phone( ?string $value ) {
		$this->set_address_prop( 'phone', 'shipping', $value );
	}

	/**
	 * Set shipping phone.
	 *
	 * @param ?string $value Shipping phone.
	 *
	 * @throws StoreEngineException
	 */
	public function set_shipping_email( ?string $value ) {
		$value = $value ?? '';
		if ( $value && ! is_email( $value ) ) {
			$this->error( 'order_invalid_shipping_email', __( 'Invalid shipping email address', 'storeengine' ) );
		}

		$this->set_address_prop( 'email', 'shipping', sanitize_email( $value ) );
	}

	/**
	 * Set the payment method.
	 *
	 * @param string|PaymentGateway $payment_method Supports WC_Payment_Gateway for bw compatibility with < 3.0.
	 */
	public function set_payment_method( $payment_method = '' ) {
		if ( is_object( $payment_method ) ) {
			$this->set_payment_method( $payment_method->id );
			$this->set_payment_method_title( $payment_method->get_title() );
		} elseif ( '' === $payment_method ) {
			$this->set_prop( 'payment_method', '' );
			$this->set_prop( 'payment_method_title', '' );
		} else {
			$this->set_prop( 'payment_method', $payment_method );
		}
	}

	/**
	 * Set payment method title.
	 *
	 * @param string $value Payment method title.
	 */
	public function set_payment_method_title( string $value ) {
		$this->set_prop( 'payment_method_title', $value );
	}

	/**
	 * Check if the subscription has a payment gateway.
	 *
	 * @return bool
	 */
	public function has_payment_gateway(): bool {
		return (bool) Helper::get_payment_gateway_by_order( $this );
	}

	/**
	 * Set transaction id.
	 *
	 * @param ?string $value Transaction id.
	 */
	public function set_transaction_id( ?string $value ) {
		$this->set_prop( 'transaction_id', $value );
	}

	/**
	 * Set customer ip address.
	 *
	 * @param ?string $value Customer ip address.
	 */
	public function set_ip_address( ?string $value ) {
		$this->set_prop( 'ip_address', $value );
	}

	/**
	 * Set customer user agent.
	 *
	 * @param ?string $value Customer user agent.
	 */
	public function set_user_agent( ?string $value ) {
		$this->set_prop( 'user_agent', $value );
	}

	/**
	 * Set created via.
	 *
	 * @param ?string $value Created via.
	 */
	public function set_created_via( ?string $value ) {
		$this->set_prop( 'created_via', $value );
	}

	/**
	 * Set customer note.
	 *
	 * @param ?string $value Customer note.
	 */
	public function set_customer_note( ?string $value ) {
		$this->set_prop( 'customer_note', $value );
	}

	/**
	 * Set cart hash.
	 *
	 * @param string $value Cart hash.
	 */
	public function set_cart_hash( $value ) {
		$this->set_hash( $value );
	}

	/**
	 * Set cart hash.
	 *
	 * @param string $value Cart hash.
	 */
	public function set_hash( $value ) {
		$this->set_prop( 'hash', $value );
	}

	/**
	 * Stores information about whether stock was reduced.
	 *
	 * @param bool|string $value True if stock was reduced, false if not.
	 *
	 * @return void
	 */
	public function set_order_stock_reduced( $value ) {
		$this->set_prop( 'order_stock_reduced', Formatting::string_to_bool( $value ) );
	}

	/**
	 * Stores information about whether permissions were generated yet.
	 *
	 * @param bool|string $value True if permissions were generated, false if not.
	 *
	 * @return void
	 */
	public function set_download_permissions_granted( $value ) {
		$this->set_prop( 'download_permissions_granted', Formatting::string_to_bool( $value ) );
	}

	public function set_auto_complete_digital_order( $value ) {
		$this->set_prop( 'auto_complete_digital_order', Formatting::string_to_bool( $value ) );
	}

	/**
	 * Stores information about whether email was sent.
	 *
	 * @param bool|string $value True if email was sent, false if not.
	 *
	 * @return void
	 */
	public function set_new_order_email_sent( $value ) {
		$this->set_prop( 'new_order_email_sent', Formatting::string_to_bool( $value ) );
	}

	/**
	 * Stores information about whether sales were recorded.
	 *
	 * @param bool|string $value True if sales were recorded, false if not.
	 *
	 * @return void
	 */
	public function set_recorded_sales( $value ) {
		$this->set_prop( 'recorded_sales', Formatting::string_to_bool( $value ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	|
	| Checks if a condition is true or false.
	|
	*/

	/**
	 * Check if an order key is valid.
	 *
	 * @param string $key Order key.
	 *
	 * @return bool
	 */
	public function key_is_valid( $key ) {
		return hash_equals( $this->get_order_key(), $key );
	}

	/**
	 * See if order matches cart_hash.
	 *
	 * @param string $cart_hash Cart hash.
	 *
	 * @return bool
	 */
	public function has_cart_hash( $cart_hash = '' ) {
		return hash_equals( $this->get_cart_hash(), $cart_hash ); // @codingStandardsIgnoreLine
	}

	/**
	 * Checks if an order can be edited, specifically for use on the Edit Order screen.
	 *
	 * @return bool
	 */
	public function is_editable(): bool {
		/**
		 * Filter to check if an order is editable.
		 *
		 * @param bool $is_editable Is the order editable.
		 * @param Order $order Order object.
		 *
		 * @see WC_Stripe_Subscriptions_Trait::disable_subscription_edit_for_india
		 */
		return apply_filters( 'storeengine/order_is_editable', in_array( $this->get_status(), [
			OrderStatus::PAYMENT_PENDING,
			OrderStatus::ON_HOLD,
			OrderStatus::AUTO_DRAFT,
		], true ), $this );
	}

	/**
	 * Checks if product download is permitted.
	 *
	 * @return bool
	 */
	public function is_download_permitted(): bool {
		/**
		 * Filter to check if an order is downloadable.
		 *
		 * @param bool $is_download_permitted Is the order downloadable.
		 * @param Order $this Order object.
		 */
		return apply_filters( 'storeengine/order_is_download_permitted', $this->has_status( OrderStatus::COMPLETED ) || ( 'yes' === get_option( 'storeengine/downloads_grant_access_after_payment' ) && $this->has_status( OrderStatus::PROCESSING ) ), $this );
	}

	/**
	 * Checks if an order needs display the shipping address, based on shipping method.
	 *
	 * @return bool
	 */
	public function needs_shipping_address(): bool {
		if ( 'no' === get_option( 'storeengine/calc_shipping' ) ) {
			return false;
		}

		$hide          = apply_filters( 'storeengine/order_hide_shipping_address', [ 'local_pickup' ], $this );
		$needs_address = false;

		foreach ( $this->get_shipping_methods() as $shipping_method ) {
			$shipping_method_id = $shipping_method->get_method_id();

			if ( ! in_array( $shipping_method_id, $hide, true ) ) {
				$needs_address = true;
				break;
			}
		}

		return apply_filters( 'storeengine/order_needs_shipping_address', $needs_address, $hide, $this );
	}

	/**
	 * Returns true if the order contains a downloadable product.
	 *
	 * @return bool
	 */
	public function has_downloadable_item() {
		foreach ( $this->get_items() as $item ) {
			if ( $item->is_type( 'line_item' ) ) {
				$product = $item->get_product();

				if ( $product && $product->has_file() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get downloads from all line items for this order.
	 *
	 * @return array
	 */
	public function get_downloadable_items(): array {
		$downloads = [];

		foreach ( $this->get_items() as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			// Check item refunds.
			$refunded_qty = abs( $this->get_qty_refunded_for_item( $item->get_id() ) );
			if ( $refunded_qty && $item->get_quantity() === $refunded_qty ) {
				continue;
			}

			if ( $item->is_type( 'line_item' ) ) {
				$item_downloads = $item->get_item_downloads();
				$product        = $item->get_product();
				if ( $product && $item_downloads ) {
					foreach ( $item_downloads as $file ) {
						$downloads[] = [
							'download_url'        => $file['download_url'],
							'download_id'         => $file['id'],
							'product_id'          => $product->get_id(),
							'product_name'        => $product->get_name(),
							'product_url'         => $product->is_visible() ? $product->get_permalink() : '',
							'download_name'       => $file['name'],
							'order_id'            => $this->get_id(),
							'order_key'           => $this->get_order_key(),
							'downloads_remaining' => $file['downloads_remaining'],
							'access_expires'      => $file['access_expires'],
							'file'                => [
								'name' => $file['name'],
								'file' => $file['file'],
							],
						];
					}
				}
			}
		}

		return apply_filters( 'storeengine/order_get_downloadable_items', $downloads, $this );
	}

	/**
	 * Checks if an order needs payment, based on status and order total.
	 *
	 * @return bool
	 */
	public function needs_payment(): bool {
		/**
		 * Filter the valid order statuses for payment.
		 *
		 * @param array $valid_order_statuses Array of valid order statuses for payment.
		 * @param Order $order Order object.
		 */
		$paid_status          = $this->get_paid_status();
		$valid_order_statuses = apply_filters( 'storeengine/valid_order_statuses_for_payment', [ 'unpaid', 'failed' ], $this );

		return apply_filters( 'storeengine/order_needs_payment', ( in_array( $paid_status, $valid_order_statuses, true ) && $this->get_total() > 0 ), $this, $valid_order_statuses );
	}

	/**
	 * See if the order needs processing before it can be completed.
	 *
	 * Orders which only contain virtual, downloadable items do not need admin
	 * intervention.
	 *
	 * Uses a transient so these calls are not repeated multiple times, and because
	 * once the order is processed this code/transient does not need to persist.
	 *
	 * @return bool
	 */
	public function needs_processing(): bool {
		$transient_name   = 'storeengine/order_' . $this->get_id() . '_needs_processing';
		$needs_processing = get_transient( $transient_name );

		if ( false === $needs_processing ) {
			$needs_processing = 0;

			if ( count( $this->get_items() ) > 0 ) {
				foreach ( $this->get_items() as $item ) {
					if ( $item->is_type( 'line_item' ) ) {
						/** @var $product AbstractProduct */
						$product = $item->get_product();

						if ( ! $product ) {
							continue;
						}

						$virtual_downloadable_item = $product->is_downloadable() && $product->is_virtual();

						if ( apply_filters( 'storeengine/order/item_needs_processing', ! $virtual_downloadable_item, $product, $this->get_id() ) ) {
							$needs_processing = 1;
							break;
						}
					}
				}
			}

			set_transient( $transient_name, $needs_processing, DAY_IN_SECONDS );
		}

		return 1 === absint( $needs_processing );
	}

	/*
	|--------------------------------------------------------------------------
	| URLs and Endpoints
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generates a URL so that a customer can pay for their (unpaid - pending) order. Pass 'true' for the checkout version which doesn't offer gateway choices.
	 *
	 * @param bool $on_checkout If on checkout.
	 *
	 * @return string
	 */
	public function get_checkout_payment_url( bool $on_checkout = false ): string {
		$pay_url = Helper::get_endpoint_url( 'order-pay', $this->get_id(), Helper::get_checkout_url() );

		if ( $on_checkout ) {
			$pay_url = add_query_arg( 'key', $this->get_order_key(), $pay_url );
		} else {
			$pay_url = add_query_arg( [
				'pay_for_order' => 'true',
				'key'           => $this->get_order_key(),
			], $pay_url );
		}

		return apply_filters( 'storeengine/get_checkout_payment_url', $pay_url, $this );
	}

	/**
	 * Generates a URL for the thanks page (order received).
	 *
	 * @return string
	 */
	public function get_checkout_order_received_url(): string {
		$order_received_url = add_query_arg( 'order_hash', $this->get_order_key(), Helper::get_page_permalink( 'thankyou_page' ) );

		return apply_filters( 'storeengine/order/get_checkout_order_received_url', $order_received_url, $this );
	}

	/**
	 * Generates a URL so that a customer can cancel their (unpaid - pending) order.
	 *
	 * @param string $redirect Redirect URL.
	 *
	 * @return string
	 * @see \WC_Form_Handler::cancel_order
	 */
	public function get_cancel_order_url( string $redirect = '' ): string {
		/**
		 * Filter the URL to cancel the order in the frontend.
		 *
		 * @param string $url
		 * @param Order $order Order data.
		 * @param string $redirect Redirect URL.
		 */
		return apply_filters(
			'storeengine/order/get_cancel_order_url',
			wp_nonce_url(
				add_query_arg(
					[
						'cancel_order' => 'true',
						'order'        => $this->get_order_key(),
						'order_id'     => $this->get_id(),
						'redirect'     => $redirect,
					],
					$this->get_cancel_endpoint()
				),
				'storeengine-cancel_order'
			),
			$this,
			$redirect
		);
	}

	/**
	 * Generates a raw (unescaped) cancel-order URL for use by payment gateways.
	 *
	 * @param string $redirect Redirect URL.
	 *
	 * @return string The unescaped cancel-order URL.
	 *
	 * @see \WC_Form_Handler::cancel_order
	 */
	public function get_cancel_order_url_raw( string $redirect = '' ): string {
		/**
		 * Filter the raw URL to cancel the order in the frontend.
		 *
		 * @param string $url
		 * @param Order $order Order data.
		 * @param string $redirect Redirect URL.
		 */
		return apply_filters(
			'storeengine/order/get_cancel_order_url_raw',
			add_query_arg(
				[
					'cancel_order' => 'true',
					'order'        => $this->get_order_key(),
					'order_id'     => $this->get_id(),
					'redirect'     => $redirect,
					'_wpnonce'     => wp_create_nonce( 'storeengine-cancel_order' ),
				],
				$this->get_cancel_endpoint()
			),
			$this,
			$redirect
		);
	}

	/**
	 * Helper method to return the cancel endpoint.
	 *
	 * @return string the cancel endpoint; either the cart page or the home page.
	 */
	public function get_cancel_endpoint(): string {
		$cancel_endpoint = Helper::get_cart_url();
		if ( ! $cancel_endpoint ) {
			$cancel_endpoint = home_url();
		}

		if ( false === strpos( $cancel_endpoint, '?' ) ) {
			$cancel_endpoint = trailingslashit( $cancel_endpoint );
		}

		return $cancel_endpoint;
	}

	/**
	 * Generates a URL to view an order from the myaccount page.
	 *
	 * @return string
	 */
	public function get_view_order_url(): string {
		return apply_filters( 'storeengine/get_view_order_url', Helper::get_account_endpoint_url( 'orders', $this->get_id() ), $this );
	}

	/**
	 * Get's the URL to edit the order in the backend.
	 *
	 * @return string
	 */
	public function get_edit_order_url(): string {
		$edit_url = admin_url( 'admin.php?page=storeengine-orders&id=' . $this->get_id() . '&action=edit' );

		/**
		 * Filter the URL to edit the order in the backend.
		 */
		return apply_filters( 'storeengine/get_edit_order_url', $edit_url, $this );
	}

	/*
	|--------------------------------------------------------------------------
	| Order notes.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds a note (comment) to the order. Order must exist.
	 *
	 * @param string $note Note to add.
	 * @param int|string $is_customer_note Is this a note for the customer?.
	 * @param bool $added_by_user Was the note added by a user?.
	 *
	 * @return int                       Comment ID.
	 */
	public function add_order_note( string $note, $is_customer_note = 0, bool $added_by_user = false ): int {
		if ( ! $this->get_id() ) {
			return 0;
		}

		$is_customer_note = absint( $is_customer_note );

		// @TODO edit_shop_orders cap doesn't exists in storeengine.

		if ( is_user_logged_in() && current_user_can( 'edit_shop_orders', $this->get_id() ) && $added_by_user ) {
			$user                 = get_user_by( 'id', get_current_user_id() );
			$comment_author       = $user->display_name;
			$comment_author_email = $user->user_email;
		} else {
			$comment_author       = _x( 'StoreEngine', 'System Comment Author', 'storeengine' );
			$comment_author_email = strtolower( $comment_author ) . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
			$comment_author_email = sanitize_email( $comment_author_email );
		}

		$commentdata = apply_filters(
			'storeengine/new_order_note_data',
			[
				'comment_post_ID'      => $this->get_id(),
				'comment_author'       => $comment_author,
				'comment_author_email' => $comment_author_email,
				'comment_author_url'   => '',
				'comment_content'      => $note,
				'comment_agent'        => 'StoreEngine',
				'comment_type'         => 'order_note',
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			],
			[
				'order_id'         => $this->get_id(),
				'is_customer_note' => $is_customer_note,
			]
		);

		$comment_id = wp_insert_comment( $commentdata );

		if ( $is_customer_note ) {
			add_comment_meta( $comment_id, 'is_customer_note', 1 );

			/**
			 * Action hook fired after an order note is added for the customer.
			 *
			 * @param string $note Comment data.
			 * @param Order $this Comment data.
			 */
			do_action( 'storeengine/order/new_customer_note', $note, $this );
		}

		/**
		 * Action hook fired after an order note is added.
		 *
		 * @param int $comment_id Order note ID.
		 * @param Order $this Order object.
		 */
		do_action( 'storeengine/order/note_added', $comment_id, $this );

		return $comment_id;
	}

	/**
	 * Add an order note for status transition
	 *
	 * @param string $note Note to be added giving status transition from and to details.
	 * @param bool $transition Details of the status transition.
	 *
	 * @return int                  Comment ID.
	 * @uses self::add_order_note()
	 */
	protected function add_status_transition_note( $note, $transition ) {
		return $this->add_order_note( trim( $transition['note'] . ' ' . $note ), 0, $transition['manual'] );
	}

	/**
	 * List order notes (public) for the customer.
	 *
	 * @return WP_Comment[]
	 */
	public function get_customer_order_notes(): array {
		return $this->get_order_notes( 'customer' );
	}

	/**
	 * List order notes.
	 *
	 * @param string $customer_notes switch for customer (public) notes or internal (admin) notes. Default all notes.
	 * @param bool $ids
	 *
	 * @return WP_Comment[]
	 * @see wc_get_order_note
	 */
	public function get_order_notes( string $customer_notes = '', bool $ids = false ): array {
		$notes = [];

		if ( ! $this->get_id() ) {
			return $notes;
		}

		$args = [
			'post_id'    => $this->get_id(),
			'orderby'    => 'comment_ID',
			'order'      => 'DESC',
			'approve'    => 'approve',
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
			],
		];

		// type->order_note & author->StoreEngine conditions are added via filter below.

		if ( $ids ) {
			$args['fields'] = 'ids';
		}

		if ( 'customer' === $customer_notes ) {
			$args['meta_query'][] = [
				'key'     => 'is_customer_note',
				'value'   => 1,
				'compare' => '=',
				'type'    => 'UNSIGNED',
			];
		} elseif ( 'internal' === $customer_notes ) {
			$args['meta_query'][] = [
				'key'     => 'is_customer_note',
				'compare' => 'NOT EXISTS',
			];
		}

		remove_filter( 'comments_clauses', [ Hooks::class, 'exclude_order_comments' ] );
		add_filter( 'comments_clauses', [ Hooks::class, 'include_order_comments' ] );

		$comments = get_comments( $args );

		foreach ( $comments as $comment ) {
			$comment->comment_content = make_clickable( $comment->comment_content );
			$notes[]                  = $comment;
		}

		remove_filter( 'comments_clauses', [ Hooks::class, 'include_order_comments' ] );
		add_filter( 'comments_clauses', [ Hooks::class, 'exclude_order_comments' ] );

		return array_filter( array_map( [ __CLASS__, 'get_order_note' ], $notes ) );
	}

	/**
	 * Get an order note.
	 *
	 * @param int|WP_Comment $data Note ID (or WP_Comment instance for internal use only).
	 *
	 * @return stdClass|null        Object with order note details or null when does not exists.
	 * @throws StoreEngineException
	 */
	public static function get_order_note( $data ) {
		if ( is_numeric( $data ) ) {
			$data = get_comment( $data );
		}

		if ( ! is_a( $data, 'WP_Comment' ) ) {
			return null;
		}

		// @TODO use OrderNote object.
		return (object) apply_filters( 'storeengine/order/get_order_note', [
			'id'            => (int) $data->comment_ID,
			'date_created'  => $data->comment_date_gmt,
			//'date_created'  => Formatting::string_to_datetime( $data->comment_date ),
			'content'       => $data->comment_content,
			'customer_note' => (bool) get_comment_meta( $data->comment_ID, 'is_customer_note', true ),
			'added_by'      => __( 'StoreEngine', 'storeengine' ) === $data->comment_author ? 'system' : $data->comment_author,
			'order_id'      => absint( $data->comment_post_ID ),
		], $data );
	}

	/**
	 * Delete an order note.
	 *
	 * @param int $note_id Order note.
	 *
	 * @return bool         True on success, false on failure.
	 * @throws StoreEngineException
	 */
	public static function delete_order_note( int $note_id ): bool {
		$note = self::get_order_note( $note_id );
		if ( $note && wp_delete_comment( $note_id, true ) ) {
			/**
			 * Action hook fired after an order note is deleted.
			 *
			 * @param int      $note_id Order note ID.
			 * @param stdClass $note    Object with the deleted order note details.
			 */
			do_action( 'storeengine/order/note_deleted', $note_id, $note );

			return true;
		}

		return false;
	}

	/*
	|--------------------------------------------------------------------------
	| Refunds
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get order refunds.
	 *
	 * @return Refund[] of Order_Refund objects
	 * @throws StoreEngineException
	 */
	public function get_refunds(): array {
		$cache_key = Caching::get_cache_prefix( 'orders' ) . 'refunds' . $this->get_id();
		$ids       = wp_cache_get( $cache_key, $this->cache_group );
		$refunds   = [];

		if ( false === $ids || ! is_array( $ids ) ) {
			$query = ( new Refund() )->query();
			// @TODO cache properly to prevent 2x query while creating refund object.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared in refund class.
			$results = $this->wpdb->get_results( $this->wpdb->prepare( "$query WHERE o.parent_order_id = %d AND o.type = %s GROUP BY o_id;", $this->get_id(), 'refund_order' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- common query prepared
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared

			if ( ! $results ) {
				return $refunds;
			}

			$ids = array_unique( array_filter( array_map( 'absint', array_column( $results, 'o_id' ) ) ) );
			wp_cache_set( $cache_key, $ids, $this->cache_group );
		}

		foreach ( $ids as $id ) {
			$refunds[] = new Refund( $id );
		}

		return $refunds;
	}

	/**
	 * Get amount already refunded.
	 *
	 * @param bool $refresh
	 *
	 * @return float|int
	 * @see \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_total_refunded()
	 */
	public function get_total_refunded( bool $refresh = false ) {
		$cache_key   = Caching::get_cache_prefix( 'orders' ) . 'total_refunded' . $this->get_id();
		$cached_data = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_data && ! $refresh ) {
			return (float) $cached_data;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is hardcoded.
		$total_refunded = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT SUM( total_amount ) FROM $this->table WHERE type = %s AND parent_order_id = %d;",
				'refund_order',
				$this->get_id()
			)
		) ?? 0;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is hardcoded.

		$total_refunded = - 1 * floatval( $total_refunded );

		wp_cache_set( $cache_key, $total_refunded, $this->cache_group );

		return $total_refunded;
	}

	/**
	 * Get the total tax refunded.
	 *
	 * @return float
	 */
	public function get_total_tax_refunded(): float {
		$cache_key   = Caching::get_cache_prefix( 'orders' ) . 'total_tax_refunded' . $this->get_id();
		$cached_data = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared.
		$total_refunded = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT SUM( order_item_meta.meta_value )
				FROM {$wpdb->prefix}storeengine_order_item_meta AS order_item_meta
				INNER JOIN $this->table AS orders ON ( orders.type = 'shop_order_refund' AND orders.parent_order_id = %d )
				INNER JOIN {$this->wpdb->prefix}storeengine_order_items AS order_items ON ( order_items.order_id = orders.id AND order_items.order_item_type = 'tax' )
				WHERE order_item_meta.order_item_id = order_items.order_item_id
				AND order_item_meta.meta_key IN ('tax_amount', 'shipping_tax_amount')",
				$this->get_id()
			)
		) ?? 0;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared

		$total_refunded = floatval( $total_refunded );

		wp_cache_set( $cache_key, $total_refunded, $this->cache_group );

		return $total_refunded;
	}

	/**
	 * Get the total shipping refunded.
	 *
	 * @return float
	 */
	public function get_total_shipping_refunded() {
		$cache_key   = Caching::get_cache_prefix( 'orders' ) . 'total_shipping_refunded' . $this->get_id();
		$cached_data = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared
		$total_refunded = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT SUM( order_item_meta.meta_value )
				FROM {$this->wpdb->prefix}storeengine_order_item_meta AS order_item_meta
				INNER JOIN $this->table AS orders ON ( orders.type = 'shop_order_refund' AND orders.parent_order_id = %d )
				INNER JOIN {$this->wpdb->prefix}storeengine_order_items AS order_items ON ( order_items.order_id = orders.id AND order_items.order_item_type = 'shipping' )
				WHERE order_item_meta.order_item_id = order_items.order_item_id
				AND order_item_meta.meta_key IN ('cost')",
				$this->get_id()
			)
		) ?? 0;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared

		$total_refunded = floatval( $total_refunded );

		wp_cache_set( $cache_key, $total_refunded, $this->cache_group );

		return $total_refunded;
	}

	/**
	 * Gets the count of order items of a certain type that have been refunded.
	 *
	 * @param string $item_type Item type.
	 *
	 * @return int
	 */
	public function get_item_count_refunded( $item_type = '' ): int {
		if ( empty( $item_type ) ) {
			$item_type = [ 'line_item' ];
		}
		if ( ! is_array( $item_type ) ) {
			$item_type = [ $item_type ];
		}
		$count = 0;

		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				$count += abs( $refunded_item->get_quantity() );
			}
		}

		return apply_filters( 'storeengine/get_item_count_refunded', $count, $item_type, $this );
	}

	/**
	 * Get the total number of items refunded.
	 *
	 * @param string $item_type Type of the item we're checking, if not a line_item.
	 *
	 * @return int
	 */
	public function get_total_qty_refunded( $item_type = 'line_item' ) {
		$qty = 0;
		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				$qty += $refunded_item->get_quantity();
			}
		}

		return $qty;
	}

	/**
	 * Get the refunded amount for a line item.
	 *
	 * @param int $item_id ID of the item we're checking.
	 * @param string $item_type Type of the item we're checking, if not a line_item.
	 *
	 * @return int
	 */
	public function get_qty_refunded_for_item( $item_id, $item_type = 'line_item' ) {
		$qty = 0;
		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				if ( absint( $refunded_item->get_meta( '_refunded_item_id' ) ) === $item_id ) {
					$qty += $refunded_item->get_quantity();
				}
			}
		}

		return $qty;
	}

	/**
	 * Get the refunded amount for a line item.
	 *
	 * @param int $item_id ID of the item we're checking.
	 * @param string $item_type Type of the item we're checking, if not a line_item.
	 *
	 * @return int
	 */
	public function get_total_refunded_for_item( $item_id, $item_type = 'line_item' ) {
		$total = 0;
		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				if ( absint( $refunded_item->get_meta( '_refunded_item_id' ) ) === $item_id ) {
					$total += $refunded_item->get_total();
				}
			}
		}

		return $total * - 1;
	}

	/**
	 * Get the refunded tax amount for a line item.
	 *
	 * @param int $item_id ID of the item we're checking.
	 * @param int $tax_id ID of the tax we're checking.
	 * @param string $item_type Type of the item we're checking, if not a line_item.
	 *
	 * @return double
	 */
	public function get_tax_refunded_for_item( $item_id, $tax_id, $item_type = 'line_item' ) {
		$total = 0;
		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				$refunded_item_id = (int) $refunded_item->get_meta( '_refunded_item_id' );
				if ( $refunded_item_id === $item_id ) {
					$taxes = $refunded_item->get_taxes();
					// Add to total.
					$total += isset( $taxes['total'][ $tax_id ] ) ? (float) $taxes['total'][ $tax_id ] : 0;
					break;
				}
			}
		}

		return Formatting::round_tax_total( $total ) * - 1;
	}

	/**
	 * Get total tax refunded by rate ID.
	 *
	 * @param int $rate_id Rate ID.
	 *
	 * @return float
	 */
	public function get_total_tax_refunded_by_rate_id( $rate_id ) {
		$total = 0;
		foreach ( $this->get_refunds() as $refund ) {
			foreach ( $refund->get_items( 'tax' ) as $refunded_item ) {
				if ( absint( $refunded_item->get_rate_id() ) === $rate_id ) {
					$total += abs( $refunded_item->get_tax_total() ) + abs( $refunded_item->get_shipping_tax_total() );
				}
			}
		}

		return $total;
	}

	/**
	 * How much money is left to refund?
	 *
	 * @return string
	 */
	public function get_remaining_refund_amount() {
		return Formatting::format_decimal( $this->get_total() - $this->get_total_refunded(), Formatting::get_price_decimals() );
	}

	/**
	 * How many items are left to refund?
	 *
	 * @return int
	 */
	public function get_remaining_refund_items() {
		return absint( $this->get_item_count() - $this->get_item_count_refunded() );
	}

	/**
	 * Add total row for the payment method.
	 *
	 * @param array $total_rows Total rows.
	 * @param string $tax_display Tax to display.
	 */
	protected function add_order_item_totals_payment_method_row( &$total_rows, $tax_display ) {
		if ( $this->get_total() > 0 && $this->get_payment_method_title() && 'other' !== $this->get_payment_method() ) {
			$value = $this->get_payment_method_title();

			$card_info = $this->get_payment_card_info();
			if ( isset( $card_info['last4'] ) && $card_info['last4'] ) {
				$value .= ' - ' . $card_info['last4'];
			}

			$total_rows['payment_method'] = [
				'type'  => 'payment_method',
				'label' => __( 'Payment method:', 'storeengine' ),
				'value' => $value,
			];
		}
	}

	/**
	 * Add total row for refunds.
	 *
	 * @param array $total_rows Total rows.
	 * @param string $tax_display Tax to display.
	 */
	protected function add_order_item_totals_refund_rows( &$total_rows, $tax_display ) {
		$refunds = $this->get_refunds();
		if ( $refunds ) {
			foreach ( $refunds as $id => $refund ) {
				$reason = trim( $refund->get_reason() );

				if ( strlen( $reason ) > 0 ) {
					$reason = "<br><small>$reason</small>";
				}

				$total_rows[ 'refund_' . $id ] = [
					'type'  => 'refund',
					'label' => __( 'Refund', 'storeengine' ) . ':',
					'value' => Formatting::price( $refund->get_total_amount(), [ 'currency' => $this->get_currency() ] ) . $reason,
				];
			}
		}
	}

	/**
	 * Get totals for display on pages and in emails.
	 *
	 * @param string $tax_display Tax to display.
	 *
	 * @return array
	 */
	public function get_order_item_totals( $tax_display = '' ) {
		$tax_display = $tax_display ? $tax_display : Helper::get_settings( 'tax_display_cart' );
		$total_rows  = [];

		$this->add_order_item_totals_subtotal_row( $total_rows, $tax_display );
		$this->add_order_item_totals_discount_row( $total_rows, $tax_display );
		$this->add_order_item_totals_shipping_row( $total_rows, $tax_display );
		$this->add_order_item_totals_fee_rows( $total_rows, $tax_display );
		$this->add_order_item_totals_tax_rows( $total_rows, $tax_display );
		$this->add_order_item_totals_refund_rows( $total_rows, $tax_display );
		$this->add_order_item_totals_total_row( $total_rows, $tax_display );
		$this->add_order_item_totals_payment_method_row( $total_rows, $tax_display );

		return apply_filters( 'storeengine/get_order_item_totals', $total_rows, $this, $tax_display );
	}

	/**
	 * Check if order has been created via admin, checkout, or in another way.
	 *
	 * @param string $modus Way of creating the order to test for.
	 *
	 * @return bool
	 */
	public function is_created_via( $modus ) {
		return apply_filters( 'storeengine/order_is_created_via', $modus === $this->get_created_via(), $this, $modus );
	}

	/**
	 * Indicates that regular orders have an associated Cost of Goods Sold value.
	 * Note that this is true even if the order has no line items with COGS values (in that case the COGS value for the order will be zero)-
	 *
	 * @return bool Always true.
	 */
	public function has_cogs(): bool {
		return true;
	}

	// -----------------------

	/**
	 * Coupons array.
	 *
	 * @var OrderItemCoupon[]
	 */
	protected array $coupons = [];

	/**
	 * @param int $customer_id
	 * @param null $deprecated
	 * @param bool $create
	 *
	 * @return $this|false|Order
	 */
	public function get_recent_draft_order( int $customer_id = 0, $deprecated = null, bool $create = true ) {
		$cart_hash = Helper::get_cart_hash_from_cookie();
		if ( 0 === $customer_id ) {
			$customer_id = get_current_user_id();
		}

		if ( ! $cart_hash && ! $customer_id ) {
			return $this;
		}

		try {
			$cache_key = 'order:draft:' . $cart_hash;
			$id        = wp_cache_get( $cache_key, $this->cache_group );

			if ( false !== $id && false !== wp_cache_get( $id, $this->cache_group ) ) {
				$this->set_id( $id );
				$this->read();

				return $this;
			}

			$data = $this->read_db_data( [ $cart_hash, $customer_id ], 'cart_hash' );

			wp_cache_set( $cache_key, $data['id'], $this->cache_group );
			wp_cache_set( $data['id'], $data, $this->cache_group );

			$this->set_id( $data['id'] );
			$this->read();

			return $this;
		} catch ( Exception $e ) {
			if ( 404 !== $e->getCode() ) {
				// @TODO implement error logger.
				Helper::log_error( $e );
			}
		}

		if ( $create ) {
			return self::create_draft_order( [
				'cart_hash'   => $cart_hash,
				'customer_id' => $customer_id,
			] );
		}

		return false;
	}

	public static function create_draft_order( array $args = [] ): Order {
		$args = wp_parse_args( $args, [
			'customer_id'        => get_current_user_id(),
			'ip_address'         => Helper::get_user_ip(),
			'user_agent'         => Helper::get_user_agent(),
			'cart_hash'          => Helper::get_cart_hash_from_cookie(),
			'prices_include_tax' => TaxUtil::prices_include_tax(),
		] );

		$order = new self();
		$order->set_props( $args );
		$order->set_prop( 'status', OrderStatus::DRAFT );
		$order->save();

		if ( $order->get_id() ) {
			$cache_key = 'order:draft:' . Helper::get_cart_hash_from_cookie();
			wp_cache_set( $cache_key, $order->get_id(), 'storeengine_orders' );
		}

		return $order;
	}

	public function has_address( string $context = 'view' ): bool {
		return $this->has_shipping_address( $context ) || $this->has_billing_address( $context );
	}

	public function get_payment_url(): string {
		$checkout_page     = Helper::get_settings( 'checkout_page' );
		$order_id          = $this->get_id();
		$checkout_page_url = get_permalink( $checkout_page );

		if ( get_option( 'permalink_structure' ) ) {
			return trailingslashit( $checkout_page_url ) . "order-pay/$order_id/";
		} else {
			return add_query_arg( [
				'order_pay' => 'true',
				'order_id'  => $order_id,
			], $checkout_page_url );
		}
	}

	/**
	 * @return DownloadPermission[]
	 */
	public function get_downloadable_permissions(): array {
		return Helper::get_download_permissions_by_order_id( $this->get_id() );
	}

	public function get_tax_amount( string $context = 'view' ) {
		return $this->get_total_tax( $context );
	}

	public function get_total_amount( string $context = 'view' ) {
		return $this->get_total( $context );
	}

	public function set_total_amount( $amount ) {
		$this->set_total( $amount );
	}

	public function set_order_placed_date_gmt( $value = null ) {
		if ( ! $value ) {
			$value = current_time( 'mysql', true );
		}

		$this->set_date_prop( 'order_placed_date_gmt', $value );
	}

	public function set_order_placed_date( $value = null ) {
		if ( ! $value ) {
			$value = current_time( 'mysql', false );
		}

		$this->set_date_prop( 'order_placed_date', $value );
	}
}
