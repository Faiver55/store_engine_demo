<?php
/**
 * Abstract payment gateway.
 */

namespace StoreEngine\Payment\Gateways;

use StoreEngine;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Utils\Helper;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Payment_Gateway
 */
abstract class PaymentGateway {
	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	public string $id = '';

	/**
	 * Sort index.
	 *
	 * @var int
	 */
	protected int $index = 0;

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Gateway description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Check if the method is enabled.
	 *
	 * @var bool
	 */
	public bool $is_enabled = false;

	/**
	 * Check if saving payment method is allowed.
	 *
	 * @var bool
	 */
	public bool $saved_cards = false;

	/**
	 * Chosen payment method id.
	 *
	 * @var bool
	 */
	public bool $chosen = false;

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	public string $method_title = '';

	/**
	 * Gateway description.
	 *
	 * @var string
	 */
	public string $method_description = '';

	/**
	 * True if the gateway shows fields on the checkout.
	 *
	 * @var bool
	 */
	public bool $has_fields = false;

	/**
	 * Icon for the gateway (url).
	 *
	 * @var string
	 */
	public string $icon;

	/**
	 * Supported features such as 'refunds'.
	 *
	 * @var array
	 */
	public array $supports = [ 'products' ];

	/**
	 * Maximum transaction amount, zero does not define a maximum.
	 *
	 * @var int
	 */
	public int $max_amount = 0;

	/**
	 * Optional URL to view a transaction.
	 *
	 * @var string
	 */
	public string $view_transaction_url = '';

	/**
	 * Optional label to show for "new payment method" in the payment
	 * method/token selection radio selection.
	 *
	 * @var string
	 */
	public string $new_method_label = '';

	/**
	 * Pay button ID if supported.
	 *
	 * @var string
	 */
	public string $pay_button_id = '';

	/**
	 * Contains a users saved tokens for this gateway.
	 *
	 * @var array
	 */
	protected array $tokens = [];

	/**
	 * Admin field schema.
	 *
	 * @var array
	 */
	protected array $admin_fields = [];

	protected ?array $settings = null;

	protected bool $verify_config = false;

	/**
	 * Returns a users saved tokens for this gateway.
	 *
	 * @return array
	 */
	public function get_tokens(): array {
		if ( count( $this->tokens ) > 0 ) {
			return $this->tokens;
		}

		if ( is_user_logged_in() && $this->supports( 'tokenization' ) ) {
			$this->tokens = PaymentTokens::get_customer_tokens( get_current_user_id(), $this->id );
		}

		return $this->tokens;
	}

	public function get_index():int {
		return $this->index;
	}

	/**
	 * Returns verification endpoint if gateway settings needs any verification.
	 *
	 * @return string|bool
	 */
	public function need_config_verification(): bool {
		return $this->verify_config;
	}

	final public function set_index( int $index ): void {
		$this->index = $index;
	}

