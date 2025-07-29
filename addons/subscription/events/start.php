<?php
namespace StoreEngine\Addons\Subscription\Events;

use StoreEngine\Utils\Constants;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Start {

	public static function init(): void {
		$self = new self();
		add_action( 'storeengine/subscription/start', [ $self, 'update' ], 10, 3 );
	}

	public function update( int $id ): void {
		try {
			$subscription = Subscription::get_subscription( $id );
			$subscription->set_status( Constants::SUBSCRIPTION_STATUS_ACTIVE );
			$subscription->set_start_date( current_time( 'mysql' ) );
			$subscription->save();
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			Helper::log_error( $e );
		}
	}
}
