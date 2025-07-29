<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class Created extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action( 'storeengine/checkout/after_place_order',
			function ( $order ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $order ) ] );
			}
		);

		add_action( 'storeengine/api/after_create_order',
			function ( $order ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $order ) ] );
			}
		);
	}

	public static function get_payload( Order $order ): array {
		$data = [
			'id'                             => $order->get_id(),
			'is_editable'                    => $order->is_editable(),
			'currency'                       => $order->get_currency(),
			'status'                         => $order->get_status(),
			'paid_status'                    => $order->get_paid_status(),
			'date_paid_gmt'                  => $order->get_date_paid_gmt() ? $order->get_date_paid_gmt() : null,
			'type'                           => $order->get_type(),
			'tax_amount'                     => $order->get_total_tax(),
			'refunds_total'                  => $order->get_total_refunded(),
			'subtotal_amount'                => $order->get_subtotal(),
			'date_created_gmt'               => $order->get_date_created_gmt(),
			'order_placed_date_gmt'          => $order->get_order_placed_date_gmt() ? $order->get_order_placed_date_gmt() : null,
			'coupons'                        => self::get_coupons( $order ),
			'shipping_total'                 => $order->get_shipping_total(),
			'fees'                           => self::get_fees( $order ),
			'taxes'                          => self::get_taxes( $order ),
			'total_amount'                   => $order->get_total(),
			'customer_id'                    => $order->get_customer_id(),
			'customer_email'                 => $order->get_order_email(),
			'customer_name'                  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'billing_email'                  => $order->get_billing_email(),
			'payment_method'                 => $order->get_payment_method(),
			'payment_method_title'           => $order->get_payment_method_title(),
			'transaction_id'                 => $order->get_transaction_id(),
			'customer_note'                  => $order->get_customer_note(),
			'is_auto_complete_digital_order' => $order->get_auto_complete_digital_order(),
			'purchase_items'                 => self::get_line_items( $order ),
			'refunds'                        => self::get_refunds( $order ),
			'billing_address'                => [
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'country'    => $order->get_billing_country() ?? '-',
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
			],
			'shipping_address'               => [
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'country'    => $order->get_shipping_country() ?? '-',
				'email'      => $order->get_shipping_email(),
				'phone'      => $order->get_shipping_phone(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
			],
			'meta_data'                      => $order->get_meta_data(),
		];

		// Deprecated.
		$data['order_billing_address'] = $data['billing_address'];

		return $data;
	}

	protected static function get_taxes( Order $order ): array {
		return array_values( array_map( fn( $tax ) => [
			'code'   => $tax->code,
			'label'  => $tax->label,
			'amount' => Formatting::round_tax_total( $tax->amount ),
		], $order->get_tax_totals() ) );
	}

	protected static function get_fees( Order $order ): array {
		return array_values( array_map( fn( $fee ) => [
			'id'         => $fee->get_id(),
			'name'       => $fee->get_name( 'edit' ),
			'tax_class'  => $fee->get_tax_class(),
			'tax_status' => $fee->get_tax_status(),
			'amount'     => Formatting::round_tax_total( $fee->get_amount( 'edit' ) ),
			'total'      => $fee->get_total(),
			'total_tax'  => $fee->get_total_tax(),
			'taxes'      => $fee->get_taxes(),
		], $order->get_fees() ) );
	}

	protected static function get_coupons( Order $order ): array {
		return array_values( array_map( fn( $coupon ) => [
			'code'   => $coupon->get_name(),
			'amount' => $coupon->get_discount(),
		], $order->get_coupons() ) );
	}

	protected static function get_line_items( Order $order ): array {
		return array_values( array_map( fn( $order_item ) => [
			'id'           => $order_item->get_id(),
			'product_name' => $order_item->get_name(),
			'product_id'   => $order_item->get_product_id(),
			'product_qty'  => $order_item->get_quantity(),
			'price'        => $order_item->get_price(),
		], $order->get_line_product_items() ) );
	}

	protected static function get_refunds( Order $order ): array {
		try {
			return array_map( function ( $refund ) {
				$refund_by = $refund->get_refunded_by_user();

				return [
					'id'         => $refund->get_id(),
					'amount'     => abs( $refund->get_total() ),
					'refund_by'  => [
						'user_id'      => $refund_by ? $refund_by->get_id() : null,
						'name'         => $refund_by ? trim( $refund_by->get_first_name() . ' ' . $refund_by->get_last_name() ) : null,
						'display_name' => $refund_by ? $refund_by->get_display_name() : null,
					],
					'created_at' => $refund->get_date_created_gmt(),
				];
			}, $order->get_refunds() );
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );

			return [];
		}
	}
}
