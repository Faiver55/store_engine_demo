<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Cache\OrderCache;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\Order\OrderItemFee;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\Order\OrderItemShipping;
use StoreEngine\Classes\Order\OrderItemTax;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\Tax;
use StoreEngine\Classes\Cart;
use StoreEngine\models\Order as OrderModel;
use StoreEngine\Models\Price;
use StoreEngine\Shipping\ShippingRate;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use StoreEngine\Utils\Template;

class Checkout extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_states'             => [
				'callback'             => [ $this, 'get_states' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'cc' => 'string',
				],
			],
			'update_checkout'        => [
				'callback'             => [ $this, 'update_checkout' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'user_email'               => 'email',
					'shipping_method'          => 'string',
					'same_as_shipping'         => 'string',
					// Billing address.
					'billing_first_name'       => 'string',
					'billing_last_name'        => 'string',
					'billing_company'          => 'string',
					'billing_address_1'        => 'string',
					'billing_address_2'        => 'string',
					'billing_city'             => 'string',
					'billing_state'            => 'string',
					'billing_postcode'         => 'string',
					'billing_country'          => 'string',
					'billing_email'            => 'string',
					'billing_phone'            => 'string',
					// Shipping address.
					'shipping_first_name'      => 'string',
					'shipping_last_name'       => 'string',
					'shipping_phone'           => 'string',
					'shipping_address_1'       => 'string',
					'shipping_address_2'       => 'string',
					'shipping_country'         => 'string',
					'shipping_city'            => 'string',
					'shipping_state'           => 'string',
					'shipping_postal_code'     => 'string',
					// Mics.
					'payment_method'           => 'string',
					'transaction_id'           => 'string',
					'order_note'               => 'string',
					'stripe_payment_intent_id' => 'string',
				],
			],
			'refresh_cart'           => [
				'callback'             => [ $this, 'refresh_cart' ],
				'allow_visitor_action' => true,
			],
			'place_order'            => [
				'callback'             => [ $this, 'place_order' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'user_email'               => 'email',
					'shipping_method'          => 'string',
					'same_as_shipping'         => 'string',
					// Billing address.
					'shipping_first_name'      => 'string',
					'shipping_last_name'       => 'string',
					'shipping_phone'           => 'string',
					'shipping_address_1'       => 'string',
					'shipping_address_2'       => 'string',
					'shipping_country'         => 'string',
					'shipping_city'            => 'string',
					'shipping_state'           => 'string',
					'shipping_postal_code'     => 'string',
					// Shipping address.
					'billing_first_name'       => 'string',
					'billing_last_name'        => 'string',
					'billing_company'          => 'string',
					'billing_address_1'        => 'string',
					'billing_address_2'        => 'string',
					'billing_city'             => 'string',
					'billing_state'            => 'string',
					'billing_postcode'         => 'string',
					'billing_country'          => 'string',
					'billing_email'            => 'string',
					'billing_phone'            => 'string',
					// Mics.
					'payment_method'           => 'string',
					'transaction_id'           => 'string',
					'order_note'               => 'string',
					'stripe_payment_intent_id' => 'string',
				],
			],
			'pay_order'              => [
				'callback' => [ $this, 'pay_order' ],
				'fields'   => [
					'order_id'       => 'integer',
					'payment_method' => 'string',
				],
			],
			'change_subscription'    => [
				'callback'             => [ $this, 'change_subscription' ],
				'allow_visitor_action' => false,
				'fields'               => [
					'order_id'         => 'int',
					'product_id'       => 'int',
					'current_price_id' => 'int',
					'upgrade_price_id' => 'int',
				],
			],
			'refund'                 => [
				'callback'             => [ $this, 'refund' ],
				'allow_visitor_action' => false,
				'capability'           => 'manage_options',
				'fields'               => [
					'order_id'               => 'int',
					'refund_type'            => 'string',
					'refund_amount'          => 'float',
					'refund_reason'          => 'string',
					'api_refund'             => 'boolean',
					'refunded_amount'        => 'float',
					'line_item_qtys'         => 'string',
					'line_item_totals'       => 'string',
					'line_item_tax_totals'   => 'string',
					'restock_refunded_items' => 'string',
				],
			],
			'direct_checkout'        => [
				'callback'             => [ $this, 'direct_checkout' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'quantity'     => 'integer',
					'product_id'   => 'integer',
					'price_id'     => 'integer',
					'variation_id' => 'integer',
				],
			],
			'country_to_state_lists' => [
				'callback'             => [ $this, 'country_to_state_lists' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'country_code' => 'string',
				],
			],
		];
	}

	protected function get_states( $payload ) {
		if ( empty( $payload['cc'] ) ) {
			wp_send_json_error( esc_html__( 'Country code is required.', 'storeengine' ) );
		}

		$cc     = strtoupper( $payload['cc'] );
		$states = Countries::init()->get_states( $cc );

		wp_send_json_success( [
			'cc'     => $cc,
			'states' => $states ? $states : false,
		] );
	}

	/**
	 * Set checkout data.
	 *
	 * @param Order $order
	 * @param array $data
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function set_checkout_data( Order $order, array $data ): void {
		OrderCache::delete_draft_order();

		$need_shipping = StoreEngine::init()->get_cart()->needs_shipping();
		if ( $need_shipping && isset( $data['same_as_shipping'] ) ) {
			$data['billing_first_name'] = $data['shipping_first_name'] ?? '';
			$data['billing_last_name']  = $data['shipping_last_name'] ?? '';
			$data['billing_address_1']  = $data['shipping_address_1'] ?? '';
			$data['billing_address_2']  = $data['shipping_address_2'] ?? '';
			$data['billing_city']       = $data['shipping_city'] ?? '';
			$data['billing_state']      = $data['shipping_state'] ?? '';
			$data['billing_postcode']   = $data['shipping_postal_code'] ?? '';
			$data['billing_country']    = $data['shipping_country'] ?? '';
			$data['billing_email']      = $data['user_email'] ?? '';
			$data['billing_phone']      = $data['shipping_phone'] ?? '';
		}

		// Billing data.
		$order->set_billing_first_name( $data['billing_first_name'] ?? '' );
		$order->set_billing_last_name( $data['billing_last_name'] ?? '' );
		$order->set_billing_address_1( $data['billing_address_1'] ?? '' );
		$order->set_billing_address_2( $data['billing_address_2'] ?? '' );
		$order->set_billing_country( $data['billing_country'] ?? '' );
		$order->set_billing_state( $data['billing_state'] ?? '' );
		$order->set_billing_city( $data['billing_city'] ?? '' );
		$order->set_billing_postcode( $data['billing_postcode'] ?? '' );
		$order->set_billing_email( $data['billing_email'] ?? '' );
		$order->set_billing_phone( $data['billing_phone'] ?? '' );

		// Set order email.
		$order->set_order_email( $data['user_email'] ?? '' );

		if ( ! empty( $data['shipping_method'] ) ) {
			Helper::cart()->set_meta( 'chosen_shipping_methods', [ $data['shipping_method'] ] );
		} elseif ( $need_shipping ) {
			Helper::cart()->set_meta( 'chosen_shipping_methods', [] );
		}

		if ( ! $need_shipping ) {
			$order->set_shipping_first_name( $data['billing_first_name'] ?? '' );
			$order->set_shipping_last_name( $data['billing_last_name'] ?? '' );
			$order->set_shipping_address_1( $data['billing_address_1'] ?? '' );
			$order->set_shipping_address_2( $data['billing_address_2'] ?? '' );
			$order->set_shipping_country( $data['billing_country'] ?? '' );
			$order->set_shipping_state( $data['billing_state'] ?? '' );
			$order->set_shipping_city( $data['billing_city'] ?? '' );
			$order->set_shipping_postcode( $data['billing_postcode'] ?? '' );
			$order->set_shipping_email( $data['billing_email'] ?? '' );
			$order->set_shipping_phone( $data['billing_phone'] ?? '' );
		} else {
			$order->set_shipping_first_name( $data['shipping_first_name'] ?? '' );
			$order->set_shipping_last_name( $data['shipping_last_name'] ?? '' );
			$order->set_shipping_address_1( $data['shipping_address_1'] ?? '' );
			$order->set_shipping_address_2( $data['shipping_address_2'] ?? '' );
			$order->set_shipping_country( $data['shipping_country'] ?? '' );
			$order->set_shipping_state( $data['shipping_state'] ?? '' );
			$order->set_shipping_city( $data['shipping_city'] ?? '' );
			$order->set_shipping_postcode( $data['shipping_postal_code'] ?? '' );
			$order->set_shipping_email( $data['shipping_email'] ?? '' );
			$order->set_shipping_phone( $data['shipping_phone'] ?? '' );
		}


		if ( isset( $data['payment_method'] ) ) {
			$order->set_payment_method( Helper::get_payment_gateway( $data['payment_method'] ) );
		}

		// set totals.
		$order->set_shipping_total( Helper::cart()->get_shipping_total() );
		$order->set_shipping_tax( Helper::cart()->get_shipping_tax() );
		$order->set_discount_total( Helper::cart()->get_discount_total() );
		$order->set_discount_tax( Helper::cart()->get_discount_tax() );
		$order->set_cart_tax( Helper::cart()->get_cart_contents_tax() + Helper::cart()->get_fee_tax() );

		$order->set_total( Helper::cart()->get_total( 'edit' ) );

		$order->save();
	}

	protected function update_checkout( $data ) {
		$data        = apply_filters( 'storeengine/frontend/checkout/before_update_draft_order', $data );
		$draft_order = Helper::get_recent_draft_order();

		$old_shipping_method = Helper::cart()->get_meta( 'chosen_shipping_methods' );
		$old_shipping_method = $old_shipping_method ? reset( $old_shipping_method ) : false;

		$this->set_checkout_data( $draft_order, $data );
		$draft_order->set_cart_hash( Helper::cart()->get_cart_hash() );
		$draft_order->save();

		// storeengine/before_update_draft_order -> $order_data
		// storeengine/after_update_order -> $order_result

		do_action( 'storeengine/frontend/checkout/update_checkout', $draft_order );
		$data['order'] = $draft_order->get_id();

		$response = [
			'order'   => $data,
			'massage' => esc_html__( 'Order updated successfully.', 'storeengine' ),
			'hash'    => $draft_order->get_hash(),
		];

		if ( TaxUtil::is_tax_enabled() || StoreEngine\Utils\ShippingUtils::is_shipping_enabled() ) {
			$keys        = [
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',

				'shipping_city',
				'shipping_state',
				'shipping_postal_code',
				'shipping_country',
			];
			$new_address = [];
			$old_address = [];
			$customer    = StoreEngine::init()->get_cart()->get_customer();
			foreach ( $keys as $key ) {
				$new_address[] = $data[ $key ] ?? '';
				$old_address[] = $customer->{'get_' . $key}( 'edit' ) ?? '';
			}

			$response['refresh'] = md5( wp_json_encode( $new_address ) ) !== md5( wp_json_encode( $old_address ) );

			if ( $old_shipping_method && ! empty( $data['shipping_method'] ) && $data['shipping_method'] !== $old_shipping_method ) {
				$response['refresh'] = true;
			}
		}

		wp_send_json_success( $response );
	}

	protected function refresh_cart() {
		$shortcodes = apply_filters( 'storeengine/cart/refresh_shortcodes', [
			'storeengine-order-summary-shortcode'        => '[storeengine_order_summary]',
			'storeengine-cart-sub-total-table-shortcode' => '[storeengine_cart_sub_total_table]',
		] );

		wp_send_json_success( array_map( fn( $shortcode ) => do_shortcode( $shortcode ), $shortcodes ) );
	}

	protected function prepare_purchase_items_data(): array {
		$cart = Helper::cart();
		do_action( 'storeengine/frontend/cart/check_items' );

		$purchase_items = [];
		foreach ( Helper::cart()->get_cart_items() as $cart_item ) {
			$purchase_items[] = [
				'product_id'          => $cart_item['product_id'],
				'variation_id'        => 0,
				'price_id'            => $cart_item['price_id'],
				'price'               => $cart_item['price'],
				'product_qty'         => $cart_item['quantity'],
				'coupon_amount'       => $cart->get_total_discount(),
				'tax_amount'          => $cart->get_taxes_total( true, false ),
				'shipping_amount'     => 0,
				'shipping_tax_amount' => 0,
			];
		}

		return [ $cart, $purchase_items ];
	}

	/**
	 * Prepare order data.
	 *
	 * @param array $data Data.
	 * @param array $purchase_items Purchase Items.
	 *
	 * @return array
	 */
	protected function prepare_order_data( array $data, array $purchase_items ): array {
		return [
			'status'                 => 'draft',
			'currency'               => Helper::get_settings( 'store_currency', 'USD' ),
			'type'                   => Helper::cart()->has_subscription_product() ? 'subscription' : 'onetime',
			'tax_amount'             => Helper::cart()->get_taxes_total( true, false ),
			'total_amount'           => Helper::cart()->get_total( 'draft_order' ),
			'customer_id'            => get_current_user_id(),
			'billing_email'          => $data['billing_email'],
			'payment_method'         => $data['payment_method'] ?? '',
			'payment_method_title'   => $data['payment_method'] ?? '',
			'customer_note'          => $data['order_note'] ?? '',
			'transaction_id'         => null,
			'purchase_items'         => $purchase_items,
			'order_billing_address'  => [
				'first_name' => $data['billing_first_name'] ?? '',
				'last_name'  => $data['billing_last_name'] ?? '',
				'company'    => $data['billing_company'] ?? '',
				'address_1'  => $data['billing_address_1'] ?? '',
				'address_2'  => $data['billing_address_2'] ?? '',
				'city'       => $data['billing_city'] ?? '',
				'state'      => $data['billing_state'] ?? '',
				'postcode'   => $data['billing_postcode'] ?? '',
				'country'    => $data['billing_country'] ?? '',
				'email'      => $data['billing_email'] ?? '',
				'phone'      => $data['billing_phone'] ?? '',
			],
			'order_shipping_address' => [
				'first_name' => $data['billing_first_name'] ?? '',
				'last_name'  => $data['billing_last_name'] ?? '',
				'company'    => $data['billing_company'] ?? '',
				'address_1'  => $data['billing_address_1'] ?? '',
				'address_2'  => $data['billing_address_2'] ?? '',
				'city'       => $data['billing_city'] ?? '',
				'state'      => $data['billing_state'] ?? '',
				'postcode'   => $data['billing_postcode'] ?? '',
				'country'    => $data['billing_country'] ?? '',
				'email'      => $data['billing_email'] ?? '',
				'phone'      => $data['billing_phone'] ?? '',
			],
		];
	}

	protected function place_order( $payload ) {
		try {
			$this->validate_checkout_data( $payload );

			$cart = Helper::cart();
			if ( $cart->needs_shipping() ) {
				if ( empty( $payload['shipping_method'] ) && empty( $cart->get_meta( 'chosen_shipping_methods' ) ) ) {
					wp_send_json_error( [
						'message' => esc_html__( 'Sorry, this order requires a shipping option.', 'storeengine' ),
					] );
				}
			}

			$order = Helper::get_recent_draft_order( get_current_user_id(), null, false );
			if ( ! $order ) {
				wp_send_json_error( [
					'message' => esc_html__( 'Order not found.', 'storeengine' ),
				] );
			}

			do_action( 'storeengine/frontend/checkout/before_place_order', $order );

			// @TODO Create customer first if necessary...

			// Prepare order data.
			$order->clear_items();

			$order->set_currency( Formatting::get_currency() );
			$order->set_order_placed_date_gmt();
			$order->set_order_placed_date();
			$this->set_checkout_data( $order, $payload );
			$this->subscribe_to_email( $payload );

			$gateway = Helper::get_payment_gateway( $payload['payment_method'] );

			if ( ! $gateway ) {
				wp_send_json_error( esc_html__( 'Invalid payment gateway.', 'storeengine' ) );
			}

			$order->set_customer_id( apply_filters( 'storeengine/frontend/checkout/customer_id', get_current_user_id() ) );
			$order->set_prices_include_tax( TaxUtil::prices_include_tax() );

			$order->set_ip_address( Helper::get_user_ip() );
			$order->set_user_agent( Helper::get_user_agent() );
			$order->set_customer_note( $payload['order_note'] ?? '' );
			$order->set_payment_method( $gateway );

			// Order metadata
			$order->update_meta_data( 'is_vat_exempt', StoreEngine::init()->customer->get_is_vat_exempt() );

			// Order items
			self::add_product( $order, Helper::cart() );
			self::add_fee( $order, Helper::cart() );
			self::add_shipping( $order, Helper::cart() );
			self::apply_coupon( $order, Helper::cart() );
			self::add_tax( $order, Helper::cart() );

			// Add cart hash.
			$order->set_cart_hash( Helper::cart()->get_cart_hash() );

			$customer = $this->create_or_update_customer( $order );

			if ( is_wp_error( $customer ) ) {
				wp_send_json_error( [
					'message' => $customer->get_error_message(),
					'code'    => $customer->get_error_code(),
					'trace'   => null,
				] );
			}

			$order_context = new OrderContext( $order->get_status() );

			// update order status to processing
			$order_context->proceed_to_next_status( 'order_placed', $order );

			// Save order data before processing payment as payment method needs order id.
			$order->save();
			$order->read( true );

			// Get coupons before cart gets cleared.
			$coupons = Helper::cart()->get_coupons();

			// Let other addon/plugin handle things before payment and redirects.
			do_action( 'storeengine/checkout/order_processed', $order, $payload );

			$result = $gateway->process_payment( $order );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				] );
			}

			do_action( 'storeengine/checkout/after_place_order', $order, $payload );

			$this->update_coupon_usage( $coupons, $order );

			$result['order_id'] = $order->get_id();

			// Redirect to success/confirmation/payment page.
			if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
				$result = apply_filters( 'storeengine/checkout/payment_successful', $result, $order->get_id() );
			}

			Helper::cart()->clear_cart();

			wp_send_json_success( $result );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage(),
				'code'    => $e->get_wp_error_code(),
			] );
		}
	}

	public function pay_order( array $payload ) {
		if ( ! isset( $payload['order_id'] ) ) {
			wp_send_json_error( esc_html__( 'Order ID is required.', 'storeengine' ) );
		}

		if ( ! isset( $payload['payment_method'] ) ) {
			wp_send_json_error( esc_html__( 'Payment method is required.', 'storeengine' ) );
		}

		$order = Helper::get_order( $payload['order_id'] );
		if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
			wp_send_json_error( esc_html__( 'Order not found.', 'storeengine' ) );
		}

		if ( 'pending_payment' !== $order->get_status() ) {
			wp_send_json_error( esc_html__( 'Order isn\'t available for payment!', 'storeengine' ) );
		}

		$gateway = Helper::get_payment_gateway( $payload['payment_method'] );

		if ( ! $gateway ) {
			wp_send_json_error( esc_html__( 'Invalid payment gateway.', 'storeengine' ) );
		}

		$result = $gateway->process_payment( $order );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
				'trace'   => null,
			] );
		}

		$order->set_payment_method( $gateway );
		$order->save();

		do_action( 'storeengine/checkout/after_pay_order', $order );

		$result['order_id'] = $order->get_id();

		// Redirect to success/confirmation/payment page.
		if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
			$result = apply_filters( 'storeengine/frontend/checkout/payment_successful_result', $result, $order->get_id() );
		}

		if ( empty( $result['redirect'] ) ) {
			$result['redirect'] = $order->get_checkout_order_received_url();
		}

		wp_send_json_success( $result );
	}

	/**
	 * @param Order $order
	 * @param Cart $cart
	 *
	 * @throws StoreEngineException
	 */
	public static function add_product( Order &$order, Cart $cart ) {
		$digital_auto_completes = [];
		foreach ( $cart->get_cart_items() as $values ) {
			$item  = new OrderItemProduct();
			$price = new StoreEngine\Classes\Price( $values->price_id );

			if ( ! $price->get_id() ) {
				throw new StoreEngineException( __( 'Price not found!', 'storeengine' ), 'not_found_price' );
			}

			if ( ! $values->name ) {
				$values->name = $price->get_product_title();
			}

			$item->set_props( [
				// Product
				'name'                  => $values->name,
				'product_id'            => $values->product_id ?? $price->get_product_id(),
				'variation_id'          => $values->variation_id ?? 0,
				'variation'             => $values->variation ?? [],
				// Type
				'product_type'          => $price->get_product_type() ?? '',
				'shipping_type'         => $price->get_shipping_type() ?? '',
				'digital_auto_complete' => $price->get_digital_auto_complete(),
				'price_type'            => $values->price_type ?? $price->get_price_type(),
				// Price
				'price_id'              => $price->get_id(),
				'price_name'            => $price->get_name(),
				'price'                 => $price->get_price(),
				// cart & tax
				'quantity'              => absint( $values->quantity ),
				'tax_class'             => $values->tax_class ?? '',
				'subtotal'              => $values->line_subtotal ?? 0,
				'total'                 => $values->line_total ?? 0,
				'subtotal_tax'          => $values->line_subtotal_tax ?? 0,
				'total_tax'             => $values->line_tax ?? 0,
				'taxes'                 => $values->line_tax_data ?? [],
			] );

			$digital_auto_completes[] = $price->get_shipping_type() && $price->get_digital_auto_complete();

			$item->add_meta_data( '_price_settings', $price->get_settings(), true );

			foreach ( $price->get_settings() as $field => $value ) {
				if ( method_exists( $item, "set_{$field}" ) ) {
					$item->{"set_{$field}"}( $value );
				}
			}

			$item->set_backorder_meta();

			$variation_id = $values->variation_id ?? 0;

			if ( 0 < $variation_id ) {
				$variation = Helper::get_product_variation( $variation_id );
				if ( ! $variation ) {
					throw new StoreEngineException( __( 'Invalid variation selected!', 'storeengine' ), 'invalid-product-variation' );
				}

				$item->add_meta_data( '_variation_price', (float) $variation->get_price(), true );
				$item->set_price( $price->get_price() + (float) $variation->get_price() );

				foreach ( $variation->get_attributes() as $attribute ) {
					$item->add_meta_data( $attribute->taxonomy, $attribute->slug, true );
				}
			}

			/**
			 * @param OrderItemProduct $item
			 * @param StoreEngine\Classes\CartItem $values
			 * @param Order $order
			 */
			do_action( 'storeengine/order/create_order_line_item', $item, $values, $order );

			$order->add_item( $item );
		}

		if ( ! empty( $digital_auto_completes ) ) {
			$order->set_auto_complete_digital_order( Helper::array_every( $digital_auto_completes, fn( $item ) => true === $item ) );
		}
	}

	public static function add_fee( Order &$order, Cart $cart ) {
		foreach ( $cart->get_fees() as $fee ) {
			$item = new OrderItemFee();
			$fee  = (object) $fee;

			$item->set_props( [
				'name'      => $fee->name,
				'tax_class' => $fee->taxable ? $fee->tax_class : '',
				'amount'    => $fee->amount,
				'total'     => $fee->total,
				'total_tax' => $fee->tax,
				'taxes'     => [ 'total' => $fee->tax_data ],
			] );

			/**
			 * Fires after creating fee on Order.
			 *
			 * @param OrderItemFee $item ItemFee object.
			 * @param object $fee Fee data.
			 * @param Order $order Order instance.
			 */
			do_action( 'storeengine/frontend/checkout/order/create_order_fee_item', $item, $fee, $order );

			$order->add_item( $item );
		}
	}

	public static function add_tax( Order &$order, Cart $cart ) {
		foreach ( array_keys( $cart->get_cart_contents_taxes() + $cart->get_shipping_taxes() + $cart->get_fee_taxes() ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'storeengine/frontend/cart/remove_taxes_zero_rate_id', 'zero-rated' ) === $tax_rate_id ) {
				return;
			}

			$item = new OrderItemTax();
			$item->set_props( [
				'rate_id'            => $tax_rate_id,
				'order_id'           => $order->get_id(),
				'tax_total'          => $cart->get_tax_amount( $tax_rate_id ),
				'shipping_tax_total' => $cart->get_shipping_tax_amount( $tax_rate_id ),
				'rate_code'          => Tax::get_rate_code( $tax_rate_id ),
				'label'              => Tax::get_rate_label( $tax_rate_id ),
				'compound'           => Tax::is_compound( $tax_rate_id ),
				'rate_percent'       => Tax::get_rate_percent_value( $tax_rate_id ),
			] );

			/**
			 * Fires after adding tax on Order.
			 *
			 * @param OrderItemTax $item ItemTax object.
			 * @param int $tax_rate_id Tax rate id.
			 * @param Order $order Order instance.
			 */
			do_action( 'storeengine/frontend/checkout/order/create_order_tax_item', $item, $tax_rate_id, $order );

			$order->add_item( $item );
		}
	}

	public static function add_shipping( Order &$order, Cart $cart ) {
		if ( ! $cart->needs_shipping() ) {
			return;
		}

		$shipping_rate = $cart->get_shipping_methods();
		if ( empty( $shipping_rate ) ) {
			return;
		}

		/** @var ShippingRate $shipping_rate */
		$shipping_rate = $shipping_rate[0];

		$shipping_item = new OrderItemShipping();

		$shipping_item->set_props( [
			'method_title' => $shipping_rate->get_label(),
			'method_id'    => $shipping_rate->get_method_id(),
			'instance_id'  => $shipping_rate->get_instance_id(),
			'total'        => Formatting::format_decimal( $shipping_rate->get_cost() ),
			'taxes'        => [ 'total' => $shipping_rate->get_taxes() ],
			'tax_status'   => $shipping_rate->get_tax_status(),
		] );

		foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
			$shipping_item->add_meta_data( $key, $value, true );
		}

		/**
		 * Fires after adding shipping on Order.
		 *
		 * @param OrderItemShipping $shipping_item Shipping item.
		 * @param ShippingRate $shipping_rate Shipping rate from cart.
		 * @param Order $order Order instance.
		 */
		do_action( 'storeengine/frontend/checkout/order/create_order_shipping_item', $shipping_item, $shipping_rate, $order );

		$order->add_item( $shipping_item );
	}

	public static function apply_coupon( Order &$order, Cart $cart ) {
		foreach ( $cart->get_coupons() as $coupon ) {
			$item = new OrderItemCoupon();

			$item->set_props( [
				'code'         => $coupon->get_code(),
				'discount'     => $cart->get_coupon_discount_amount( $coupon->get_code(), $cart->display_prices_including_tax() ),
				'discount_tax' => $cart->get_coupon_discount_tax_amount( $coupon->get_code() ),
			] );

			do_action( 'storeengine/frontend/checkout/order/create_order_coupon_item', $item, $coupon, $order );

			$order->add_item( $item );
		}
	}

	protected function create_or_update_customer( Order $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			$customer_id = $this->create_customer( $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_order_email() );

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			} else {
				$order->set_customer_id( $customer_id );
				$order->save();
			}
		}

		$customer = Helper::get_customer( $customer_id );

		// Save Billing details.
		$customer->set_billing_first_name( $order->get_billing_first_name() );
		$customer->set_billing_last_name( $order->get_billing_last_name() );
		$customer->set_billing_address_1( $order->get_billing_address_1() );
		$customer->set_billing_address_2( $order->get_billing_address_2() );
		$customer->set_billing_city( $order->get_billing_city() );
		$customer->set_billing_state( $order->get_billing_state() );
		$customer->set_billing_postcode( $order->get_billing_postcode() );
		$customer->set_billing_country( $order->get_billing_country() );
		$customer->set_billing_phone( $order->get_billing_phone() );
		$customer->set_billing_email( $order->get_billing_email() );

		if ( $order->needs_shipping_address() ) {
			// Save Shipping details.
			$customer->set_shipping_first_name( $order->get_shipping_first_name() );
			$customer->set_shipping_last_name( $order->get_shipping_last_name() );
			$customer->set_shipping_address_1( $order->get_shipping_address_1() );
			$customer->set_shipping_address_2( $order->get_shipping_address_2() );
			$customer->set_shipping_city( $order->get_shipping_city() );
			$customer->set_shipping_state( $order->get_shipping_state() );
			$customer->set_shipping_postcode( $order->get_shipping_postcode() );
			$customer->set_shipping_country( $order->get_shipping_country() );
			$customer->set_shipping_phone( $order->get_shipping_phone() );
			$customer->set_shipping_email( $order->get_shipping_email() );
		}

		$customer->save();

		return $customer;
	}

	protected function create_customer( $first_name, $last_name, $email ) {
		$email_exists = email_exists( $email );
		if ( $email_exists ) {
			return $email_exists;
		}

		$base_username = strstr( $email, '@', true );
		$username      = $base_username;
		$counter       = 1;

		while ( username_exists( $username ) ) {
			$username = $base_username . $counter;
			$counter ++;
		}

		$userdata = apply_filters( 'storeengine/checkout/create_customer_data', [
			'user_login'   => $username,
			'user_email'   => $email,
			'role'         => 'storeengine_customer',
			'display_name' => $first_name . ' ' . $last_name,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
		] );

		// Don't pass password through any filter.
		$userdata['user_pass'] = wp_generate_password();

		$user_id = wp_insert_user( $userdata );

		if ( ! is_wp_error( $user_id ) ) {
			wp_signon( array(
				'user_login'    => $username,
				'user_password' => $userdata['user_pass'],
				'remember'      => true,
			), is_ssl() );

			wp_new_user_notification( $user_id, null, 'user' );
		}

		return $user_id;
	}

	/**
	 * @param Coupon[] $coupons
	 *
	 * @return void
	 */
	protected function update_coupon_usage( array $coupons, Order $order ) {
		foreach ( $coupons as $coupon ) {
			add_post_meta( $coupon->get_id(), '_storeengine_coupon_used_by', $order->get_customer_id() );
			$usage_count = (int) get_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', true );
			update_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', $usage_count + 1 );
		}
	}

	private function validate_checkout_data( array $data ) {
		$required_fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_email',
			'billing_phone',
			'payment_method',
		];
		$missing_fields  = [];

		// loop through all the fields and check if they are empty if empty return error for that field
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Please fill all the required fields', 'storeengine' ),
				'fields'  => $missing_fields,
			] );
		}

		$cart = Helper::cart();

		$coupons = $cart->get_coupons();
		if ( empty( $coupons ) || is_user_logged_in() || ! $cart->has_items() ) {
			return;
		}

		foreach ( $coupons as $coupon ) {
			$is_valid = $coupon->validate_coupon( false );
			if ( is_wp_error( $is_valid ) ) {
				wp_send_json_error( $is_valid->get_error_message() );

				continue;
			}

			$coupon_settings = $coupon->validate_coupon();

			if ( 'unlimitedPerCustomer' !== $coupon_settings['coupon_is_total_usage_limit'] ) {
				$user = get_user_by( 'email', $data['user_email'] );
				if ( ! $user ) {
					continue;
				}
				$current_users_usage = array_filter( $coupon->get_used_by(), fn( $user_id ) => (int) $user_id === $user->ID );

				if ( count( $current_users_usage ) >= (int) $coupon_settings['coupon_total_usage_limit'] ) {
					wp_send_json_error( esc_html__( 'Sorry, Coupon has reached its limit', 'storeengine' ) );
				}
			}
		}
	}

	public function change_subscription( $data ) {
		if ( empty( $data['order_id'] ) ) {
			wp_send_json_error( __( 'Order ID is required.', 'storeengine' ) );
		}

		if ( empty( $data['upgrade_price_id'] ) ) {
			wp_send_json_error( __( 'Price ID is required.', 'storeengine' ) );
		}

		$order_id         = $data['order_id'];
		$upgrade_price_id = $data['upgrade_price_id'];

		$order         = new OrderModel();
		$order_data    = $order->get_by_primary_key( $order_id );
		$cancel_result = $order->cancel_subscription( $order_data );
		if ( is_wp_error( $cancel_result ) ) {
			wp_send_json_error( $cancel_result->get_error_message() );
		}

		Helper::cart()->clear_cart();
		Helper::cart()->add_product_to_cart( $upgrade_price_id );

		$purchase_items = $this->prepare_purchase_items_data();

		$order_data = $this->prepare_order_data( $order_data, $purchase_items );
		$order->save( $order_data );

		do_action( 'storeengine/frontend/subscription/after_change_subscription', $data, $order_id );

		wp_send_json_success( 'Subscription status updated successfully.' );
	}

	public function refund( $data ) {
		$order = Helper::get_order( absint( $data['order_id'] ?? 0 ) );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( $order->get_error_message() );
		}

		if ( ! $order->get_id() ) {
			wp_send_json_error( esc_html__( 'Order not found', 'storeengine' ), 404 );
		}

		if ( empty( $data['refund_type'] ) ) {
			wp_send_json_error( esc_html__( 'Select if it is a full or partial refund.', 'storeengine' ) );
		}

		if ( 'full' === $data['refund_type'] ) {
			$refund_amount = Formatting::format_decimal( $order->get_total( 'refund' ) - $order->get_total_refunded( 'refund' ), Formatting::get_price_decimals() );
		} else {
			$refund_amount = Formatting::format_decimal( sanitize_text_field( wp_unslash( $data['refund_amount'] ?? 0 ) ), Formatting::get_price_decimals() );
		}
		// Required.
		$refund_reason   = isset( $data['refund_reason'] ) ? sanitize_text_field( wp_unslash( $data['refund_reason'] ) ) : '';
		$api_refund      = isset( $data['api_refund'] ) && Formatting::string_to_bool( $data['api_refund'] );
		$refunded_amount = Formatting::format_decimal( sanitize_text_field( wp_unslash( $data['refunded_amount'] ?? 0 ) ), Formatting::get_price_decimals() );

		// Optional.
		$line_item_qtys         = isset( $data['line_item_qtys'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_qtys'] ) ), true ) : [];
		$line_item_totals       = isset( $data['line_item_totals'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_totals'] ) ), true ) : [];
		$line_item_tax_totals   = isset( $data['line_item_tax_totals'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_tax_totals'] ) ), true ) : [];
		$restock_refunded_items = isset( $data['restock_refunded_items'] ) && Formatting::string_to_bool( $data['restock_refunded_items'] );
		$refund                 = false;
		$response               = [];

		try {
			$max_refund = Formatting::format_decimal( $order->get_total() - $order->get_total_refunded(), Formatting::get_price_decimals() );
			if ( ( ! $refund_amount && ( Formatting::format_decimal( 0, Formatting::get_price_decimals() ) !== $refund_amount ) ) || $max_refund < $refund_amount || 0 > $refund_amount ) {
				throw new StoreEngineException( __( 'Invalid refund amount', 'storeengine' ), 'invalid_refund_amount' );
			}

			if ( Formatting::format_decimal( $order->get_total_refunded(), Formatting::get_price_decimals() ) !== $refunded_amount ) {
				throw new StoreEngineException( __( 'Error processing refund. Please try again.', 'storeengine' ), 'invalid_refunded_amount' );
			}

			// Prepare line items which we are refunding.
			$line_items = [];
			$item_ids   = array_unique( array_merge( array_keys( $line_item_qtys ), array_keys( $line_item_totals ) ) );

			foreach ( $item_ids as $item_id ) {
				$line_items[ $item_id ] = [
					'qty'          => 0,
					'refund_total' => 0,
					'refund_tax'   => [],
				];
			}
			foreach ( $line_item_qtys as $item_id => $qty ) {
				$line_items[ $item_id ]['qty'] = max( $qty, 0 );
			}
			foreach ( $line_item_totals as $item_id => $total ) {
				$line_items[ $item_id ]['refund_total'] = Formatting::format_decimal( $total );
			}
			foreach ( $line_item_tax_totals as $item_id => $tax_totals ) {
				$line_items[ $item_id ]['refund_tax'] = array_filter( array_map( [
					Formatting::class,
					'format_decimal',
				], $tax_totals ) );
			}

			// Create the refund object.
			/**
			 * @see \WC_AJAX::refund_line_items()
			 */
			$refund = Helper::create_refund( [
				'amount'         => $refund_amount,
				'reason'         => $refund_reason,
				'order_id'       => $order->get_id(),
				'line_items'     => $line_items,
				'refund_payment' => $api_refund,
				'restock_items'  => $restock_refunded_items,
			] );


			if ( is_wp_error( $refund ) ) {
				throw StoreEngineException::from_wp_error( $refund );
			}

			$status = '';
			if ( did_action( 'storeengine/order/partially_refunded' ) ) {
				$status = 'partially_refunded';
			}

			if ( did_action( 'storeengine/order/fully_refunded' ) ) {
				$status = 'fully_refunded';
			}

			// Reload order data from db.
			$order->read( true );

			wp_send_json_success( array_merge(
				Helper::get_payment_data( $order ),
				[
					'refunds_total'   => $order->get_total_refunded( true ),
					'refunded_amount' => $order->get_total_refunded( true ),
					'refund_status'   => $status,
				]
			) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->get_wp_error() );
		}
	}

	public function direct_checkout( $payload ) {
		if ( ! isset( $payload['price_id'] ) ) {
			wp_send_json_error( esc_html__( 'Price ID is required.', 'storeengine' ) );
		}
		if ( ! isset( $payload['quantity'] ) ) {
			wp_send_json_error( esc_html__( 'Quantity is required.', 'storeengine' ) );
		}

		$product = Helper::get_product_by_price_id( $payload['price_id'] );
		if ( ! $product ) {
			wp_send_json_error( esc_html__( 'Product not found.', 'storeengine' ) );
		}

		$variation_id = $payload['variation_id'] ?? 0;
		if ( $product->is_type('variable') && $variation_id <= 0 ) {
			wp_send_json_error( esc_html__( 'Sorry, Variation ID is missing', 'storeengine' ) );
		}

		$variation_data = [];
		if ( $variation_id > 0 ) {
			$variation = Helper::get_product_variation( $variation_id );
			if ( ! $variation ) {
				wp_send_json_error( esc_html__( 'Sorry, Variation not found', 'storeengine' ) );
			}
			foreach ( $variation->get_attributes() as $attributeData ) {
				$variation_data[ $attributeData->taxonomy ] = $attributeData->slug;
			}
		}

		Helper::cart()->clear_cart();
		Helper::cart()->add_product_to_cart( $payload['price_id'], $payload['quantity'], $variation_id, $variation_data );

		wp_send_json_success( [ 'redirect' => esc_url( apply_filters( 'storeengine/checkout/get_checkout_url', Helper::get_page_permalink( 'checkout_page' ) ) ) ] );
	}

	protected function subscribe_to_email( array $data ) {
		if ( isset( $data['subscribe_to_email'] ) ) {
			$customer = Helper::get_customer();
			$customer->set_subscribe_to_email( true );
			$customer->save();
		}
	}

	public static function country_to_state_lists( $payload ) {
		$states = Countries::init()->get_states( $payload['country_code'] );
		wp_send_json_success( $states );
	}
}
