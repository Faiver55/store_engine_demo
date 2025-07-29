<?php
/**
 * Payment Gateways
 *
 * @package StoreEngine/PaymentGateways
 */

namespace StoreEngine;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Order;
use StoreEngine\Traits\Singleton;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment\Gateways\GatewayCod;
use StoreEngine\Payment\Gateways\GatewayBacs;
use StoreEngine\Payment\Gateways\GatewayCheck;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Payment_Gateways
 */
final class Payment_Gateways {
	use Singleton;

	/**
	 * @var PaymentGateway[]
	 */
	protected array $gateways = [];

	protected bool $loaded = false;

	protected function __construct() {
		add_action( 'init', [ $this, 'load_gateways' ] );

		add_filter( 'storeengine/api/settings', [ $this, 'add_to_settings_api' ] );
		add_filter( 'storeengine/payment_settings_fields', [ $this, 'add_to_payment_settings_fields' ] );
	}

	public function load_gateways() {
		if ( $this->loaded ) {
			return;
		}

		$this->loaded = true;

		$gateways = [
			GatewayCod::class,
			GatewayBacs::class,
			GatewayCheck::class,
		];

		$gateways = apply_filters( 'storeengine/payment_gateways', $gateways );

		foreach ( $gateways as $gateway ) {
			$gateway = new $gateway();
			if ( ! is_a( $gateway, PaymentGateway::class ) ) {
				continue;
			}

			if ( $gateway->need_config_verification() && method_exists( $gateway, 'verify_config' ) ) {
				add_action( "storeengine/api/settings/payment-gateways/verify/$gateway->id", [ $gateway, 'verify_config' ] );
			}

			if ( method_exists( $gateway, 'handle_save_request' ) ) {
				add_action( "storeengine/api/settings/payment-gateways/update/$gateway->id", function ( $payload ) use ( $gateway ) {
					$this->payment_gateway_settings_option_changed( $gateway, $payload, $gateway->get_settings() );
					$gateway->handle_save_request( $payload );
				} );
			}

			if ( has_action( 'storeengine/gateway/' . $gateway->id . '/init' ) ) {
				do_action_ref_array( 'storeengine/gateway/' . $gateway->id . '/init', [ &$gateway ] );
			}

			$this->gateways[] = $gateway;
		}

		uasort( $this->gateways, fn( $a, $b ) => $a->get_index() > $b->get_index() ? 1 : - 1 );

		$this->gateways = array_values( $this->gateways );

		/**
		 * Hook that is called when the payment gateways have been initialized.
		 *
		 * @param Payment_Gateways $wc_payment_gateways The payment gateways instance.
		 */
		do_action( 'storeengine/payment_gateways_initialized', $this );
	}

	public function get_enabled_gateways(): array {
		return array_filter( $this->gateways, fn( $gateway ) => $gateway->is_enabled );
	}

	public function get_available_gateways(): array {
		return array_filter( $this->gateways, fn( $gateway ) => $gateway->is_available() );
	}

	/**
	 * @return PaymentGateway[]
	 */
	public function get_gateways():array {
		return $this->gateways;
	}

	public function get_gateway( string $id ): ?PaymentGateway {
		if ( ! $id ) {
			return null;
		}

		$gateway = current( array_filter( $this->gateways, fn( $gateway ) => $gateway->id === $id ) );

		return $gateway instanceof PaymentGateway ? $gateway : null;
	}

	public function add_gateways_data( array $data ): array {
		return array_merge( $data, [
			'payment_gateways' => array_map( fn( $gateway ) => [
				'id'            => $gateway->id,
				'index'         => $gateway->get_index(),
				'label'         => $gateway->get_method_title(),
				'description'   => $gateway->get_method_description(),
				'fields'        => $gateway->get_admin_fields_sorted(),
				'settings'      => $gateway->get_settings(),
				'verify_config' => $gateway->need_config_verification(),
			], $this->gateways ),
		] );
	}

	public function add_to_settings_api( $settings ) {
		$settings->payment_gateways = array_map( fn( $gateway ) => [
			'id'            => $gateway->id,
			'index'         => $gateway->get_index(),
			'label'         => $gateway->get_method_title(),
			'description'   => $gateway->get_method_description(),
			'fields'        => $gateway->get_admin_fields_sorted(),
			'settings'      => $gateway->get_settings(),
			'verify_config' => $gateway->need_config_verification(),
			'icon'          => $gateway->get_icon_url(),
		], $this->gateways );

		return $settings;
	}

