<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Addons\Invoice\PDF\Generator;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Mpdf\Output\Destination;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class Pdf extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	public function __construct() {
		$this->actions = [
			'generate_pdf' => [
				'callback' => [ $this, 'generate_pdf' ],
				'fields'   => [
					'document_type' => 'string',
					'order_id'      => 'int',
					'download'      => 'string',
				],
			],
		];
	}

	public function generate_pdf( array $payload ) {
		if ( ! Formatting::string_to_bool( get_option( 'storeengine_invoice_fonts_downloaded', false ) ) ) {
			$this->send_notice( __( 'Please download the fonts before generating the PDF.', 'storeengine' ) );
		}

		if ( ! isset( $payload['document_type'] ) || ! in_array( $payload['document_type'], [
			'invoice',
			'packing-slip',
		], true ) ) {
			$this->send_notice( __( 'Invalid document type!', 'storeengine' ) );
		}

		if ( ! isset( $payload['order_id'] ) || ! is_numeric( $payload['order_id'] ) ) {
			$this->send_notice( __( 'Invalid order ID!', 'storeengine' ) );
		}

		$order_id = $payload['order_id'];
		$order    = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			$this->send_notice( __( 'Order not found!', 'storeengine' ) );
		}

		if ( ! current_user_can( 'manage_options' ) && $order->get_customer_id() !== get_current_user_id() ) {
			$this->send_notice( __( 'You are not authorized to view this document!', 'storeengine' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$can_preview = HelperAddon::get_setting( 'invoice_front_btn', 'order_paid' );
			if ( 'never' === $can_preview || ( 'order_paid' === $can_preview && ! $order->is_paid() ) ) {
				$this->send_notice( __( 'You are not allowed to view this document!', 'storeengine' ) );
			}
		}

		$invoice_date = null;
		if ( ( 'order_paid' === HelperAddon::get_setting( 'invoice_date_from', 'order_paid' ) && $order->get_date_paid_gmt() ) ) {
			$invoice_date = $order->get_date_paid_gmt()->format( HelperAddon::get_setting( 'date_format', 'd F, Y' ) );
		} elseif ( 'order_created' === HelperAddon::get_setting( 'invoice_date_from', 'order_paid' ) ) {
			$invoice_date = $order->get_date_created_gmt( HelperAddon::get_setting( 'date_format', 'd F, Y' ) );
		}

		ob_start();
		include STOREENGINE_INVOICE_TEMPLATE_DIR . '/invoice/template.php';
		$invoice_html = ob_get_clean();
		$generator    = new Generator( $invoice_html, file_get_contents( STOREENGINE_INVOICE_TEMPLATE_DIR . '/invoice/style.css' ) );

		$download = $payload['download'] && Formatting::string_to_bool( $payload['download'] );
		$generator->preview( "invoice-{$order->get_id()}", $download );
	}

	protected function send_notice( string $message ) {
		?>
		<p>
			<?php echo esc_html( $message ); ?>
			<a href="<?php echo esc_attr( home_url() ); ?>">Back to Home</a>
		</p>
		<?php
		exit;
	}

}
