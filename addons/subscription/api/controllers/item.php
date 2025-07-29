<?php

namespace StoreEngine\Addons\Subscription\API\Controllers;

use WP_REST_Request;
use WP_Error;
use StoreEngine\Addons\Subscription\Classes\Subscription as SubsModel;
use StoreEngine\Addons\Subscription\API\Schema\SubscriptionSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Item extends Abstracts\SubscriptionController {
	use Traits\Helper, SubscriptionSchema;
	protected string $route = '(?P<id>[\d]+)';

	public function args() : array {
		return [
			'id' => [
				'description' => __( 'Unique identifier for the object.', 'storeengine' ),
				'type'        => 'integer',
			],
		];
	}

	public function read( WP_REST_Request $request ) {
		$id = absint( $request['id'] );

		try {
			$subs_object = SubsModel::get_subscription( $id );
		} catch ( \Throwable $e ) {
			return rest_ensure_response( new WP_Error( 'invalid_subscription_id', __( 'Subscription not found.', 'storeengine' ), [ 'status' => 404 ] ) );
		}

		if ( empty( $subs_object ) ) {
			return rest_ensure_response( new WP_Error( 'subscription-not-found', __( 'Subscription not found.', 'storeengine' ), [ 'status' => 404 ] ) );
		}

		$subscription = $this->prepare_item_for_response( $subs_object, $request );

		return rest_ensure_response( $subscription );
	}
}
