<?php

namespace StoreEngine\Addons\Subscription\Events;

use StoreEngine\Classes\{AbstractOrder, Order, OrderStatus\OrderStatus};
use StoreEngine\Utils\Constants;
use StoreEngine\Addons\Subscription\Classes\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renewal {

	public static function init(): void {
		$self = new self();
		add_action( 'storeengine/subscription/renewal', [ $self, 'update_subscription' ], 10, 3 );
	}

	public function update_subscription( int $id ): void {
		$subscription = Subscription::get_subscription( $id );
		$subscription->set_status( Constants::SUBSCRIPTION_STATUS_EXPIRED );

		$order = $this->create_new_order( $subscription );
		$subscription->set_parent_order_id( $order->get_id() );

		$related_orders = $subscription->get_related_order_ids();
		if ( ! in_array( $order->get_id(), $related_orders, true ) ) {
			$related_orders[] = $order->get_id();
			$subscription->set_related_order_ids( $related_orders );
		}

		$subscription->save();
	}

	public function create_new_order( Subscription $subscription ): Order {
		do_action( 'storeengine/api/before_create_renewal_order', $subscription );

		$order = new Order();
		$order->set_status( OrderStatus::PAYMENT_PENDING );
		$order->set_currency( $subscription->get_currency() );
		$order->set_prices_include_tax( $subscription->get_prices_include_tax() );
		$order->set_discount_tax( $subscription->get_discount_tax() );
		$order->set_shipping_tax( $subscription->get_shipping_tax() );
		$order->set_cart_tax( $subscription->get_cart_tax() );
		$order->set_shipping_tax_amount( $subscription->get_shipping_tax_amount() );
		$order->set_discount_tax_amount( $subscription->get_discount_tax_amount() );
		$order->set_date_created_gmt( $subscription->get_date_created_gmt() );
		$order->set_customer_id( $subscription->get_customer_id() );
		$order->set_payment_method( $subscription->get_payment_method() );
		$order->set_payment_method_title( $subscription->get_payment_method_title() );
		$order->set_transaction_id( $subscription->get_transaction_id() );
		$order->set_ip_address( $subscription->get_ip_address() );
		$order->set_user_agent( $subscription->get_user_agent() );
		$order->set_created_via( $subscription->get_created_via() );
		$order->set_customer_note( $subscription->get_customer_note() );
		$order->set_date_completed_gmt( $subscription->get_date_completed_gmt() );
		$order->set_date_paid_gmt( $subscription->get_date_paid_gmt() );
		$order->set_cart_hash( $subscription->get_cart_hash() );
		$order->set_hash( $subscription->get_hash() );
		$order->set_order_stock_reduced( $subscription->get_order_stock_reduced() );
		$order->set_download_permissions_granted( $subscription->get_download_permissions_granted() );
		$order->set_new_order_email_sent( $subscription->get_new_order_email_sent() );
		$order->set_recorded_sales( $subscription->get_recorded_sales() );
		$order->set_total_amount( $subscription->get_total_amount() );

		// set billing addr
		$order->set_billing_first_name( $subscription->get_billing_first_name() );
		$order->set_billing_last_name( $subscription->get_billing_last_name() );
		$order->set_billing_country( $subscription->get_billing_country() ?? '-' );
		$order->set_billing_email( $subscription->get_billing_email() );
		$order->set_billing_phone( $subscription->get_billing_phone() );
		$order->set_billing_address_1( $subscription->get_billing_address_1() );
		$order->set_billing_address_2( $subscription->get_billing_address_2() );
		$order->set_billing_city( $subscription->get_billing_city() );
		$order->set_billing_state( $subscription->get_billing_state() );
		$order->set_billing_postcode( $subscription->get_billing_postcode() );

		// set shipping addr
		$order->set_shipping_first_name( $subscription->get_shipping_first_name() );
		$order->set_shipping_last_name( $subscription->get_shipping_last_name() );
		$order->set_shipping_company( $subscription->get_shipping_company() );
		$order->set_shipping_address_1( $subscription->get_shipping_address_1() );
		$order->set_shipping_address_2( $subscription->get_shipping_address_2() );
		$order->set_shipping_city( $subscription->get_shipping_city() );
		$order->set_shipping_state( $subscription->get_shipping_state() );
		$order->set_shipping_postcode( $subscription->get_shipping_postcode() );
		$order->set_shipping_country( $subscription->get_shipping_country() );
		$order->set_shipping_phone( $subscription->get_shipping_phone() );
		$order->set_shipping_email( $subscription->get_billing_email() );

		// set renewal relation meta data.
		$order->add_meta_data( '_subscription_renewal', $subscription->get_id() );

		// add product to subs
		foreach ( $subscription->get_items() as $product ) {
			$order->add_product( $product->get_price_id(), $product->get_quantity() );
		}

		$order->calculate();

		// save
		$order->save();

		do_action( 'storeengine/api/after_create_renewal_order', $order );

		return $order;
	}
}
