<?php
/**
 * Gateway BACS.
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

class GatewayBacs extends PaymentGateway {

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
		$this->id                 = 'bacs';
		$this->icon               = apply_filters( 'storeengine/bank_icon', Helper::get_assets_url( 'images/payment-methods/bacs-alt.svg' ) );
		$this->method_title       = __( 'Direct bank transfer', 'storeengine' );
		$this->method_description = __( 'Take payments in person via BACS. More commonly known as direct bank/wire transfer.', 'storeengine' );
		$this->has_fields         = false;
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
			$order->add_order_note( _x( 'Awaiting BACS (Bank) payment.', 'BACS payment method', 'storeengine' ) );
			$order->set_paid_status( 'unpaid' );
		} else {
			$order->set_paid_status( 'paid' );
			$order->add_order_note( _x( 'Payment not needed.', 'BACS payment method', 'storeengine' ) );
		}

		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'processing', $order, [ 'note' => __( 'Payment not needed.', 'storeengine' ) ] );
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
				'default'  => __( 'Direct bank transfer', 'storeengine' ),
				'priority' => 0,
			],
			'description'  => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'storeengine' ),
				'priority' => 0,
			],
			'instructions' => [
				'label'    => __( 'Instructions', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Instructions that will be added to the thank you page and emails.', 'storeengine' ),
				'default'  => '',
				'priority' => 0,
			],
			'accounts'     => [
				'label'          => __( 'Bank Accounts', 'storeengine' ),
				/* translators: %s Account details sequence number (E.G. Account Details: #1, Account Details: #2, etc) */
				'repeater_label' => _x( 'Accounts details: #%s', 'Repeater fieldset label with placeholder for sequence number', 'storeengine' ),
				'add_label'      => _x( 'Add new Accounts details', 'Repeater fieldset label with placeholder for sequence number', 'storeengine' ),
				'tooltip'        => __( 'These account details will be displayed within the order thank you page and confirmation email.', 'storeengine' ),
				'type'           => 'repeater',
				'fields'         => [
					'ac_name'   => [
						'label'        => esc_html__( 'Account name', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'required'     => true,
						'autocomplete' => 'none',
					],
					'ac_number' => [
						'label'        => esc_html__( 'Account number', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'required'     => true,
						'autocomplete' => 'none',
					],
					'bank_name' => [
						'label'        => esc_html__( 'Bank name', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'required'     => true,
						'autocomplete' => 'none',
					],
					'sortcode'  => [
						/**
						 * @TODO shortcode label changes with country locale. Check WC.
						 * @See \WC_Gateway_BACS::generate_account_details_html()
						 */
						'label'        => esc_html__( 'Sort code', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'autocomplete' => 'none',
					],
					'iban'      => [
						'label'        => esc_html__( 'IBAN', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'autocomplete' => 'none',
					],
					'bic'       => [
						'label'        => esc_html__( 'BIC / Swift', 'storeengine' ),
						'type'         => 'text',
						'default'      => '',
						'autocomplete' => 'none',
					],
				],
				'default'        => [
					[
						'ac_name'   => '',
						'ac_number' => '',
						'bank_name' => '',
						'sortcode'  => '',
						'iban'      => '',
						'bic'       => '',
					],
				],
			],
		];
	}
}

// End of file gateway-bacs.php.
