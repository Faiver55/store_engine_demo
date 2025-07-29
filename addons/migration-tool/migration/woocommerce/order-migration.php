<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\Refund;
use StoreEngine\Database;
use WC_Order;
use WC_Coupon;
use StoreEngine\Utils\Helper;
use StoreEngine\Classes\Coupon;

class OrderMigration {
	protected int $wc_order_id;
	protected Order $se_order;
	protected object $wpdb;
	protected ?int $order_id;

	protected array $available_statuses = [
		'pending'    => OrderStatus::PAYMENT_PENDING,
		'processing' => OrderStatus::PROCESSING,
		'on-hold'    => OrderStatus::ON_HOLD,
		'completed'  => OrderStatus::COMPLETED,
		'cancelled'  => OrderStatus::CANCELLED,
		'refunded'   => OrderStatus::REFUNDED,
		'failed'     => OrderStatus::PAYMENT_FAILED,
	];

	public function __construct( int $wc_order_id ) {
		$this->wc_order_id = $wc_order_id;
		$this->se_order    = new Order();

		try {
			$this->se_order->get_by_meta( '_wc_to_se_oid', $this->wc_order_id );
		} catch ( StoreEngineException $e ) {
			// no-op.
		}
	}

	public function is_exists(): bool {
		return $this->se_order->get_id() > 0;
	}

	public function migrate(): ?int {
		if ( $this->is_exists() ) {
			return $this->se_order->get_id();
		} else {
			return $this->add_order();
		}
	}

