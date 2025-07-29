<?php
/**
 * Gateway COD.
 */

namespace StoreEngine\Payment\Gateways;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayCod extends PaymentGateway {

	public string $instructions;
	public bool $enable_for_virtual;

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', false );

		add_action( 'storeengine/thankyou/' . $this->id, [ $this, 'thankyou_page' ] );
		add_action( 'storeengine/email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
	}

	protected function setup() {
		$this->id                 = 'cod';
		$this->icon               = apply_filters( 'storeengine/cod_icon', Helper::get_assets_url( 'images/payment-methods/cod-alt.svg' ) );
		$this->method_title       = __( 'Cash on delivery', 'storeengine' );
		$this->method_description = __( 'Let your shoppers pay upon delivery — by cash or other methods of payment.', 'storeengine' );
		$this->has_fields         = false;
	}

	public function is_available(): bool {
		$is_virtual = true;

		if ( (bool) get_query_var( 'order_pay' ) ) {
			$order            = Helper::get_order( absint( get_query_var( 'order_pay' ) ) );
			$shipping_methods = $order && $order->get_id() ? $order->get_shipping_methods() : [];
			$is_virtual       = ! count( $shipping_methods );
		} elseif ( \StoreEngine::init()->get_cart() && \StoreEngine::init()->get_cart()->needs_shipping() ) {
			// @TODO check if need to check shipping methods.
			$is_virtual = false;
		}

		// If COD is not enabled for virtual orders and the order does not need shipping, return false.
		if ( ! $this->enable_for_virtual && $is_virtual ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param Order $order
	 *
	 * @return array
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */
	public function process_payment( Order $order ): array {
		if ( $this->is_payment_needed( $order ) ) {
			$order->add_order_note( _x( 'Payment to be made upon delivery.', 'COD payment method', 'storeengine' ) );
			$order->set_paid_status( 'unpaid' );
		} else {
			$order->set_paid_status( 'paid' );
			$order->add_order_note( _x( 'Payment not needed.', 'COD payment method', 'storeengine' ) );
		}

		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'processing', $order );
		$order->save();

		// Remove cart.
		Helper::cart()->clear_cart();

		// Return thankyou redirect.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'        => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'This controls the title which the user sees during checkout.', 'storeengine' ),
				'default'  => __( 'Cash on delivery', 'storeengine' ),
				'priority' => 0,
			],
			'description'  => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Pay with cash upon delivery.', 'storeengine' ),
				'priority' => 0,
			],
			'instructions' => [
				'label'    => __( 'Instructions', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Instructions that will be added to the thank you page and emails.', 'storeengine' ),
				'default'  => __( 'Pay with cash upon delivery.', 'storeengine' ),
				'priority' => 0,
			],
			// enable_for_virtual, Accept for virtual orders, Accept COD if the order is virtual, checkbox, false, 0
		];
	}
}

// End of file gateway-cod.php.