	public function add_to_payment_settings_fields( array $fields ): array {
		$type_mapping = [
			'password' => 'text',
			'checkbox' => 'boolean',
		];

		foreach ( $this->gateways as $gateway ) {
			$fields[ $gateway->id ] = [
				'is_enabled' => 'boolean',
				'index'      => 'int',
			];
			foreach ( $gateway->get_admin_fields() as $field => $cfg ) {
				$type = $cfg['type'] ?? 'text';
				if ( 'repeater' === $type ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					// @TODO repeater ...
				} else {
					$fields[ $gateway->id ][ $field ] = $type_mapping[ $type ] ?? $type;
				}
			}
		}

		return $fields;
	}

	/**
	 * Callback for when a gateway settings option was added or updated.
	 *
	 * @param PaymentGateway $gateway   The gateway for which the option was added or updated.
	 * @param array              $payload     New value.
	 * @param ?array             $old_settings    Option name.
	 */
	private function payment_gateway_settings_option_changed( PaymentGateway $gateway, array $payload, ?array $old_settings = null ) {
		if ( ! $this->was_gateway_enabled( $payload, $old_settings ) ) {
			return;
		}

		// This is a change to a payment gateway's settings and it was just enabled. Let's send an email to the admin.
		// "untitled" shouldn't happen, but just in case.
		$this->notify_admin_payment_gateway_enabled( $gateway );
	}