	protected function add_order(): ?int {
		global $wpdb;

		if ( ! $this->wc_order_id ) {
			return null;
		}

		$gateways = Helper::get_payment_gateways()->payment_gateways();

		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		$wc_order = wc_get_order( $this->wc_order_id );

		if ( ! $wc_order ) {
			return null;
		}

		$wc_parent    = $wc_order->get_parent_id( 'edit' );
		$parent_order = 0;

		if ( $wc_parent ) {
			try {
				$parent_order = ( new Order() )->get_by_meta( '_wc_to_se_oid', $wc_parent );
				$parent_order = $parent_order->get_id();
			} catch ( StoreEngineException $e ) {
			}
		}

		$order_placed_date     = null;
		$order_placed_date_gmt = null;

		if ( ! in_array( $wc_order->get_payment_method( 'edit' ), [ 'cod', 'bacs', 'cheque' ] ) ) {
			$order_placed_date     = $wc_order->get_date_paid( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_paid( 'edit' )->getOffsetTimestamp() ) : null;
			$order_placed_date_gmt = $wc_order->get_date_paid( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_paid( 'edit' )->getTimestamp() ) : null;
		}

		if ( null === $order_placed_date ) {
			$order_placed_date     = $wc_order->get_date_created( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_created( 'edit' )->getOffsetTimestamp() ) : null;
			$order_placed_date_gmt = $wc_order->get_date_created( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_created( 'edit' )->getTimestamp() ) : null;
		}

		// Prepare order data.
		$date_created   = $wc_order->get_date_created( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_created( 'edit' )->getTimestamp() ) : null;
		$date_modified  = $wc_order->get_date_modified( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_modified( 'edit' )->getTimestamp() ) : null;
		$date_paid      = $wc_order->get_date_paid( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_paid( 'edit' )->getTimestamp() ) : null;
		$date_completed = $wc_order->get_date_completed( 'edit' ) ? gmdate( 'Y-m-d H:i:s', $wc_order->get_date_completed( 'edit' )->getTimestamp() ) : null;
		$data           = [
			'version'                      => STOREENGINE_VERSION,
			'customer_id'                  => $wc_order->get_customer_id( 'edit' ),
			'parent_order_id'              => $parent_order,
			'status'                       => $this->available_statuses[ $wc_order->get_status( 'edit' ) ] ?? OrderStatus::PAYMENT_PENDING,
			'date_created_gmt'             => $date_created,
			'date_updated_gmt'             => $date_modified,
			'date_paid_gmt'                => $date_paid,
			'date_completed_gmt'           => $date_completed,
			'order_placed_date'            => $order_placed_date,
			'order_placed_date_gmt'        => $order_placed_date_gmt,
			// OP Data.
			'currency'                     => $wc_order->get_currency( 'edit' ),
			'prices_include_tax'           => $wc_order->get_prices_include_tax( 'edit' ),
			'discount_total'               => $wc_order->get_discount_total( 'edit' ),
			'discount_tax'                 => $wc_order->get_discount_tax( 'edit' ),
			'shipping_total'               => $wc_order->get_shipping_total( 'edit' ),
			'shipping_tax'                 => $wc_order->get_shipping_tax( 'edit' ),
			'cart_tax'                     => $wc_order->get_cart_tax( 'edit' ),
			'total'                        => $wc_order->get_total( 'edit' ),
			'recorded_coupon_usage_counts' => $wc_order->get_recorded_coupon_usage_counts( 'edit' ),
			'cart_hash'                    => $wc_order->get_cart_hash( 'edit' ),
			'new_order_email_sent'         => $wc_order->get_new_order_email_sent( 'edit' ),
			'order_key'                    => $wc_order->get_order_key( 'edit' ),
			'order_stock_reduced'          => $wc_order->get_order_stock_reduced( 'edit' ),
			'shipping_tax_amount'          => $wc_order->get_shipping_tax( 'edit' ),
			'shipping_total_amount'        => $wc_order->get_shipping_total( 'edit' ),
			'discount_tax_amount'          => $wc_order->get_discount_tax( 'edit' ),
			'discount_total_amount'        => $wc_order->get_discount_total( 'edit' ),
			'recorded_sales'               => $wc_order->get_recorded_sales( 'edit' ),
			// Billing address.
			'billing_first_name'           => $wc_order->get_billing_first_name( 'edit' ),
			'billing_last_name'            => $wc_order->get_billing_last_name( 'edit' ),
			'billing_company'              => $wc_order->get_billing_company( 'edit' ),
			'billing_address_1'            => $wc_order->get_billing_address_1( 'edit' ),
			'billing_address_2'            => $wc_order->get_billing_address_2( 'edit' ),
			'billing_city'                 => $wc_order->get_billing_city( 'edit' ),
			'billing_state'                => $wc_order->get_billing_state( 'edit' ),
			'billing_postcode'             => $wc_order->get_billing_postcode( 'edit' ),
			'billing_country'              => $wc_order->get_billing_country( 'edit' ),
			'billing_email'                => $wc_order->get_billing_email( 'edit' ),
			'billing_phone'                => $wc_order->get_billing_phone( 'edit' ),
			// Shipping address.
			'shipping_first_name'          => $wc_order->get_shipping_first_name( 'edit' ),
			'shipping_last_name'           => $wc_order->get_shipping_last_name( 'edit' ),
			'shipping_company'             => $wc_order->get_shipping_company( 'edit' ),
			'shipping_address_1'           => $wc_order->get_shipping_address_1( 'edit' ),
			'shipping_address_2'           => $wc_order->get_shipping_address_2( 'edit' ),
			'shipping_city'                => $wc_order->get_shipping_city( 'edit' ),
			'shipping_state'               => $wc_order->get_shipping_state( 'edit' ),
			'shipping_postcode'            => $wc_order->get_shipping_postcode( 'edit' ),
			'shipping_country'             => $wc_order->get_shipping_country( 'edit' ),
			'shipping_phone'               => $wc_order->get_shipping_phone( 'edit' ),
			'shipping_email'               => $wc_order->get_billing_email( 'edit' ),
			// Op Data.
			'transaction_id'               => $wc_order->get_transaction_id( 'edit' ),
			'ip_address'                   => $wc_order->get_customer_ip_address( 'edit' ),
			'user_agent'                   => $wc_order->get_customer_user_agent( 'edit' ),
			'created_via'                  => 'migration-addon',
			'customer_note'                => $wc_order->get_customer_note( 'edit' ),
			'hash'                         => $wc_order->get_cart_hash( 'edit' ),
			'download_permissions_granted' => $wc_order->get_download_permissions_granted( 'edit' ),
			'total_amount'                 => $wc_order->get_total( 'edit' ),
		];

		if ( array_key_exists( $wc_order->get_payment_method( 'edit' ), $gateways ) ) {
			$data['payment_method'] = $gateways[ $wc_order->get_payment_method( 'edit' ) ];
		} elseif ( 'cheque' === $wc_order->get_payment_method( 'edit' ) && ! array_key_exists( 'check', $gateways ) ) {
			$data['payment_method'] = $gateways['check'];
		} else {
			$data['payment_method']       = $wc_order->get_payment_method( 'edit' );
			$data['payment_method_title'] = $wc_order->get_payment_method_title( 'edit' );
		}

		$order_items = [
			'line_item' => [],
			'tax'       => [],
			'shipping'  => [],
			'fee'       => [],
			'coupon'    => [],
		];

		/*
		'meta_data'      => $wc_order->get_meta_data(),
		'shipping_lines' => $wc_order->get_items( 'shipping' ),
		'fee_lines'      => $wc_order->get_items( 'fee' ),
		*/
		// Prepare order items.
		foreach ( $wc_order->get_items() as $item_product ) {
			/** @var \WC_Order_Item_Product $item_product */
			if ( $product = $item_product->get_product() ) {
				if ( ! in_array( $product->get_type(), [ 'simple', 'variation' ], true ) ) {
					continue;
				}

				// @TODO handle downloadable product data.
				$cost            = $wc_order->get_item_subtotal( $item_product );
				$se_variation_id = 0;
				$variation_obj   = null;
				if ( $product->is_type( 'simple' ) ) {
					$se_product_id = ProductMigration::get_product_by_wc_id( $product->get_id() );
				} else {
					$se_variation_id = ProductMigration::get_variation_by_wc_id( $product->get_id() );
					$variation_obj   = Helper::get_product_variation( $se_variation_id );
					if ( ! $variation_obj ) {
						continue;
					}
					$se_product_id = $variation_obj->get_product_id();
				}

				$se_price_id = $this->get_price_id( $cost, $se_product_id );
				$se_price    = Helper::get_price( $se_price_id );


				$variation = [];
				if ( $variation_obj ) {
					foreach ( $variation_obj->get_attributes() as $attribute ) {
						$variation[ $attribute->taxonomy ] = $attribute->slug;
					}
				}

				$order_items['line_item'][] = array_merge( [
					// Product
					'product_id'   => $se_product_id,
					'name'         => $product->get_name( 'edit' ),
					'variation_id' => $se_variation_id,
					'variation'    => $variation,
					// Type
					'product_type' => get_post_meta( $se_product_id, '_storeengine_product_type', true ) ?? 'simple',
					// Price
					'price_id'     => $se_price_id,
					'price'        => $cost,
					// cart & tax
					'quantity'     => absint( $item_product->get_quantity( 'edit' ) ),
					'tax_class'    => $item_product->get_tax_class(),
					'subtotal'     => $item_product->get_subtotal( 'edit' ),
					'total'        => $item_product->get_total( 'edit' ),
					'subtotal_tax' => $item_product->get_subtotal_tax( 'edit' ),
					'total_tax'    => $item_product->get_total_tax( 'edit' ),
					'taxes'        => $item_product->get_taxes( 'edit' ),
				], $se_price->get_data() );
			}
		}

		foreach ( $wc_order->get_items( 'coupon' ) as $coupon ) {
			/** @var \WC_Order_Item_Coupon $coupon */
			$order_items['coupon'][ $coupon->get_code( 'edit' ) ] = [
				'code'         => $coupon->get_code( 'edit' ),
				'discount'     => $coupon->get_discount( 'edit' ),
				'discount_tax' => $coupon->get_discount_tax( 'edit' ),
			];
		}

		foreach ( $wc_order->get_items( 'tax' ) as $tax ) {
			/** @var \WC_Order_Item_Tax $tax */
			$order_items['tax'][] = [
				'rate_id'             => $tax->get_rate_id(),
				'name'                => $tax->get_name(),
				'label'               => $tax->get_label(),
				'compound'            => $tax->get_compound(),
				'tax_total'           => $tax->get_tax_total(),
				'shipping_tax_amount' => $tax->get_shipping_tax_total(),
				'rate_percent'        => $tax->get_rate_percent(),
			];
		}

		$refunds = wc_get_orders( [
			'type'   => 'shop_order_refund',
			'parent' => $wc_order->get_id(),
			'limit'  => - 1,
		] );

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		// @TODO: remove all actions that trigger's email.
		remove_all_actions( 'storeengine/order/payment_status_changed' );

		foreach ( $data as $key => $value ) {
			$setter = 'set_' . $key;
			if ( method_exists( $this->se_order, $setter ) ) {
				$this->se_order->{$setter}( $value );
			}
		}

		// Reference.
		$this->se_order->add_meta_data( '_wc_to_se_oid', $this->wc_order_id, true );

		if ( is_wp_error( $this->se_order->save() ) ) {
			return null;
		}

		if ( ! empty( $order_items['line_item'] ) ) {
			foreach ( $order_items['line_item'] as $item ) {
				$product_item = new OrderItemProduct();
				$product_item->set_props( $item );
				$this->se_order->add_item( $product_item );
			}
		}

		if ( ! empty( $order_items['coupon'] ) ) {
			foreach ( $order_items['coupon'] as $coupon ) {

				// Manually add coupon item (avoid triggering coupon validation.
				// Old coupon can trigger validation error and brake import.

				$coupon_item = new OrderItemCoupon();
				$coupon_item->set_code( $coupon['code'] );

				$se_coupon = new Coupon( $coupon['code'] );
				if ( $se_coupon->get_id() ) {
					$coupon_item->add_meta_data( 'coupon_settings', $se_coupon->get_settings() );
				}

				$coupon_item->set_discount( $coupon['discount'] );
				$coupon_item->set_discount_tax( $coupon['discount_tax'] );

				$this->se_order->add_item( $coupon_item );
			}
		}

		foreach ( $order_items['tax'] as $tax ) {
			$tax_item = new Order\OrderItemTax();
			$tax_item->set_rate_id( $tax['rate_id'] );
			$tax_item->set_name( $tax['name'] );
			$tax_item->set_label( $tax['label'] );
			$tax_item->set_compound( $tax['compound'] );
			$tax_item->set_tax_total( $tax['tax_total'] );
			$tax_item->set_shipping_tax_total( $tax['shipping_tax_amount'] );
			$tax_item->set_rate_percent( $tax['rate_percent'] );

			$this->se_order->add_item( $tax_item );
		}

		$this->se_order->save();

		$this->se_order->recalculate_coupons();
		$this->se_order->calculate();
		$this->se_order->save();

		if ( ! empty( $refunds ) ) {
			foreach ( $refunds as $refund ) {
				$this->add_refund( $refund, $this->se_order->get_id() );
			}
		}

		// Update the dates.
		$wpdb->update(
			$wpdb->prefix . 'storeengine_orders',
			[
				'status'           => $data['status'],
				'date_created_gmt' => $data['date_created_gmt'],
				'date_updated_gmt' => $data['date_updated_gmt'],
			],
			[ 'id' => $this->se_order->get_id() ]
		);

		$wpdb->update(
			$wpdb->prefix . 'storeengine_order_operational_data',
			[
				'date_paid_gmt'      => $data['date_paid_gmt'],
				'date_completed_gmt' => $data['date_completed_gmt'],
			],
			[ 'order_id' => $this->se_order->get_id() ]
		);

		return $this->se_order->get_id();
	}

