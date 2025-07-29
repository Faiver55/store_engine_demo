<?php
/**
 * Gateway PayPal.
 */

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayPaypal extends PaymentGateway {

	public int $index = 1;

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
	}

	protected function setup() {
		$this->id                 = 'paypal';
		$this->icon               = apply_filters( 'storeengine/paypal_icon', Helper::get_assets_url( 'images/payment-methods/paypal-alt.svg' ) );
		$this->method_title       = __( 'PayPal', 'storeengine' );
		$this->method_description = __( 'PayPal Standard redirects customers to PayPal to enter their payment information.', 'storeengine' );
		$this->has_fields         = true;
		$this->verify_config      = true;
		$this->supports           = [
			'products',
			'refunds',
		];
	}

	/**
	 * Verify Config.
	 *
	 * @param array $config
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function verify_config( array $config ) {
		$is_production = $config['is_production'] ?? true;
		$key_type      = $is_production ? 'production' : 'sandbox';
		$client_id     = $config[ 'client_id_' . $key_type ] ?? '';
		$client_secret = $config[ 'client_secret_' . $key_type ] ?? '';

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the PayPal currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by PayPal. Please update your store currency to one that is supported by PayPal to ensure smooth transactions. Visit the %2$sPayPal currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://developer.paypal.com/api/rest/reference/currency-codes/' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		if ( ! $client_id ) {
			throw new StoreEngineException( __( 'PayPal Client ID is required.', 'storeengine' ), 'paypal-client-id-required', 400 );
		}

		if ( ! $client_secret ) {
			throw new StoreEngineException( __( 'PayPal Client secret is required.', 'storeengine' ), 'paypal-secret-id-required', 400 );
		}

		$response = PaypalExpressService::validate_credentials( $client_id, $client_secret, $is_production );

		if ( is_wp_error( $response ) ) {
			if ( 'http_request_failed' === $response->get_error_code() ) {
				throw StoreEngineException::from_wp_error( $response );
			}

			throw new StoreEngineException( __( 'PayPal API keys are not valid. Please check your client id and client secret.', 'storeengine' ), 'invalid-paypal-api-keys', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, PaypalExpressService::get_supported_currencies(), true );
	}

	public function is_available(): bool {
		if ( ! $this->is_currency_supported() ) {
			return false;
		}

		return parent::is_available();
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'                    => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'PayPal', 'storeengine' ),
				'priority' => 0,
			],
			'description'              => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'default'  => '',
				'priority' => 0,
			],
			'is_production'            => [
				'label'    => __( 'Is Live Mode?', 'storeengine' ),
				'tooltip'  => __( 'Enable PayPal Live (Production) Mode.', 'storeengine' ),
				'type'     => 'checkbox',
				'default'  => true,
				'priority' => 0,
			],
			'client_id_production'     => [
				'label'        => __( 'Client ID', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_secret_production' => [
				'label'        => __( 'Client Secret', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_id_sandbox'        => [
				'label'        => __( 'Client ID (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_secret_sandbox'    => [
				'label'        => __( 'Client Secret (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
		];
	}

	public function payment_fields() {
		$user        = wp_get_current_user();
		$user_email  = '';
		$description = $this->get_description();
		$description = ! empty( $description ) ? $description : '';
		$firstname   = '';
		$lastname    = '';

		if ( $user && $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ?: $user->user_email;
			$firstname  = $user->user_firstname;
			$lastname   = $user->user_lastname;
		}

		if ( ! $this->get_option( 'is_production', true ) ) {
			/** @noinspection HtmlUnknownTarget */
			$description .= ' ' . sprintf(
				/* translators: %s: Link to PayPal sandbox testing guide */
					__( 'SANDBOX ENABLED. You can only use sandbox testing accounts. See the <a href="%s" target="_blank" rel="noopener noreferrer">PayPal Sandbox Testing Guide</a> for more details.', 'storeengine' ),
					'https://developer.paypal.com/tools/sandbox/'
				);
		}

		if ( $description ) {
			echo '<div class="storeengine-payment-method-description storeengine-mb-4">';
			// KSES is running within get_description, but not here since there may be custom HTML returned by extensions.
			echo wpautop( wptexturize( $description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}

		?>
		<fieldset
			id="storeengine-<?php echo esc_attr( $this->id ); ?>-form"
			class="storeengine-<?php echo esc_attr( $this->id ); ?>-form storeengine-payment-form"
			data-email="<?php echo esc_attr( $user_email ); ?>"
			data-full-name="<?php echo esc_attr( trim( $firstname . ' ' . $lastname ) ); ?>"
			data-currency="<?php echo esc_attr( strtolower( Formatting::get_currency() ) ); ?>"
			style="background:transparent;border:none;padding:0;"
		>
			<div id="storeengine-paypal-element" class="storeengine-paypal-elements-field">
				<!-- A PayPal Element will be inserted here by js. -->
			</div>
		</fieldset>
		<?php
	}

	public function process_payment( Order $order ): array {
		/**
		 * Fires before collect payment for PayPal.
		 *
		 * @param $order $order Order object.
		 * @param PaymentGateway $gateway Gateway object.
		 */
		do_action( 'storeengine/api/paypal/before_collect_payment', $order, $this );

		$paypal_order_id = $order->get_meta( '_paypal_order_id' );

		if ( ! $paypal_order_id ) {
			throw new StoreEngineException( __( 'PayPal intent id missing.', 'storeengine' ), 'paypal-intent-id-missing' );
		}

		$order_context = new OrderContext( $order->get_status() );

		// Try capture.
		$result = PaypalExpressService::init()->capture_order( $paypal_order_id );

		if ( empty( $result ) ) {
			throw new StoreEngineException( __( 'Invalid paypal order.', 'storeengine' ), 'invalid_paypal_order', null, 400 );
		}

		$payment_success = 'COMPLETED' === strtoupper( $result->status );

		if ( $payment_success ) {
			$order->set_paid_status( 'paid' );
			// translators: %s is the gateway title.
			$order_context->proceed_to_next_status( 'process_order', $order, sprintf( __( '%s payment captured.', 'storeengine' ), $this->get_title() ) );
		} else {
			$order->set_paid_status( 'on_hold' );
			// Keep the order on hold for admin review.
			$order_context->proceed_to_next_status( 'hold_order', $order, __( 'Payment required review.', 'storeengine' ) );
		}

		if ( isset( $result->purchase_units[0]->payments->captures[0]->id ) ) {
			$order->set_transaction_id( $result->purchase_units[0]->payments->captures[0]->id );
		}

		$order->save();

		/**
		 * Fires after PayPal credentials validation.
		 *
		 * @param array|mixed $result Result.
		 * @param $order $order Order object.
		 * @param PaymentGateway $gateway Gateway object.
		 */
		do_action( 'storeengine/api/paypal/after_collect_payment', $result, $order, $this );

		return [
			'result'   => $payment_success ? 'success' : 'failed',
			'redirect' => $order->get_checkout_order_received_url(),
		];
	}
}

// End of file gateway-paypal.php.
