<?php

namespace StoreEngine\Addons\Subscription\API\Controllers\Traits;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\API\Orders as OrdersApi;
use StoreEngine\Classes\Order\AbstractOrderItem;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Integrations;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper as UtilsHelper;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	public function args(): array {
		return [];
	}

	public function permission_check(): bool {
		return UtilsHelper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * @param AbstractOrderItem[]|OrderItemProduct[] $products
	 *
	 * @return array
	 */
	protected function product_data( array $products ): array {
		$product_names = [];
		foreach ( $products as $product ) {
			$product_names[] = [
				'product_name'    => $product->get_name(),
				'price_name'      => $product->get_price_name(),
				'price_structure' => Utils::format_cart_price_html( [
					'recurring_amount'      => $product->get_price(),
					// Schedule details
					'subscription_interval' => $product->get_payment_duration(),
					'subscription_period'   => $product->get_payment_duration_type(),
					'subscription_length'   => 0,
				] ),
			];
		}

		return $product_names;
	}

	protected function date_as_string( ?StoreengineDatetime $date_time_object ): ?string {
		return is_null( $date_time_object ) ? null : $date_time_object->format( 'Y-m-d H:i:s' );
	}

	/**
	 * @param Subscription $item
	 * @param $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$start_date        = $this->date_as_string( $item->get_start_date() );
		$end_date          = $this->date_as_string( $item->get_end_date() );
		$next_payment_date = $this->date_as_string( $item->get_next_payment_date() );
		$last_payment_date = $this->date_as_string( $item->get_last_payment_date() );
		$trial_end_date    = $this->date_as_string( $item->get_trial_end_date() );
		$product_data      = $this->product_data( $item->get_items() );

		$data = [
			'id'                    => $item->get_id(),
			'is_editable'           => $item->is_editable(),
			'currency'              => $item->get_currency(),

			'trial'                 => $item->get_trial(),
			'trial_days'            => $item->get_trial_days(),
			'start_date'            => $start_date,
			'trial_end_date'        => $trial_end_date,
			'next_payment_date'     => $next_payment_date,
			'last_payment_date'     => $last_payment_date,
			'end_date'              => $end_date,
			'payment_duration'      => $item->get_payment_duration(),
			'payment_duration_type' => $item->get_payment_duration_type(),
			// Deprecated items.
			'products'              => $product_data,
			'product_name'          => $product_data[0]['product_name'] ?? '',
			'price_name'            => $product_data[0]['price_name'] ?? '',
			'price_structure'       => $product_data[0]['price_structure'] ?? '',
		];

		$data['status']        = $item->get_status();
		$data['type']          = $item->get_type();
		$data['tax_amount']    = $item->get_tax_amount();
		$data['refunds_total'] = $item->get_total_refunded();
		if ( $item->get_date_created_gmt() ) {
			$data['date_created_gmt'] = $item->get_date_created_gmt()->format( 'Y-m-d H:i:s' );
		} else {
			$data['date_created_gmt'] = null;
		}
		if ( $item->get_order_placed_date_gmt() ) {
			$data['order_placed_date_gmt'] = $item->get_order_placed_date_gmt()->format( 'Y-m-d H:i:s' );
		} else {
			$data['order_placed_date_gmt'] = null;
		}
		if ( $item->get_order_placed_date() ) {
			$data['order_placed_date'] = $item->get_order_placed_date()->format( 'Y-m-d H:i:s' );
		} else {
			$data['order_placed_date'] = null;
		}

		$data['coupons']        = array_values( array_map( fn( $coupon ) => [
			'id'   => $coupon->get_id(),
			'code' => $coupon->get_name(),
		], $item->get_coupons() ) );
		$data['total_discount'] = $item->get_total_discount();

		$data['shipping_methods'] = array_values( array_map( fn( $shipping ) => [
			'name' => $shipping->get_name(),
		], $item->get_shipping_methods() ) );

		$data['shipping_total'] = $item->get_shipping_total();

		$data['fees'] = array_values( array_map( fn( $fee ) => [
			'id'         => $fee->get_id(),
			'name'       => $fee->get_name( 'edit' ),
			'tax_class'  => $fee->get_tax_class(),
			'tax_status' => $fee->get_tax_status(),
			'amount'     => Formatting::round_tax_total( $fee->get_amount( 'edit' ) ),
			'total'      => $fee->get_total(),
			'total_tax'  => $fee->get_total_tax(),
			'taxes'      => $fee->get_taxes(),
		], $item->get_fees() ) );

		$data['taxes'] = array_values( array_map( fn( $tax ) => [
			'code'   => $tax->code,
			'label'  => $tax->label,
			'amount' => Formatting::round_tax_total( $tax->amount ),
		], $item->get_tax_totals() ) );

		$data['total_amount']         = $item->get_total_amount();
		$data['subtotal_amount']      = $item->get_subtotal();
		$data['customer_id']          = $item->get_customer_id();
		$data['customer_email']       = $item->get_billing_email();
		$data['customer_name']        = trim( $item->get_billing_first_name() . ' ' . $item->get_billing_last_name() );
		$data['billing_email']        = $item->get_billing_email();
		$data['payment_method']       = $item->get_payment_method();
		$data['payment_method_title'] = $item->get_payment_method_title();
		$data['transaction_id']       = $item->get_transaction_id();
		$data['customer_note']        = $item->get_customer_note();
		$data['meta']                 = $item->get_meta_data();
		$data['billing_address']      = $item->get_address();
		$data['purchase_items']       = [];
		$data['integrations']         = [];
		$data['shipping_address']     = $item->get_address( 'shipping' );
		$data['notes']                = $item->get_order_notes();

		foreach ( $item->get_items() as $order_item ) {
			$data['purchase_items'][] = array_merge(
				[
					'id'       => $order_item->get_id(),
					'edit_url' => get_post( $order_item->get_product_id() ) ? admin_url( 'admin.php?page=storeengine-products&id=' . $order_item->get_product_id() . '&action=edit' ) : '',
				],
				$order_item->get_data(),
				[
					'formatted_metadata' => array_values( $order_item->get_all_formatted_metadata() ),
				]
			);

			foreach ( UtilsHelper::get_integrations_by_price_id( $order_item->get_price_id() ) as $integration ) {
				$provider                           = Integrations::init()->get_integration( $integration->get_provider() );
				$integration_data                   = $integration->get_data();
				$integration_data['provider_label'] = $provider->get_label();
				$integration_data['provider_logo']  = $provider->get_logo();
				$integration_data['details']        = sprintf(
					esc_html__( '%1$s: %2$s', 'storeengine' ),
					esc_html( $provider->get_label() ),
					esc_html( get_the_title( $integration->get_integration_id() ) )
				);

				$data['integrations'][] = $integration_data;
			}
		}

		if ( isset( $data['billing_address']['address_type'] ) ) {
			unset( $data['billing_address']['address_type'] );
		}

		if ( isset( $data['shipping_address']['address_type'] ) ) {
			unset( $data['shipping_address']['address_type'] );
		}


		$related_orders      = [];
		$subscription_orders = array_values( $item->get_related_orders() );

		if ( $subscription_orders ) {
			$query = new OrderCollection( [
				'per_page' => - 1,
				'where'    => [
					[
						'key'     => 'id',
						'value'   => array_values( $subscription_orders ),
						'compare' => 'IN',
					],
				],
			], 'order' );

			$order_api = new OrdersApi();

			if ( $query->have_results() ) {
				foreach ( $query->get_results() as $order ) {
					$response = $order_api->prepare_item_for_response( $order, $request );
					if ( array_key_exists( 'notes', $response->data ) ) {
						unset( $response->data['notes'] );
					}
					foreach ( [ 'parent', 'renewal', 'switch' ] as $order_type ) {
						if ( 'parent' === $order_type && $item->get_parent_id() === $order->get_id() ) {
							$response->data['order_type'] = 'parent_order';
							break;
						}
						if ( (int) $order->get_meta( '_subscription_' . $order_type ) === $item->get_id() ) {
							$response->data['order_type'] = $order_type . '_order';
							break;
						}
					}

					$related_orders[] = $order_api->prepare_response_for_collection( $response );
				}
			}
		}

		$data['related_orders'] = $related_orders;

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $item, $request ) );

		return $response;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param Subscription $item Order object.
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return array Links for the given order.
	 */
	protected function prepare_links( $item, $request ): array {
		$links = parent::prepare_links( $item, $request );

		if ( 0 !== $item->get_user_id() ) {
			$links['customer'] = [
				'href' => rest_url( sprintf( '/%s/customers/%d', $this->namespace, $item->get_user_id() ) ),
			];
		}

		if ( 0 !== $item->get_parent_id() ) {
			$links['up'] = [
				'href' => rest_url( sprintf( '/%s/order/%d', $this->namespace, $item->get_parent_id() ) ),
			];
		}

		return $links;
	}

}
