<?php

namespace StoreEngine\Addons\Subscription\Events;

use StoreEngine\Classes\{AbstractOrder, Order};
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Utils\Constants;
use StoreEngine\Addons\Subscription\Traits\Scheduler;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateSubscriptionStatus {
	use Scheduler;

	public static function init(): void {
		$self = new self();

		add_action( 'storeengine/order/payment_status_changed', [ $self, 'update_after_paid' ], 10, 3 );
		//add_action( 'storeengine/order/status_changed', [ $self, 'update' ], 10, 4 );
	}

	public function update_after_paid( Order $order, $status, $old_status ): void {
		if ( 'order' !== $order->get_type() ) {
			return;
		}

		remove_action( 'storeengine/order/payment_status_changed', [ $this, 'update_after_paid' ] );

		global $wpdb;
		$subscriptions = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE parent_order_id = %d", $order->get_id() ) );
		$subscriptions = array_map( 'absint', $subscriptions );

		if ( ! empty( $subscriptions ) ) {
			$was_activated = false;

			foreach ( $subscriptions as $subscription ) {
				$subscription = new Subscription( $subscription );
				// A special case where payment completes after user cancels subscription
				if ( 'paid' === $status && $subscription->has_status( 'cancelled' ) ) {
					// Store the actual cancelled_date to restore it after it is rewritten by update_status()
					$cancelled_date = $subscription->get_cancelled_date();

					// Force set cancelled_date and end date to 0 temporarily so that next_payment_date can be calculated properly
					// This next_payment_date will be the end of prepaid term that will be picked by action scheduler
					$subscription->set_cancelled_date( 0 );
					$subscription->set_end_date( 0 );
					$subscription->set_next_payment_date( $subscription->calculate_date( 'next_payment_date' ) );

					$subscription->update_status( 'pending_cancel', __( 'Payment completed on order after subscription was cancelled.', 'storeengine' ) );

					// Restore the actual cancelled date
					$subscription->set_cancelled_date( $cancelled_date );
				}

				// Do we need to activate a subscription?
				if ( 'paid' === $status && ! $subscription->has_status( SubscriptionCollection::get_ended_statuses() ) && ! $subscription->has_status( 'active' ) ) {
					$new_start_date_offset = time() - $subscription->get_start_date()->getTimestamp();

					// if the payment has been processed more than an hour after the order was first created, let's update the dates on the subscription to account for that, because it may have even been processed days after it was first placed
					if ( abs( $new_start_date_offset ) > HOUR_IN_SECONDS ) {
						$subscription->set_start_date( current_time( 'mysql', true ) );

						// phpcs:disable Squiz.PHP.CommentedOutCode.Found
						/*if ( WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription ) ) {

							$trial_end    = $subscription->get_time( 'trial_end_date' );
							$next_payment = $subscription->get_time( 'next_payment_date' );

							// if either there is a free trial date or a next payment date that falls before now, we need to recalculate all the sync'd dates
							if ( ( $trial_end > 0 && $trial_end < wcs_date_to_time( $dates['start_date'] ) ) || ( $next_payment > 0 && $next_payment < wcs_date_to_time( $dates['start_date'] ) ) ) {

								foreach ( $subscription->get_items() as $item ) {
									$product_id = wcs_get_canonical_product_id( $item );

									if ( WC_Subscriptions_Synchroniser::is_product_synced( $product_id ) ) {
										$dates['trial_end_date']    = WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $dates['start_date'] );
										$dates['next_payment_date'] = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product_id, 'mysql', $dates['start_date'] );
										$dates['end_date']          = WC_Subscriptions_Product::get_expiration_date( $product_id, $dates['start_date'] );
										break;
									}
								}
							}
						} else {*/
						// phpcs:enable Squiz.PHP.CommentedOutCode.Found

						// No sync'ing to mess about with, just add the offset to the existing dates
						if ( $subscription->get_trial_end_date() ) {
							$subscription->set_trial_end_date( $subscription->get_trial_end_date()->getTimestamp() + $new_start_date_offset );
						}

						if ( $subscription->get_next_payment_date() ) {
							$subscription->set_next_payment_date( $subscription->get_next_payment_date()->getTimestamp() + $new_start_date_offset );
						}

						if ( $subscription->get_end_date() ) {
							$subscription->set_end_date( $subscription->get_end_date()->getTimestamp() + $new_start_date_offset );
						}
						//}
					}

					$subscription->payment_complete_for_order( $order );
					$was_activated = true;
				} elseif ( 'failed' === $status ) {
					$subscription->payment_failed();
				}

				$subscription->save();
			}

			if ( $was_activated ) {
				do_action( 'storeengine/subscription/activated_for_order', $order->get_id() );
			}
		}

		$subscriptions = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}storeengine_orders_meta WHERE meta_key = '_subscription_renewal' AND order_id=%d;", $order->get_id() ) );
		$subscriptions = array_map( 'absint', $subscriptions );

		if ( ! empty( $subscriptions ) ) {
			$was_activated = false;

			foreach ( $subscriptions as $subscription ) {
				$subscription = new Subscription( $subscription );
				// Do we need to activate a subscription?
				if ( 'paid' === $status && ! $subscription->has_status( SubscriptionCollection::get_ended_statuses() ) && ! $subscription->has_status( 'active' ) ) {

					// Included here because calling payment_complete sets the retry status to 'cancelled'
					$is_failed_renewal_order = 'failed' === $old_status;
					$is_failed_renewal_order = apply_filters( 'storeengine/subscription/is_failed_renewal_order', $is_failed_renewal_order, $order->get_id(), $old_status );

					// @TODO handel activation.
					$subscription->payment_complete();
					$was_activated = true;

					if ( $is_failed_renewal_order ) {
						do_action( 'storeengine/subscription/paid_for_failed_renewal_order', $order, $subscription );
					}
				} elseif ( 'failed' === $status ) {
					$subscription->payment_failed();
				}

				$subscription->save();
			}

			if ( $was_activated ) {
				do_action( 'storeengine/subscription/activated_for_order', $order->get_id() );
			}
		}

		add_action( 'storeengine/order/payment_status_changed', [ $this, 'update_after_paid' ], 10, 3 );
	}

	/**
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 * @param Order $order
	 *
	 * @return void
	 * @throws \StoreEngine\Classes\Exceptions\StoreEngineException
	 * @deprecated
	 */
	public function update( $order_id, $old_status, $new_status, Order $order ): void {
		if ( 'order' !== $order->get_type() ) {
			return;
		}

		$paid_statuses   = [ apply_filters( 'storeengine/payment_complete_order_status', 'processing', $order_id, $order ), 'processing', 'completed' ];
		$unpaid_statuses = apply_filters( 'storeengine/valid_order_statuses_for_payment', [ 'pending_payment', 'pending', 'on_hold', 'failed' ], $order );
		$order_completed = in_array( $new_status, $paid_statuses, true ) && in_array( $old_status, $unpaid_statuses, true );

		if ( SubscriptionCollection::order_contains_subscription( $order_id, [ 'parent' ] ) ) {
			$was_activated = false;
			$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order_id, [ 'parent' ] );

			foreach ( $subscriptions as $subscription ) {
				// A special case where payment completes after user cancels subscription
				if ( $order_completed && $subscription->has_status( 'cancelled' ) ) {

					// Store the actual cancelled_date so as to restore it after it is rewritten by update_status()
					$cancelled_date = $subscription->get_cancelled_date();

					// Force set cancelled_date and end date to 0 temporarily so that next_payment_date can be calculated properly
					// This next_payment_date will be the end of prepaid term that will be picked by action scheduler
					$subscription->set_cancelled_date( 0 );
					$subscription->set_end_date( 0 );
					$subscription->set_next_payment_date( $subscription->calculate_date( 'next_payment_date' ) );

					$subscription->update_status( 'pending_cancel', __( 'Payment completed on order after subscription was cancelled.', 'storeengine' ) );

					// Restore the actual cancelled date
					$subscription->set_cancelled_date( $cancelled_date );
				}

				// Do we need to activate a subscription?
				if ( $order_completed && ! $subscription->has_status( SubscriptionCollection::get_ended_statuses() ) && ! $subscription->has_status( 'active' ) ) {
					$new_start_date_offset = time() - $subscription->get_start_date()->getTimestamp();

					// if the payment has been processed more than an hour after the order was first created, let's update the dates on the subscription to account for that, because it may have even been processed days after it was first placed
					if ( abs( $new_start_date_offset ) > HOUR_IN_SECONDS ) {
						$subscription->set_start_date( current_time( 'mysql', true ) );

						// phpcs:disable Squiz.PHP.CommentedOutCode.Found
						/*if ( WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription ) ) {

							$trial_end    = $subscription->get_time( 'trial_end_date' );
							$next_payment = $subscription->get_time( 'next_payment_date' );

							// if either there is a free trial date or a next payment date that falls before now, we need to recalculate all the sync'd dates
							if ( ( $trial_end > 0 && $trial_end < wcs_date_to_time( $dates['start_date'] ) ) || ( $next_payment > 0 && $next_payment < wcs_date_to_time( $dates['start_date'] ) ) ) {

								foreach ( $subscription->get_items() as $item ) {
									$product_id = wcs_get_canonical_product_id( $item );

									if ( WC_Subscriptions_Synchroniser::is_product_synced( $product_id ) ) {
										$dates['trial_end_date']    = WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $dates['start_date'] );
										$dates['next_payment_date'] = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product_id, 'mysql', $dates['start_date'] );
										$dates['end_date']          = WC_Subscriptions_Product::get_expiration_date( $product_id, $dates['start_date'] );
										break;
									}
								}
							}
						} else {*/
						// phpcs:enable Squiz.PHP.CommentedOutCode.Found

						// No sync'ing to mess about with, just add the offset to the existing dates
						if ( $subscription->get_trial_end_date() ) {
							$subscription->set_trial_end_date( $subscription->get_trial_end_date()->getTimestamp() + $new_start_date_offset );
						}

						if ( $subscription->get_next_payment_date() ) {
							$subscription->set_next_payment_date( $subscription->get_next_payment_date()->getTimestamp() + $new_start_date_offset );
						}

						if ( $subscription->get_end_date() ) {
							$subscription->set_end_date( $subscription->get_end_date()->getTimestamp() + $new_start_date_offset );
						}
						//}
					}

					$subscription->payment_complete_for_order( $order );
					$was_activated = true;
				} elseif ( 'failed' === $new_status ) {
					$subscription->payment_failed();
				}

				$subscription->save();
			}

			if ( $was_activated ) {
				do_action( 'storeengine/subscription/activated_for_order', $order_id );
			}
		}

		if ( SubscriptionCollection::order_contains_subscription( $order_id, [ 'renewal' ] ) ) {
			$order_needed_payment = in_array( $old_status, $unpaid_statuses, true );
			$was_activated        = false;
			$subscriptions        = SubscriptionCollection::get_subscriptions_for_order( $order_id, [ 'renewal' ] );

			foreach ( $subscriptions as $subscription ) {

				// Do we need to activate a subscription?
				if ( $order_completed && ! $subscription->has_status( SubscriptionCollection::get_ended_statuses() ) && ! $subscription->has_status( 'active' ) ) {

					// Included here because calling payment_complete sets the retry status to 'cancelled'
					$is_failed_renewal_order = 'failed' === $old_status;
					$is_failed_renewal_order = apply_filters( 'storeengine/subscription/is_failed_renewal_order', $is_failed_renewal_order, $order_id, $old_status );

					if ( $order_needed_payment ) {
						// @TODO handel activation.
						$subscription->payment_complete();
						$was_activated = true;
					}

					if ( $is_failed_renewal_order ) {
						do_action( 'storeengine/subscription/paid_for_failed_renewal_order', Helper::get_order( $order_id ), $subscription );
					}
				} elseif ( 'failed' === $old_status ) {
					$subscription->payment_failed();
				}

				$subscription->save();
			}

			if ( $was_activated ) {
				do_action( 'storeengine/subscription/activated_for_order', $order_id );
			}
		}
	}

	/**
	 * @deprecated
	 * @param Subscription $subscription
	 * @param AbstractOrder $order
	 *
	 * @return void
	 */
	public function change_status( Subscription $subscription, AbstractOrder $order ): void {
		$statuses = [
			Constants::SUBSCRIPTION_STATUS_ON_HOLD,
			Constants::SUBSCRIPTION_STATUS_EXPIRED,
			Constants::SUBSCRIPTION_STATUS_PENDING_PAYMENT,
		];

		if ( 'completed' === $order->get_status() && in_array( $subscription->get_status(), $statuses, true ) ) {
			$subscription->set_status( Constants::SUBSCRIPTION_STATUS_ACTIVE );
			$subscription->update_date();
			$subscription->set_last_payment_date( gmdate( 'c' ) );

			$subscription->save();

			$this->add_to_schedule( $subscription );
			do_action( 'storeengine/change_subscription_status', $subscription );
		}
	}
}
