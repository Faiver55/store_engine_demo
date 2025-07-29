<?php

namespace StoreEngine\Addons\Subscription\Classes;

use StoreEngine\Addons\Subscription\Events\CreateSubscription;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\CartItem;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Price;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\SqlTransaction;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use WP_Error;

class Utils {
	public static function create_order_from_subscription( Subscription $subscription, string $type ) {
		if ( ! in_array( $type, [ 'renewal_order', 'resubscribe_order', 'parent' ], true ) ) {
			do_action( 'storeengine/subscription/failed_to_create_renewal_order', $type, $subscription );

			// translators: placeholder is an order type.
			return new WP_Error( 'invalid-subscription-order-type', sprintf( __( '"%s" is not a valid new order type.', 'storeengine' ), $type ) );
		}

		$transaction = new SqlTransaction();
		$transaction->start();

		try {
			$order = new Order();

			$order->set_customer_id( $subscription->get_customer_id() );
			$order->set_customer_note( $subscription->get_customer_note() );
			$order->set_created_via( 'subscription' );

			// Copy data.

			// Order data.
			$order->set_status( 'pending_payment' );
			$order->set_currency( $subscription->get_currency( 'edit' ) );
			$order->set_cart_tax( $subscription->get_cart_tax( 'edit' ) );
			$order->set_total( $subscription->get_total( 'edit' ) );
			$order->set_customer_id( $subscription->get_customer_id( 'edit' ) );
			$order->set_billing_email( $subscription->get_billing_email( 'edit' ) );
			$order->set_payment_method( $subscription->get_payment_method( 'edit' ) );
			$order->set_payment_method_title( $subscription->get_payment_method_title( 'edit' ) );
			$order->set_ip_address( $subscription->get_ip_address( 'edit' ) );
			$order->set_user_agent( $subscription->get_user_agent( 'edit' ) );
			$order->set_transaction_id( $subscription->get_transaction_id( 'edit' ) );

			// Operational data
			$order->set_prices_include_tax( Formatting::string_to_bool( $subscription->get_prices_include_tax( 'edit' ) ) );
			$order->set_recorded_coupon_usage_counts( Formatting::string_to_bool( $subscription->get_recorded_coupon_usage_counts( 'edit' ) ) );
			$order->set_download_permissions_granted( Formatting::bool_to_string( $subscription->get_download_permissions_granted( 'edit' ) ) );
			$order->set_cart_hash( $subscription->get_cart_hash( 'edit' ) );
			$order->set_new_order_email_sent( Formatting::string_to_bool( $subscription->get_new_order_email_sent( 'edit' ) ) );
			$order->set_order_key( $subscription->get_order_key( 'edit' ) );
			$order->set_order_stock_reduced( $subscription->get_order_stock_reduced( 'edit' ) );
			$order->set_date_paid_gmt( $subscription->get_date_paid_gmt( 'edit' ) );
			$order->set_date_completed_gmt( $subscription->get_date_completed_gmt( 'edit' ) );
			$order->set_shipping_tax( $subscription->get_shipping_tax( 'edit' ) );
			$order->set_shipping_total( $subscription->get_shipping_total( 'edit' ) );
			$order->set_discount_tax( $subscription->get_discount_tax( 'edit' ) );
			$order->set_discount_total( $subscription->get_discount_total( 'edit' ) );
			$order->set_recorded_sales( Formatting::bool_to_string( $subscription->get_recorded_sales( 'edit' ) ) );
			// Address data.
			// Billing address.
			$order->set_billing_first_name( $subscription->get_billing_first_name( 'edit' ) );
			$order->set_billing_last_name( $subscription->get_billing_last_name( 'edit' ) );
			$order->set_billing_company( $subscription->get_billing_company( 'edit' ) );
			$order->set_billing_address_1( $subscription->get_billing_address_1( 'edit' ) );
			$order->set_billing_address_2( $subscription->get_billing_address_2( 'edit' ) );
			$order->set_billing_city( $subscription->get_billing_city( 'edit' ) );
			$order->set_billing_state( $subscription->get_billing_state( 'edit' ) );
			$order->set_billing_postcode( $subscription->get_billing_postcode( 'edit' ) );
			$order->set_billing_country( $subscription->get_billing_country( 'edit' ) );
			$order->set_billing_email( $subscription->get_billing_email( 'edit' ) );
			$order->set_billing_phone( $subscription->get_billing_phone( 'edit' ) );

			// Shipping address.
			$order->set_shipping_first_name( $subscription->get_shipping_first_name( 'edit' ) );
			$order->set_shipping_last_name( $subscription->get_shipping_last_name( 'edit' ) );
			$order->set_shipping_company( $subscription->get_shipping_company( 'edit' ) );
			$order->set_shipping_address_1( $subscription->get_shipping_address_1( 'edit' ) );
			$order->set_shipping_address_2( $subscription->get_shipping_address_2( 'edit' ) );
			$order->set_shipping_city( $subscription->get_shipping_city( 'edit' ) );
			$order->set_shipping_state( $subscription->get_shipping_state( 'edit' ) );
			$order->set_shipping_postcode( $subscription->get_shipping_postcode( 'edit' ) );
			$order->set_shipping_country( $subscription->get_shipping_country( 'edit' ) );

			foreach ( $subscription->get_meta_data() as $meta ) {
				$order->add_meta_data( $meta->key, $meta->value );
			}

			$tokens = $subscription->get_payment_tokens();
			if ( ! empty( $tokens ) ) {
				$order->add_meta_data( '_payment_tokens', $tokens );
			}

			$order->save();

			foreach ( $subscription->get_items( [ 'line_item', 'fee', 'shipping', 'tax', 'coupon' ] ) as $item ) {
				$order_item = clone $item;
				$order->add_item( $order_item );
				$order_item->save();

				if ( $item->is_type( 'line_item' ) && $item->get_product() ) {
					$order_item->set_backorder_meta();
					$order_item->save();
				}
			}

			$order->save();

			$transaction->commit();

			return $order;
		} catch ( \Exception $e ) {
			// There was an error adding the subscription
			$transaction->rollback();
			return new WP_Error( 'new-subscription-order-error', $e->getMessage() );
		}
	}

