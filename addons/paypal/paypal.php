<?php
/**
 * PayPal Payment Addon.
 *
 * @version 1.5.0
 */

namespace StoreEngine\Addons\Paypal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

final class Paypal extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'paypal';

	public function define_constants() {
		define( 'STOREENGINE_PAYPAL_VERSION', '1.0' );
		define( 'STOREENGINE_PAYPAL_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'paypal/' );
	}

	public function init_addon() {
		add_filter( 'storeengine/payment_gateways', [ $this, 'add_gateway' ] );

		add_action( 'storeengine/gateway/paypal/init', static function ( $gateway ) {
			API::init( $gateway );
			Hooks::init( $gateway );
			if ( $gateway->is_enabled() ) {
				Assets::init( $gateway );
			}
		} );
	}

	public function add_gateway( array $gateways ): array {
		$gateways[] = GatewayPaypal::class;

		return $gateways;
	}
}
