<?php

namespace StoreEngine\Addons\Invoice;

use StoreEngine\Addons\Invoice\PDF\Generator;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

class HelperAddon {

	public static function get_fonts_dir(): string {
		return Helper::get_upload_dir() . '/invoice/fonts';
	}

	public static function get_setting( string $name, $default = null ) {
		$settings = Settings::get_settings_saved_data();

		return $settings[ $name ] ?? $default;
	}

	public static function get_pdf_url( int $order_id, string $document_type = 'invoice', bool $download = false ): string {
		return add_query_arg( [
			'action'        => 'storeengine_invoice/generate_pdf',
			'document_type' => $document_type,
			'order_id'      => $order_id,
			'download'      => $download,
			'security'      => wp_create_nonce( 'storeengine_nonce' ),
		], admin_url( 'admin-ajax.php' ) );
	}

	public static function get_invoice_preview_url( string $order_key ): string {
		return add_query_arg( [
			'store_document_type' => 'invoice',
			'key'                 => str_replace( 'se_order_', 'store_order_', $order_key ),
		], site_url() );
	}

	public static function generate_pdf( Order $order ): ?string {
		$invoice_date = null;
		if ( ( 'order_paid' === self::get_setting( 'invoice_date_from', 'order_paid' ) && $order->get_date_paid_gmt() ) ) {
			$invoice_date = $order->get_date_paid_gmt()->format( self::get_setting( 'date_format', 'd F, Y' ) );
		} elseif ( 'order_created' === self::get_setting( 'invoice_date_from', 'order_paid' ) ) {
			$invoice_date = $order->get_date_created_gmt()->format( self::get_setting( 'date_format', 'd F, Y' ) );
		}

		ob_start();
		include STOREENGINE_INVOICE_TEMPLATE_DIR . 'invoice/template.php';
		$invoice_html = ob_get_clean();
		$generator    = new Generator( $invoice_html, file_get_contents( STOREENGINE_INVOICE_TEMPLATE_DIR . 'invoice/style.css' ) );

		$dir = Helper::get_upload_dir() . '/tmp-invoices';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$file_path = trailingslashit( $dir ) . "invoice-{$order->get_id()}.pdf";
		$result    = $generator->save( $file_path );
		if ( is_wp_error( $result ) ) {
			error_log( 'Failed to save invoice PDF: ' . $result->get_error_message() );

			return null;
		}

		$hook = 'storeengine/mail/clean_tmp_invoices';
		if ( ! as_next_scheduled_action( $hook ) ) {
			as_schedule_single_action( time() + 600, $hook );
		}

		return $file_path;
	}
}