	/**
	 * Create a renewal order to record a scheduled subscription payment.
	 *
	 * This method simply creates an order with the same post meta, order items and order item meta as the subscription
	 * passed to it.
	 *
	 * @param  Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object
	 * @return Order|WP_Error
	 */
	public static function create_renewal_order( Subscription $subscription ) {
		$renewal_order = self::create_order_from_subscription( $subscription, 'renewal_order' );

		if ( is_wp_error( $renewal_order ) ) {
			do_action( 'storeengine/subscription/failed_to_create_renewal_order', $renewal_order, $subscription );
			return new WP_Error( 'renewal-order-error', $renewal_order->get_error_message() );
		}

		$renewal_order->add_meta_data( '_subscription_id', $subscription->get_id() );
		$renewal_order->add_meta_data( '_subscription_renewal', $subscription->get_id() );

		$renewal_order->save();

		return apply_filters( 'storeengine/subscription/renewal_order_created', $renewal_order, $subscription );
	}

	public static function create_resubscribe_order( Subscription $subscription ) {
		$resubscribe_order = self::create_order_from_subscription( $subscription, 'resubscribe_order' );

		if ( is_wp_error( $resubscribe_order ) ) {
			do_action( 'storeengine/subscription/failed_to_create_resubscribe_order', $resubscribe_order, $subscription );
			return new WP_Error( 'renewal-order-error', $resubscribe_order->get_error_message() );
		}

		$resubscribe_order->add_meta_data( '_subscription_id', $subscription->get_id() );
		$resubscribe_order->add_meta_data( '_subscription_resubscribe', $subscription->get_id() );

		$resubscribe_order->save();

		return apply_filters( 'storeengine/subscription/resubscribe_order_created', $resubscribe_order, $subscription );
	}

	public static function create_parent_order( Subscription $subscription ) {
		$parent_order = self::create_order_from_subscription( $subscription, 'parent' );

		if ( is_wp_error( $parent_order ) ) {
			do_action( 'storeengine/subscription/failed_to_create_parent_order', $parent_order, $subscription );
			return new WP_Error( 'renewal-order-error', $parent_order->get_error_message() );
		}

		$subscription->set_parent_order_id( $parent_order->get_id() );

		return apply_filters( 'storeengine/subscription/parent_order_created', $parent_order, $subscription );
	}

	/**
	 * Get payment gateway class by order data.
	 *
	 * @param Order|Subscription $order Order instance.
	 * @return PaymentGateway|bool
	 */
	public static function get_payment_gateway_by_order( $order ) {
		return Helper::get_payment_gateway_by_order( $order );
	}