	/**
	 * @param \WC_Order_Refund|\OrderRefund $wc_refund
	 * @param int $se_order_id
	 *
	 * @return void
	 */
	protected function add_refund( $wc_refund, int $se_order_id ) {
		global $wpdb;

		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		// Read data.
		$wc_refund_id = $wc_refund->get_id();
		$data         = [
			'parent_order_id'  => $se_order_id,
			'refunded_by'      => $wc_refund->get_refunded_by( 'edit' ),
			'total_amount'     => $wc_refund->get_amount( 'edit' ),
			'status'           => OrderStatus::COMPLETED,
			'reason'           => $wc_refund->get_reason( 'edit' ),
			'date_created_gmt' => gmdate( 'Y-m-d H:i:s', $wc_refund->get_date_created( 'edit' )->getTimestamp() ),
			'date_updated_gmt' => gmdate( 'Y-m-d H:i:s', $wc_refund->get_date_modified( 'edit' )->getTimestamp() ),
		];

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		// Remove actions.
		remove_all_actions( 'storeengine/order/refund_created' );

		/**
		 * @see Helper::create_refund()
		 */
		$refund = new Refund();

		foreach ( $data as $key => $value ) {
			$setter = 'set_' . $key;
			if ( method_exists( $refund, $setter ) ) {
				$refund->{$setter}( $value );
			}
		}

		// Reference.
		$refund->add_meta_data( '_wc_to_se_oid', $wc_refund_id, true );

		if ( is_wp_error( $refund->save() ) ) {
			return null;
		}

		// Update the dates.
		$wpdb->update(
			$wpdb->prefix . 'storeengine_orders',
			[
				'date_created_gmt' => $data['date_created_gmt'],
				'date_updated_gmt' => $data['date_updated_gmt'],
			],
			[ 'id' => $refund->get_id() ]
		);
	}

	protected function get_price_id( float $price, int $product_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_product_price WHERE product_id = %d AND price = %f", $product_id, $price ) );
	}
}
