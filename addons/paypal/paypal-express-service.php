<?php
/**
 * PayPal Express Service.
 *
 * @version 1.5.0
 */

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Models\Product;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * PayPal express Checkout Gateway.
 *
 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_create
 */
final class PaypalExpressService {
	const INVALID_SANDBOX_SECRET_EXCEPTION = 90;

	const INVALID_LIVE_SECRET_EXCEPTION = 91;

	const INVALID_SANDBOX_ID_EXCEPTION = 92;

	const INVALID_LIVE_ID_EXCEPTION = 93;

	const EMPTY_SANDBOX_SECRET_EXCEPTION = 94;

	const EMPTY_LIVE_SECRET_EXCEPTION = 95;

	const EMPTY_SANDBOX_ID_EXCEPTION = 96;

	const EMPTY_LIVE_ID_EXCEPTION = 97;

	const HTTPS_API_SANDBOX_PAYPAL_COM = 'https://api.sandbox.paypal.com/';

	const HTTPS_API_PAYPAL_COM = 'https://api.paypal.com/';

	protected bool $is_live = false;

	protected string $client_id = '';

	protected string $client_secret = '';

	protected string $redirect_url = '';

	protected string $currency = 'USD';

	/**
	 * Currencies supported by PayPal.
	 *
	 * From https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/
	 *
	 * @var string[]
	 */
	protected static array $supported_currencies = [
		'AUD',
		'BRL',
		'CAD',
		'CNY',
		'CZK',
		'DKK',
		'EUR',
		'HKD',
		'HUF',
		'ILS',
		'JPY',
		'MYR',
		'MXN',
		'TWD',
		'NZD',
		'NOK',
		'PHP',
		'PLN',
		'GBP',
		'RUB',
		'SGD',
		'SEK',
		'CHF',
		'THB',
		'USD',
	];

	protected static array $zero_decimal_currencies = [
		'HUF',
		'JPY',
		'TWD',
	];

