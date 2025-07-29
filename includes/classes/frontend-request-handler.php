<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendRequestHandler extends AbstractRequestHandler {
	use Singleton;

	/**
	 * Default Nonce Action.
	 *
	 * @var string
	 */
	protected string $nonce_action = 'storeengine_nonce';

	public function __construct() {
		$this->dispatch_actions();
	}

	final public function dispatch_actions() {
		add_action( 'wp', [ $this, 'handle_request' ], 20 );
	}

	/**
	 * Handle action callback.
	 *
	 * @return void
	 */
	public function handle_request() {
		global $wp;

		$this->actions = array_filter( Helper::get_frontend_dashboard_menu_items(), fn( $item ) => ! $item['public'] );

		if ( ! empty( $wp->query_vars['storeengine_dashboard_page'] ) && array_key_exists( $wp->query_vars['storeengine_dashboard_page'], $this->actions ) ) {
			$type    = sanitize_text_field( $wp->query_vars['storeengine_dashboard_page'] );
			$value   = sanitize_text_field( $wp->query_vars['storeengine_dashboard_sub_page'] ?? '' );
			$details = $this->actions[ $type ];

			// Prevent caching.
			Caching::nocache_headers();

			try {
				$this->respond_request( $type, $value, $details );
			} catch ( StoreEngineException $e ) {
				$title = _x( 'The', 'error title', 'storeengine' );
				$error = $e->toWpError();
				if ( ! array_key_exists( 'title', $error->get_error_data() ) ) {
					$title = $this->actions[ $type ]['label'] ?? str_replace( '_', ' ', $type );
				}

				wp_die(
					$error, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Error object.
					sprintf(
					/* translators: %s. Error page title. */
						esc_html__( 'Error Processing: %s Request', 'storeengine' ),
						esc_html( ucwords( $title ) )
					)
				);
			}
		}

		$this->handle_cancel_order();
	}

	/**
	 * @param string $type
	 * @param string $value
	 * @param array $details
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function respond_request( string $type, string $value, array $details ) {
		if ( has_action( "storeengine_dashboard_handle_{$type}_request" ) ) {
			$this->validate_request( $type, $value, $details );
			do_action( "storeengine_dashboard_handle_{$type}_request", $value ?: null );
		} else {
			if ( ! empty( $action['callback'] ) && is_callable( $action['callback'] ) ) {
				$this->validate_request( $type, $value, $details );
				if ( ! empty( $action['fields'] ) ) {
					$this->respond( $action['callback'], $this->prepare_payload( $action['fields'] ) );
				} else {
					call_user_func( $action['callback'], $value ?: null );
				}
			}
		}
	}

	/**
	 * @param string $type
	 * @param string $value
	 * @param array $details
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function validate_request( string $type, string $value, array $details ) {
		$nonce = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : '';

		if ( empty( $nonce ) && isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		if ( ! $nonce ) {
			throw new StoreEngineException(
				__( 'Missing security token data.', 'storeengine' ),
				'missing_nonce_field',
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Security token is missing.', 'storeengine' ),
				]
			);
		}

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) && ! wp_verify_nonce( $nonce, $type . '-' . $value ) ) {
			throw new StoreEngineException(
				__( 'Invalid Security token data.', 'storeengine' ),
				'invalid_nonce',
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Invalid Security token.', 'storeengine' ),
				]
			);
		}

		if ( ! isset( $details['allow_visitor_action'] ) ) {
			$this->is_visitor_action = true;
		} else {
			$this->is_visitor_action = (bool) $details['allow_visitor_action'];
		}
		$user_cap       = ! empty( $details['capability'] ) ? (string) $details['capability'] : '';
		$has_permission = $this->check_permission( $user_cap, $this->is_visitor_action );

		if ( is_wp_error( $has_permission ) ) {
			throw StoreEngineException::from_wp_error( $has_permission );
		}
	}

	protected function handle_cancel_order() {
		if (
			isset( $_GET['cancel_order'], $_GET['order'], $_GET['order_id'], $_GET['_wpnonce'] ) &&
			wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'storeengine-cancel_order' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			&& is_user_logged_in()
		) {
			// Prevent caching.
			Caching::nocache_headers();

			$order_key = wp_unslash( $_GET['order'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$order_id  = absint( $_GET['order_id'] );
			$order     = Helper::get_order( $order_id );

			/**
			 * Filter valid order statuses for cancel.
			 *
			 * @param array $valid_statuses Array of valid order statuses for cancel.
			 * @param Order $order          Order object.
			 */
			$valid_statuses   = apply_filters( 'storeengine/order/valid_statuses_for_cancel', [ OrderStatus::PAYMENT_PENDING, OrderStatus::PAYMENT_FAILED ], $order );
			$user_can_cancel  = get_current_user_id() === $order->get_user_id();
			$order_can_cancel = $order->has_status( $valid_statuses );
			$redirect         = isset( $_GET['redirect'] ) ? wp_unslash( $_GET['redirect'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $user_can_cancel && $order_can_cancel && $order->get_id() === $order_id && hash_equals( $order->get_order_key(), $order_key ) ) {
				// Cancel the order + restore stock.
				$order->update_status( OrderStatus::CANCELLED, __( 'Order cancelled by customer.', 'storeengine' ) );

				// info -> Your order was cancelled.

				do_action( 'storeengine/order/order_cancelled', $order->get_id() );
			} elseif ( $user_can_cancel && ! $order_can_cancel ) {
				wp_die(
					__( 'Your order can no longer be cancelled. Please contact us if needed.', 'storeengine' ),
					__( 'Invalid action.', 'storeengine' )
				);
			} else {
				wp_die(
					__( 'Invalid order.', 'storeengine' ),
					__( 'Invalid order.', 'storeengine' )
				);
			}

			if ( $redirect ) {
				wp_safe_redirect( $redirect );
				exit;
			}
		}
	}
}

// End of file frontend-request-handler.php.
