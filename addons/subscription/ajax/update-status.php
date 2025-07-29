<?php

namespace StoreEngine\Addons\Subscription\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Subscription\Classes\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateStatus extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'change_subscription_status' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update' ],
				'fields'     => [
					'subscription_id' => 'int',
					'status'          => 'string',
				],
			],
		];
	}

	public function update( array $payload ): void {
		$id     = $payload['subscription_id'] ?? null;
		$status = $payload['status'] ?? '';

		if ( ! $id ) {
			wp_send_json_error( __( 'Subscription id is required', 'storeengine' ) );
		}

		if ( ! $status ) {
			wp_send_json_error( __( 'Subscription status is required', 'storeengine' ) );
		}

		if ( ! in_array( $status, Subscription::$whitelisted_status_list, true ) ) {
			wp_send_json_error( __( 'id and status field is required.', 'storeengine' ) );
		}

		try {
			$subscription = Subscription::get_subscription( $id );
			$subscription->set_status( $status );
			$subscription->save();
			do_action( 'storeengine/change_subscription_status', $subscription );
			wp_send_json_success( __( 'Subscription status updated', 'storeengine' ) );
		} catch ( \Throwable $e ) {
			wp_send_json_error( __( 'Subscription not found.', 'storeengine' ) );
		}
	}
}
