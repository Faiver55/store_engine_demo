<?php
/**
 * StoreEngine Payment Tokens
 *
 * An API for storing and managing tokens for gateways and customers.
 *
 * @package StoreEngine\Classes
 */

namespace StoreEngine\Classes\PaymentTokens;

use stdClass;
use StoreEngine\Classes\AbstractCollection;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment tokens class.
 */
class PaymentTokens extends AbstractCollection {
	protected static ?PaymentTokens $instance = null;

	public static function get_instance(): ?PaymentTokens {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected string $table = 'storeengine_payment_tokens';

	protected string $primary_key = 'token_id';

	/**
	 * Gets valid tokens from the database based on user defined criteria.
	 *
	 * @param array $args Query arguments {
	 *     Array of query parameters.
	 *
	 * @type string $token_id Token ID.
	 * @type string $user_id User ID.
	 * @type string $gateway_id Gateway ID.
	 * @type string $type Token type.
	 * }
	 * @return PaymentToken[]
	 */
	public static function get_tokens( array $args ): array {
		$args = wp_parse_args( $args, [
			'token_id'   => '',
			'user_id'    => '',
			'gateway_id' => '',
			'type'       => '',
		] );

		$token_results = self::get_instance()->read_tokens( $args );
		$tokens        = [];

		if ( ! empty( $token_results ) ) {
			foreach ( $token_results as $token_result ) {
				$_token = self::get( $token_result->token_id, $token_result );
				if ( ! empty( $_token ) ) {
					$tokens[ $token_result->token_id ] = $_token;
				}
			}
		}

		return $tokens;
	}

	protected function read_tokens( $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			[
				'token_id'   => '',
				'user_id'    => '',
				'gateway_id' => '',
				'type'       => '',
			]
		);

		$sql   = "SELECT * FROM {$this->table}";
		$where = [ '1=1' ];

		if ( $args['token_id'] ) {
			$token_ids = array_map( 'absint', is_array( $args['token_id'] ) ? $args['token_id'] : array( $args['token_id'] ) );
			$where[]   = "token_id IN ('" . implode( "','", array_map( 'esc_sql', $token_ids ) ) . "')";
		}

		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', absint( $args['user_id'] ) );
		}

		if ( $args['gateway_id'] ) {
			$gateway_ids = array( $args['gateway_id'] );
		} else {
			$gateway_ids = Payment_Gateways::get_instance()->get_payment_gateway_ids();
		}

		$page           = isset( $args['page'] ) ? absint( $args['page'] ) : 1;
		$posts_per_page = absint( $args['limit'] ?? get_option( 'posts_per_page' ) );

		$pgstrt = absint( ( $page - 1 ) * $posts_per_page ) . ', ';
		$limits = 'LIMIT ' . $pgstrt . $posts_per_page;

		$gateway_ids[] = '';
		$where[]       = "gateway_id IN ('" . implode( "','", array_map( 'esc_sql', $gateway_ids ) ) . "')";

