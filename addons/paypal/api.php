<?php

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Models\Product;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class API extends WP_REST_Controller {

	protected GatewayPaypal $gateway;

	public static function init( $gateway ): void {
		$self            = new self();
		$self->gateway   = $gateway;
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'payment/paypal';

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/validate', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_credentials' ],
				'permission_callback' => [ $this, 'backend_permissions_check' ],
				'args'                => $this->get_item_schema(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/create-order', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_paypal_order' ],
				'permission_callback' => [ $this, 'checkout_permissions_check' ],
				'args'                => $this->get_item_schema(),
			],
		] );

		// subscription webhooks
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/webhook', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'paypal_webhook' ],
				'permission_callback' => [ $this, 'checkout_permissions_check' ],
				'args'                => $this->get_item_schema(),
			],
		] );
	}

	public function backend_permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function checkout_permissions_check( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );
		if ( $order_id ) {
			$order = Helper::get_order( $order_id );
			return $order instanceof Order;
		}

		return false;
	}

	public function validate_credentials( WP_REST_Request $request ) {
		/**
		 * Fires before PayPal credentials validation.
		 *
		 * @param WP_REST_Request $request Request object.
		 */
		do_action( 'storeengine/api/paypal/before_validate_credentials', $request );

		if ( ! $request->get_param( 'mode' ) || ! $request->get_param( 'sandbox_secret' ) || ! $request->get_param( 'sandbox_secret' ) ) {
			return new WP_Error( 'missing-required-params', __( 'Missing required params.', 'storeengine' ), [ 'status' => 400 ] );
		}

		try {
			$paypal = PaypalExpressService::init( $this->gateway );
			$result = $paypal->validate_credentials(
				$request->get_param( 'mode' ),
				$request->get_param( 'sandbox_id' ),
				$request->get_param( 'sandbox_secret' )
			);

			/**
			 * Fires after PayPal credentials validation. Throw `\StoreEngine\Classes\Exceptions\StoreEngineException` exception if you want to produce credentials validation failure.
			 *
			 * @param array|WP_Error $result Result.
			 * @param WP_REST_Request $request Request object.
			 */
			do_action( 'storeengine/api/paypal/after_validate_credentials', $result, $request );

			return rest_ensure_response( $result );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public function create_paypal_order( WP_REST_Request $request ) {
		if ( 'paypal' !== $request->get_param( 'payment_method' ) ) {
			return new WP_Error( 'invalid_payment_method', __( 'Invalid payment method.', 'storeengine' ), [ 'status' => 402 ] );
		}

		$order = Helper::get_order( (int) $request->get_param( 'order_id' ) );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'storeengine' ), [ 'status' => 400 ] );
		}

		try {
			$paypalOrder = PaypalExpressService::init()->create_order( $order );

			if ( isset( $paypalOrder->id ) ) {
				$order->add_meta_data( '_paypal_order_id', $paypalOrder->id, true );
				$order->save();
			} else {
				return new WP_Error( 'paypal-intent-id-missing', __( 'Failed to initialize PayPal order intent.', 'storeengine' ), [ 'status' => 400 ] );
			}

			return rest_ensure_response( $paypalOrder );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}


	/**
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	public function paypal_webhook( $request ) {
		if ( 'BILLING.SUBSCRIPTION.ACTIVATED' === $request->get_param( 'event_type' ) ) {
			$billing_agreement_id = $request->get_param( 'resource' )['id'];
			$order                = Helper::get_order_by_meta( '_paypal_subscription_id', $billing_agreement_id );
			if ( ! $order ) {
				return new WP_Error( 'invalid_order', __( 'Invalid order.', 'storeengine' ), [ 'status' => 404 ] );
			}
			if ( 'active' === $order->get_status() ) {
				// if current data is greater or equal to next billing date then create renewal order
				$next_billing_date = strtotime( $order->get_meta( '_paypal_next_billing_date', true, 'edit' ) );
				$current_date      = time();
				if ( $current_date >= $next_billing_date ) {
					$this->create_renewal_order( $order, $request->get_param( 'resource' ) );
				}

				return rest_ensure_response( [ 'status' => 'success' ] );
			}

			// 1. if the first payment is successful then activate the subscription
			if ( 'pending_payment' === $order->get_status() ) {
				$order_context = new OrderContext( $order->get_status() );
				$order_context->proceed_to_next_status( 'payment_initiate', $order );
				$order_context->proceed_to_next_status( 'payment_confirmed', $order );
				$order_context->proceed_to_next_status( 'active', $order );

				if ( ! empty( $request->get_param( 'resource' )['id'] ) ) {
					$order->set_transaction_id( $request->get_param( 'resource' )['id'] );
				}

				$order->add_meta_data( '_paypal_next_billing_date', $request->get_param( 'resource' )['billing_info']['next_billing_time'], true );
				$order->save();
			}

			return rest_ensure_response( [ 'status' => 'success' ] );
		}

		if ( 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' === $request->get_param( 'event_type' ) ) {
			$this->expire_subscription( $request->get_param( 'resource' ) );

			return new WP_Error( 'payment_failed', __( 'Payment Failed.', 'storeengine' ), [ 'status' => 402 ] );
		}

		return new WP_Error( 'not-implemented', __( 'Not Implemented.', 'storeengine' ), [ 'status' => 501 ] );
	}

	/**
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineInvalidOrderStatusException
	 * @deprecated
	 */
	protected function create_renewal_order( $subscription, $paypal_payload ): array {
		// get parent order
		$parent_order = $subscription;
		// changing status to fire renewal event
		$order_context = new OrderContext( $subscription['status'] );
		$order_context->proceed_to_next_status( 'renew', $parent_order );
		$new_status        = $order_context->get_order_status();
		$product_model     = new Product();
		$price_id          = $parent_order['purchase_items'][0]->price_id;
		$next_payment_date = $product_model->next_payment_date( $price_id );

		$args = [
			'status'                => $new_status,
			'currency'              => $parent_order['currency'],
			'type'                  => $parent_order['type'],
			'tax_amount'            => $parent_order['tax_amount'],
			'total_amount'          => $parent_order['total_amount'],
			'customer_id'           => $parent_order['customer_id'],
			'billing_email'         => $parent_order['billing_email'],
			'payment_method'        => $parent_order['payment_method'],
			'parent_order_id'       => $parent_order['id'],
			'payment_method_title'  => $parent_order['payment_method_title'],
			'customer_note'         => 'Renewal Subscription for ' . $parent_order['id'],
			'transaction_id'        => '',
			'meta'                  => [
				'paypal_next_billing_date' => $paypal_payload['billing_info']['next_billing_time'],
			],
			'purchase_items'        => [
				[
					'product_id'          => $parent_order['purchase_items'][0]->product_id,
					'variation_id'        => 0,
					'price_id'            => $parent_order['purchase_items'][0]->price_id,
					'price'               => $parent_order['purchase_items'][0]->price,
					'product_qty'         => $parent_order['purchase_items'][0]->product_qty,
					'coupon_amount'       => $parent_order['purchase_items'][0]->coupon_amount,
					'tax_amount'          => $parent_order['purchase_items'][0]->tax_amount,
					'shipping_amount'     => $parent_order['purchase_items'][0]->shipping_amount,
					'shipping_tax_amount' => $parent_order['purchase_items'][0]->shipping_tax_amount,
				],
			],
			'order_billing_address' => [
				'first_name' => $parent_order['order_billing_address']->first_name,
				'last_name'  => $parent_order['order_billing_address']->last_name,
				'company'    => $parent_order['order_billing_address']->company,
				'address_1'  => $parent_order['order_billing_address']->address_1,
				'address_2'  => $parent_order['order_billing_address']->address_2,
				'city'       => $parent_order['order_billing_address']->city,
				'state'      => $parent_order['order_billing_address']->state,
				'postcode'   => $parent_order['order_billing_address']->postcode,
				'country'    => $parent_order['order_billing_address']->country,
				'email'      => $parent_order['order_billing_address']->email,
				'phone'      => $parent_order['order_billing_address']->phone,
			],
		];

		$order_model = new Order();

		$renewal_order = $order_model->save( $args );

		return array( $parent_order, $order_context, $next_payment_date, $order_model, $renewal_order );
	}

	/**
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	private function expire_subscription( $get_param ) {
		$order = Helper::get_order_by_meta( '_paypal_subscription_id', $get_param['id'] );
		if ( ! $order ) {
			return;
		}
		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'expired', $order );
		$order->save();
	}

	public static function create_paypal_subscription() {
		$cart       = Helper::cart();
		$cart_data  = $cart->get_cart_items();
		$product_id = (int) $cart_data[0]['product_id'];
		$price_id   = (int) $cart_data[0]['price_id'];
		$paypal     = PaypalExpressService::init();
		$plan       = $paypal->create_product_and_subscription( $product_id, $price_id );

		return $plan;
	}
}