	/**
	 * Email the site admin when a payment gateway has been enabled.
	 *
	 * @param PaymentGateway $gateway The gateway that was enabled.
	 * @return bool Whether the email was sent or not.
	 */
	private function notify_admin_payment_gateway_enabled( $gateway ) {
		$admin_email          = get_option( 'admin_email' );
		$user                 = get_user_by( 'email', $admin_email );
		$username             = $user ? $user->user_login : $admin_email;
		$gateway_title        = $gateway->get_method_title();
		$gateway_settings_url = esc_url_raw( self_admin_url( 'admin.php?page=storeengine-settings&path=payment-method&method=' . $gateway->id ) );
		$site_name            = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$site_url             = home_url();

		/**
		 * Allows adding to the addresses that receive payment gateway enabled notifications.
		 *
		 * @param array              $email_addresses The array of email addresses to notify.
		 * @param PaymentGateway $gateway The gateway that was enabled.
		 * @return array             The augmented array of email addresses to notify.
		 */
		$email_addresses   = apply_filters( 'storeengine/payment_gateway_enabled_notification_email_addresses', [], $gateway );
		$email_addresses[] = $admin_email;
		$email_addresses   = array_unique( array_filter( $email_addresses, fn( $email_address ) =>filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// @TODO implement error logger.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Payment gateway enabled: "%s"', $gateway_title ) );
		}

		$email_text = sprintf(
		/* translators: Payment gateway enabled notification email. 1: Username, 2: Gateway Title, 3: Site URL, 4: Gateway Settings URL, 5: Admin Email, 6: Site Name, 7: Site URL. */
			__(
				'Howdy %1$s,

The payment gateway "%2$s" was just enabled on this site:
%3$s

If this was intentional you can safely ignore and delete this email.

If you did not enable this payment gateway, please log in to your site and consider disabling it here:
%4$s

This email has been sent to %5$s

Regards,
All at %6$s
%7$s',
				'storeengine'
			),
			$username,
			$gateway_title,
			$site_url,
			$gateway_settings_url,
			$admin_email,
			$site_name,
			$site_url
		);

		if ( '' !== get_option( 'blogname' ) ) {
			$site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		} else {
			$site_title = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		return wp_mail(
			$email_addresses,
			sprintf(
			/* translators: Payment gateway enabled notification email subject. %s1: Site title, $s2: Gateway title. */
				__( '[%1$s] Payment gateway "%2$s" enabled', 'storeengine' ),
				$site_title,
				$gateway_title
			),
			$email_text
		);
	}

	/**
	 * Determines from changes in settings if a gateway was enabled.
	 *
	 * @param array $value New value.
	 * @param array|null $old_value Old value.
	 *
	 * @return bool Whether the gateway was enabled or not.
	 */
	private function was_gateway_enabled( array $value, ?array $old_value = null ): bool {
		if ( null === $old_value ) {
			// There was no old value, so this is a new option.
			return array_key_exists( 'is_enabled', $value ) && $value['is_enabled'];
		}

		$new_val = array_key_exists( 'is_enabled', $value ) ? $value['is_enabled'] : null;
		$old_val = $old_value && array_key_exists( 'is_enabled', $old_value ) ? $old_value['is_enabled'] : null;

		// There was an old value, so this is an update.
		return $new_val && ! $old_val;
	}

	/**
	 * Get gateways.
	 *
	 * @return PaymentGateway[]
	 */
	public function payment_gateways(): array {
		$_available_gateways = [];

		if ( count( $this->gateways ) > 0 ) {
			foreach ( $this->gateways as $gateway ) {
				$_available_gateways[ $gateway->id ] = $gateway;
			}
		}

		return $_available_gateways;
	}

	/**
	 * Get array of registered gateway ids
	 *
	 * @return array of strings
	 */
	public function get_payment_gateway_ids() {
		return wp_list_pluck( $this->gateways, 'id' );
	}

	/**
	 * Get available gateways.
	 *
	 * @return PaymentGateway[]
	 */
	public function get_available_payment_gateways(): array {
		$_available_gateways = [];

		foreach ( $this->gateways as $gateway ) {
			if ( $gateway->is_available() ) {
				if ( ! Helper::is_add_payment_method_page() ) {
					$_available_gateways[ $gateway->id ] = $gateway;
				} elseif ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
					$_available_gateways[ $gateway->id ] = $gateway;
				}
			}
		}

		return array_filter( (array) apply_filters( 'storeengine/available_payment_gateways', $_available_gateways ), [ $this, 'filter_valid_gateway_class' ] );
	}

	protected static array $one_gateway_supports = [];

	public function one_gateway_supports( string $supports_flag ) {
		// Only check if we haven't already run the check
		if ( ! isset( self::$one_gateway_supports[ $supports_flag ] ) ) {
			self::$one_gateway_supports[ $supports_flag ] = false;

			foreach ( $this->get_available_payment_gateways() as $gateway ) {
				if ( $gateway->supports( $supports_flag ) ) {
					self::$one_gateway_supports[ $supports_flag ] = true;
					break;
				}
			}
		}

		return self::$one_gateway_supports[ $supports_flag ];
	}

	/**
	 * Get payment gateway class by order data.
	 *
	 * @param Order|Subscription $order Order instance.
	 * @return PaymentGateway|bool
	 */
	public static function get_payment_gateway_by_order( $order ) {
		Helper::get_payment_gateways()->load_gateways();
		$payment_gateways = Helper::get_payment_gateways()->payment_gateways();

		return isset( $payment_gateways[ $order->get_payment_method() ] ) ? $payment_gateways[ $order->get_payment_method() ] : false;
	}

	public function get_available_payment_gateway( string $id ): ?PaymentGateway {
		if ( ! $id ) {
			return null;
		}

		$available_gateways = $this->get_available_payment_gateways();

		return $available_gateways[ $id ] ?? null;
	}

	/**
	 * Callback for array filter. Returns true if gateway is of correct type.
	 *
	 * @param object $gateway Gateway to check.
	 *
	 * @return bool
	 */
	protected function filter_valid_gateway_class( object $gateway ): bool {
		return $gateway && is_a( $gateway, '\StoreEngine\Payment\Gateways\PaymentGateway' );
	}

	/**
	 * Set the current, active gateway.
	 *
	 * @param PaymentGateway[] $gateways Available payment gateways.
	 */
	public function set_current_gateway( array $gateways ) {
		// Be on the defensive.
		if ( empty( $gateways ) ) {
			return;
		}

		$current_gateway = false;
		$draft_order     = Helper::get_recent_draft_order();

		if ( $draft_order ) {
			$current = $draft_order->get_payment_method( 'edit' );


			if ( $current && isset( $gateways[ $current ] ) ) {
				$current_gateway = $gateways[ $current ];
			}
		}

		if ( ! $current_gateway ) {
			$current_gateway = current( $gateways );
		}

		// Ensure we can make a call to set_current() without triggering an error.
		if ( $current_gateway && is_callable( [ $current_gateway, 'set_current' ] ) ) {
			$current_gateway->set_current();
		}
	}
}