	/**
	 * Returns a string representing the details of the subscription.
	 *
	 * For example "$20 per Month for 3 Months with a $10 sign-up fee".
	 *
	 * @param int $price_id A Price ID.
	 * @param array $include An associative array of flags to indicate how to calculate the price and what to include, values:
	 *    'tax_calculation'     => false to ignore tax, 'include_tax' or 'exclude_tax' To indicate that tax should be added or excluded respectively
	 *    'subscription_length' => true to include subscription's length (default) or false to exclude it
	 *    'sign_up_fee'         => true to include subscription's sign up fee (default) or false to exclude it
	 *    'price'               => string a price to short-circuit the price calculations and use in a string for the product
	 *
	 * @throws StoreEngineException
	 */
	public static function subscription_price_html( int $price_id, array $include = [] ) {
		$priceObject = new Price( $price_id );

		if ( ! $priceObject->is_subscription() ) {
			return '';
		}

		$include = wp_parse_args( $include, [
			'tax_calculation'     => Helper::get_settings( 'tax_display_shop' ),
			'subscription_price'  => true,
			'subscription_period' => true,
			'subscription_length' => true,
			'sign_up_fee'         => $priceObject->has_setup_fee(),
		] );

		$include = apply_filters( 'storeengine/subscription/price_html_inclusions', $include, $priceObject );

		$base_price          = $priceObject->get_price();
		$billing_interval    = $priceObject->get_payment_duration();
		$billing_period      = $priceObject->get_payment_duration_type();
		$subscription_length = 0;
		$sign_up_fee         = 0;
		$include_length      = $include['subscription_length'] && 0 !== $subscription_length;

		if ( empty( $billing_period ) ) {
			$billing_period = 'month';
		}

		$fee_name = __( 'Setup Fee', 'storeengine' );
		if ( $include['sign_up_fee'] ) {
			$sign_up_fee = is_bool( $include['sign_up_fee'] ) && $priceObject->has_setup_fee() ? $priceObject->get_setup_fee_price() : $include['sign_up_fee'];
			$fee_name    = is_bool( $include['sign_up_fee'] ) && $priceObject->has_setup_fee() ? $priceObject->get_setup_fee_name() : $fee_name;
		}

		if ( $include['tax_calculation'] ) {
			if ( in_array( $include['tax_calculation'], array( 'exclude_tax', 'excl' ), true ) ) {
				// Calculate excluding tax.
				$price = $include['price'] ?? Formatting::get_price_excluding_tax( $priceObject->get_price(), $priceObject->get_id(), $priceObject->get_product_id() );
				if ( true === $include['sign_up_fee'] ) {
					$sign_up_fee = Formatting::get_price_excluding_tax( $priceObject->get_price(), $priceObject->get_id(), $priceObject->get_product_id(), [
						'price' => $priceObject->get_setup_fee_price(),
					] );
				}
			} else {
				// Calculate including tax.
				$price = $include['price'] ?? Formatting::get_price_including_tax( $priceObject->get_price(), $priceObject->get_id(), $priceObject->get_product_id() );
				if ( true === $include['sign_up_fee'] ) {
					$sign_up_fee = Formatting::get_price_including_tax( $priceObject->get_price(), $priceObject->get_id(), $priceObject->get_product_id(), [
						'price' => $priceObject->get_setup_fee_price(),
					] );
				}
			}
		} else {
			$price = $include['price'] ?? Formatting::price( $base_price );
		}

		if ( is_numeric( $sign_up_fee ) ) {
			$sign_up_fee = Formatting::price( $sign_up_fee );
		}

		$price .= ' <span class="storeengine-subscription-details">';

		$subscription_string = '';

		if ( $include['subscription_price'] && $include['subscription_period'] ) { // Allow extensions to not show price or billing period e.g. Name Your Price.
			if ( $include_length && $subscription_length === $billing_interval ) {
				$subscription_string = $price; // Only for one billing period so show "$5 for 3 months" instead of "$5 every 3 months for 3 months".
			} elseif ( $priceObject->is_product_synced() && in_array( $billing_period, [ 'day', 'week', 'month', 'year' ], true ) ) {
				$subscription_string = '';
				// @TODO implement sync-date for subscription.
			} else {
				$subscription_string = sprintf(
				// translators: 1$: recurring amount, 2$: subscription period (e.g. "month" or "3 months") (e.g. "$15 / month" or "$15 every 2nd month")
					_n( '%1$s / %2$s', '%1$s every %2$s', $billing_interval, 'storeengine' ),
					$price,
					self::get_subscription_period_strings( $billing_interval, $billing_period )
				);
			}
		} elseif ( $include['subscription_price'] ) {
			$subscription_string = $price;
		} elseif ( $include['subscription_period'] ) {
			$subscription_string = '<span class="storeengine-subscription-details">' . sprintf(
				// translators: billing period (e.g. "every week").
					__( 'every %s', 'storeengine' ),
					self::get_subscription_period_strings( $billing_interval, $billing_period )
				);
		} else {
			$subscription_string = '<span class="storeengine-subscription-details">';
		}

		// Add the length to the end.
		if ( $include_length ) {
			$ranges = self::get_subscription_ranges( $billing_period );
			// translators: 1$: subscription string (e.g. "$10 up front then $5 on March 23rd every 3rd year"), 2$: length (e.g. "4 years").
			$subscription_string = sprintf( __( '%1$s for %2$s', 'storeengine' ), $subscription_string, $ranges[ $subscription_length ] );
		}

		$subscription_string = apply_filters( 'storeengine/subscription/price_options', $subscription_string, $priceObject, $include );

		if ( $include['sign_up_fee'] && $priceObject->get_setup_fee_price() > 0 ) {
			// translators: 1$: subscription string (e.g. "$15 on March 15th every 3 years for 6 years with 2 months free trial"), 2$: signup fee price (e.g. "and a $30 sign-up fee"), 3$ signup fee name (e.g. "sign-up fee" or "setup cost").
			$subscription_string = sprintf( __( '%1$s and a %2$s %3$s', 'storeengine' ), $subscription_string, $sign_up_fee, $fee_name );
		}

		$subscription_string .= '</span>';

		return apply_filters( 'storeengine/subscription/get_price_html', $subscription_string, $priceObject, $include );
	}

