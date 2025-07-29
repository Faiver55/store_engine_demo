<?php
/**
 * Gateway Check.
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

class GatewayCheck extends PaymentGateway {

	public string $instructions;

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'storeengine/thankyou/' . $this->id, [ $this, 'thankyou_page' ] );
		add_action( 'storeengine/email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
	}

	protected function setup() {
		$this->id                 = 'check';
		$this->icon               = apply_filters( 'storeengine/check_icon', Helper::get_assets_url( 'images/payment-methods/check-alt.svg' ) );
		$this->method_title       = _x( 'Check payments', 'Check payment method', 'storeengine' );
		$this->method_description = __( 'Take payments in person via checks. This offline gateway can also be useful to test purchases.', 'storeengine' );
		$this->has_fields         = false;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param Order $order
	 *
	 * @return array
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function process_payment( Order $order ): array {
		if ( $this->is_payment_needed( $order ) ) {
			// Mark as on_hold (we're awaiting the check).
			$order->add_order_note( _x( 'Awaiting check payment', 'Check payment method', 'storeengine' ) );
			$order->set_paid_status( 'unpaid' );
		} else {
			$order->set_paid_status( 'paid' );
			$order->add_order_note( _x( 'Payment not needed.', 'Check payment method', 'storeengine' ) );
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
				'default'  => __( 'Check payments', 'storeengine' ),
				'priority' => 0,
			],
			'description'  => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'storeengine' ),
				'priority' => 0,
			],
			'instructions' => [
				'label'    => __( 'Instructions', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Instructions that will be added to the thank you page and emails.', 'storeengine' ),
				'default'  => '',
				'priority' => 0,
			],
		];
	}
}

// End of file gateway-check.php.
