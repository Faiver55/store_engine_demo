<?php

namespace StoreEngine\Addons\Subscription\API\Controllers;

use WP_REST_Request;
use WP_Error;
use StoreEngine\Addons\Subscription\Classes\Subscription as SubsModel;
use StoreEngine\Addons\Subscription\Traits\Scheduler;
use StoreEngine\Addons\Subscription\API\Schema\SubscriptionSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delete extends Abstracts\SubscriptionController {
	use Traits\Helper, SubscriptionSchema, Scheduler;
	protected string $route = '(?P<id>[\d]+)';

	public function args() : array {
		return [
			'id' => [
				'description' => __( 'Unique identifier for the object.', 'storeengine' ),
				'type'        => 'integer',
			],
		];
	}

	public function delete( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		try {
			$subscription = SubsModel::get_subscription( $id );
		} catch ( \Throwable $e ) {
			return rest_ensure_response( new WP_Error( 'invalid_subscription_id', __( 'Subscription not found.', 'storeengine' ), [ 'status' => 404 ] ) );
		}
		$subs = clone $subscription;
		do_action( 'storeengine/api/before_delete_subscription', $subscription );
		if ( ! $subscription->delete() ) {
			return rest_ensure_response( new WP_Error( 'subscription-is-not-deleted', __( 'Subscription is not deleted.', 'storeengine' ), [ 'status' => 500 ] ) );
		}

		$this->remove_schedule( $subs );

		do_action( 'storeengine/api/after_delete_subscription', $subs );

		return rest_ensure_response( new WP_Error( 'subscription-is-deleted', __( 'Subscription is deleted.', 'storeengine' ), [ 'status' => 200 ] ) );
	}
}