		if ( $args['type'] ) {
			$where[] = $wpdb->prepare( 'type = %s', $args['type'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql . ' WHERE ' . implode( ' AND ', $where ) . ' ' . $limits );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns an array of payment token objects associated with the passed customer ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @param string $gateway_id Optional Gateway ID for getting tokens for a specific gateway.
	 *
	 * @return PaymentToken[]  Array of token objects.
	 */
	public static function get_customer_tokens( int $customer_id, string $gateway_id = '' ): array {
		if ( $customer_id < 1 ) {
			return [];
		}

		$tokens = self::get_tokens( [
			'user_id'    => $customer_id,
			'gateway_id' => $gateway_id,
			/**
			 * Controls the maximum number of Payment Methods that will be listed via the My Account page.
			 *
			 * @param int $limit Defaults to the value of the `posts_per_page` option.
			 */
			'limit'      => apply_filters( 'storeengine/get_customer_payment_tokens_limit', get_option( 'posts_per_page' ) ),
		] );

		return apply_filters( 'storeengine/get_customer_payment_tokens', $tokens, $customer_id, $gateway_id );
	}

	/**
	 * Returns a customers default token or NULL if there is no default token.
	 *
	 * @param int $customer_id Customer ID.
	 *
	 * @return PaymentToken|null
	 */
	public static function get_customer_default_token( int $customer_id ): ?PaymentToken {
		if ( $customer_id < 1 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		global $wpdb;
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}storeengine_payment_tokens WHERE user_id = %d AND is_default = 1",
				$customer_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $token ) {
			return self::get( $token->token_id, $token );
		} else {
			return null;
		}
	}

	/**
	 * Returns an array of payment token objects associated with the passed order ID.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return PaymentToken[] Array of token objects.
	 */
	public static function get_order_tokens( $order_id ) {
		$order = Helper::get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$token_ids = $order->get_payment_tokens();

		if ( empty( $token_ids ) ) {
			return array();
		}

		$tokens = self::get_tokens( [ 'token_id' => $token_ids ] );

		return apply_filters( 'storeengine/get_order_payment_tokens', $tokens, $order_id );
	}

	/**
	 * Get a token object by ID.
	 *
	 * @param int $token_id Token ID.
	 * @param object $token_result Token result.
	 *
	 * @return null|PaymentToken Returns a valid payment token or null if no token can be found.
	 */
	public static function get( $token_id, $token_result = null ) {
		if ( ! empty( $token_result->type ) ) {
			$type    = $token_result->type;
			$gateway = $token_result->gateway_id;
		} else {
			$type    = self::get_instance()->get_token_type_by_id( $token_id );
			$gateway = $type['gateway_id'] ?? '';
			$type    = $type['type'] ?? 'CC';
		}

		// Still empty? Token doesn't exist? Don't continue.
		if ( empty( $type ) ) {
			return null;
		}

		$token_class = self::get_token_classname( $type, $gateway );

		if ( class_exists( $token_class ) ) {
			$token = new $token_class( $token_id );

			if ( $token->get_id() ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Returns an stdObject of a token.
	 * Should contain the fields token_id, gateway_id, token, user_id, type, is_default.
	 *
	 * @param int $token_id Token ID.
	 *
	 * @return object|stdClass
	 */
	public function get_token_by_id( int $token_id ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE token_id = %d",
				$token_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Remove a payment token from the database by ID.
	 *
	 * @param int $token_id Token ID.
	 *
	 * @throws StoreEngineException
	 */
	public static function delete( int $token_id ): bool {
		$token = self::get( $token_id );
		if ( ! $token ) {
			return false;
		}

		return $token->delete();
	}

	/**
	 * Loops through all of a users payment tokens and sets is_default to false for all but a specific token.
	 *
	 * @param int $user_id User to set a default for.
	 * @param int $token_id The ID of the token that should be default.
	 */
	public static function set_users_default( int $user_id, int $token_id ) {
		$users_tokens = self::get_customer_tokens( $user_id );
		// Only one token can be default at a time.
		// This can be done with 2 query, without looping multiple token.
		foreach ( $users_tokens as $token ) {
			if ( $token_id === $token->get_id() ) {
				$token->set_default_status( $token->get_id() );
				do_action( 'storeengine/payment_token/set_default', $token_id, $token );
			} else {
				$token->set_default_status( false );
			}
		}
	}

	/**
	 * Returns what type (credit card, echeck, etc) of token a token is by ID.
	 *
	 * @param int $token_id Token ID.
	 *
	 * @return ?array{type:string,gateway:string}        Type.
	 */
	public function get_token_type_by_id( int $token_id ): ?array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT type, gateway_id FROM {$this->table} WHERE token_id = %d",
				$token_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get classname based on token type.
	 *
	 * @param string $type Token type.
	 * @param string $gateway
	 *
	 * @return string
	 */
	protected static function get_token_classname( string $type, string $gateway ): string {
		$classname = '';
		switch ( $type ) {
			case 'eCheck':
				$classname = PaymentTokenEcheck::class;
				break;
			case 'CC':
				$classname = PaymentTokenCc::class;
				break;
		}

		/**
		 * Filter payment token class per type.
		 *
		 * @param string $classname Payment token classname with namesapce.
		 * @param string $type Token type.
		 * @param string $gateway Payment gateway.
		 */
		return apply_filters( 'storeengine/payment_token/token_classname', $classname, $type, $gateway );
	}
}