	/**
	 * Creates a subscription price string from an array of subscription details. For example, "$5 / month for 12 months".
	 *
	 * @param array $subscription_details A set of name => value pairs for the subscription details to include in the string. Available keys:
	 *    'initial_amount': The upfront payment for the subscription, including sign up fees, as a string from the @see Formatting::price(). Default empty string (no initial payment)
	 *    'initial_description': The word after the initial payment amount to describe the amount. Examples include "now" or "initial payment". Defaults to "up front".
	 *    'recurring_amount': The amount charged per period. Default 0 (no recurring payment).
	 *    'subscription_interval': How regularly the subscription payments are charged. Default 1, meaning each period e.g. per month.
	 *    'subscription_period': The temporal period of the subscription. Should be one of {day|week|month|year} as used by @see get_subscription_period_strings()
	 *    'subscription_length': The total number of periods the subscription should continue for. Default 0, meaning continue indefinitely.
	 *    'trial_length': The total number of periods the subscription trial period should continue for.  Default 0, meaning no trial period.
	 *    'trial_period': The temporal period for the subscription's trial period. Should be one of {day|week|month|year} as used by @see get_subscription_period_strings()
	 *    'use_per_slash': Allow calling code to determine if they want the shorter price string using a slash for singular billing intervals, e.g. $5 / month, or the longer form, e.g. $5 every month, which is normally reserved for intervals > 1
	 * @return string The price string with translated and billing periods included
	 */
	public static function format_cart_price_html( $subscription_details ) {
		$subscription_details = wp_parse_args(
			$subscription_details,
			[
				'currency'                    => '',
				'initial_amount'              => '',
				'initial_description'         => _x( 'up front', 'initial payment on a subscription', 'storeengine' ),
				'recurring_amount'            => '',

				// Schedule details
				'subscription_interval'       => 1,
				'subscription_period'         => 'month',
				'subscription_length'         => 0,
				'trial_length'                => 0,
				'trial_period'                => '',

				// Syncing details
				'is_synced'                   => false,
				'synchronised_payment_day'    => 0,

				// Params for Formatting::price()
				'display_excluding_tax_label' => false,

				// Params for formatting customisation
				'use_per_slash'               => true,
			]
		);

		$subscription_details['subscription_period'] = strtolower( $subscription_details['subscription_period'] );

		// Make sure prices have been through Formatting::price()
		if ( is_numeric( $subscription_details['initial_amount'] ) ) {
			$initial_amount_string = Formatting::price(
				$subscription_details['initial_amount'],
				[
					'currency'     => $subscription_details['currency'],
					'ex_tax_label' => $subscription_details['display_excluding_tax_label'],
				]
			);
		} else {
			$initial_amount_string = $subscription_details['initial_amount'];
		}

		if ( is_numeric( $subscription_details['recurring_amount'] ) ) {
			$recurring_amount_string = Formatting::price(
				$subscription_details['recurring_amount'],
				[
					'currency'     => $subscription_details['currency'],
					'ex_tax_label' => $subscription_details['display_excluding_tax_label'],
				]
			);
		} else {
			$recurring_amount_string = $subscription_details['recurring_amount'];
		}

		$subscription_period_string = self::get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] );
		$subscription_ranges        = self::get_subscription_ranges();

		if ( $subscription_details['subscription_length'] > 0 && $subscription_details['subscription_length'] == $subscription_details['subscription_interval'] ) {
			if ( ! empty( $subscription_details['initial_amount'] ) ) {
				if ( 0 == $subscription_details['trial_length'] ) {
					$subscription_string = $initial_amount_string;
				} else {
					// translators: 1$: initial amount, 2$: initial description (e.g. "up front"), 3$: recurring amount string (e.g. "£10 / month" )
					$subscription_string = sprintf( __( '%1$s %2$s then %3$s', 'storeengine' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string );
				}
			} else {
				$subscription_string = $recurring_amount_string;
			}
		} elseif ( ! empty( $subscription_details['initial_amount'] ) ) {
			// translators: 1$: initial amount, 2$: initial description (e.g. "up front"), 3$: recurring amount, 4$: subscription period (e.g. "month" or "3 months")
			$subscription_string = sprintf( _n( '%1$s %2$s then %3$s / %4$s', '%1$s %2$s then %3$s every %4$s', $subscription_details['subscription_interval'], 'storeengine' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $subscription_period_string );
		} elseif ( ! empty( $subscription_details['recurring_amount'] ) || intval( $subscription_details['recurring_amount'] ) === 0 ) {
			if ( true === $subscription_details['use_per_slash'] ) {
				$subscription_string = sprintf(
				// translators: 1$: recurring amount, 2$: subscription period (e.g. "month" or "3 months") (e.g. "$15 / month" or "$15 every 2nd month")
					_n( '%1$s / %2$s', '%1$s every %2$s', $subscription_details['subscription_interval'], 'storeengine' ),
					$recurring_amount_string,
					$subscription_period_string
				);
			} else {
				// translators: %1$: recurring amount (e.g. "$15"), %2$: subscription period (e.g. "month") (e.g. "$15 every 2nd month")
				$subscription_string = sprintf( __( '%1$s every %2$s', 'storeengine' ), $recurring_amount_string, $subscription_period_string );
			}
		} else {
			$subscription_string = '';
		}

		if ( $subscription_details['subscription_length'] > 0 ) {
			// translators: 1$: subscription string (e.g. "$10 up front then $5 on March 23rd every 3rd year"), 2$: length (e.g. "4 years")
			$subscription_string = sprintf( __( '%1$s for %2$s', 'storeengine' ), $subscription_string, $subscription_ranges[ $subscription_details['subscription_period'] ][ $subscription_details['subscription_length'] ] );
		}

		if ( $subscription_details['trial_length'] > 0 ) {
			$trial_length = self::get_subscription_trial_period_strings( $subscription_details['trial_length'], $subscription_details['trial_period'] );
			if ( ! empty( $subscription_details['initial_amount'] ) ) {
				// translators: 1$: subscription string (e.g. "$10 up front then $5 on March 23rd every 3rd year"), 2$: trial length (e.g. "3 weeks")
				$subscription_string = sprintf( __( '%1$s after %2$s free trial', 'storeengine' ), $subscription_string, $trial_length );
			} else {
				// translators: 1$: trial length (e.g. "3 weeks"), 2$: subscription string (e.g. "$10 up front then $5 on March 23rd every 3rd year")
				$subscription_string = sprintf( __( '%1$s free trial then %2$s', 'storeengine' ), ucfirst( $trial_length ), $subscription_string );
			}
		}

		if ( $subscription_details['display_excluding_tax_label'] && TaxUtil::is_tax_enabled() ) {
			$subscription_string .= ' <small>' . Countries::init()->ex_tax_or_vat() . '</small>';
		}

		return apply_filters( 'storeengine/subscription/cart_price_string_html', $subscription_string, $subscription_details );
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription periods.
	 *
	 * @param int $number (optional) An interval in the range 1-6
	 * @param string $period (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
	 *
	 * @return string|array
	 */
	public static function get_subscription_period_strings( int $number = 1, string $period = '' ) {
		// phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
		$translated_periods = apply_filters( 'storeengine/subscription/periods',
			[
				// translators: placeholder is number of days. (e.g. "Bill this every day / 4 days")
				'day'   => sprintf( _nx( 'day',   '%s days',   $number, 'Subscription billing period.', 'storeengine' ), $number ), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
				// translators: placeholder is number of weeks. (e.g. "Bill this every week / 4 weeks")
				'week'  => sprintf( _nx( 'week',  '%s weeks',  $number, 'Subscription billing period.', 'storeengine' ), $number ), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
				// translators: placeholder is number of months. (e.g. "Bill this every month / 4 months")
				'month' => sprintf( _nx( 'month', '%s months', $number, 'Subscription billing period.', 'storeengine' ), $number ), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
				// translators: placeholder is number of years. (e.g. "Bill this every year / 4 years")
				'year'  => sprintf( _nx( 'year',  '%s years',  $number, 'Subscription billing period.', 'storeengine' ), $number ), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
			],
			$number
		);
		// phpcs:enable

		return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription periods.
	 *
	 * @param int $number (optional) An interval in the range 1-6
	 * @param string $period (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
	 *
	 * @return string
	 */
	public static function get_subscription_period_short_strings( int $number = 1, string $period = '' ): string {
		// phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
		$translated_periods = apply_filters( 'storeengine/subscription/short_strings',
			[
				'day'   => _x( 'd', 'Subscription billing period shorthand.', 'storeengine' ),
				'week'  => _x( 'wk', 'Subscription billing period shorthand.', 'storeengine' ),
				'month' => _x( 'mo', 'Subscription billing period shorthand.', 'storeengine' ),
				'year'  => _x( 'yr', 'Subscription billing period shorthand.', 'storeengine' ),
			]
		);
		// phpcs:enable

		$period = $translated_periods[ $period ] ?? $period;

		if ( $number > 1 ) {
			// translators: %1$d. Billing interval (E.g. 1, 2, 4, etc). %2$s. billing period (e.g. d, wk, mo, yr, etc).
			$period = sprintf( _x( '%1$d %2$s', 'Subscription billing period shorthand with interval.', 'storeengine' ), $number, $period );
		}

		return $period;
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription trial periods.
	 *
	 * @param int $number (optional) An interval in the range 1-6
	 * @param string $period (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
	 *
	 * @return string|array
	 */
	public static function get_subscription_trial_period_strings( int $number = 1, string $period = '' ) {
		$translated_periods = apply_filters( 'storeengine/subscription/trial_periods',
			[
				// translators: placeholder is a number of days.
				'day'   => sprintf( _n( '%s day', 'a %s-day', $number, 'storeengine' ), $number ),
				// translators: placeholder is a number of weeks.
				'week'  => sprintf( _n( '%s week', 'a %s-week', $number, 'storeengine' ), $number ),
				// translators: placeholder is a number of months.
				'month' => sprintf( _n( '%s month', 'a %s-month', $number, 'storeengine' ), $number ),
				// translators: placeholder is a number of years.
				'year'  => sprintf( _n( '%s year', 'a %s-year', $number, 'storeengine' ), $number ),
			],
			$number
		);

		return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
	}

	/**
	 * Appends the ordinal suffix to a given number.
	 *
	 * eg. Given 2, the function returns 2nd.
	 *
	 * @param string $number The number to append the ordinal suffix to.
	 *
	 * @return string
	 */
	public static function append_numeral_suffix( string $number ): string {

		// Handle teens: if the tens digit of a number is 1, then write "th" after the number. For example: 11th, 13th, 19th, 112th, 9311th. http://en.wikipedia.org/wiki/English_numerals
		if ( strlen( $number ) > 1 && 1 == substr( $number, -2, 1 ) ) {
			// translators: placeholder is a number, this is for the teens
			$number_string = sprintf( __( '%sth', 'storeengine' ), $number );
		} else { // Append relevant suffix
			switch ( substr( $number, -1 ) ) {
				case 1:
					// translators: placeholder is a number, numbers ending in 1
					$number_string = sprintf( __( '%sst', 'storeengine' ), $number );
					break;
				case 2:
					// translators: placeholder is a number, numbers ending in 2
					$number_string = sprintf( __( '%snd', 'storeengine' ), $number );
					break;
				case 3:
					// translators: placeholder is a number, numbers ending in 3
					$number_string = sprintf( __( '%srd', 'storeengine' ), $number );
					break;
				default:
					// translators: placeholder is a number, numbers ending in 4-9, 0
					$number_string = sprintf( __( '%sth', 'storeengine' ), $number );
					break;
			}
		}

		return apply_filters( 'storeengine/numeral_suffix', $number_string, $number );
	}

	/**
	 * Retaining the API, it makes use of the transient functionality.
	 *
	 * @param string $period
	 * @return bool|mixed
	 */
	public static function get_subscription_ranges( $subscription_period = '' ) {
		static $subscription_locale_ranges = array();

		if ( ! is_string( $subscription_period ) ) {
			$subscription_period = '';
		}

		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

		if ( ! isset( $subscription_locale_ranges[ $locale ] ) ) {
			$subscription_locale_ranges[ $locale ] = self::get_non_cached_subscription_ranges();
		}

		$subscription_ranges = apply_filters( 'storeengine/subscription/lengths', $subscription_locale_ranges[ $locale ], $subscription_period );

		if ( ! empty( $subscription_period ) ) {
			return $subscription_ranges[ $subscription_period ];
		} else {
			return $subscription_ranges;
		}
	}

	/**
	 * Returns an array of subscription lengths.
	 *
	 * PayPal Standard Allowable Ranges
	 * D – for days; allowable range is 1 to 90
	 * W – for weeks; allowable range is 1 to 52
	 * M – for months; allowable range is 1 to 24
	 * Y – for years; allowable range is 1 to 5
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.1.2
	 */
	public static function get_non_cached_subscription_ranges() {
		foreach ( [ 'day', 'week', 'month', 'year' ] as $period ) {
			$subscription_lengths = [
				_x( 'Never expire', 'Subscription length', 'storeengine' ),
			];

			switch ( $period ) {
				case 'day':
					$subscription_lengths[] = _x( '1 day', 'Subscription lengths. e.g. "For 1 day..."', 'storeengine' );
					$subscription_range     = range( 2, 90 );
					break;
				case 'week':
					$subscription_lengths[] = _x( '1 week', 'Subscription lengths. e.g. "For 1 week..."', 'storeengine' );
					$subscription_range     = range( 2, 52 );
					break;
				case 'month':
					$subscription_lengths[] = _x( '1 month', 'Subscription lengths. e.g. "For 1 month..."', 'storeengine' );
					$subscription_range     = range( 2, 24 );
					break;
				case 'year':
					$subscription_lengths[] = _x( '1 year', 'Subscription lengths. e.g. "For 1 year..."', 'storeengine' );
					$subscription_range     = range( 2, 5 );
					break;
			}

			foreach ( $subscription_range as $number ) {
				$subscription_range[ $number ] = self::get_subscription_period_strings( $number, $period );
			}

			// Add the possible range to all time range
			$subscription_lengths += $subscription_range;

			$subscription_ranges[ $period ] = $subscription_lengths;
		}

		return $subscription_ranges;
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the first renewal payment will be processed
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param Price $price The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @param string $timezone The timezone for the returned date, either 'site' for the site's timezone, or 'gmt'. Default, 'site'.
	 */
	public static function get_first_renewal_payment_date( Price $price, $from_date = '', string $timezone = 'gmt' ) {
		$first_renewal_timestamp = self::get_first_renewal_payment_time( $price, $from_date, $timezone );

		if ( $first_renewal_timestamp > 0 ) {
			$first_renewal_date = gmdate( 'Y-m-d H:i:s', $first_renewal_timestamp );
		} else {
			$first_renewal_date = 0;
		}

		return apply_filters( 'storeengine/subscription/product_first_renewal_payment_date', $first_renewal_date, $price, $from_date, $timezone );
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the first renewal payment will be processed
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param Price $price The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @param string $timezone The timezone for the returned date, either 'site' for the site's timezone, or 'gmt'. Default, 'site'.
	 */
	public static function get_first_renewal_payment_time( Price $price, $from_date = '', string $timezone = 'gmt' ) {
		if ( ! $price->is_subscription() ) {
			return 0;
		}

		$from_date_param  = $from_date;
		$billing_interval = $price->get_payment_duration();
		$billing_length   = 0;

		if ( $billing_interval !== $billing_length ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			$site_time_offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			// As CreateSubscription::add_time() calls wcs_add_months() which checks for last day of month, pass the site time
			$first_renewal_timestamp = CreateSubscription::add_time( $billing_interval, $price->get_payment_duration_type(), CreateSubscription::date_to_time( $from_date ) + $site_time_offset );
			if ( 'site' !== $timezone ) {
				$first_renewal_timestamp -= $site_time_offset;
			}
		} else {
			$first_renewal_timestamp = 0;
		}

		return apply_filters( 'storeengine/subscription/product_first_renewal_payment_time', $first_renewal_timestamp, $price, $from_date_param, $timezone );
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the subscription product will expire,
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param Price $price The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 */
	public static function get_expiration_date( Price $price, $from_date = '' ) {
		$subscription_length = 0;
		// @TODO implement subscription expiration.
		if ( $subscription_length > 0 ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			$expiration_date = gmdate( 'Y-m-d H:i:s', CreateSubscription::add_time( $subscription_length, $price->get_payment_duration_type(), CreateSubscription::date_to_time( $from_date ) ) );
		} else {
			$expiration_date = 0;
		}

		return apply_filters( 'storeengine/subscription/product_expiration_date', $expiration_date, $price, $from_date, $subscription_length );
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @return CartItem|false
	 */
	public static function cart_contains_renewal( ?Cart $cart = null ) {
		$contains_renewal = false;

		if ( ! $cart ) {
			$cart = \StoreEngine::init()->get_cart();
		}

		if ( $cart && ! $cart->has_items() ) {
			foreach ( $cart->get_cart_items() as $cart_item ) {
				if ( isset( $cart_item->subscription_renewal ) ) {
					$contains_renewal = $cart_item;
					break;
				}
			}
		}

		return apply_filters( 'storeengine/subscription/cart/contains_renewal', $contains_renewal );
	}

	/**
	 * Display a recurring cart's subtotal
	 *
	 * @access public
	 * @param Cart $cart The cart do print the subtotal html for.
	 */
	public static function cart_totals_subtotal_html( Cart $cart ) {
		$subtotal_html = self::prepare_cart_price_html( $cart->get_displayed_subtotal(), $cart );
		//$subtotal_html = wcs_cart_price_string( Formatting::price( $cart->get_displayed_subtotal() ), $cart );

		if ( $cart->get_subtotal_tax() > 0 ) {
			if ( $cart->display_prices_including_tax() && ! TaxUtil::prices_include_tax() ) {
				$subtotal_html .= ' <small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>';
			} elseif ( ! $cart->display_prices_including_tax() && TaxUtil::prices_include_tax() ) {
				$subtotal_html .= ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
			}
		}

		echo wp_kses_post( $subtotal_html );
	}

	/**
	 * Gets recurring total html including inc tax if needed.
	 *
	 * @param Cart $cart The cart to display the total for.
	 */
	public static function cart_totals_order_total_html( Cart $cart ) {
		$order_total_html           = '<strong>' . $cart->get_total() . '</strong> ';
		$tax_total_html             = '';
		$display_prices_include_tax = $cart->display_prices_including_tax();

		// If prices are tax inclusive, show taxes here
		if ( TaxUtil::is_tax_enabled() && $display_prices_include_tax ) {
			$tax_string_array = array();
			$cart_taxes       = $cart->get_tax_totals();

			if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
				foreach ( $cart_taxes as $tax ) {
					$tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
				}
			} elseif ( ! empty( $cart_taxes ) ) {
				$tax_string_array[] = sprintf( '%s %s', Formatting::price( $cart->get_taxes_total( true, true ) ), Countries::init()->tax_or_vat() );
			}

			if ( ! empty( $tax_string_array ) ) {
				// translators: placeholder is price string, denotes tax included in cart/order total
				$tax_total_html = '<small class="includes_tax"> ' . sprintf( _x( '(includes %s)', 'includes tax', 'storeengine' ), implode( ', ', $tax_string_array ) ) . '</small>';
			}
		}

		// Apply WooCommerce core filter
		$order_total_html = apply_filters( 'woocommerce_cart_totals_order_total_html', $order_total_html );

		$order_total_html = self::prepare_cart_price_html( $order_total_html, $cart ) . $tax_total_html;

		if ( 0 !== $cart->next_payment_date ) {
			$first_renewal_date = date_i18n( Formatting::date_format(), CreateSubscription::date_to_time( get_date_from_gmt( $cart->next_payment_date ) ) );
			// translators: placeholder is a date
			$order_total_html .= '<br><span class="first-payment-date"><small>' . sprintf( __( 'First renewal: %s', 'storeengine' ), $first_renewal_date ) . '</small></span>';
		}

		echo wp_kses_post( apply_filters( 'wcs_cart_totals_order_total_html', $order_total_html, $cart ) );
	}

	/**
	 * @param $recurring_amount
	 * @param Cart $cart
	 *
	 * @return string
	 *
	 * @see wcs_cart_price_string
	 */
	public static function prepare_cart_price_html( $recurring_amount, Cart $cart ): string {
		$item = $cart->get_cart_items();
		$item = reset( $item );

		return self::format_cart_price_html( [
			'recurring_amount'      => $recurring_amount,
			// Schedule details
			'subscription_interval' => $item->payment_duration,
			'subscription_period'   => $item->payment_duration_type,
			'subscription_length'   => 0,
		] );
	}

	/**
	 * Returns the total sign-up fee for all subscriptions in an order.
	 *
	 * Similar to WC_Subscription::get_sign_up_fee() except that it sums the sign-up fees for all subscriptions purchased in an order.
	 *
	 * @param Order|Subscription $order A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param int $product_id (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 *
	 * @return float The initial sign-up fee charged when the subscription product in the order was first purchased, if any.
	 * @throws StoreEngineException
	 */
	public static function get_sign_up_fee_from_order( $order, $product_id = '' ): float {
		$sign_up_fee = 0;

		foreach ( SubscriptionCollection::get_subscriptions_for_order( $order->get_id(), [ 'order_type' => 'parent' ] ) as $subscription ) {
			if ( empty( $product_id ) ) {
				$sign_up_fee += $subscription->get_sign_up_fee();
			} else {
				// We only want sign-up fees for certain product
				foreach ( $subscription->get_items() as $line_item ) {
					if ( $line_item->get_product_id() == $product_id || $line_item->get_variation_id() == $product_id ) {
						$sign_up_fee += $subscription->get_items_sign_up_fee( $line_item );
					}
				}
			}
		}

		return apply_filters( 'storeengine/subscription/sign_up_fee', $sign_up_fee, $order, $product_id );
	}

	/**
	 * Return a link for subscribers to change the status of their subscription, as specified with $status parameter
	 *
	 * @param Subscription $subscription A subscription's post ID
	 * @param string $status A subscription's post ID
	 * @param string $current_status A subscription's current status
	 *
	 * @return string
	 */
	public static function get_users_change_status_link( Subscription $subscription, string $status, string $current_status = '' ): string {
		if ( '' === $current_status ) {
			$current_status = $subscription->get_status();
		}

		$action_link = add_query_arg( [
			'subscription_id'        => $subscription->get_id(),
			'change_subscription_to' => $status,
		] );

		$action_link = wp_nonce_url( $action_link, $subscription->get_id() . $current_status );

		return apply_filters( 'storeengine/subscription/users_change_status_link', $action_link, $subscription->get_id(), $status );
	}

	/**
	 * Checks if a user can renew an active subscription early.
	 *
	 * @param Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object.
	 * @param int $user_id The ID of a user.
	 *
	 * @return bool Whether the user can renew a subscription early.
	 */
	public static function can_user_renew_early( Subscription $subscription, int $user_id = 0 ): bool {
		$user_id = ! empty( $user_id ) ? $user_id : get_current_user_id();
		$reason  = '';

		// Check for all the normal reasons a subscription can't be renewed early.
		if ( ! $subscription->has_status( array( 'active' ) ) ) {
			$reason = 'subscription_not_active';
		} elseif ( 0.0 === floatval( $subscription->get_total() ) ) {
			$reason = 'subscription_zero_total';
		} elseif ( $subscription->get_time( 'trial_end' ) > gmdate( 'U' ) ) {
			$reason = 'subscription_still_in_free_trial';
		} elseif ( ! $subscription->get_time( 'next_payment' ) ) {
			$reason = 'subscription_no_next_payment';
		} elseif ( ! $subscription->payment_method_supports( 'subscription_date_changes' ) ) {
			$reason = 'payment_method_not_supported';
		}

		// Make sure all line items still exist.
		foreach ( $subscription->get_line_product_items() as $line_item ) {
			if ( false === $line_item->get_product() ) {
				// @TODO validate variable product.
				$reason = 'line_item_no_longer_exists';
				break;
			}
		}

		// Non-empty $reason means we can't renew early.
		$can_renew_early = empty( $reason );

		/**
		 * Allow third-parties to filter whether the customer can renew a subscription early.
		 *
		 * @param bool            $can_renew_early Whether early renewal is permitted.
		 * @param Subscription $subscription    The subscription being renewed early.
		 * @param int             $user_id         The user's ID.
		 * @param string          $reason          The reason why the subscription cannot be renewed early. Empty
		 *                                         string if the subscription can be renewed early.
		 */
		return apply_filters( 'storeengine/subscriptions/can_user_renew_early', $can_renew_early, $subscription, $user_id, $reason );
	}

	/**
	 * Returns a URL for early renewal of a subscription.
	 *
	 * @param Subscription $subscription WC_Subscription ID, or instance of a WC_Subscription object.
	 *
	 * @return string The early renewal URL.
	 */
	public static function get_early_renewal_url( Subscription $subscription ): string {
		$url = add_query_arg( [
			'subscription' => $subscription->get_id(),
			'action'       => 'subscription_renewal_early',
		], Helper::get_dashboard_url() );

		/**
		 * Allow third-parties to filter the early renewal URL.
		 *
		 * @since 2.3.0
		 * @param string $url The early renewal URL.
		 * @param int    $subscription_id The ID of the subscription to renew to.
		 */
		return apply_filters( 'storeengine/subscription/get_early_renewal_url', $url, $subscription->get_id() ); // nosemgrep: audit.php.wp.security.xss.query-arg -- False positive. $url is escaped in the template and escaping URLs should be done at the point of output or usage.
	}

	/**
	 * Change the status of a subscription and show a notice to the user if there was an issue.
	 *
	 * @throws StoreEngineException
	 */
	public static function change_users_subscription( Subscription $subscription, $new_status ) {
		$changed = false;

		do_action( 'storeengine/subscription/before_customer_changed_to_' . $new_status, $subscription );

		switch ( $new_status ) {
			case 'active':
				if ( ! $subscription->needs_payment() ) {
					$subscription->update_status( $new_status );
					$subscription->add_order_note( _x( 'Subscription reactivated by the subscriber from their account page.', 'order note left on subscription after user action', 'storeengine' ) );
					// success -> _x( 'Your subscription has been reactivated.', 'Notice displayed to user confirming their action.', 'storeengine' )
					$changed = true;
				} else {
					throw new StoreEngineException(
						__( 'You can not reactivate that subscription until paying to renew it.', 'storeengine' ),
						'payment-required'
					);
				}
				break;
			case 'on-hold':
				throw new StoreEngineException(
					__( 'You can not put subscription on hold. Please contact us if needed.', 'storeengine' ),
					'user-plan-on-hold-not-supported'
				);
				break;
			case 'cancelled':
				$subscription->cancel_order();
				$subscription->add_order_note( _x( 'Subscription cancelled by the subscriber from their account page.', 'order note left on subscription after user action', 'storeengine' ) );
				// success -> _x( 'Your subscription has been cancelled.', 'Notice displayed to user confirming their action.', 'storeengine' )
				$changed = true;
				break;
		}

		if ( $changed ) {
			do_action( 'storeengine/subscription/customer_changed_to_' . $new_status, $subscription );
		}
	}

	public static function get_account_subscription_actions( Subscription $subscription, int $user_id = null ): array {
		$actions = [
			'view' => [
				'url'        => $subscription->get_view_order_url(),
				'icon'       => 'eye',
				/* translators: %s: order number */
				'aria-label' => sprintf( __( 'View subscription %s', 'storeengine' ),
				$subscription->get_order_number() ),
			],
		];

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id === $subscription->get_user_id() ) {
			$current_status = $subscription->get_status();

			if ( $subscription->can_be_updated_to( 'active' ) && ! $subscription->needs_payment() ) {
				$actions['reactivate'] = [
					'url'        => self::get_users_change_status_link( $subscription, 'active', $current_status ),
					'name'       => __( 'Reactivate Plan', 'storeengine' ),
					/* translators: %s: subscription id */
					'aria-label' => sprintf( __( 'Reactivate subscription %s', 'storeengine' ), $subscription->get_order_number() ),
					'data'       => [
						'confirm' => __( 'Are you sure you want to reactivate this subscription?', 'storeengine' ),
					],
				];
			}

			// Show button for subscriptions which can be cancelled and which may actually require cancellation (i.e. has a future payment)
			$next_payment = $subscription->get_next_payment_date() ? $subscription->get_next_payment_date()->getTimestamp() : 0;

			if ( $subscription->can_be_updated_to( 'cancelled' ) && ( ! $subscription->is_one_payment() && ( $subscription->has_status( 'on_hold' ) && empty( $next_payment ) ) || $next_payment > 0 ) ) {
				$actions['cancel'] = [
					'url'        => self::get_users_change_status_link( $subscription, 'cancelled', $current_status ),
					'name'       => _x( 'Cancel Plan', 'an action on a subscription', 'storeengine' ),
					/* translators: %s: subscription id */
					'aria-label' => sprintf( __( 'Cancel subscription %s', 'storeengine' ), $subscription->get_order_number() ),
					'data'       => [
						'confirm-action' => __( 'Are you sure you want to cancel this subscription?', 'storeengine' ),
					],
				];
			}

			if ( $subscription->can_be_updated_to( 'new-payment-method' ) ) {
				if ( $subscription->has_payment_gateway() && self::get_payment_gateway_by_order( $subscription )->supports( 'subscriptions' ) ) {
					$action_name = _x( 'Change payment method', 'label on button, imperative', 'storeengine' );
				} else {
					$action_name = _x( 'Add payment method', 'label on button, imperative', 'storeengine' );
				}

				$actions['change_payment_method'] = [
					'url'        => wp_nonce_url( add_query_arg( [ 'change_payment_method' => $subscription->get_id() ], $subscription->get_checkout_payment_url() ) ),
					'name'       => $action_name,
					/* translators: %1$s: Action name, %2$s subscription id */
					'aria-label' => sprintf( __( '%1$s for subscription %2$s', 'storeengine' ), $action_name, $subscription->get_order_number() ),
				];
			}

			if ( self::can_user_renew_early( $subscription ) && Helper::get_payment_gateways()->one_gateway_supports( 'subscription_date_changes' ) && $subscription->has_status( 'active' ) ) {
				$actions['subscription_renewal_early'] = array(
					'url'        => self::get_early_renewal_url( $subscription ),
					'name'       => __( 'Renew Now', 'storeengine' ),
					/* translators: %s: subscription id */
					'aria-label' => sprintf( __( 'Renew subscription %s', 'storeengine' ), $subscription->get_order_number() ),
				);
			}

			if ( $subscription->needs_shipping_address() && $subscription->has_status( [ 'active', 'on_hold' ] ) ) {
				// @TODO handle the address form and handle the submit request.
				/**
				 * @see WC_Subscriptions_Addresses::maybe_restrict_edit_address_endpoint
				 */
				$actions['change_address'] = [
					'url'        => esc_url( add_query_arg( [ 'subscription' => $subscription->get_id() ], Helper::get_endpoint_url( 'edit-address', 'shipping' ) ) ),
					'name'       => __( 'Change Address', 'storeengine' ),
					/* translators: %s: subscription id */
					'aria-label' => sprintf( __( 'Change subscription %s address', 'storeengine' ), $subscription->get_order_number() ),
				];
			}

			// @TODO handle form request & add necessary hooks in templates & add form fields.
			unset( $actions['change_payment_method'], $actions['subscription_renewal_early'], $actions['change_address'] );
		}

		return apply_filters( 'storeengine/dashboard/subscription/actions', $actions, $subscription );
	}
}

// End of file utils.php
