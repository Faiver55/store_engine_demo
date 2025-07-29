<?php

namespace StoreEngine\Addons\Subscription\Events;

use DateTimeZone;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Hooks;
use StoreEngine\Ajax\Checkout;
use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\SqlTransaction;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Addons\Subscription\Classes\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateSubscription {

	public static function init(): void {
		$self = new self();
		add_action( 'storeengine/checkout/order_processed', [ $self, 'create_subscription' ], 10, 2 );
		// @TODO Implement separate rest API to create subscription from admin-dashboard.
	}

	public function create_subscription( AbstractOrder $order, array $payload = [] ): void {
		if ( ! Helper::cart()->has_subscription_product() ) {
			return;
		}

		$query = new SubscriptionCollection( [
			'where' => [
				[
					'relation' => 'AND',
					'key'      => 'parent_order_id',
					'value'    => $order->get_id(),
					'compare'  => '=',
					'type'     => 'NUMERIC',
				],
			],
		] );

		// Clear out any subscriptions created for a failed payment & create new subscriptions with clean state.
		if ( $query->have_results() ) {
			foreach ( $query as $subscription ) {
				$subscription->delete();
			}
		}

		foreach ( Helper::cart()->recurring_carts as $recurring_cart ) {
			$subscription = $this->create( $order, $recurring_cart, $payload );

			if ( is_wp_error( $subscription ) ) {
				throw StoreEngineException::from_wp_error( $subscription );
			}

			do_action( 'storeengine/subscription/checkout_subscription_created', $subscription, $order, $recurring_cart );
		}
	}

	public function create( AbstractOrder $order, Cart $cart, array $payload = [] ) {
		// Start transaction if available
		$transaction = new SqlTransaction();
		$transaction->start();

		try {
			// set subs data
			$subscription = new Subscription();
			$subscription->set_parent_order_id( $order->get_id() );
			$subscription->set_initial_order_id( $order->get_id() );
			$subscription->set_props( [
				'status'                       => Constants::SUBSCRIPTION_STATUS_ON_HOLD,
				'currency'                     => $order->get_currency(),
				'prices_include_tax'           => $order->get_prices_include_tax(),
				'discount_tax'                 => $order->get_discount_tax(),
				'shipping_tax'                 => $order->get_shipping_tax(),
				'cart_tax'                     => $order->get_cart_tax(),
				'shipping_tax_amount'          => $order->get_shipping_tax_amount(),
				'discount_tax_amount'          => $order->get_discount_tax_amount(),
				'date_created_gmt'             => $order->get_date_created_gmt(),
				'customer_id'                  => $order->get_customer_id(),
				'payment_method'               => $order->get_payment_method(),
				'payment_method_title'         => $order->get_payment_method_title(),
				'ip_address'                   => $order->get_ip_address(),
				'user_agent'                   => $order->get_user_agent(),
				'created_via'                  => $order->get_created_via(),
				'customer_note'                => $order->get_customer_note(),
				'date_completed_gmt'           => $order->get_date_completed_gmt(),
				'date_paid_gmt'                => $order->get_date_paid_gmt(),
				'cart_hash'                    => $order->get_cart_hash(),
				'hash'                         => $order->get_hash(),
				'order_stock_reduced'          => $order->get_order_stock_reduced(),
				'download_permissions_granted' => $order->get_download_permissions_granted(),
				'new_order_email_sent'         => $order->get_new_order_email_sent(),
				'recorded_sales'               => $order->get_recorded_sales(),
				'total_amount'                 => $order->get_total_amount(),
				// Set billing addr
				'billing_first_name'           => $order->get_billing_first_name(),
				'billing_last_name'            => $order->get_billing_last_name(),
				'billing_country'              => $order->get_billing_country() ?? '-',
				'billing_email'                => $order->get_billing_email(),
				'billing_phone'                => $order->get_billing_phone(),
				'billing_address_1'            => $order->get_billing_address_1(),
				'billing_address_2'            => $order->get_billing_address_2(),
				'billing_city'                 => $order->get_billing_city(),
				'billing_state'                => $order->get_billing_state(),
				'billing_postcode'             => $order->get_billing_postcode(),
				// Set shipping addr
				'shipping_first_name'          => $order->get_shipping_first_name(),
				'shipping_last_name'           => $order->get_shipping_last_name(),
				'shipping_company'             => $order->get_shipping_company(),
				'shipping_address_1'           => $order->get_shipping_address_1(),
				'shipping_address_2'           => $order->get_shipping_address_2(),
				'shipping_city'                => $order->get_shipping_city(),
				'shipping_state'               => $order->get_shipping_state(),
				'shipping_postcode'            => $order->get_shipping_postcode(),
				'shipping_country'             => $order->get_shipping_country(),
				'shipping_phone'               => $order->get_shipping_phone(),
				'shipping_email'               => $order->get_billing_email(),
			] );

			$subscription->set_requires_manual_renewal( false );

			$available_gateways   = Helper::get_payment_gateways()->get_available_payment_gateways();
			$order_payment_method = $order->get_payment_method();

			if ( $cart->needs_payment() && isset( $available_gateways[ $order_payment_method ] ) ) {
				$subscription->set_payment_method( $available_gateways[ $order_payment_method ] );
			}

			if ( ! $cart->needs_payment() || Helper::get_settings( 'turn_off_automatic_payments', false ) ) {
				$subscription->set_requires_manual_renewal( true );
			} elseif ( ! isset( $available_gateways[ $order_payment_method ] ) || ! $available_gateways[ $order_payment_method ]->supports( 'subscriptions' ) ) {
				$subscription->set_requires_manual_renewal( true );
			}

			// Add product to subscriber, adding fee separately.
			Checkout::add_product( $subscription, $cart );
			Hooks::add_shipping( $subscription, $cart );
			Checkout::apply_coupon( $subscription, $cart );
			Checkout::add_tax( $subscription, $cart );

			// Set the recurring totals on the subscription
			$subscription->set_shipping_total( $cart->get_shipping_total() );
			$subscription->set_discount_total( $cart->get_discount_total() );
			$subscription->set_discount_tax( $cart->get_discount_tax() );
			$subscription->set_cart_tax( $cart->get_fee_tax() + $cart->get_cart_contents_tax() );
			$subscription->set_shipping_tax( $cart->get_shipping_tax() );
			$subscription->set_total( $cart->get_total() );

			$item = $cart->get_cart_items();
			$item = reset( $item );
			$subscription->set_payment_duration( $item->payment_duration );
			$subscription->set_payment_duration_type( $item->payment_duration_type );

			// add meta data
			if ( $item->trial ) {
				$subscription->set_trial( true );
				$subscription->set_trial_days( $item->trial_days );
			}

			$subscription->set_start_date( $cart->start_date );
			$subscription->set_trial_end_date( $cart->trial_end_date );
			$subscription->set_next_payment_date( $cart->next_payment_date );
			$subscription->set_end_date( $cart->end_date );

			$subscription->calculate();

			// save
			$subscription->save();

			// If we got here, the subscription was created without problems
			$transaction->commit();
		} catch ( StoreEngineException $e ) {

			// There was an error adding the subscription
			$transaction->rollback();

			return $e->toWpError();
		}

		$subscription->read( true );

		return $subscription;
	}

	public static function get_trial_expiration_date( OrderItemProduct $product, $from_date = '' ) {
		$trial_expiration_date = 0;
		if ( $product->is_trial() && $product->get_trial_days() > 0 ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			$trial_expiration_date = gmdate( 'Y-m-d H:i:s', self::add_time( $product->get_trial_days(), 'days', self::date_to_time( $from_date ) ) );
		}

		return apply_filters( 'storeengine/subscriptions/product_trial_expiration_date', $trial_expiration_date, $product, $from_date );
	}

	public static function get_first_renewal_payment_date( OrderItemProduct $product, $from_date = '', $timezone = 'gmt' ) {
		$first_renewal_timestamp = self::get_first_renewal_payment_time( $product, $from_date, $timezone );

		if ( $first_renewal_timestamp > 0 ) {
			$first_renewal_date = gmdate( 'Y-m-d H:i:s', $first_renewal_timestamp );
		} else {
			$first_renewal_date = 0;
		}

		return apply_filters( 'woocommerce_subscriptions_product_first_renewal_payment_date', $first_renewal_date, $product, $from_date, $timezone );
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the first renewal payment will be processed
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param OrderItemProduct $product The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @param string $type The return format for the date, either 'mysql', or 'timezone'. Default 'mysql'.
	 * @param string $timezone The timezone for the returned date, either 'site' for the site's timezone, or 'gmt'. Default, 'site'.
	 */
	public static function get_first_renewal_payment_time( OrderItemProduct $product, $from_date = '', string $timezone = 'gmt' ) {
		if ( ! Helper::get_price( $product->get_price_id() )->is_subscription() ) {
			return '';
		}

		$from_date_param = $from_date;

		$billing_interval = $product->get_payment_duration();
		$billing_length   = $product->get_expire( 'edit' );
		$trial_length     = $product->is_trial() ? $product->get_trial_days() : 0;

		if ( $billing_interval !== $billing_length || $trial_length > 0 ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			// If the subscription has a free trial period, the first renewal payment date is the same as the expiration of the free trial
			if ( $trial_length > 0 ) {
				$first_renewal_timestamp = self::date_to_time( self::get_trial_expiration_date( $product, $from_date ) );
			} else {
				$site_time_offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

				// As wcs_add_time() calls wcs_add_months() which checks for last day of month, pass the site time
				$first_renewal_timestamp = self::add_time( $billing_interval, $product->get_payment_duration_type(), self::date_to_time( $from_date ) + $site_time_offset );

				if ( 'site' !== $timezone ) {
					$first_renewal_timestamp -= $site_time_offset;
				}
			}
		} else {
			$first_renewal_timestamp = 0;
		}

		return apply_filters( 'storeengine/subscriptions/product_first_renewal_payment_time', $first_renewal_timestamp, $product, $from_date_param, $timezone );
	}

	public static function get_expiration_date( OrderItemProduct $product, $from_date = '' ) {
		$subscription_length = $product->get_expire( 'edit' );

		if ( $subscription_length > 0 ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			if ( $product->is_trial() && $product->get_trial_days() > 0 ) {
				$from_date = self::get_trial_expiration_date( $product, $from_date );
			}

			$expiration_date = gmdate( 'Y-m-d H:i:s', self::add_time( $subscription_length, $product->get_payment_duration_type(), self::date_to_time( $from_date ) ) );
		} else {
			$expiration_date = 0;
		}

		return apply_filters( 'storeengine/subscriptions/product_expiration_date', $expiration_date, $product, $from_date );
	}

	/**
	 * Workaround the last day of month quirk in PHP's strtotime function.
	 *
	 * Adding +1 month to the last day of the month can yield unexpected results with strtotime().
	 * For example:
	 * - 30 Jan 2013 + 1 month = 3rd March 2013
	 * - 28 Feb 2013 + 1 month = 28th March 2013
	 *
	 * What humans usually want is for the date to continue on the last day of the month.
	 *
	 * @param int $from_timestamp A Unix timestamp to add the months too.
	 * @param int $months_to_add The number of months to add to the timestamp.
	 * @param string $timezone_behaviour Optional. If the $from_timestamp parameter should be offset to the site time or not, either 'offset_site_time' or 'no_offset'. Default 'no_offset'.
	 *
	 * @see wcs_add_time()
	 */
	public static function add_months( $from_timestamp, $months_to_add, $timezone_behaviour = 'no_offset' ) {
		if ( 'offset_site_time' === $timezone_behaviour ) {
			$from_timestamp += Formatting::timezone_offset();
		}

		$first_day_of_month = gmdate( 'Y-m', $from_timestamp ) . '-1';
		$days_in_next_month = gmdate( 't', self::strtotime_dark_knight( "+ {$months_to_add} month", self::date_to_time( $first_day_of_month ) ) );
		$next_timestamp     = 0;

		// Payment is on the last day of the month OR number of days in next billing month is less than the the day of this month (i.e. current billing date is 30th January, next billing date can't be 30th February)
		if ( gmdate( 'd m Y', $from_timestamp ) === gmdate( 't m Y', $from_timestamp ) || gmdate( 'd', $from_timestamp ) > $days_in_next_month ) {
			for ( $i = 1; $i <= $months_to_add; $i ++ ) {
				$next_month     = self::add_time( 3, 'days', $from_timestamp, $timezone_behaviour ); // Add 3 days to make sure we get to the next month, even when it's the 29th day of a month with 31 days
				$from_timestamp = self::date_to_time( gmdate( 'Y-m-t H:i:s', $next_month ) ); // NB the "t" to get last day of next month
				$next_timestamp = $from_timestamp;
			}
		} else { // Safe to just add a month
			$next_timestamp = self::strtotime_dark_knight( "+ {$months_to_add} month", $from_timestamp );
		}

		if ( 'offset_site_time' === $timezone_behaviour ) {
			$next_timestamp -= Formatting::timezone_offset();
		}

		return $next_timestamp;
	}

	/**
	 * Convenience wrapper for adding "{n} {periods}" to a timestamp (e.g. 2 months or 5 days).
	 *
	 * @param int $number_of_periods The number of periods to add to the timestamp
	 * @param string $period One of day, week, month or year.
	 * @param int $from_timestamp A Unix timestamp to add the time too.
	 * @param string $timezone_behaviour Optional. If the $from_timestamp parameter should be offset to the site time or not, either 'offset_site_time' or 'no_offset'. Default 'no_offset'.
	 *
	 * @see wcs_add_time()
	 */
	public static function add_time( $number_of_periods, $period, $from_timestamp, $timezone_behaviour = 'no_offset' ) {
		if ( ! in_array( $period, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$translation = [
				'monthly' => 'month',
				'yearly'  => 'year',
				'daily'   => 'day',
				'weekly'  => 'week',
				'hourly'  => 'hour',
				'month'   => 'month',
				'year'    => 'year',
				'day'     => 'day',
				'week'    => 'week',
				'hour'    => 'hour',
				'months'  => 'month',
				'years'   => 'year',
				'days'    => 'day',
				'weeks'   => 'week',
				'hours'   => 'hour',
			];
			$period      = $translation[ $period ] ?? 'month';
		}

		if ( $number_of_periods > 0 ) {
			if ( 'month' === $period ) {
				$next_timestamp = self::add_months( $from_timestamp, $number_of_periods, $timezone_behaviour );
			} else {
				$next_timestamp = self::strtotime_dark_knight( "+ {$number_of_periods} {$period}", $from_timestamp );
			}
		} else {
			$next_timestamp = $from_timestamp;
		}

		return $next_timestamp;
	}

	/**
	 * Convert a date string into a timestamp without ever adding or deducting time.
	 *
	 * The strtotime() would be handy for this purpose, but alas, if other code running on the server
	 * is calling date_default_timezone_set() to change the timezone, strtotime() will assume the
	 * date is in that timezone unless the timezone is specific on the string (which it isn't for
	 * any MySQL formatted date) and attempt to convert it to UTC time by adding or deducting the
	 * GMT/UTC offset for that timezone, so for example, when 3rd party code has set the servers
	 * timezone using date_default_timezone_set( 'America/Los_Angeles' ) doing something like
	 * gmdate( "Y-m-d H:i:s", strtotime( gmdate( "Y-m-d H:i:s" ) ) ) will actually add 7 hours to
	 * the date even though it is a date in UTC timezone because the timezone wasn't specificed.
	 *
	 * This makes sure the date is never converted.
	 *
	 * @param string $date_string A date string formatted in MySQl or similar format that will map correctly when instantiating an instance of DateTime()
	 *
	 * @return int Unix timestamp representation of the timestamp passed in without any changes for timezones
	 *
	 * @see wcs_date_to_time()
	 */
	public static function date_to_time( string $date_string ): int {
		if ( ! $date_string ) {
			return 0;
		}

		$date_time = Formatting::string_to_datetime( $date_string );
		$date_time->setTimezone( new DateTimeZone( 'UTC' ) );

		return $date_time->getTimestamp();
	}

	/**
	 * A wrapper for strtotime() designed to stand up against those who want to watch the WordPress burn.
	 *
	 * One day WordPress will require Harvey Dent (aka PHP 5.3) then we can use DateTime::add() instead,
	 * but for now, this ensures when using strtotime() to add time to a timestamp, there are no additional
	 * changes for server specific timezone additions or deductions.
	 *
	 * @param string $time_string A string representation of a date in any format that can be parsed by strtotime()
	 *
	 * @return int Unix timestamp representation of the timestamp passed in without any changes for timezones
	 *
	 * @see wcs_strtotime_dark_knight()
	 */
	public static function strtotime_dark_knight( $time_string, $from_timestamp = null ) {
		$original_timezone = date_default_timezone_get();

		// this should be UTC anyway as WordPress sets it to that, but some plugins and l33t h4xors just want to watch the world burn and set it to something else
		date_default_timezone_set( 'UTC' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

		if ( null === $from_timestamp ) {
			$next_timestamp = strtotime( $time_string );
		} else {
			$next_timestamp = strtotime( $time_string, $from_timestamp );
		}

		date_default_timezone_set( $original_timezone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

		return $next_timestamp;
	}
}
