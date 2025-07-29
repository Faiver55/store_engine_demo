<?php

namespace StoreEngine\Addons\Webhooks\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Addons\Webhooks\Listeners\Product\{
	Created as ProductCreated,
	Updated as ProductUpdated,
	Deleted as ProductDeleted,
	Restored as ProductRestored
};
use StoreEngine\Addons\Webhooks\Listeners\Coupon\{
	Created as CouponCreated,
	Updated as CouponUpdated,
	Deleted as CouponDeleted,
	Restored as CouponRestored
};
use StoreEngine\Addons\Webhooks\Listeners\Order\{
	Created as OrderCreated,
	Updated as OrderUpdated,
	Deleted as OrderDeleted,
	Restored as OrderRestored,
};
use StoreEngine\Addons\Webhooks\Listeners\Customer\{
	Created as CustomerCreated,
	Updated as CustomerUpdated,
	Deleted as CustomerDeleted,
};
use StoreEngine\Classes\StoreengineDatetime;

class Dispatch {
	protected array $listeners = [];

	public static function init() {
		$self = new self();

		$self->listeners = (array) apply_filters( 'storeengine/webhooks_event_listeners', [
			'product_created'  => ProductCreated::class,
			'product_updated'  => ProductUpdated::class,
			'product_deleted'  => ProductDeleted::class,
			'product_restored' => ProductRestored::class,
			'coupon_created'   => CouponCreated::class,
			'coupon_updated'   => CouponUpdated::class,
			'coupon_deleted'   => CouponDeleted::class,
			'coupon_restored'  => CouponRestored::class,
			'order_created'    => OrderCreated::class,
			'order_updated'    => OrderUpdated::class,
			'order_deleted'    => OrderDeleted::class,
			'order_restored'   => OrderRestored::class,
			'customer_created' => CustomerCreated::class,
			'customer_updated' => CustomerUpdated::class,
			'customer_deleted' => CustomerDeleted::class,
		] );

		$self->init_webhooks();

		// Dispatch hook.
		add_action( 'storeengine/webhooks/async_delivery', [ $self, 'async_delivery' ], 10, 4 );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function async_delivery( int $id, string $topic, array $payload, string $triggeredAt ) {
		Dispatcher::get_instance( $id, $topic, $payload, $triggeredAt )->dispatch();
	}

	public function init_webhooks() {
		foreach ( GetPublished::all() as ['id' => $id, 'events' => $events] ) {
			foreach ( $events as $topic ) {
				if ( array_key_exists( $topic, $this->listeners ) ) {
					$cb = function ( int $id, array $payload ) use ( $topic ) {
						as_enqueue_async_action(
							'storeengine/webhooks/async_delivery',
							[
								'webhook'     => $id,
								'topic'       => $topic,
								'payload'     => $payload,
								'triggeredAt' => (string) new StoreEngineDateTime(),
							],
							'storeengine-webhooks'
						);
					};

					call_user_func( [ $this->listeners[ $topic ], 'dispatch' ], $cb, $id );
				}
			}
		}
	}

}