	/**
	 * List of currencies with three decimals parts.
	 *
	 * These currencies are subdivided into thousandths (USD subdivided into hundredth).
	 * E.g.
	 * 1⁄1000
	 * Subunit    0.001
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

	protected ?PaymentGateway $gateway = null;

	protected static ?PaypalExpressService $instance = null;

	public static function init( $gateway = null ): PaypalExpressService {
		if ( null === self::$instance ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	public function __construct( $gateway = null ) {
		if ( $gateway instanceof PaymentGateway ) {
			$this->gateway = $gateway;
		} else {
			$this->gateway = Payment_Gateways::get_instance()->get_gateway( 'paypal' );
		}

		$this->init_settings();
	}

	public function init_settings() {
		global $wp;

		if ( ! $this->gateway->is_enabled || 'paypal' !== $this->gateway->id ) {
			return;
		}

		$this->is_live       = $this->gateway->get_option( 'is_production', true );
		$key_type            = $this->is_live ? 'production' : 'sandbox';
		$this->client_id     = $this->gateway->get_option( 'client_id_' . $key_type, '' );
		$this->client_secret = $this->gateway->get_option( 'client_secret_' . $key_type, '' );
		$this->currency      = Formatting::get_currency();
		$this->redirect_url  = home_url( $wp->request );
	}

	public static function validate_credentials( string $client_id, string $client_secret, bool $is_live = true ) {
		$query_args = [
			'method'    => 'POST',
			'headers'   => [
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => self::get_auth_header( $client_id, $client_secret ),
			],
			'sslverify' => apply_filters( 'storeengine/paypal_request_sslverify', false ),
			'timeout'   => apply_filters( 'storeengine/paypal_request_timeout', 30 ),
			'body'      => [ 'grant_type' => 'client_credentials' ],
		];

		if ( ! empty( $body ) ) {
			$query_args['body'] = $body;
		}

		$result = wp_remote_request( self::get_api_url( 'v1/oauth2/token', $is_live ), $query_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = json_decode( wp_remote_retrieve_body( $result ) );

		if ( ! isset( $result->access_token ) ) {
			return new WP_Error( $result->error, $result->error_description );
		}

		return $result->access_token;
	}

	protected static function get_auth_header( string $client_id, string $client_secret ): string {
		return 'Basic ' . base64_encode( $client_id . ':' . $client_secret ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @throws StoreEngineException
	 */
	public function api_get( string $endpoint, array $args = [], bool $is_live = true ) {
		return $this->api_request( $endpoint, $is_live, $args );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function api_post( string $endpoint, array $args = [], bool $is_live = true ) {
		return $this->api_request( $endpoint, $is_live, $args, 'POST' );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function api_patch( string $endpoint, array $args = [], bool $is_live = true ) {
		return $this->api_request( $endpoint, $is_live, $args, 'PATCH' );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function api_delete( string $endpoint, array $args = [], bool $is_live = true ) {
		return $this->api_request( $endpoint, $is_live, $args, 'DELETE' );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function api_request( string $endpoint, $is_live = true, $args = [], $method = 'GET' ) {
		// Add request to the api URL.
		$url    = self::get_api_url( $endpoint, $is_live );
		$method = strtoupper( $method );

		// If method is GET we have to add the args as URL params.
		if ( 'GET' === $method ) {
			$url = add_query_arg( $args, $url );
		}

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => self::get_auth_header( $this->client_id, $this->client_secret ),
		];

		$headers = isset( $args['headers'] ) ? wp_parse_args( $args['headers'], $headers ) : $headers;
		$body    = $args['body'] ?? $args;

		// If request is POST then we have to encode the body.
		if ( ! empty( $body ) && 'POST' === $method ) {
			if ( 'application/json' === $headers['Content-Type'] ) {
				$body = wp_json_encode( $body );
			}
		}

		$query_args = [
			'method'    => $method,
			'headers'   => $headers,
			'sslverify' => apply_filters( 'storeengine/paypal_request_sslverify', false ),
			'timeout'   => apply_filters( 'storeengine/paypal_request_timeout', 30 ),
		];

		if ( ! empty( $body ) ) {
			$query_args['body'] = $body;
		}

		$query_result  = wp_remote_request( $url, $query_args );
		$body_decoded  = json_decode( wp_remote_retrieve_body( $query_result ) );
		$response_code = wp_remote_retrieve_response_code( $query_result );

		if ( empty( $body_decoded ) ) {
			$body_decoded = [];
		}

		if ( $response_code >= 400 ) {
			if ( isset( $body_decoded->message ) ) {
				$message = esc_html( $body_decoded->message );
			} else {
				if ( isset( $body_decoded->name ) ) {
					$message = sprintf( esc_html__( 'Unknown error. Code: %s', 'storeengine' ), esc_html( $body_decoded->name ) );
				} else {
					$message = esc_html__( 'Unknown error.', 'storeengine' );
				}
			}

			$data['response'] = $body_decoded;

			throw new StoreEngineException( esc_html( $message ), $response_code, $data );
		}

		if ( is_wp_error( $query_result ) ) {
			$message = $body_decoded->message ?? $query_result->get_error_message();
			$data    = $query_result->get_error_data();
			if ( ! is_array( $data ) ) {
				$data = [ 'error' => $data ];
			}

			$data['response'] = $body_decoded;

			throw new StoreEngineException( esc_html( $message ), esc_html( $query_result->get_error_code() ), $data );
		}

		return $body_decoded;
	}

	public static function get_api_url( string $endpoint, $is_live = true ): string {
		if ( ! $is_live ) {
			$api_host = self::HTTPS_API_SANDBOX_PAYPAL_COM;
		} else {
			$api_host = self::HTTPS_API_PAYPAL_COM;
		}

		return $api_host . ltrim( $endpoint, '/\\' );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function authorize_order( $id ) {
		return $this->api_post( 'v2/checkout/orders/' . $id . '/authorize', [], $this->is_live );
	}

	/**
	 * Create PayPal order/payment-session for processing payment.
	 *
	 * @param Order $order
	 *
	 * @return array|object|mixed
	 *
	 * @throws StoreEngineException Throws exception if PayPal API fails.
	 *
	 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_create
	 */
	public function create_order( Order $order ) {
		$data = [
			'intent'         => 'CAPTURE',
			'purchase_units' => [
				[
					'amount' => [
						'currency_code'   => $order->get_currency( 'edit' ),
						'value'           => $this->get_paypal_amount( $order->get_total( 'edit' ), $order->get_currency( 'edit' ) ),
						'description'     => 'Payment for ' . get_bloginfo( 'name' ) . ' Order #' . $order->get_id(),
						'soft_descriptor' => get_bloginfo( 'name' ) . '*Order#' . $order->get_id(),
					],
				],
			],
		];

		return $this->api_post( 'v2/checkout/orders', $data, $this->is_live );
	}

	/**
	 * Capture order by PayPal order id.
	 *
	 * @param string $paypal_order_id
	 *
	 * @return array|mixed
	 * @throws StoreEngineException Throws exception if PayPal API fails.
	 *
	 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_capture
	 */
	public function capture_order( string $paypal_order_id ) {
		return $this->api_post( 'v2/checkout/orders/' . $paypal_order_id . '/capture', [], $this->is_live );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function capture_subscription_order( $id ) {
		$data = array(
			'note'         => 'Capture subscription order',
			'capture_type' => 'OUTSTANDING_BALANCE',
			'amount'       => [
				'value'         => '10.00',
				'currency_code' => 'USD',
			],
		);

		return $this->api_post( 'v2/billing/subscriptions/' . $id . '/capture', $data, $this->is_live );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function get_order( $id ) {
		return $this->api_get( 'v2/checkout/orders/' . $id, [], $this->is_live );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function update_order( $id, $op, $attribute, $value ) {
		return $this->api_patch(
			'v2/checkout/orders/' . $id,
			[
				[
					'op'    => $op,
					'path'  => "/purchase_units/@reference_id=='default'/$attribute",
					'value' => $value,
				],
			],
			$this->is_live
		);
	}

	/**
	 * @throws StoreEngineException
	 */
	private function create_subscription_plan( array $plan ) {
		return $this->api_post( 'v1/billing/plans', $plan, $this->is_live );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function create_product_and_subscription( int $product_id, int $price_id ) {
		$product['name']     = get_the_title( $product_id );
		$product['type']     = 'DIGITAL';
		$product['category'] = 'SOFTWARE';
		$product_object      = $this->create_product( $product );

		$product_model = new Product();
		$price_data    = $product_model->get_price_data_by_id( $price_id );

		return $this->create_subscription_plan( [
			'product_id'          => $product_object->id,
			'name'                => $price_data->price_name,
			'billing_cycles'      => [
				[
					'frequency'      => [
						'interval_unit'  => strtoupper( $price_data->settings['payment_duration_type'] ),
						'interval_count' => $price_data->settings['payment_duration'],
					],
					'tenure_type'    => 'REGULAR',
					'sequence'       => 1,
					'total_cycles'   => 0,
					'pricing_scheme' => [
						'fixed_price' => [
							'value'         => (float) $price_data->price,
							'currency_code' => $this->get_currency(),
						],
					],
				],
			],
			'payment_preferences' => [
				'auto_bill_outstanding'     => 'true',
				'setup_fee_failure_action'  => 'CONTINUE',
				'payment_failure_threshold' => 3,
			],
		] );
	}

	/**
	 * @throws StoreEngineException
	 */
	private function create_product( $product ) {
		return $this->api_post(
			'v1/catalogs/products',
			[
				'name'        => $product['name'],
				'description' => $product['description'],
				'type'        => $product['type'],
				'category'    => $product['category'],
				'image_url'   => $product['image_url'],
				'home_url'    => $product['home_url'],
			], $this->is_live
		);
	}

	public function get_currency(): string {
		return $this->currency;
	}

	/**
	 * @throws StoreEngineException
	 */
	public function create_subscription( $id ) {
		return $this->api_post(
			'v1/billing/subscriptions',
			[
				'plan_id'    => $id,
				'start_time' => gmdate( 'c', strtotime( '+1 day' ) ),
			],
			$this->is_live
		);
	}

	/**
	 * @throws StoreEngineException
	 */
	public function create_webhook( array $data ) {
		return $this->api_post( 'v1/notifications/webhooks', $data, $this->is_live );
	}

	/**
	 * List of currencies supported by PayPal
	 *
	 * @return string[]
	 */
	public static function get_supported_currencies(): array {
		return self::$supported_currencies;
	}

	/**
	 * List of currencies supported by PayPal that has no decimals
	 *
	 * @return string[]
	 */
	public static function no_decimal_currencies(): array {
		return self::$zero_decimal_currencies;
	}

	/**
	 * List of currencies with three decimals parts.
	 *
	 * @return array $currencies
	 */
	private static function three_decimal_currencies(): array {
		return self::$three_decimal_currencies;
	}

	/**
	 * Format amount for paypal api.
	 * Amount must be string.
	 *
	 * @param float|int $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return string
	 */
	public static function get_paypal_amount( $total, string $currency = '' ): string {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return (string) absint( round( (float) $total ) );
		}

		if ( in_array( $currency, self::three_decimal_currencies(), true ) ) {
			return (string) Formatting::format_decimal( (float) $total, 3 );
		}

		return (string) Formatting::format_decimal( (float) $total, 2 );
	}
}
