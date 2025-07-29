<?php
/**
 * Abstract payment tokens
 *
 * Generic payment tokens functionality which can be extended by individual types of payment tokens.
 *
 * E.G: Credit Card, eCheck.
 *
 * @package StoreEngine\PaymentToken
 */

namespace StoreEngine\Classes\PaymentTokens;

use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Payment_Token
 */
abstract class PaymentToken extends AbstractEntity {

	protected bool $read_extra_data_separately = true;

	protected string $table = 'storeengine_payment_tokens';

	protected string $meta_type = 'payment_token';

	protected string $object_type = 'payment_token';

	protected string $primary_key = 'token_id';

	/**
	 * Core data for this object. Name value pairs (name + default value).
	 *
	 * @var array
	 */
	protected array $data = [
		'gateway_id' => '',
		'token'      => '',
		'is_default' => false,
		'user_id'    => 0,
		'type'       => '',
	];

	/**
	 * Token Type (CC, eCheck, or a custom type added by an extension).
	 * Set by child classes.
	 *
	 * @var string
	 */
	protected string $type = '';

	/**
	 * Prefix for action and filter hooks on data.
	 *
	 * @param string $prop
	 *
	 * @return string
	 */
	protected function get_hook_prefix( string $prop ): string {
		if ( $this->get_type() ) {
			return 'storeengine/' . $this->object_type . '/' . $this->get_type() . '/get/' . $prop;
		}

		return 'storeengine/' . $this->object_type . '/get/' . $prop;
	}

	protected function prepare_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		$props = [
			'gateway_id',
			'token',
			'user_id',
			'type',
		];

		// Don't insert or update `is_default` directly.
		// Only one token can be default, create/update methods are utilizing PaymentTokens::set_users_default()
		// to make sure that one token can be default token.

		foreach ( $props as $prop ) {
			$value         = $this->{"get_$prop"}( 'edit' );
			$format[]      = $this->predict_format( $prop, $value );
			$data[ $prop ] = $value;
		}

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	public function create() {
		if ( ! $this->is_default() && $this->get_user_id() > 0 ) {
			$default_token = PaymentTokens::get_customer_default_token( $this->get_user_id() );
			if ( is_null( $default_token ) ) {
				$this->set_default( true );
			}
		}

		parent::create();

		if ( $this->is_default() && $this->get_user_id() > 0 ) {
			PaymentTokens::set_users_default( $this->get_user_id(), $this->get_id() );
		}
	}

	public function update() {
		parent::update();

		if ( $this->is_default() && $this->get_user_id() > 0 ) {
			PaymentTokens::set_users_default( $this->get_user_id(), $this->get_id() );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the raw payment token.
	 *
	 * @param string $context Context in which to call this.
	 *
	 * @return string|null Raw token
	 */
	public function get_token( string $context = 'view' ) {
		return $this->get_prop( 'token', $context );
	}

	/**
	 * Returns the type of this payment token (CC, eCheck, or something else).
	 * Overwritten by child classes.
	 *
	 * @return string Payment Token Type (CC, eCheck)
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get type to display to user.
	 * Get's overwritten by child classes.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		return $this->get_type();
	}

	/**
	 * Returns the user ID associated with the token or false if this token is not associated.
	 *
	 * @param string $context In what context to execute this.
	 *
	 * @return int|null User ID if this token is associated with a user or 0 if no user is associated
	 */
	public function get_user_id( string $context = 'view' ) {
		return $this->get_prop( 'user_id', $context );
	}

	/**
	 * Returns the ID of the gateway associated with this payment token.
	 *
	 * @param string $context In what context to execute this.
	 *
	 * @return string|null Gateway ID
	 */
	public function get_gateway_id( string $context = 'view' ) {
		return $this->get_prop( 'gateway_id', $context );
	}

	/**
	 * Returns the ID of the gateway associated with this payment token.
	 *
	 * @param string $context In what context to execute this.
	 *
	 * @return bool Gateway ID
	 */
	public function get_is_default( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'is_default', $context ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set the raw payment token.
	 *
	 * @param string $token Payment token.
	 */
	public function set_token( string $token ) {
		$this->set_prop( 'token', $token );
	}

	/**
	 * Set the user ID for the user associated with this order.
	 *
	 * @param int|string $user_id User ID.
	 */
	public function set_user_id( $user_id ) {
		$this->set_prop( 'user_id', absint( $user_id ) );
	}

	/**
	 * Set the gateway ID.
	 *
	 * @param string $gateway_id Gateway ID.
	 */
	public function set_gateway_id( string $gateway_id ) {
		$this->set_prop( 'gateway_id', $gateway_id );
	}

	/**
	 * Marks the payment as default or non-default.
	 *
	 * @param boolean|string $is_default True or false.
	 */
	public function set_default( $is_default ) {
		$this->set_prop( 'is_default', Formatting::string_to_bool( $is_default ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Other Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns if the token is marked as default.
	 *
	 * @return boolean True if the token is default
	 */
	public function is_default(): bool {
		return (bool) $this->get_prop( 'is_default', 'view' );
	}

	/**
	 * @param bool $status
	 *
	 * @return void
	 */
	public function set_default_status( bool $status ) {
		if ( ! $this->get_id() ) {
			return;
		}

		$this->wpdb->update(
			$this->wpdb->prefix . 'storeengine_payment_tokens',
			[ 'is_default' => (int) $status ],
			[ 'token_id' => $this->get_id() ]
		);
	}

	/**
	 * Validate basic token info (token and type are required).
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate(): bool {
		$token = $this->get_prop( 'token', 'edit' );
		if ( empty( $token ) ) {
			return false;
		}

		return true;
	}
}

// End of file payment-token.php.
