<?php
namespace StoreEngine\Addons\Subscription\Classes;

use DateTimeZone;
use StoreEngine;
use StoreEngine\Addons\Subscription\Events\CreateSubscription;
use StoreEngine\Addons\Subscription\Events\Renewal;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SubscriptionScheduler {
	use Singleton;

	protected function __construct() {
		add_action( 'storeengine/subscription/date_updated', [ $this, 'update_date' ], 10, 3 );
		add_action( 'storeengine/subscription/date_deleted', [ $this, 'delete_date' ], 10, 2 );
		add_action( 'storeengine/subscription/status_updated', [ $this, 'update_status' ], 10, 3 );

		add_action( 'storeengine/subscription/scheduled_payment', [ $this, 'handle_subscription_payment' ] );
		add_action( 'storeengine/subscription/schedule_payment_retry', [ $this, 'handle_subscription_payment_retry' ] );
		add_action( 'storeengine/subscription/schedule_trial_end', [ $this, 'handle_subscription_trial_end' ] );
		add_action( 'storeengine/subscription/schedule_end_of_prepaid_term', [ $this, 'handle_subscription_end_of_prepaid_term' ] );
		add_action( 'storeengine/subscription/schedule_expiration', [ $this, 'handle_subscription_expiration' ] );
	}

	public function handle_subscription_payment( $subscription_id ) {
		$subscription = $this->sanitize_subscription_id( $subscription_id );

		$order_note = _x( 'Subscription renewal payment due:', 'used in order note as reason for why subscription status changed', 'storeengine' );

		if ( $subscription->has_status( 'active' ) && ( ! (float) $subscription->get_total() || $subscription->is_manual() || ! $subscription->get_payment_method() || ! $subscription->payment_method_supports( 'gateway_scheduled_payments' ) ) ) {

			// Always put the subscription on hold in case something goes wrong while trying to process renewal
			$subscription->update_status( 'on_hold', $order_note );

			// Generate a renewal order for payment gateways to use to record the payment (and determine how much is due)
			$renewal_order = Utils::create_renewal_order( $subscription );

			if ( is_wp_error( $renewal_order ) ) {
				// let's try this again
				$renewal_order = Utils::create_renewal_order( $subscription );

				if ( is_wp_error( $renewal_order ) ) {
					// translators: placeholder is an order note.
					throw new StoreEngineException( sprintf( __( 'Error: Unable to create renewal order with note "%s"', 'storeengine' ), $order_note ) );
				}
			}

			if ( ! (float) $renewal_order->get_total() ) {
				$order_context = new OrderContext( $renewal_order->get_status() );
				$order_context->proceed_to_next_status( 'process_order', $renewal_order, [ 'note' => __( 'Payment not needed.', 'storeengine' ) ] );
			} else {
				if ( $subscription->is_manual() ) {
					// @TODO trigger notification.
					do_action( 'storeengine/subscription/generated_manual_renewal_order', $renewal_order->get_id(), $subscription );
					$renewal_order->add_order_note( __( 'Manual renewal order awaiting customer payment.', 'storeengine' ) );
				} else {
					$renewal_order->set_payment_method( Utils::get_payment_gateway_by_order( $subscription ) ); // We need to pass the payment gateway instance to be compatible with WC < 3.0, only WC 3.0+ supports passing the string name
				}
			}

			$renewal_order->save();
		}

		if ( ! $subscription->is_manual() && ! $subscription->has_status( SubscriptionCollection::get_ended_statuses() ) ) {
			$renewal_order = $subscription->get_last_order( 'all', 'renewal' );
			if ( ! empty( $renewal_order ) && $renewal_order->get_total() > 0 && $renewal_order->get_payment_method() ) {

				// Make sure gateways are loaded
				Helper::get_payment_gateways()->load_gateways();

				do_action( 'storeengine/subscription/scheduled_payment_' . $renewal_order->get_payment_method(), $renewal_order );
			}
		}
	}

	public function handle_subscription_payment_retry( $subscription_id ) {
		$subscription = $this->sanitize_subscription_id( $subscription_id );
		$last_order   = $subscription->get_last_order();

		if ( ! $last_order || ! $last_order->needs_payment() ) {
			return;
		}

		$last_order->set_status( 'pending_payment' );

		$last_order->save();
		// Load gateways.
		Helper::get_payment_gateways()->load_gateways();

		do_action( 'storeengine/subscription/scheduled_payment_' . $last_order->get_payment_method(), $last_order );
	}

	public function handle_subscription_trial_end( $subscription_id ) {
		do_action( 'storeengine/subscription/trial_ended', $subscription_id );
	}

	public function handle_subscription_end_of_prepaid_term( $subscription_id ) {
		$this->sanitize_subscription_id( $subscription_id )->update_status( 'cancelled' );
	}

	public function handle_subscription_expiration( $subscription_id ) {
		$this->sanitize_subscription_id( $subscription_id )->update_status( 'expired' );
	}

	protected function sanitize_subscription_id( $subscription_id ): Subscription {
		$subscription = new Subscription( absint( $subscription_id ) );
		if ( ! $subscription_id || ! $subscription->get_id() || 'subscription' !== $subscription->get_type() ) {
			throw new StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException(
				// translators: %d. Subscription id.
				sprintf( __( 'Subscription doesn\'t exist in scheduled action: %d', 'storeengine' ), $subscription_id ),
				'invalid-subscription-id'
			);
		}

		return $subscription;
	}


	public function get_date_types_to_schedule(): array {
		return [
			'trial_end_date',
			'next_payment_date',
			'cancelled_date',
			'end_date',
		];
	}

	/**
	 * Maybe set a schedule action if the new date is in the future
	 *
	 * @param Subscription $subscription An instance of a Subscription object
	 * @param string $date_type Can be 'trial_end_date', 'next_payment_date', 'payment_retry_date', 'end_date', 'end_of_prepaid_term_date' or a custom date type
	 * @param string|int $datetime A MySQL formated date/time string in the GMT/UTC timezone.
	 *
	 * @throws StoreEngineException
	 */
	public function update_date( Subscription $subscription, string $date_type, $datetime ) {
		if ( in_array( $date_type, $this->get_date_types_to_schedule(), true ) ) {
			$action_hook = $this->get_scheduled_action_hook( $subscription, $date_type );

			if ( ! empty( $action_hook ) ) {
				$action_args    = $this->get_action_args( $date_type, $subscription );
				$timestamp      = CreateSubscription::date_to_time( $datetime );
				$next_scheduled = as_next_scheduled_action( $action_hook, $action_args );

				if ( $next_scheduled !== $timestamp ) {
					// Maybe clear the existing schedule for this hook
					$this->unschedule_actions( $action_hook, $action_args );

					// Only reschedule if it's in the future
					if ( $timestamp <= time() ) {
						return;
					}

					// Only schedule it if it's valid. It's active, it's a payment retry or it's pending cancelled and the end date being updated.
					if ( 'payment_retry_date' === $date_type || $subscription->has_status( 'active' ) || ( $subscription->has_status( 'pending_cancel' ) && 'end_date' === $date_type ) ) {
						StoreEngine::init()->queue()->schedule_single( $timestamp, $action_hook, $action_args );
					}
				}
			}
		}
	}

	/**
	 * Delete a date from the action scheduler queue
	 *
	 * @param Subscription $subscription An instance of a Subscription object
	 * @param string $date_type Can be 'trial_end_date', 'next_payment_date', 'end_date', 'end_of_prepaid_term_date' or a custom date type
	 *
	 * @throws StoreEngineException
	 */
	public function delete_date( Subscription $subscription, string $date_type ) {
		$this->update_date( $subscription, $date_type, 0 );
	}

	/**
	 * When a subscription's status is updated, maybe schedule an event
	 *
	 * @param Subscription $subscription An instance of a Subscription object
	 * @param string $new_status
	 * @param string $old_status
	 *
	 * @throws StoreEngineException
	 */
	public function update_status( Subscription $subscription, string $new_status, string $old_status ) {
		switch ( $new_status ) {
			case 'active':
				$this->unschedule_actions( 'storeengine/subscription/schedule_end_of_prepaid_term', $this->get_action_args( 'end_date', $subscription ) );

				foreach ( $this->get_date_types_to_schedule() as $date_type ) {
					$action_hook = $this->get_scheduled_action_hook( $subscription, $date_type );

					if ( empty( $action_hook ) ) {
						continue;
					}

					if ( method_exists( $subscription, 'get_' . $date_type ) ) {
						$event_time = $subscription->{'get_' . $date_type}( 'edit' );
						$event_time = $event_time ? $event_time->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp() : 0;
					} else {
						$event_time = 0;
					}

					// If there's no payment retry date, avoid calling get_action_args() because it calls the resource intensive Subscription::get_last_order() / get_related_orders()
					if ( 'payment_retry_date' === $date_type && 0 === $event_time ) {
						continue;
					}

					$action_args    = $this->get_action_args( $date_type, $subscription );
					$next_scheduled = StoreEngine::init()->queue()->get_next( $action_hook, $action_args );

					// Maybe clear the existing schedule for this hook
					if ( false !== $next_scheduled && $next_scheduled !== $event_time ) {
						$this->unschedule_actions( $action_hook, $action_args );
					}

					if ( 0 !== $event_time && $event_time > time() && $next_scheduled !== $event_time ) {
						StoreEngine::init()->queue()->schedule_single( $event_time, $action_hook, $action_args );
					}
				}

				break;
			case 'pending_cancel':
				// Now that we have the current times, clear the scheduled hooks
				foreach ( $this->get_date_types_to_schedule() as $date_type ) {
					$action_hook = $this->get_scheduled_action_hook( $subscription, $date_type );

					if ( empty( $action_hook ) ) {
						continue;
					}

					$this->unschedule_actions( $action_hook, $this->get_action_args( $date_type, $subscription ) );
				}

				$end_time       = $subscription->get_end_date(); // This will have been set to the correct date already
				$end_time       = $end_time ? $end_time->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp() : 0;
				$action_args    = $this->get_action_args( 'end_date', $subscription );
				$next_scheduled = as_next_scheduled_action( 'storeengine/subscription/schedule_end_of_prepaid_term', $action_args );

				if ( false !== $next_scheduled && $next_scheduled !== $end_time ) {
					$this->unschedule_actions( 'storeengine/subscription/schedule_end_of_prepaid_term', $action_args );
				}

				// The end date was set in Subscription to the appropriate value, so we can schedule our action for that time
				if ( $end_time > time() && $next_scheduled !== $end_time ) {
					StoreEngine::init()->queue()->schedule_single( $end_time, 'storeengine/subscription/schedule_end_of_prepaid_term', $action_args );
				}
				break;
			case 'on_hold':
			case 'cancelled':
			case 'switched':
			case 'expired':
			case 'trash':
				foreach ( $this->get_date_types_to_schedule() as $date_type ) {
					$action_hook = $this->get_scheduled_action_hook( $subscription, $date_type );

					if ( empty( $action_hook ) ) {
						continue;
					}

					$this->unschedule_actions( $action_hook, $this->get_action_args( $date_type, $subscription ) );
				}
				$this->unschedule_actions( 'storeengine/subscription/schedule_expiration', $this->get_action_args( 'end_date', $subscription ) );
				$this->unschedule_actions( 'storeengine/subscription/schedule_end_of_prepaid_term', $this->get_action_args( 'end_date', $subscription ) );
				break;
		}
	}

	/**
	 * Get the hook to use in the action scheduler for the date type
	 *
	 * @param Subscription $subscription An instance of Subscription to get the hook for
	 * @param string $date_type Can be 'trial_end_date', 'next_payment_date', 'expiration_date', 'end_of_prepaid_term_date' or a custom date type
	 */
	protected function get_scheduled_action_hook( Subscription $subscription, string $date_type ) {
		$hook = '';

		switch ( $date_type ) {
			case 'next_payment_date':
				$hook = 'storeengine/subscription/scheduled_payment';
				break;
			case 'payment_retry_date':
				$hook = 'storeengine/subscription/schedule_payment_retry';
				break;
			case 'trial_end_date':
				$hook = 'storeengine/subscription/schedule_trial_end';
				break;
			case 'end_date':
				// End dates may need either an expiration or end of prepaid term hook, depending on the status
				if ( $subscription->has_status( [ 'cancelled', 'pending_cancel' ] ) ) {
					$hook = 'storeengine/subscription/schedule_end_of_prepaid_term';
				} elseif ( $subscription->has_status( 'active' ) ) {
					$hook = 'storeengine/subscription/schedule_expiration';
				}
				break;
		}

		return apply_filters( 'storeengine/subscriptions/scheduled_action_hook', $hook, $date_type );
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param string $date_type Can be 'trial_end_date', 'next_payment_date', 'expiration_date', 'end_of_prepaid_term_date_date' or a custom date type
	 * @param Subscription $subscription An instance of Subscription to get the hook for
	 * @return array Array of name => value pairs stored against the scheduled action.
	 */
	protected function get_action_args( string $date_type, Subscription $subscription ): array {
		if ( 'payment_retry_date' === $date_type ) {
			$last_order_id = $subscription->get_last_order( 'ids', 'renewal' );
			$action_args   = [ 'order_id' => $last_order_id ];
		} else {
			$action_args = [ 'subscription_id' => $subscription->get_id() ];
		}

		return apply_filters( 'storeengine/subscriptions/scheduled_action_args', $action_args, $date_type, $subscription );
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param string $action_hook Name of event used as the hook for the scheduled action.
	 * @param array $action_args Array of name => value pairs stored against the scheduled action.
	 *
	 * @throws StoreEngineException
	 */
	protected function unschedule_actions( string $action_hook, array $action_args ) {
		StoreEngine::init()->queue()->cancel_all( $action_hook, $action_args );
	}
}

// End of file subscription-scheduler.php.
