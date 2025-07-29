<?php

namespace StoreEngine\Addons\Email\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Email\Admin\Settings as EmailSettings;
use StoreEngine\Addons\Email\Traits\Email;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Admin extends AbstractAjaxHandler {

	use Email {
		__construct as private EmailInit;
	}

	/**
	 * WP Mail error.
	 *
	 * @var WP_Error|null
	 */
	protected ?WP_Error $mail_error = null;

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_email';

	public function __construct() {
		$this->actions = [
			'preview_template' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'preview_template' ],
				'fields'     => [
					'templateName'    => 'string',
					'templateSubName' => 'string',
				],
			],
			'test_email'       => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'test_email' ],
				'fields'     => [
					'templateName'    => 'string',
					'templateSubName' => 'string',
				],
			],
		];
	}

	public function preview_template( $payload ) {
		if ( empty( $payload['templateName'] ) ) {
			wp_send_json_error( esc_html__( 'Template name is required.', 'storeengine' ) );
		}

		$templateName     = $payload['templateName'];
		$templateSubName  = ! empty( $payload['templateSubName'] ) ? $payload['templateSubName'] : '';
		$settings         = EmailSettings::get_settings_saved_data();
		$templateSettings = $templateSubName ? $settings[ $templateName ][ $templateSubName ] : $settings[ $templateName ];
		$templateFile     = Helper::get_email_template_name( $templateName, $templateSubName );

		if ( empty( $templateFile ) ) {
			wp_send_json_error( esc_html__( 'Template file not exists.', 'storeengine' ) );
		}

		ob_start();
		Helper::get_template( 'email/' . $templateFile, [
			'heading' => $templateSettings['email_heading'],
			'content' => $templateSettings['email_content'],
			'footer'  => $settings['footer_text'],
		] );
		$preview = ob_get_clean();

		wp_send_json_success( $this->style_inline( $preview ) );
	}

	public function test_email( $payload ) {
		if ( empty( $payload['templateName'] ) ) {
			wp_send_json_error( esc_html__( 'Template name is required.', 'storeengine' ) );
		}

		$settings         = EmailSettings::get_settings_saved_data();
		$templateSettings = ! empty( $payload['templateSubName'] ) ? $settings[ $payload['templateName'] ][ $payload['templateSubName'] ] : $settings['templateName'];
		$templateFile     = Helper::get_email_template_name( $payload['templateName'], $payload['templateSubName'] ?? '' );

		if ( empty( $templateFile ) ) {
			wp_send_json_error( esc_html__( 'Template file not exists.', 'storeengine' ) );
		}

		if ( ! $templateSettings['is_enable'] ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s. eMail template name. */
					esc_html__( '“%s” Mail is disabled. Please enable and try again.', 'storeengine' ),
					esc_html( $this->get_template_name( $payload['templateName'], $payload['templateSubName'] ?? '' ) )
				)
			);
		}

		$this->EmailInit($payload['templateName']);
		$to        = get_option( 'admin_email' );
		$site_url  = get_bloginfo( 'url' );
		$site_name = get_bloginfo( 'name' );

		$subject = str_replace(
			[
				'{user_display_name}',
				'{site_title}',
				'{site_url}',
				'{order_id}',
			],
			[
				'John Doe',
				$site_name,
				$site_url,
				100,
			],
			$templateSettings['email_subject']
		);

		if ( 'plainText' === $settings['email_content_type'] ) {
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
			$body    = $this->prepare_text_body( $templateSettings['email_heading'], $templateSettings['email_content'], $settings['footer_text'] );
		} else {
			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
			ob_start();
			Helper::get_template( 'email/' . $templateFile, [
				'heading' => $templateSettings['email_heading'],
				'content' => $templateSettings['email_content'],
				'footer'  => $settings['footer_text'],
			] );
			$body = ob_get_clean();
			$body = $this->style_inline( $body );
		}

		$order_item_template = $this->get_order_item_template();
		$body                = str_replace(
			[
				'{user_display_name}',
				'{user_email}',
				'{order_id}',
				'{order_created_date}',
				'{order_items}',
				'{order_payment_method}',
				'{order_totals}',
				'{order_note}',
				'{order_refunds}',
				'{order_old_status}',
				'{order_new_status}',
				'{invoice_button}',
			],
			[
				'John Doe',
				'john.doe@example.com',
				'100',
				esc_html(gmdate( 'F j, Y' )),
				$this->prepare_body_without_layout(
					implode( '', array_map( fn( $order_item ) => str_replace(
						array( '{order_item_name}', '{order_item_meta_html}', '{order_item_quantity}', '{order_item_line_total}' ),
						[
							esc_html( $order_item[0] ),
							'Cap' === $order_item[0] ? "<li data-list='bullet'><strong>Color </strong>: Blue</li><li data-list='bullet'><strong>Size </strong>: XL</li>" : null,
							esc_html( $order_item[1] ),
							wp_kses_post( Formatting::price( $order_item[2] ) ),
						],
						$order_item_template
					), [ [ 'Album', 1, 20.0 ], [ 'Cap', 1, 12.0 ] ] ) )
				),
				'Bank Transfer',
				$this->prepare_body_without_layout(implode('', array_map(fn( array $total) => "<p><strong>{$total['label']} </strong> {$total['value']}</p>", [
					[
						'label' => 'Subtotal:',
						'value' => Formatting::price(32),
					],
					[
						'label' => 'Discount:',
						'value' => Formatting::price(-2),
					],
					[
						'label' => 'Total:',
						'value' => Formatting::price(30),
					],
					[
						'label' => 'Payment Method:',
						'value' => 'Bank Transfer',
					],
				]))),
				'This is a text of order note',
				$this->prepare_body_without_layout(
					'<ul>' . implode( '', array_map( function ( $refund ) {
						$refund_template = '<li data-list=bullet>{refund_name}: <strong>{refund_amount}</strong></li>';
						return str_replace(
							array( '{refund_name}', '{refund_amount}' ),
							array( "Refund #$refund[0] - $refund[1] by $refund[2]", ( esc_html( Formatting::price( $refund[3] ) ) ) ),
							$refund_template
						);
					}, [ [ 10, esc_html(gmdate( 'F j, Y' )), wp_get_current_user()->display_name, 12.00 ] ] ) ) . '</ul>'
				),
				'Pending payment',
				'Payment Confirmed',
				'<a href="#" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">View Invoice</a>',
			],
			$body
		);

		add_action( 'wp_mail_failed', [ $this, 'catch_wp_mail_error' ] );
		$is_send = $this->mail_send( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', [ $this, 'catch_wp_mail_error' ] );

		if ( $is_send ) {
			wp_send_json_success( esc_html__( 'Test email sent successfully.', 'storeengine' ) );
		} else {
			if ( $this->mail_error && is_wp_error( $this->mail_error ) ) {
				wp_send_json_error( sprintf(
					/* translators: %s. PHP Mailer Error Message. */
					esc_html__( 'Error sending test email. Error: %s', 'storeengine' ),
					esc_html( $this->mail_error->get_error_message() )
				) );
			}

			wp_send_json_error( esc_html__( 'Something went wrong. Unable to send test email.', 'storeengine' ) );
		}
	}

	public function catch_wp_mail_error( WP_Error $error ) {
		$this->mail_error = $error;
	}

	protected function get_template_name( string $templateName, string $templateSubName ) {
		$templateSlug = $templateName . '_' . $templateSubName;
		$names        = [
			'order_confirmation_admin'    => __( 'Order Confirmation Admin', 'storeengine' ),
			'order_confirmation_customer' => __( 'Order Confirmation Customer', 'storeengine' ),
			'order_invoice_customer'      => __( 'Order Invoice Customer', 'storeengine' ),
			'order_note_customer'         => __( 'Order Note Customer', 'storeengine' ),
			'order_refund_customer'       => __( 'Order Refund Customer', 'storeengine' ),
			'order_status_customer'       => __( 'Order Status Customer', 'storeengine' ),
		];

		return $names[ $templateSlug ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $templateSlug ) );
	}
}
