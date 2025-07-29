<?php
namespace StoreEngine\Addons\Subscription\API\Controllers;

use WP_Error;
use WP_REST_Request;
use DateTimeImmutable;
use StoreEngine\Utils\Constants;
use StoreEngine\Addons\Subscription\Classes\Subscription as SubsModel;
use StoreEngine\Classes\Order;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Edit extends Create {
	protected string $route        = '(?P<id>[\d]+)';
	protected array $ignore_action = [ 'create' ];

	public function args() : array {
		return [
			'id' => [
				'description' => __( 'Unique identifier for the object.', 'storeengine' ),
				'type'        => 'integer',
			],
		];
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 * @throws \DateMalformedStringException
	 */
	public function edit( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		try {
			$subscription_instance = new SubsModel( $id );
		} catch ( Throwable $e ) {
			return rest_ensure_response( new WP_Error( 'invalid_subscription_id', __( 'Subscription not found.', 'storeengine' ), [ 'status' => 404 ] ) );
		}

		try {
			$order = new Order( $subscription_instance->get_parent_order_id() );
		} catch ( Throwable $e ) {
			$order = new Order();
		}

		$meta_fields = $this->validate_meta_fields( $request );
		if ( is_object( $meta_fields ) ) {
			return $meta_fields;
		}

		if ( ( new DateTimeImmutable( $meta_fields['start_date'] ) ) > ( new DateTimeImmutable( 'now' ) ) ) {
			$request['status'] = Constants::SUBSCRIPTION_STATUS_PENDING;
		}
		// set subs data
		do_action( 'storeengine/before_update_subscription', $order );

		$this->set_core_data( $request, $subscription_instance, $order );
		$this->set_address( __FUNCTION__, $request, $subscription_instance, $order );
		$this->set_meta_data( $subscription_instance, $meta_fields, $order );
		$this->set_items( $request, $subscription_instance, $order );
		$this->apply_coupons( $request, $subscription_instance, $order );

		// recalculate
		$order->calculate();
		$subscription_instance->calculate();

		// save
		$order->save();
		$subscription_instance->save();

		// update cheduler
		$this->add_to_schedule( $subscription_instance );

		do_action( 'storeengine/after_create_subscription', $subscription_instance );

		$response = rest_ensure_response( [
			'message'         => __('Subscription successfully updated!', 'storeengine'),
			'subscription_id' => $subscription_instance->get_id(),
			'order_id'        => $order->get_id(),
		], [ 'status' => 200 ]);
		$response->add_links( $this->prepare_links( $subscription_instance, $request ) );
		return $response;
	}
}
