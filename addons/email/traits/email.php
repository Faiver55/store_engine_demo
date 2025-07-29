<?php

namespace StoreEngine\Addons\Email\Traits;

use Exception;
use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Pelago\Emogrifier\CssInliner;
use StoreEngine\Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use StoreEngine\Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Email {

	protected string $email_name;
	private $settings;

	public function __construct( string $name ) {
		$this->email_name = $name;
		$this->settings   = HelperAddon::get_setting( $name, [] );
	}

	public function mail_send( $to, $subject, $body, $headers, $args = [] ) {
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );

		$arguments = apply_filters( 'storeengine/email/mail_send_arguments', [
			'headers'     => $headers,
			'body'        => $body,
			'to'          => $to,
			'subject'     => $subject,
			'attachments' => $args['attachments'] ?? [],
		], $this->email_name, $args );

		$is_send = wp_mail( $arguments['to'], wp_specialchars_decode( $arguments['subject'] ), $arguments['body'], $arguments['headers'], $arguments['attachments'] );

		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );

		return $is_send;
	}

	public function get_from_name( $name ) {
		$form_name = HelperAddon::get_setting( 'form_name' );
		if ( ! empty( $form_name ) ) {
			return sanitize_text_field( $form_name );
		}

		return $name;
	}

	public function get_from_address( $email_address ) {
		$form_email_address = HelperAddon::get_setting( 'email_address' );
		if ( ! empty( $form_email_address ) && is_email( $form_email_address ) ) {
			return sanitize_text_field( $form_email_address );
		}

		return $email_address;
	}

	public function get_order_item_template(): string {
		return '<ul><li data-list=bullet><strong>Product Name </strong>: {order_item_name}</li>{order_item_meta_html}<li data-list=bullet><strong>Product Quantity </strong>: {order_item_quantity}</li><li data-list=bullet><strong>Total Price </strong>: {order_item_line_total}</li></ul>';
	}

	private function get_settings( $action_name ) {
		if ( isset( $this->settings[ $action_name ] ) ) {
			return $this->settings[ $action_name ];
		}

		return false;
	}

	private function get_the_email_body( $settings, $template_path ): array {
		$email_type = HelperAddon::get_setting( 'email_content_type' );
		$footer     = HelperAddon::get_setting( 'footer_text' );
		if ( 'plainText' === $email_type ) {
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
			$body    = $this->prepare_text_body( $settings['email_heading'], $settings['email_content'], $footer );
		} else {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			ob_start();
			Helper::get_template( $template_path, array(
				'heading' => $settings['email_heading'],
				'content' => $settings['email_content'],
				'footer'  => $footer,
			) );
			$body = ob_get_clean();
			$body = $this->style_inline( $body );
		}

		return array( $headers, $body );
	}

	protected function prepare_text_body( $email_heading, $email_content, $email_footer ): string {
		$allowed_tags = [ 'p', 'li' ];
		$body         = strip_tags( $email_heading, $allowed_tags ) . PHP_EOL;
		$body        .= strip_tags( $email_content, $allowed_tags ) . PHP_EOL;
		$body        .= strip_tags( $email_footer, $allowed_tags ) . PHP_EOL;
		$body         = preg_replace( '/<p[^>]*>(.*?)<\/p>/', '$1' . PHP_EOL, $body );
		$body         = preg_replace( '/<li[^>]*>(.*?)<\/li>/', '$1' . PHP_EOL, $body );

		return trim( html_entity_decode( $body ) );
	}

	protected function prepare_body_without_layout( $body ): string {
		if ( 'plainText' === HelperAddon::get_setting( 'email_content_type' ) ) {
			$body = $this->prepare_text_body( '', $body, '' );
		}

		return $body;
	}

	/**
	 * Apply inline styles to dynamic content.
	 *
	 * We only inline CSS for html emails, and to do so we use Emogrifier library (if supported).
	 *
	 * @param string|null $content Content that will receive inline styles.
	 *
	 * @return string
	 */
	protected function style_inline( $content ) {
		if ( 'plainText' === HelperAddon::get_setting( 'email_content_type' ) ) {
			return $content;
		}

		$css  = PHP_EOL;
		$css .= $this->get_must_use_css_styles();
		$css .= PHP_EOL;

		ob_start();
		Helper::get_template( 'email/styles.php' );
		$css .= ob_get_clean();

		// @TODO compile email-template & css in same function to leverage wp-enqueue if DOM-Document ext not available.

		if ( class_exists( 'DOMDocument' ) ) {
			try {
				$css_inliner  = CssInliner::fromHtml( $content )->inlineCss( $css );
				$dom_document = $css_inliner->getDomDocument();
				HtmlPruner::fromDomDocument( $dom_document )->removeElementsWithDisplayNone();
				$content = CssToAttributeConverter::fromDomDocument( $dom_document )->convertCssToVisualAttributes()->render();
			} catch ( Exception $e ) {
				// CSS not applicable convert to text email.
				$content = nl2br( wp_strip_all_tags( $content ) );
			}
		} else {
			// CSS not applicable convert to text email.
			$content = nl2br( wp_strip_all_tags( $content ) );
		}

		return $content;
	}

	/**
	 * Returns CSS styles that should be included with all HTML e-mails, regardless of theme specific customizations.
	 *
	 * @return string
	 * @since 0.0.4
	 */
	protected function get_must_use_css_styles(): string {
		/**
		 * Temporary measure until e-mail clients more properly support the correct styles.
		 *
		 * @see https://github.com/woocommerce/woocommerce/pull/47738
		 */
		$css  = '.screen-reader-text {' . PHP_EOL;
		$css .= '	display: none !important;' . PHP_EOL;
		$css .= '	visibility: hidden !important;' . PHP_EOL;
		$css .= '	opacity: 0 !important;' . PHP_EOL;
		$css .= '	width: 0 !important;' . PHP_EOL;
		$css .= '	height: 0 !important;' . PHP_EOL;
		$css .= '}' . PHP_EOL;

		return $css;
	}

	private function get_order_email_body( Order $order, string $body ): string {
		$customer            = $order->get_customer();
		$order_item_template = $this->get_order_item_template();

		return (
			str_replace(
				array(
					'{user_display_name}',
					'{user_email}',
					'{order_id}',
					'{order_created_date}',
					'{order_payment_method}',
					'{order_totals}',
					'{order_items}',
				),
				array(
					$customer ? $customer->get_display_name() : null,
					$customer ? $customer->get_email() : null,
					esc_html($order->get_id()),
					$order->get_date_created_gmt() ? $order->get_date_created_gmt()->date( 'F j, Y' ) : null,
					esc_html($order->get_payment_method_title()),
					$this->prepare_body_without_layout(implode('', array_map(fn( array $total) => "<p><strong>{$total['label']} </strong> {$total['value']}</p>", $order->get_order_item_totals()))),
					$this->prepare_body_without_layout(
						implode( '', array_map( fn( $order_item ) => str_replace(
							array(
								'{order_item_name}',
								'{order_item_meta_html}',
								'{order_item_quantity}',
								'{order_item_line_total}',
							),
							array(
								esc_html( $order_item->get_name() ),
								implode( '', array_map( fn( $metadata ) => "<li data-list='bullet'><strong>{$metadata['display_key']} </strong>: {$metadata['display_value']}</li>", $order_item->get_formatted_metadata() ) ),
								esc_html( $order_item->get_quantity() ),
								wp_kses_post( Formatting::price( $order_item->get_subtotal() ) ),
							),
							$order_item_template
						), $order->get_items() ) )
					),
				),
				$body
			)
		);
	}

	private function get_email_subject( Order $order, $email_subject = '' ) {
		$customer  = $order->get_customer();
		$site_url  = get_bloginfo( 'url' );
		$site_name = get_bloginfo( 'name' );

		return str_replace(
			array(
				'{user_display_name}',
				'{site_title}',
				'{site_url}',
				'{order_id}',
			),
			array(
				esc_html( $customer ? $customer->get_display_name() : '' ),
				esc_html( $site_name ),
				esc_html( $site_url ),
				esc_html( $order->get_id() ),
			),
			$email_subject
		);
	}
}
