<?php

namespace StoreEngine\Addons\Webhooks\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Classes\Http;
use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use WP_Error;

class Dispatcher {
	protected int $id;
	protected string $resource;
	protected string $topic;
	protected string $event;
	protected array $payload;
	protected string $triggeredAt;
	protected string $secret;
	protected string $delivery_url;

	public function __construct( int $id, string $topic, array $payload, string $triggeredAt ) {
		$topic              = explode( '_', $topic );
		$this->id           = $id;
		$this->topic        = implode( '.', $topic );
		$this->resource     = $topic[0];
		$this->event        = $topic[1];
		$this->payload      = $payload;
		$this->triggeredAt  = $triggeredAt;
		$this->secret       = get_post_meta( $this->id, '_storeengine_webhook_secret', true );
		$this->delivery_url = get_post_meta( $this->id, '_storeengine_webhook_delivery_url', true );
	}

	protected function generate_signature( $payload ): string {
		$hash_algo = apply_filters( 'storeengine/addons/webhook/hashing_algorithm', 'sha256', $payload, $this->id );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( hash_hmac( $hash_algo, trim( wp_json_encode( $this->payload ) ), wp_specialchars_decode( $this->secret, ENT_QUOTES ), true ) );
	}

	public static function get_instance( int $id, string $event_name, array $payload, string $triggeredAt ): Dispatcher {
		return new static( $id, $event_name, $payload, $triggeredAt );
	}

	protected function get_useragent_string() {
		return apply_filters( 'storeengine/addons/webhook/useragent_string', sprintf( 'StoreEngine/%s Webhook, (WordPress/%s)', STOREENGINE_VERSION, $GLOBALS['wp_version'] ) );
	}

	/**
	 * Get the resource for the webhook, e.g. `order_created`.
	 *
	 * @return string
	 */
	public function get_topic(): string {
		return apply_filters( 'storeengine/addons/webhook/topic', $this->topic, $this->id );
	}

	/**
	 * Get the resource for the webhook, e.g. `order`.
	 *
	 * @return string
	 */
	public function get_resource(): string {
		return apply_filters( 'storeengine/addons/webhook/resource', $this->resource, $this->id );
	}

	/**
	 * Get the event for the webhook, e.g. `created`.
	 *
	 * @return string
	 */
	public function get_event(): string {
		return apply_filters( 'storeengine/addons/webhook/event', $this->event, $this->id );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function dispatch(): void {
		$start_time = microtime( true );

		// Delivery id must be unique for each request & generated before sending the request.
		// We generate a unique hash based on current time and webhook ID and wp auth salt.
		$delivery_id = hash_hmac( 'sha256', $this->id . time(), wp_salt( 'auth' ) );

		// Deliver the data to requested URL.
		$response = Http::request( $this->delivery_url )
			->set_payload( $this->payload )
			->set_user_agent( $this->get_useragent_string() )
			->set_headers( [
				'Content-Type'                       => 'application/json',
				'X-StoreEngine-Webhook-Source'       => home_url( '/' ),
				'X-StoreEngine-Webhook-Topic'        => $this->get_topic(),
				'X-StoreEngine-Webhook-Resource'     => $this->get_resource(),
				'X-StoreEngine-Webhook-Event'        => $this->get_event(),
				'X-StoreEngine-Webhook-Signature'    => $this->generate_signature( $this->payload ),
				'X-StoreEngine-Webhook-ID'           => $this->id,
				'X-StoreEngine-Webhook-Triggered-At' => $this->triggeredAt,
				'X-StoreEngine-Webhook-Delivery-ID'  => $delivery_id,
			] )
			->post();

		$duration = NumberUtil::round( microtime( true ) - $start_time, 5 );

		$http_args = is_wp_error( $response ) ? $response->get_error_data( 'http_args' ) : $response['http_args'];

		$this->log_delivery( $delivery_id, $http_args, $response, $duration );

		do_action( 'storeengine/webhook/delivery', $http_args, $response, $duration, $http_args, $this->id );
	}

	/**
	 * Log the delivery request/response.
	 *
	 * @param string $delivery_id Previously created hash.
	 * @param array $request     Request data.
	 * @param array|WP_Error $response    Response data.
	 * @param float $duration    Request duration.
	 */
	private function log_delivery( string $delivery_id, array $request, $response, float $duration ) {
		if ( is_wp_error( $response ) ) {
			$response_code = $response->get_error_code();
		} else {
			$response_code = wp_remote_retrieve_response_code( $response );
		}

		if ( intval( $response_code ) >= 200 && intval( $response_code ) < 303 ) {
			update_post_meta( $this->id, 'failure_count', 0 );
		} else {
			$count = absint( get_post_meta( $this->id, 'failure_count', true ) ?? 0 );
			update_post_meta( $this->id, 'failure_count', $count + 1 );
			// @TODO show status in admin about failure count
			// @TODO after some failure disable webhook
		}

		if ( ! Constants::is_true( 'WP_DEBUG' ) ) {
			return;
		}

		$message = [
			'Webhook Delivery' => [
				'Delivery ID' => $delivery_id,
				'Date'        => date_i18n( __( 'M j, Y @ G:i', 'storeengine' ), strtotime( 'now' ), true ),
				'URL'         => $this->delivery_url,
				'Duration'    => $duration,
				'Request'     => [
					'Method'  => $request['method'],
					'Headers' => array_merge(
						[ 'User-Agent' => $request['user-agent'] ],
						$request['headers']
					),
				],
				'Body'        => wp_slash( $request['body'] ),
			],
		];

		// Parse response.
		if ( is_wp_error( $response ) ) {
			$response_message = $response->get_error_message();
			$response_headers = [];
			$response_body    = '';
		} else {
			$response_message = wp_remote_retrieve_response_message( $response );
			$response_headers = wp_remote_retrieve_headers( $response );
			$response_body    = wp_remote_retrieve_body( $response );
		}

		$message['Webhook Delivery']['Response'] = [
			'Code'    => $response_code,
			'Message' => $response_message,
			'Headers' => $response_headers,
			'Body'    => $response_body,
		];

		Helper::log_error( print_r( $message, true ) );
	}
}
