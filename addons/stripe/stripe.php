<?php
/**
 * Stripe Payment Addon.
 *
 * @version 1.5.0
 */

namespace StoreEngine\Addons\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokens;
use StoreEngine\Admin\Notices;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

final class Stripe extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'stripe';

	public function define_constants() {
		define( 'STOREENGINE_STRIPE_VERSION', '1.0' );
		define( 'STOREENGINE_STRIPE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'stripe/' );
	}

	public function init_addon() {
		add_filter( 'storeengine/payment_gateways', [ $this, 'add_gateway' ] );

		add_action( 'storeengine/gateway/stripe/init', static function ( $gateway ) {
			Hooks::init( $gateway );
			StripePaymentTokens::get_instance();
			StripeService::init( $gateway );
			if ( $gateway->is_enabled() ) {
				Ajax::init();
				Api::init();
				Assets::init( $gateway );
			}
		} );
	}

	public function add_gateway( array $gateways ): array {
		$gateways[] = GatewayStripe::class;

		return $gateways;
	}
}