	/**
	 * Return the title for admin screens.
	 *
	 * @return string
	 */
	public function get_method_title(): string {
		/**
		 * Filter the method title.
		 *
		 * @param string $title Method title.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_method_title', $this->method_title, $this );
	}

	/**
	 * Return the description for admin screens.
	 *
	 * @return string
	 */
	public function get_method_description(): string {
		/**
		 * Filter the method description.
		 *
		 * @param string $description Method description.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_method_description', $this->method_description, $this );
	}

	/**
	 * Output the gateway settings screen.
	 */
	public function admin_options() {
		// @TODO implement
	}

	public function get_option_key() {
		return 'storeengine_payment_' . $this->id . '_settings';
	}

	public function handle_save_request( $payload ) {
		$this->set_index( absint( $payload['index'] ?? 0 ) );
		$this->is_enabled = (bool) ( $payload['is_enabled'] ?? false );

		$this->set_option( 'index', $this->index );
		$this->set_option( 'is_enabled', $this->is_enabled );

		if ( $this->is_enabled && $this->need_config_verification() && method_exists( $this, 'verify_config' ) ) {
			/**
			 * @deprecated
			 */
			do_action( "storeengine/admin/verify_payment_{$this->id}_config", $payload );
			$this->verify_config( $payload );
		}

		foreach ( array_keys( $this->admin_fields ) as $field ) {
			$this->set_option( $field, $payload[ $field ] ?? '' );
		}

		$this->save_settings();
		$this->init_settings( true );

		do_action_ref_array( "storeengine/payment_gateway/$this->id/settings_saved", [ &$this ] );
	}

	/**
	 * Init settings for gateways.
	 */
	public function init_settings( $reload = false ) {
		if ( null !== $this->settings && ! $reload ) {
			return;
		}
		$settings = get_option( $this->get_option_key(), null );

		// If there are no settings defined, use defaults.
		if ( ! is_array( $settings ) ) {
			$form_fields = $this->get_admin_fields();
			$settings    = array_merge( array_fill_keys( array_keys( $form_fields ), '' ), wp_list_pluck( $form_fields, 'default' ) );
		}

		if ( ! array_key_exists( 'is_enabled', $settings ) ) {
			$settings['is_enabled'] = false;
		} else {
			$settings['is_enabled'] = is_string( $settings['is_enabled'] ) || is_numeric( $settings['is_enabled'] ) ? in_array( $settings['is_enabled'], [ 'true', 'yes', 'on', '1', 1 ], true ) : $settings['is_enabled'];
		}

		if ( ! array_key_exists( 'index', $settings ) ) {
			$settings['index'] = 0;
		} else {
			$settings['index'] = (int) $settings['index'];
		}

		$this->settings   = $settings;
		$this->index      = $settings['index'];
		$this->is_enabled = true === $settings['is_enabled'];
	}

	public function get_settings(): array {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		return $this->settings;
	}

	/**
	 * Get option from DB.
	 *
	 * Gets an option from the settings API, using defaults if necessary to prevent undefined notices.
	 *
	 * @param string $key Option key.
	 * @param  mixed  $empty_value Value when empty.
	 *
	 * @return mixed The value specified for the option or a default value for the option.
	 */
	public function get_option( string $key, $empty_value = null ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$form_fields            = $this->get_admin_fields();
			$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->get_field_default( $form_fields[ $key ] ) : '';
		}

		if ( ! is_null( $empty_value ) && '' === $this->settings[ $key ] ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}

	/**
	 * Update a single option.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Value to set.
	 * @return bool was anything saved?
	 */
	public function update_option( string $key, $value = '' ): bool {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		$this->settings[ $key ] = $value;

		return $this->save_settings();
	}

	public function set_option( string $key, $value = '' ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		$this->settings[ $key ] = $value;
	}

	public function set_settings( array $settings ): void {
		$this->settings = $settings;
	}

	public function save_settings(): bool {
		return update_option( $this->get_option_key(), apply_filters( 'storeengine/payment_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
	}

	protected function get_field_default( $field ) {
		return empty( $field['default'] ) ? '' : $field['default'];
	}

	/**
	 * Return whether this gateway still requires setup to function.
	 *
	 * When this gateway is toggled on via AJAX, if this returns true a
	 * redirect will occur to the settings page instead.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {
		return false;
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param Order|null $order Order object.
	 * @return string
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = add_query_arg( 'order_hash', '', Helper::get_page_permalink( 'thankyou_page' ) );
		}

		/**
		 * Filter the return url.
		 *
		 * @param string $return_url Return URL.
		 * @param Order|null $order Order object.
		 * @return string
		 */
		return apply_filters( 'storeengine/get_return_url', $return_url, $order );
	}

	/**
	 * Get a link to the transaction on the 3rd party gateway site (if applicable).
	 *
	 * @param  Order $order the order object.
	 * @return string transaction URL, or empty string.
	 */
	public function get_transaction_url( $order ): string {
		// @TODO implement

		$return_url     = '';
		$transaction_id = $order->get_transaction_id();

		if ( ! empty( $this->view_transaction_url ) && ! empty( $transaction_id ) ) {
			$return_url = sprintf( $this->view_transaction_url, $transaction_id );
		}

		/**
		 * Filter the transaction url.
		 *
		 * @param string $return_url Transaction URL.
		 * @param Order|null $order Order object.
		 * @return string
		 */
		return apply_filters( 'storeengine_get_transaction_url', $return_url, $order, $this );
	}

	/**
	 * Get the order total in checkout and pay_for_order.
	 *
	 * @return float
	 */
	protected function get_order_total() {
		$order_pay = get_query_var( 'order_pay' );
		if ( 'true' === $order_pay ) {
			$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );
			return ! $order ? 0 : (float) $order->get_total();
		} elseif ( Helper::cart()->get_total( 'order_pay' ) ) {
			return (float) Helper::cart()->get_total( 'order_pay' );
		} else {
			return 0;
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$is_available = $this->is_enabled;

		if ( StoreEngine::init()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		if ( Helper::cart() && Helper::cart()->has_subscription_product() && ! $this->supports( 'subscriptions' ) ) {
			$is_available = false;
		}

		return $is_available;
	}

	public function is_enabled(): bool {
		return $this->is_enabled;
	}

	/**
	 * Checks if the setting to allow the user to save cards is enabled.
	 *
	 * @return bool Whether the setting to allow saved cards is enabled or not.
	 */
	public function is_saved_cards_enabled(): bool {
		return $this->saved_cards;
	}

	/**
	 * Check if the gateway has fields on the checkout.
	 *
	 * @return bool
	 */
	public function has_fields(): bool {
		return $this->has_fields;
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		$kss_rules = [
			'br'   => true,
			'img'  => [
				'alt'   => true,
				'class' => true,
				'src'   => true,
				'title' => true,
			],
			'p'    => [
				'class' => true,
			],
			'span' => [
				'class' => true,
				'title' => true,
			],
		];
		$title     = wp_kses( force_balance_tags( stripslashes( $this->title ) ), $kss_rules );

		/**
		 * Filter the gateway title.
		 *
		 * @param string $title Gateway title.
		 * @param string $id Gateway ID.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_title', $title, $this->id );
	}

	/**
	 * Return the gateway's description.
	 *
	 * @return string
	 */
	public function get_description() {
		/**
		 * Filters the gateway description.
		 *
		 * Descriptions can be overridden by extending this method or through the use of `storeengine_gateway_description`
		 * To avoid breaking custom HTML that may be returned we cannot enforce KSES at render time, so we run it here.
		 *
		 * @param string $description Gateway description.
		 * @param string $id Gateway ID.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_description', wp_kses_post( $this->description ), $this->id );
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon_url(): string {
		/**
		 * Filter the gateway icon.
		 *
		 * @param string $icon Gateway icon.
		 * @param string $id Gateway ID.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_icon', $this->icon, $this->id );
	}

	public function has_icon(): bool {
		return ! empty( $this->get_icon_url() );
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		$icon = $this->has_icon() ? '<img src="' . esc_url( $this->get_icon_url() ) . '" alt="' . esc_attr( $this->get_title() ) . '"/>' : '';

		/**
		 * Filter the gateway icon.
		 *
		 * @param string $icon Gateway icon.
		 * @param string $id Gateway ID.
		 * @return string
		 */
		return apply_filters( 'storeengine/gateway_icon_html', $icon, $this->id );
	}

	/**
	 * Return the gateway's pay button ID.
	 *
	 * @return string
	 */
	public function get_pay_button_id() {
		return sanitize_html_class( $this->pay_button_id );
	}

	/**
	 * Set as current gateway.
	 *
	 * Set this as the current gateway.
	 */
	public function set_current() {
		$this->chosen = true;
	}

	public function is_current(): bool {
		return $this->chosen;
	}

	public function get_instructions( ?Order $order = null ): ?string {
		if ( property_exists( $this, 'instructions' ) ) {
			return apply_filters( 'storeengine/gateway/' . $this->id . '_instructions', trim( $this->instructions ), $order );
		}

		return null;
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		$instructions = $this->get_instructions();
		if ( $instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $instructions ) ) );
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 *
	 * @param Order $order Order object.
	 * @param bool $sent_to_admin Sent to admin.
	 * @param bool $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( Order $order, bool $sent_to_admin, bool $plain_text = false ) {
		$instructions = $this->get_instructions();
		if ( $instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			/**
			 * Filter the email instructions order status.
			 *
			 * @param string $status The default status.
			 * @param object $order  The order object.
			 */
			$instructions_order_status = apply_filters( 'storeengine/gateway/' . $this->id . '_email_instructions_order_status', OrderStatus::ON_HOLD, $order );
			if ( $order->has_status( $instructions_order_status ) ) {
				echo wp_kses_post( wpautop( wptexturize( $instructions ) ) . PHP_EOL );
			}
		}
	}

	/**
	 * Process Payment.
	 *
	 * Process the payment. Override this in your gateway. When implemented, this should.
	 * return the success and redirect in an array. e.g:
	 *
	 *        return array(
	 *            'result'   => 'success',
	 *            'redirect' => $this->get_return_url( $order )
	 *        );
	 *
	 * @param Order $order Order.
	 *
	 * @return array|WP_Error
	 */
	public function process_payment( Order $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- used by subclasses.
		return [];
	}

	public function get_total_payment( ?Order $order = null ) {
		// Free trial subscriptions without a sign-up fee, or any other type
		// of order with a `0` amount should fall into the logic below.
		$amount = is_null( StoreEngine::init()->get_cart() ) ? 0 : StoreEngine::init()->get_cart()->get_total( 'edit' );
		if ( $order && $order->get_id() ) {
			$amount = $order->get_total();
		}

		return $amount;
	}

	/**
	 * Returns true if a payment is needed for the current cart or order.
	 * Pre-Orders and Subscriptions may not require an upfront payment, so we need to check whether
	 * the payment is necessary to decide for either a setup intent or a payment intent.
	 *
	 * @param Order $order The order ID being processed.
	 *
	 * @return bool Whether a payment is necessary.
	 */
	public function is_payment_needed( Order $order ): bool {
		return 0 < $this->get_total_payment( $order );
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param int $order_id Order ID.
	 * @param  float|string|null $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( int $order_id, $amount = null, string $reason = '' ) {
		// Refund without an amount is a no-op, but required to succeed
		if ( '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		return false;
	}

	protected function get_refund_log_meta_key(): string {
		return '_' . $this->id . '_lock_refund';
	}

	/**
	 * Locks an order for refund processing for 5 minutes.
	 *
	 * @param Order $order  The order that is being refunded.
	 *
	 * @return bool A flag that indicates whether the order is already locked.
	 */
	public function lock_order_refund( Order $order ): bool {
		$order->read_meta_data( true );

		$existing_lock = $order->get_meta( $this->get_refund_log_meta_key(), true );

		if ( $existing_lock ) {
			$expiration = (int) $existing_lock;

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		$new_lock = time() + 5 * MINUTE_IN_SECONDS;

		$order->update_meta_data( $this->get_refund_log_meta_key(), $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing refund.
	 *
	 * @param Order $order The order that is being unlocked.
	 */
	public function unlock_order_refund( Order $order ) {
		$order->delete_meta_data( $this->get_refund_log_meta_key() );
		$order->save_meta_data();
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return void
	 *
	 * @throws StoreEngineException
	 */
	public function validate_fields(): void {
	}

	/**
	 * Default payment fields display. Override this in your gateway to customize displayed fields.
	 *
	 * By default, this renders the payment gateway description.
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( $description ) {
			echo '<div class="storeengine-payment-method-description">';
			// KSES is running within get_description, but not here since there may be custom HTML returned by extensions.
			echo wpautop( wptexturize( $description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}
	}

	/**
	 * Check if a gateway supports a given feature.
	 *
	 * Gateways should override this to declare support (or lack of support) for a feature.
	 * For backward compatibility, gateways support 'products' by default, but nothing else.
	 *
	 * @param string $feature string The name of a feature to test support for.
	 *
	 * @return bool True if the gateway supports the feature, false otherwise.
	 */
	public function supports( string $feature ): bool {
		/**
		 * Filter the gateway supported features.
		 *
		 * @param boolean $supports If the gateway supports the feature.
		 * @param string $feature Feature to check.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'storeengine/payment_gateway_supports', in_array( $feature, $this->supports, true ), $feature, $this );
	}

	/**
	 * Can the order be refunded via this gateway?
	 *
	 * Should be extended by gateways to do their own checks.
	 *
	 * @param  Order $order Order object.
	 * @return bool If false, the automatic refund button is hidden in the UI.
	 */
	public function can_refund_order( $order ) {
		return $order && $this->supports( 'refunds' );
	}

	/**
	 * Enqueues our tokenization script to handle some of the new form options.
	 */
	public function tokenization_script() {
		add_filter( 'storeengine/frontend_scripts_data', static function ( $data ) {
			$data['tokenization_data'] = [
				'is_registration_required' => Helper::is_registration_required(),
				'is_logged_in'             => is_user_logged_in(),
			];

			return $data;
		} );
	}

	/**
	 * Grab and display our saved payment methods.
	 */
	public function saved_payment_methods() {
		$html = '<ul class="storeengine-saved-payment-methods" style="list-style:none;margin:0;padding:0" data-count="' . esc_attr( count( $this->get_tokens() ) ) . '">';

		foreach ( $this->get_tokens() as $token ) {
			$html .= $this->get_saved_payment_method_option_html( $token );
		}

		$html .= $this->get_new_payment_method_option_html();
		$html .= '</ul>';

		echo apply_filters( 'storeengine/form_saved_payment_methods_html', $html, $this ); // @codingStandardsIgnoreLine
	}

	/**
	 * Gets saved payment method HTML from a token.
	 *
	 * @param  PaymentToken $token Payment Token.
	 * @return string Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html = sprintf(
			'<li class="storeengine-saved-payment-methods-token">
				<input id="storeengine-%1$s-payment-token-%2$s" type="radio" name="storeengine-%1$s-payment-token" value="%2$s" style="width:auto;" class="storeengine-saved-payment-methods-token-input" %4$s />
				<label for="storeengine-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
			esc_attr( $this->id ),
			esc_attr( $token->get_id() ),
			esc_html( $token->get_display_name() ),
			checked( $token->is_default(), true, false )
		);

		/**
		 * Filter the saved payment method HTML.
		 *
		 * @param string $html HTML for the saved payment methods.
		 * @param string $token Token.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'storeengine_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
	}

	/**
	 * Displays a radio button for entering a new payment method (new CC details) instead of using a saved method.
	 * Only displayed when a gateway supports tokenization.
	 */
	public function get_new_payment_method_option_html() {
		/**
		 * Filter the saved payment method label.
		 *
		 * @param string $label Label.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		$label = apply_filters( 'storeengine_payment_gateway_get_new_payment_method_option_html_label', $this->new_method_label ? $this->new_method_label : __( 'Use a new payment method', 'storeengine' ), $this );
		$html  = sprintf(
			'<li class="storeengine-saved-payment-methods-new">
				<input id="storeengine-%1$s-payment-token-new" class="storeengine-%1$s-payment-token-new storeengine-saved-payment-methods-new storeengine-saved-payment-methods-token-input" type="radio" name="storeengine-%1$s-payment-token" value="new" style="width:auto;"/>
				<label for="storeengine-%1$s-payment-token-new">%2$s</label>
			</li>',
			esc_attr( $this->id ),
			esc_html( $label )
		);
		/**
		 * Filter the saved payment method option.
		 *
		 * @param string $html Option HTML.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'storeengine_payment_gateway_get_new_payment_method_option_html', $html, $this );
	}

	/**
	 * Outputs a checkbox for saving a new payment method to the database.
	 */
	public function save_payment_method_checkbox() {
		$html = sprintf(
			'<p class="storeengine-save-new-payment-methods">
				<input id="storeengine-%1$s-new-payment-method" class="storeengine-%1$s-new-payment-method" name="storeengine-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="storeengine-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html__( 'Save to account', 'storeengine' )
		);
		/**
		 * Filter the saved payment method checkbox HTML
		 *
		 * @param string $html Checkbox HTML.
		 * @param PaymentGateway $this Payment gateway instance.
		 * @return string
		 */
		echo apply_filters( 'storeengine_payment_gateway_save_new_payment_method_option_html', $html, $this ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Add payment method via account screen. This should be extended by gateway plugins.
	 *
	 * @param array $payload
	 *
	 * @return array
	 */
	public function add_payment_method( array $payload ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- used by subclasses.
		return [
			'result'   => 'failure',
			'redirect' => Helper::get_account_endpoint_url( 'payment-methods' ),
		];
	}

	public function has_subscription( Order $order ): bool {
		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			return false;
		}

		if ( $order->get_meta( '_subscription_renewal' ) ) {
			return true;
		}

		return SubscriptionCollection::order_has_subscription( $order->get_id() );
	}

	protected function init_admin_fields() {
	}

	public function get_admin_fields() {
		return apply_filters( 'storeengine/payment_gateway_admin_fields_' . $this->id, array_map( [ $this, 'set_defaults' ], $this->admin_fields ) );
	}

	public function get_admin_fields_sorted() {
		$filtered_fields = $this->get_admin_fields();

		uasort( $filtered_fields, fn( $a, $b ) => ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 ) );

		return $filtered_fields;
	}

	/**
	 * Set default required properties for each field.
	 *
	 * @param array $field Setting field array.
	 * @return array
	 */
	protected function set_defaults( array $field ): array {
		if ( ! isset( $field['default'] ) ) {
			$field['default'] = '';
		}
		return $field;
	}
}

// End of file payment-gateway.php.
