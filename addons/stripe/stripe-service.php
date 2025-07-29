<?php

namespace StoreEngine\Addons\Stripe;

use Exception;
use stdClass;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment_Gateways;
use StoreEngine\Stripe\Account;
use StoreEngine\Stripe\BankAccount;
use StoreEngine\Stripe\Card;
use StoreEngine\Stripe\Customer as StripeCustomer;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Stripe\Exception\AuthenticationException;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Price as StripePrice;
use StoreEngine\Stripe\Product as StripeProduct;
use StoreEngine\Stripe\SetupIntent;
use StoreEngine\Stripe\Source;
use StoreEngine\Stripe\StripeClient;
use StoreEngine\Stripe\Subscription as StripeSubscription;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\traits\Gateway;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StripeService {

	protected bool $is_live = false;

	protected string $publishable_key = '';

	protected string $secret_key = '';

	protected string $redirect_url = '';

	protected string $currency = 'USD';

	/**
	 * List of currencies supported by Stripe.
	 *
	 * @link https://docs.stripe.com/currencies
	 *
	 * @var array|string[]
	 */
	protected static array $supported_currencies = [
		'USD',
		'AED',
		'AFN',
		'ALL',
		'AMD',
		'ANG',
		'AOA',
		'ARS',
		'AUD',
		'AWG',
		'AZN',
		'BAM',
		'BBD',
		'BDT',
		'BGN',
		'BIF',
		'BMD',
		'BND',
		'BOB',
		'BRL',
		'BSD',
		'BWP',
		'BYN',
		'BZD',
		'CAD',
		'CDF',
		'CHF',
		'CLP',
		'CNY',
		'COP',
		'CRC',
		'CVE',
		'CZK',
		'DJF',
		'DKK',
		'DOP',
		'DZD',
		'EGP',
		'ETB',
		'EUR',
		'FJD',
		'FKP',
		'GBP',
		'GEL',
		'GIP',
		'GMD',
		'GNF',
		'GTQ',
		'GYD',
		'HKD',
		'HNL',
		'HTG',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'ISK',
		'JMD',
		'JPY',
		'KES',
		'KGS',
		'KHR',
		'KMF',
		'KRW',
		'KYD',
		'KZT',
		'LAK',
		'LBP',
		'LKR',
		'LRD',
		'LSL',
		'MAD',
		'MDL',
		'MGA',
		'MKD',
		'MMK',
		'MNT',
		'MOP',
		'MUR',
		'MVR',
		'MWK',
		'MXN',
		'MYR',
		'MZN',
		'NAD',
		'NGN',
		'NIO',
		'NOK',
		'NPR',
		'NZD',
		'PAB',
		'PEN',
		'PGK',
		'PHP',
		'PKR',
		'PLN',
		'PYG',
		'QAR',
		'RON',
		'RSD',
		'RUB',
		'RWF',
		'SAR',
		'SBD',
		'SCR',
		'SEK',
		'SGD',
		'SHP',
		'SLE',
		'SOS',
		'SRD',
		'STD',
		'SZL',
		'THB',
		'TJS',
		'TOP',
		'TRY',
		'TTD',
		'TWD',
		'TZS',
		'UAH',
		'UGX',
		'UYU',
		'UZS',
		'VND',
		'VUV',
		'WST',
		'XAF',
		'XCD',
		'XOF',
		'XPF',
		'YER',
		'ZAR',
		'ZMW',
	];

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://docs.stripe.com/currencies#zero-decimal from https://docs.stripe.com/currencies#presentment-currencies
	 * ugx is an exception and not in this list for being a special cases in Stripe https://docs.stripe.com/currencies#special-cases
	 *
	 * @var array|string[]
	 */
	protected static array $zero_decimal_currencies = [
		'BIF', // Burundian Franc
		'CLP', // Chilean Peso
		'DJF', // Djiboutian Franc
		'GNF', // Guinean Franc
		'JPY', // Japanese Yen
		'KMF', // Comorian Franc
		'KRW', // South Korean Won
		'MGA', // Malagasy Ariary
		'PYG', // Paraguayan Guaraní
		'RWF', // Rwandan Franc
		//'UGX', // Ugandan Shilling
		'VND', // Vietnamese Đồng
		'VUV', // Vanuatu Vatu
		'XAF', // Central African Cfa Franc
		'XOF', // West African Cfa Franc
		'XPF', // Cfp Franc
	];

	/**
	 * List of currencies supported by Stripe that has three decimals
	 * https://docs.stripe.com/currencies?presentment-currency=AE#three-decimal
	 *
	 * @var array|string[]
	 */
	protected static array $three_decimal_currencies = [
		'BHD', // Bahraini Dinar
		'JOD', // Jordanian Dinar
		'KWD', // Kuwaiti Dinar
		'OMR', // Omani Rial
		'TND', // Tunisian Dinar
	];

	protected static array $currency_minimum_charges = [
		'USD' => 0.50,
		'AED' => 2.00,
		'AUD' => 0.50,
		'BGN' => 1.00,
		'BRL' => 0.50,
		'CAD' => 0.50,
		'CHF' => 0.50,
		'CZK' => 15.00,
		'DKK' => 2.50,
		'EUR' => 0.50,
		'GBP' => 0.30,
		'HKD' => 4.00,
		'HUF' => 175.00,
		'INR' => 0.50,
		'JPY' => 50,
		'MXN' => 10,
		'MYR' => 2,
		'NOK' => 3.00,
		'NZD' => 0.50,
		'PLN' => 2.00,
		'RON' => 2.00,
		'SEK' => 3.00,
		'SGD' => 0.50,
		'THB' => 10,
	];

	/**
	 * @var ?GatewayStripe
	 */
	protected ?GatewayStripe $gateway;

	private ?StripeClient $stripe_client = null;

	protected static ?StripeService $instance = null;

	public static function init( $gateway = null ): StripeService {
		if ( null === self::$instance ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	public function __construct( $gateway = null ) {
		if ( $gateway instanceof GatewayStripe ) {
			$this->gateway = $gateway;
		} else {
			$this->gateway = Payment_Gateways::get_instance()->get_gateway( 'stripe' );
		}

		$this->init_settings();
	}

	public function init_settings() {
		global $wp;

		if ( ! $this->gateway ) {
			return;
		}

		if ( ! $this->gateway->is_enabled || 'stripe' !== $this->gateway->id ) {
			return;
		}

		// WP-Org doesn't allow certificate files in the repo.
		// Using ca bundle from WP-core.
		\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );

		$this->is_live         = $this->gateway->get_option( 'is_production', true );
		$key_type              = $this->is_live ? '' : 'test_';
		$this->publishable_key = $this->gateway->get_option( $key_type . 'publishable_key' );
		$this->secret_key      = $this->gateway->get_option( $key_type . 'secret_key' );
		$this->currency        = Formatting::get_currency();
		$this->redirect_url    = home_url( $wp->request );

		if ( ! $this->secret_key ) {
			return;
		}

		$this->stripe_client = new StripeClient( $this->secret_key );
	}

	public function get_customer( Customer $customer, bool $create = true ) {
		if ( ! $customer->get_id() ) {
			return new WP_Error( 'customer_not_found', __( 'Customer not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		// Create a Stripe customer if not stored
		$customer_id = get_user_meta( $customer->get_id(), 'stripe_customer_id', true );

		if ( ! $customer_id && $create ) {
			$stripe_customer = $this->stripe_client->customers->create( [
				'email'          => $customer->get_billing_email() ?: $customer->get_email(),
				'name'           => $customer->get_billing_full_name(),
				'phone'          => $customer->get_billing_phone(),
				'invoice_prefix' => 'SE', // @TODO: Allow store to change that via gateway settings.
				'metadata'       => [
					'email' => $customer->get_email(),
					'se_id' => $customer->get_id(),
				],
			] );

			// Save stripe customer id.
			$customer_id = $stripe_customer->id;
			update_user_meta( $customer->get_id(), 'stripe_customer_id', $customer_id );
		}

		return $customer_id;
	}

	/**
	 * @param Order $order
	 * @param ?string $stripe_customer_id
	 * @param ?string $payment_method_id
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 */
	public function create_payment_intent( Order $order, ?string $stripe_customer_id = null, ?string $payment_method_id = null ): PaymentIntent {
		try {
			if ( ! $order->get_id() ) {
				throw new StoreEngineException( __( 'Order not found.', 'storeengine' ), 'order_not_found', null, 404 );
			}

			$checkout_customer_name  = $order->get_billing_first_name( 'edit' ) ?? 'Guest User – ' . $order->get_order_key( 'edit' );
			$checkout_customer_email = $order->get_billing_email() ?? 'guest.' . $order->get_order_key( 'edit' ) . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
			$amount                  = Helper::cart() ? Helper::cart()->get_total( 'create_payment' ) : 0;

			if ( ! $amount && $order->get_meta( '_subscription_renewal' ) ) {
				$amount = $order->get_total( 'create_payment' );
			}

			if ( $stripe_customer_id ) {
				$customer = $this->stripe_client->customers->retrieve( $stripe_customer_id );
				$order->add_meta_data( '_stripe_customer_id', $stripe_customer_id, true );
			} else {
				$customer = $this->create_customer( $checkout_customer_name, $checkout_customer_email );
				$order->add_meta_data( '_stripe_customer_id', $customer->id, true );
			}

			$args = [
				'capture_method'            => 'manual', // Important for delayed capture.
				'currency'                  => $order->get_currency( 'edit' ),
				'amount'                    => self::get_stripe_amount( $amount, $order->get_currency() ),
				'automatic_payment_methods' => [
					'enabled'         => true,
					'allow_redirects' => 'never',
				],
				'description'               => 'Payment for ' . get_bloginfo( 'name' ) . ' Order #' . $order->get_id(),
				'customer'                  => $customer->id,
				'setup_future_usage'        => $this->gateway->has_subscription( $order ) ? 'off_session' : 'on_session',
				// 'off_session' for one-time payments
				'metadata'                  => [
					'customer_email'         => $customer->email,
					'customer_name'          => $customer->name,
					'storeengine_order_id'   => $order->get_id(),
					'storeengine_order_hash' => $order->get_cart_hash(),
					'site_url'               => home_url(),
				],
			];

			if ( $payment_method_id && $stripe_customer_id ) {
				$this->stripe_client->paymentMethods->attach( $payment_method_id, [ 'customer' => $stripe_customer_id ] );
				$args['payment_method'] = $payment_method_id;
				$args['customer']       = $stripe_customer_id;
				$args['confirm']        = true;
			}


			return $this->stripe_client->paymentIntents->create( $args );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-payment-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	public function create_payment_intent_and_charge_for_subscription( $subscription ) {
		try {
			$this->stripe_client->paymentMethods->attach( $subscription['meta']['stripe_payment_method_id'], [ 'customer' => $subscription['meta']['stripe_customer_id'] ] );
			$previousPaymentIntents = $this->stripe_client->paymentIntents->retrieve( $subscription['meta']['stripe_payment_intent_id'] );

			return $this->stripe_client->paymentIntents->create( [
				// As it's cents, it would not be 1000
				'amount'                    => self::get_stripe_amount( $subscription['total_amount'], $previousPaymentIntents->currency ),
				'currency'                  => $previousPaymentIntents->currency,
				'automatic_payment_methods' => [
					'enabled'         => true,
					'allow_redirects' => 'never',
				],
				'description'               => 'Renewal Payment for ' . get_bloginfo( 'name' ) . ' subscription-' . $subscription['id'],
				'customer'                  => $subscription['meta']['stripe_customer_id'],
				'setup_future_usage'        => 'on_session',
				// 'off_session' for one-time payments
				'payment_method'            => $subscription['meta']['stripe_payment_method_id'],
				'confirm'                   => true,
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-get-payment-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $product
	 * @param $stripe_payment_intent
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_product_and_subscription( $product, $stripe_payment_intent ): StripeSubscription {

		// get customer id from stripe_payment_intent
		$payment_intent         = $this->get_payment_intent( $stripe_payment_intent );
		$customer_id            = $payment_intent->customer;
		$default_payment_method = $payment_intent->payment_method;
		$product_object         = $this->create_product( $product['name'] );
		$price_object           = $this->create_price(
			$product_object->id,
			$product['price'],
			$product['interval_type'],
			$product['interval']
		);

		return $this->create_subscription( $customer_id, $price_object->id, $default_payment_method );
	}

	/**
	 * @param string $name
	 *
	 * @return StripeProduct
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_product( string $name ): StripeProduct {
		try {
			return $this->stripe_client->products->create( [
				'name' => $name,
				'type' => 'service',
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-product', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $product_id
	 * @param $price
	 * @param $interval
	 * @param $interval_count
	 *
	 * @return StripePrice
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_price( $product_id, $price, $interval, $interval_count ): StripePrice {
		try {
			return $this->stripe_client->prices->create( [
				'product'     => $product_id,
				'unit_amount' => self::get_stripe_amount( $price, $this->currency ),
				'currency'    => $this->currency,
				'recurring'   => [
					'interval'       => $interval,
					'interval_count' => $interval_count,
				],
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-price', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $name
	 * @param $email
	 *
	 * @return StripeCustomer
	 * @throws StoreEngineException
	 */
	public function create_customer( $name, $email ): StripeCustomer {
		try {
			return $this->stripe_client->customers->create( [
				'name'  => $name,
				'email' => $email,
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-customer', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $customer_id
	 * @param $price_id
	 * @param $default_payment_method
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_subscription( $customer_id, $price_id, $default_payment_method ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->create( [
				'customer'               => $customer_id,
				'items'                  => [
					[
						'price' => $price_id,
					],
				],
				'default_payment_method' => $default_payment_method,
				'metadata'               => [
					'created_by' => 'StoreEngine Subscription Plugin',
				],
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-subscription', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	public function get_payment_intent( $stripe_payment_intent ) {
		try {
			return $this->stripe_client->paymentIntents->retrieve( $stripe_payment_intent );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-get-payment-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}


	public function cancel_subscription( $subscription_id ) {
		try {
			return $this->stripe_client->subscriptions->cancel( $subscription_id );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-cancel-subscription', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $customer_id
	 *
	 * @return \StoreEngine\Stripe\Collection
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function list_subscriptions( string $customer_id ): \StoreEngine\Stripe\Collection {
		try {
			return $this->stripe_client->subscriptions->all( [ 'customer' => $customer_id ] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-retrieve-subscriptions', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function retrieve_subscription( $subscription_id ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->retrieve( $subscription_id );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-retrieve-subscription', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function resume_subscription( $subscription_id ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->update( $subscription_id, [
				'cancel_at_period_end' => false,
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-update-subscription', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $customer_id
	 * @param string|int $product_id
	 *
	 * @return false|mixed
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function search_subscription( string $customer_id, $product_id ) {
		$subscriptions = $this->list_subscriptions( $customer_id );
		foreach ( $subscriptions as $subscription ) {
			if ( (int) $subscription->items->data[0]->price->product === (int) $product_id ) {
				return $subscription;
			}
		}

		return false;
	}


	public function create_webhook( array $events, string $url ): \StoreEngine\Stripe\WebhookEndpoint {
		try {
			return $this->stripe_client->webhookEndpoints->create( [
				'url'            => $url,
				'enabled_events' => $events,
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-webhook', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	public function get_webhook( string $webhook_id ): \StoreEngine\Stripe\WebhookEndpoint {
		try {
			return $this->stripe_client->webhookEndpoints->retrieve( $webhook_id );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-get-webhook', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $secret_key
	 *
	 * @return string|WP_Error
	 */
	public static function validate_keys( string $secret_key ) {
		try {
			// WP-Org doesn't allow certificate files in the repo.
			// Using ca bundle from WP-core.
			\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );
			$stripe  = new StripeClient( $secret_key );
			$account = $stripe->accounts->retrieve();

			return $account->id;
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'invalid_secret_key', $e->getMessage() );
		}
	}

	public static function validate_publishable_key( string $publishable_key ): bool {
		try {
			// WP-Org doesn't allow certificate files in the repo.
			// Using ca bundle from WP-core.
			\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );

			\StoreEngine\Stripe\Stripe::setApiKey( $publishable_key );
			PaymentMethod::all( [ 'limit' => 1 ] );

			return true;
		} catch ( AuthenticationException $e ) {
			return false;
		} catch ( Exception $e ) {
			return true;
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return array
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function get_subscription_current_period_info( $subscription_id ): array {
		$subscription = $this->retrieve_subscription( $subscription_id );
		$period_start = $subscription->current_period_start;
		$period_end   = $subscription->current_period_end;

		return [
			'start' => $period_start,
			'end'   => $period_end,
		];
	}

	/**
	 * @param $amount
	 * @param $currency
	 * @param $source
	 * @param $description
	 *
	 * @return \StoreEngine\Stripe\Charge
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_charge( $amount, $currency, $source, $description ): \StoreEngine\Stripe\Charge {
		try {
			// @TODO use self::get_stripe_amount
			return $this->stripe_client->charges->create( [
				'amount'      => $amount,
				'currency'    => $currency,
				'source'      => $source,
				'description' => $description,
			] );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-charge', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $intent_id
	 * @param array|null $params
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 */
	public function capture_payment( string $intent_id, ?array $params = null ): PaymentIntent {
		try {
			return $this->stripe_client->paymentIntents->capture( $intent_id, $params );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'stripe-capture-payment-intent-failed', [
				'intent_id' => $intent_id,
				'params'    => $params,
				'response'  => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $intent_id
	 * @param array|null $params
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 */
	public function update_payment_intent( string $intent_id, ?array $params = null ): PaymentIntent {
		try {
			return $this->stripe_client->paymentIntents->update( $intent_id, $params );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'stripe-update-payment-intent-failed', [
				'intent_id' => $intent_id,
				'params'    => $params,
				'response'  => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @return true|WP_Error
	 * @deprecated
	 */
	public function is_stripe_configured() {
		if ( empty( $this->secret_key ) ) {
			return new WP_Error( 'empty_secret_key', 'Stripe secret key is empty' );
		}
		$result = $this->validate_keys( $this->secret_key );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'invalid_secret_key', $result->get_error_message() );
		}

		return true;
	}

	public function refund( $charge_id, $amount, Order $order ) {
		try {
			return $this->stripe_client->refunds->create( [
				'charge' => $charge_id,
				'amount' => self::get_stripe_amount( $amount, $order->get_currency() ),
			] );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	public function get_balance_history( $transaction_id ) {
		try {
			return $this->stripe_client->balanceTransactions->retrieve( $transaction_id );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	/**
	 * @param $params
	 *
	 * @return SetupIntent
	 * @throws StoreEngineException
	 */
	public function create_setup_intent( $params = [] ): SetupIntent {
		try {
			return $this->stripe_client->setupIntents->create( $params );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-setup-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * @param string $params
	 *
	 * @return SetupIntent
	 * @throws StoreEngineException
	 */
	public function get_setup_intent( $setup_intent_id ): SetupIntent {
		try {
			return $this->stripe_client->setupIntents->retrieve( $setup_intent_id );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-retrive-setup-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	/**
	 * Creates and confirm a setup intent with the given payment method ID.
	 *
	 * @param array $payment_information The payment information to be used for the setup intent.
	 *
	 * @return SetupIntent
	 * @throws StoreEngineException If the create intent call returns with an error.
	 */
	public function create_and_confirm_setup_intent( array $payment_information ): SetupIntent {
		$request = [
			'payment_method' => $payment_information['payment_method'],
			//'payment_method_types' => $payment_information['payment_method_types'] ?? [ $payment_information['selected_payment_type'] ],
			'customer'       => $payment_information['customer'],
			'confirm'        => true,
			'return_url'     => $payment_information['return_url'],
		];

		// Removes the return URL if Single Payment Element is not enabled or if the request doesn't need redirection.

		try {
			return $this->stripe_client->setupIntents->create( $request );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-create-setup-intent', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	public function get_setup_intents( array $params = null ): \StoreEngine\Stripe\Collection {
		try {
			return $this->stripe_client->setupIntents->all( $params );
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-get-setup-intents', [
				'response' => [
					'http_status'  => $e->getHttpStatus(),
					'requestId'    => $e->getRequestId(),
					'stripeCode'   => $e->getStripeCode(),
					'body'         => $e->getJsonBody() ?? $e->getHttpBody(),
					'original_msg' => (string) $e,
				],
			], $e->getCode(), $e );
		}
	}

	public function getClient(): ?StripeClient {
		return $this->stripe_client;
	}

	/**
	 * Returns a payment method object from Stripe given an ID. Accepts both 'src_xxx' and 'pm_xxx'
	 *  style IDs for backwards compatibility.
	 *
	 * @param string $payment_method_id The ID of the payment method to retrieve.
	 *
	 * @return PaymentMethod|Source|WP_Error
	 * @throws StoreEngineException
	 */
	public function get_payment_method( string $payment_method_id, bool $wp_error = true ) {
		if ( ! $payment_method_id ) {
			return new WP_Error( 'empty_payment_method_or_source_id', __( 'Payment method or source ID is empty.', 'storeengine' ) );
		}

		try {
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				// Sources have a separate API.
				return $this->stripe_client->sources->retrieve( $payment_method_id );
			}

			// If it's not a source it's a PaymentMethod.
			return $this->stripe_client->paymentMethods->retrieve( $payment_method_id );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';

			$exception = new StoreEngineException( $e->getMessage(), 'error-retrieving-stripe-' . $type, [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );

			if ( ! $wp_error ) {
				throw $exception;
			}

			return $exception->toWpError();
		}
	}

	/**
	 * @param PaymentMethod|Source $source
	 * @param array $params
	 *
	 * @return PaymentMethod|Source
	 * @throws StoreEngineException
	 */
	public function update_payment_method( $source, array $params = [] ) {
		try {
			if ( 0 === strpos( $source->id, 'src_' ) ) {
				// Sources have a separate API.
				return $this->stripe_client->sources->update( $source->id, $params );
			}

			// If it's not a source it's a PaymentMethod.
			return $this->stripe_client->paymentMethods->update( $source->id, $params );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $source->id, 'src_' ) ? 'source' : 'payment_method';

			throw new StoreEngineException( $e->getMessage(), 'error-retrieving-stripe-' . $type, [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Returns true if the provided payment method is a card, false otherwise.
	 *
	 * @param PaymentMethod|Source $payment_method The provided payment method object. Can be a Source or a Payment Method.
	 *
	 * @return bool  True if payment method is a card, false otherwise.
	 */
	public static function is_card_payment_method( $payment_method ): bool {
		if ( ! isset( $payment_method->object ) || ! isset( $payment_method->type ) ) {
			return false;
		}

		if ( 'payment_method' !== $payment_method->object && 'source' !== $payment_method->object ) {
			return false;
		}

		return 'card' === $payment_method->type;
	}

	/**
	 * Evaluates whether a given Stripe Source (or Stripe Payment Method) is reusable.
	 * Payment Methods are always reusable; Sources are only reusable when the appropriate
	 * usage metadata is provided.
	 *
	 * @param PaymentMethod|Source $payment_method The source or payment method to be evaluated.
	 *
	 * @return bool  Returns true if the source is reusable; false otherwise.
	 */
	public static function is_reusable_payment_method( $payment_method ): bool {
		return self::is_payment_method_object( $payment_method ) || ( isset( $payment_method->usage ) && 'reusable' === $payment_method->usage );
	}

	/**
	 * Evaluates whether the object passed to this function is a Stripe Payment Method.
	 *
	 * @param PaymentMethod|Source $payment_method The object that should be evaluated.
	 *
	 * @return bool             Returns true if the object is a Payment Method; false otherwise.
	 */
	public static function is_payment_method_object( $payment_method ): bool {
		return isset( $payment_method->object ) && 'payment_method' === $payment_method->object;
	}

	/**
	 * Attaches a payment method to the given customer.
	 *
	 * @param string $customer_id The ID of the customer the payment method should be attached to.
	 * @param string $payment_method_id The payment method that should be attached to the customer.
	 *
	 * @return Account|BankAccount|Card|Source
	 * @throws StoreEngineException
	 */
	public function attach_payment_method_to_customer( string $customer_id, string $payment_method_id ) {
		try {
			// Sources and Payment Methods need different API calls.
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				return $this->stripe_client->customers->updateSource( $customer_id, $payment_method_id );
			}

			return $this->stripe_client->paymentmethods->customers->attach( $payment_method_id, [ 'customer' => $customer_id ] );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';
			throw new StoreEngineException( $e->getMessage(), 'error-attach-customer-method-' . $type, [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Detaches a payment method from the given customer.
	 *
	 * @param string $customer_id The ID of the customer that contains the payment method that should be detached.
	 * @param string $payment_method_id The ID of the payment method that should be detached.
	 *
	 * @return array|Account|BankAccount|Card|PaymentMethod|Source The response from the API request
	 *
	 * @throws StoreEngineException
	 */
	public static function detach_payment_method_from_customer( string $customer_id, string $payment_method_id ) {
		if ( ! self::should_detach_payment_method_from_customer() ) {
			return [];
		}

		$payment_method_id = sanitize_text_field( $payment_method_id );

		try {
			// Sources and Payment Methods need different API calls.
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				return self::init()->getClient()->customers->deleteSource( $customer_id, $payment_method_id );
			}

			return self::init()->getClient()->paymentMethods->detach( $payment_method_id );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';
			throw new StoreEngineException( $e->getMessage(), 'error-detach-customer-method-' . $type, [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Checks if a payment method should be detached from a customer.
	 *
	 * If the site is a staging/local/development site in live mode, we should not detach the payment method
	 * from the customer to avoid detaching it from the production site.
	 *
	 * @return bool True if the payment should be detached, false otherwise.
	 */
	public static function should_detach_payment_method_from_customer(): bool {
		// If we are in test mode, we can always detach the payment method.
		if ( ! self::init()->is_live ) {
			return true;
		}

		// Return true for the delete user request from the admin dashboard when the site is a production site
		// and return false when the site is a staging/local/development site.
		// This is to avoid detaching the payment method from the live production site.
		// Requests coming from the customer account page i.e delete payment method, are not affected by this and returns true.
		if ( is_admin() ) {
			if ( 'production' === wp_get_environment_type() ) {
				return true;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * List of currencies supported by Stripe
	 *
	 * @return string[]
	 */
	public static function get_supported_currencies(): array {
		return self::$supported_currencies;
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 *
	 * @return string[]
	 */
	public static function no_decimal_currencies(): array {
		return self::$zero_decimal_currencies;
	}

	/**
	 * List of currencies supported by Stripe that has three decimals
	 *
	 * @return array $currencies
	 */
	private static function three_decimal_currencies(): array {
		return self::$three_decimal_currencies;
	}

	/**
	 * Get Stripe amount to pay.
	 * Amount is be in cents, for some country it needs to be multiplied by 1000.
	 *
	 * @param float|int $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_stripe_amount( $total, string $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return absint( round( $total ) );
		}

		if ( in_array( $currency, self::three_decimal_currencies(), true ) ) {
			$price_decimals = Formatting::get_price_decimals();
			$amount         = absint( Formatting::format_decimal( ( (float) $total * 1000 ), $price_decimals ) ); // For tree decimal currencies.

			return $amount - ( $amount % 10 ); // Round the last digit down. See https://docs.stripe.com/currencies?presentment-currency=AE#three-decimal
		}

		return absint( Formatting::format_decimal( ( (float) $total * 100 ), Formatting::get_price_decimals() ) ); // In cents.
	}

	public static function get_currency_minimum_charges(): array {
		return self::$currency_minimum_charges;
	}

	/**
	 * Checks Stripe minimum order value authorized per currency
	 */
	public static function get_minimum_amount( $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return self::$currency_minimum_charges[ $currency ] ?? .50;
	}

	/**
	 * Stripe uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from Stripe into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 *
	 * @return string
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return '';
		}

		if ( in_array( strtoupper( $balance_transaction->currency ), self::no_decimal_currencies(), true ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
	}
}
