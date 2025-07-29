<?php

namespace StoreEngine\Addons\Subscription\Traits;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Scheduler {

	public function add_to_schedule( Subscription $subscription ): void {
		$this->remove_schedule( $subscription );

		if ( $subscription->get_status() !== Constants::SUBSCRIPTION_STATUS_ACTIVE ) {
			as_schedule_single_action( $subscription->get_start_date()->getTimestamp(), 'storeengine/subscription/start', [ 'subscription_id' => $subscription->get_id() ] );
		}

		as_schedule_single_action( $subscription->get_end_date()->getTimestamp(), 'storeengine/subscription/renewal', [ 'subscription_id' => $subscription->get_id() ]
		);
	}

	public function remove_schedule( Subscription $subscription ): void {
		$args = [ 'subscription_id' => $subscription->get_id() ];

		as_unschedule_action( 'storeengine/subscription/start', $args );
		as_unschedule_action( 'storeengine/subscription/renewal', $args );
	}
}
