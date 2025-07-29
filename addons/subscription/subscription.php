<?php

namespace StoreEngine\Addons\Subscription;

use StoreEngine\Addons\Subscription\Classes\SubscriptionCoupon;
use StoreEngine\Addons\Subscription\Classes\SubscriptionScheduler;
use StoreEngine\Admin\Notices;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Traits\Singleton;
use StoreEngine\Addons\Subscription\API\Register as InitRestRoutes;
use StoreEngine\Addons\Subscription\Events\{
	CreateSubscription,
	UpdateSubscriptionStatus,
	Renewal,
	Start
};
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Subscription extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'subscription';

	public function define_constants() {
		define( 'STOREENGINE_SUBSCRIPTION_VERSION', '1.0' );
		define( 'STOREENGINE_SUBSCRIPTION_VERSION_NAME', 'storeengine_subscription_version' );
		define( 'STOREENGINE_SUBSCRIPTION_SETTINGS_NAME', 'storeengine_subscription_settings' );
	}

	public function init_addon() {
		CreateSubscription::init();
		UpdateSubscriptionStatus::init();
		Renewal::init();
		Ajax::init();
		InitRestRoutes::init();
		Start::init();
		SubscriptionScheduler::init();
		Hooks::init();
		SubscriptionCoupon::init();

		add_filter( 'storeengine/admin_menu_list', [ $this, 'admin_menu_items' ] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-subscriptions' => [
				'title'      => __( 'Subscriptions', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 60,
			],
		] );
	}

	public function addon_activation_hook() {
		Helper::flush_rewire_rules();

		// Clean deprecated queue.
		\StoreEngine::init()->queue()->cancel_all( 'storeengine_subscription_renewal' );
		\StoreEngine::init()->queue()->cancel_all( 'storeengine_create_renewal_order_schedule' );
	}

	public function addon_deactivation_hook() {
		Helper::flush_rewire_rules();

		// Clean deprecated queue.
		\StoreEngine::init()->queue()->cancel_all( 'storeengine_subscription_renewal' );
		\StoreEngine::init()->queue()->cancel_all( 'storeengine_create_renewal_order_schedule' );
	}
}
