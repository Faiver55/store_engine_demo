<?php

namespace StoreEngine\API;

use StoreEngine\API\Schema\AnalyticsSchema;
use StoreEngine\Utils\Helper;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * @deprecated
 */
class Subscription extends WP_REST_Controller {
	use AnalyticsSchema;

	/**
	 * @deprecated
	 */
	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'subscription';
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * @deprecated
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_subscriptions' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => $this->get_collection_params(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			'args' => [
				'id' => [
					'description' => __( 'Unique identifier for the object.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
		] );
	}

	/**
	 * @deprecated
	 */
	public function get_permission_check(): bool {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * @deprecated
	 */
	public function get_subscriptions( $request ): \WP_REST_Response {
		$order_model   = new \StoreEngine\models\Order();
		$subscriptions = $order_model->get_subscription_orders();

		$response      = [];
		$product_model = new \StoreEngine\models\Product();
		foreach ( $subscriptions as $key => $subscription ) {
			$response[ $key ] = $this->prepare_item_for_response( $subscription, $request );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @deprecated
	 */
	public function prepare_item_for_response( $item, $request ): array {
		$product_model = new \StoreEngine\models\Product();
		return array(
			'id'                 => $item['id'],
			'customer_email'     => $item['billing_email'],
			'customer_name'      => $item['order_billing_address']->first_name . ' ' . $item['order_billing_address']->last_name,
			'status'             => $item['status'],
			'amount'             => Helper::currency_format( $item['total_amount'] ),
			'product_name'       => get_the_title( $item['purchase_items'][0]->product_id ),
			'price_name'         => $product_model->get_price_name( $item['purchase_items'][0]->price_id ),
			'price_structure'    => $product_model->get_price_duration( $item['purchase_items'][0]->price_id ),
			'remaining_payments' => 'inf',
			'next_payment_date'  => gmdate( 'd F Y \a\t H:i', $item['meta']['next_payment_date'] ),
			'last_payment_date'  => isset( $item['meta']['last_payment_date'] ) ? gmdate( 'd F Y \a\t H:i',
			$item['meta']['last_payment_date'] ) : gmdate( 'd F Y \a\t H:i',
			strtotime( $item['date_created_gmt'] ) ),
			'payment_method'     => $item['payment_method'],
			'integration'        => '',
			'created_at'         => $item['date_created_gmt'],
			'billing_periods'    => $item['billing_periods'],
		);
	}

	/**
	 * @deprecated
	 */
	public function get_item( $request ) {
		$id           = $request['id'];
		$order_model  = new \StoreEngine\models\Order();
		$subscription = $this->prepare_item_for_response( $order_model->get_subscription_by_order_id( $id ), $request );
		if ( empty( $subscription ) ) {
			return [];
		}

		return rest_ensure_response( $subscription );
	}
}
