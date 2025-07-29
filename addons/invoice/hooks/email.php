<?php

namespace StoreEngine\Addons\Invoice\Hooks;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Utils\Helper;

class Email {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/email/settings_default_data', [ $self, 'add_email_settings' ] );
		add_filter( 'storeengine/email/settings_fields', [ $self, 'add_email_settings_fields' ] );
		add_filter( 'storeengine/email/mail_send_arguments', [ $self, 'filter_mail_arguments' ], 10, 3 );
		add_action( 'storeengine/mail/clean_tmp_invoices', [ $self, 'clean_tmp_invoices' ] );
	}

	public function add_email_settings( array $settings ): array {
		return array_merge( $settings, [
			'order_invoice' => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Invoice for order #{order_id}',
					'email_heading' => 'Order Invoice #{order_id}',
					'email_content' => '<h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p><span style=\"color: rgb(14, 14, 14)\">Thank you for your order! We’ve attached your invoice for your reference.</span></p><p>You can also view or download your invoice using the button below:</p><p>{invoice_button}</p><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
		] );
	}

	public function add_email_settings_fields( array $settings ): array {
		return array_merge( $settings, [
			'order_invoice' => [
				'customer' => [
					'is_enable'     => 'boolean',
					'email_subject' => 'string',
					'email_heading' => 'string',
					'email_content' => 'post',
				],
			],
		] );
	}

	public function filter_mail_arguments( array $mail_arguments, string $email_name, array $args ): array {
		if ( ! isset( $args['order_id'] ) || 'order_invoice' === $email_name ) {
			return $mail_arguments;
		}

		$order = Helper::get_order( absint( $args['order_id'] ) );
		if ( is_wp_error( $order ) ) {
			return $mail_arguments;
		}

		// support {invoice_button} shortcode.
		$preview_url            = HelperAddon::get_invoice_preview_url( $order->get_order_key() );
		$mail_arguments['body'] = str_replace(
			'{invoice_button}',
			'<a href="' . esc_attr( $preview_url ) . '" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">View Invoice</a>',
			$mail_arguments['body'] );

		// support attachments.
		$attachment_settings = HelperAddon::get_setting( 'invoice_mail_attachment' );
		if ( ! in_array( $email_name, $attachment_settings, true ) ) {
			return $mail_arguments;
		}

		$invoice_file_path = HelperAddon::generate_pdf( $order );
		if ( ! $invoice_file_path ) {
			return $mail_arguments;
		}
		$mail_arguments['attachments'][] = $invoice_file_path;

		return $mail_arguments;
	}

	public function clean_tmp_invoices() {
		$tmp_folder = trailingslashit( Helper::get_upload_dir() . '/tmp-invoices' );

		if ( file_exists( $tmp_folder ) ) {
			array_map( 'unlink', glob( $tmp_folder . '/*.*' ) );
			rmdir( $tmp_folder );
		}

		wp_mkdir_p( $tmp_folder );
	}

}
