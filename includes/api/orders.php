<?php

namespace StoreEngine\API;

use Exception;
use StoreEngine\API\Schema\OrderSchema;
use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\Order as StoreEngineOrder;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\StringUtil;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Orders extends AbstractRestApiController {
	use OrderSchema;

	protected $rest_base = 'order';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_collection_params(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_post_item_schema(),
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			'args'   => [
				'id' => [
					'description' => __( 'Unique identifier for the object.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			'schema' => [ $this, 'get_item_schema' ],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/order_item', [
			'args' => [
				'id' => [
					'description' => __( 'Unique identifier for the object.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_order_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_order_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/order_item/(?P<item_id>[\d]+)', [
			'args' => [
				'id'      => [
					'description' => __( 'Unique identifier for the order.', 'storeengine' ),
					'type'        => 'integer',
				],
				'item_id' => [
					'description' => __( 'Unique identifier for the order item.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_order_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/get_states/(?P<cc>[\S]{2})', [
			'args'   => [
				'cc' => [
					'description' => __( 'ISO 3166-1 alpha-2 (two-letter) country code.', 'storeengine' ),
					'type'        => 'string',
					'required'    => true,
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_states' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			'schema' => [ $this, 'get_item_schema' ],
		] );
	}

	public function add_order_item( $request ) {
		$order_id = $request->get_param( 'id' );
		$order    = Helper::get_order( $order_id );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! $order ) {
			return new WP_Error( 'no-order', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $order->is_editable() ) {
			return new WP_Error( 'order-not-editable', __( 'Order is no longer editable.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$body = $request->get_json_params();
		$type = $body['type'] ?? '';

		switch ( $type ) {
			case 'product':
				if ( ! empty( $body['price_id'] ) ) {
					$price = new \StoreEngine\Classes\Price( absint( $body['price_id'] ) );
					if ( ! $price->get_id() ) {
						return new WP_Error( 'product-not-found', __( 'Product not found.', 'storeengine' ) );
					}

					$order_item = $order->add_product(
						$price,
						max( 1, absint( $body['quantity'] ?? 1 ) ),
						[
							'order'        => $order,
							'variation_id' => $body['variation'] ?? 0,
						]
					);
				} else {
					$order_item = new WP_Error( 'invalid-request', __( 'Product Price ID is required.', 'storeengine' ), [ 'status' => 400 ] );
				}
				break;
			case 'coupon':
				if ( ! empty( $body['code'] ) && ! StringUtil::is_null_or_whitespace( $body['code'] ) ) {
					$order_item = $order->apply_coupon( strtolower( $body['code'] ) );
				} else {
					$order_item = new WP_Error( 'invalid-request', __( 'Coupon code is required.', 'storeengine' ), [ 'status' => 400 ] );
				}
				break;
			case 'fee':
				if ( ! empty( $body['name'] ) && ! empty( $body['amount'] ) && (float) $body['amount'] > 0 ) {
					$order_item = $order->add_fee( $body['name'], (float) $body['amount'] );
				} else {
					$order_item = new WP_Error( 'invalid-request', __( 'Fee name & amount is required.', 'storeengine' ), [ 'status' => 400 ] );
				}
				break;
			default:
				$order_item = new WP_Error( 'invalid-request', __( 'Invalid request.', 'storeengine' ), [ 'status' => 400 ] );
				break;
		}

		if ( is_wp_error( $order_item ) ) {
			return $order_item;
		}

		$order->recalculate_coupons();
		$order->calculate();
		$order->save();

		do_action( 'storeengine/api/order/add_order_item', $order, $order_item, $type );

		return rest_ensure_response( $this->prepare_item_for_response( $order, $request ) );
	}

	public function update_order_item( $request ) {
		$order_id = $request->get_param( 'id' );
		$item_id  = $request->get_param( 'item_id' );

		if ( ! $order_id || ! $item_id ) {
			return new WP_Error( 'invalid-request', __( 'Invalid request.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$order = Helper::get_order( $order_id );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! $order ) {
			return new WP_Error( 'no-order', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $order->is_editable() ) {
			return new WP_Error( 'order-not-editable', __( 'Order is no longer editable.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$item = $order->get_item( absint( $item_id ) );

		if ( ! $item ) {
			return new WP_Error( 'no-order-item', __( 'Order item not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$body     = $request->get_json_params();
		$quantity = absint( $body['quantity'] ?? 0 );

		if ( ! $quantity ) {
			return new WP_Error( 'no-order-item', __( 'Quantity is required.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$total = Formatting::get_price_excluding_tax(
			$item->get_price( 'edit' ),
			$item->get_price_id( 'edit' ),
			$item->get_product_id( 'edit' ),
			[
				'qty'   => $quantity,
				'order' => $order,
			]
		);

		$item->set_quantity( $quantity );
		$item->set_subtotal( $total );
		$item->set_total( $total );
		$item->save();

		$order->recalculate_coupons();
		$order->calculate();
		$order->save();

		do_action( 'storeengine/api/order/update_order_item', $order, $item );

		return rest_ensure_response( $this->prepare_item_for_response( $order, $request ) );
	}

	public function delete_order_item( $request ) {
		$order_id = $request->get_param( 'id' );
		$order    = Helper::get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'no-order', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! $order->is_editable() ) {
			return new WP_Error( 'order-not-editable', __( 'Order is no longer editable.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$body = $request->get_json_params();

		if ( empty( $body['item_id'] ) ) {
			return new WP_Error( 'no-item', __( 'Order item not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$item_id = absint( $body['item_id'] );
		$item    = $order->get_item( $item_id, false );

		$order->remove_item( $item_id );
		$order->recalculate_coupons();
		$order->calculate();
		$order->save();

		do_action( 'storeengine/api/order/delete_order_item', $order, $item, $item_id );

		return rest_ensure_response( $this->prepare_item_for_response( $order, $request ) );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws StoreEngineInvalidArgumentException
	 */
	public function get_items( $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$status   = $request->get_param( 'status' );
		$search   = $request->get_param( 'search' );
		$customer = absint( $request->get_param( 'customer' ) );
		$where    = [];

		if ( $status && ! in_array( $status, [ 'all', 'any', 'draft' ], true ) ) {
			$where[] = [
				'key'   => 'status',
				'value' => $status,
			];
		} else {
			$where[] = [
				'key'     => 'status',
				'value'   => 'draft',
				'compare' => '!=',
			];
		}

		if ( $customer ) {
			$where[] = [
				'key'     => 'customer_id',
				'value'   => $customer,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
			if ( $search ) {
				$where[] = [
					'key'     => 'payment_method_title',
					'value'   => "%$search%",
					'compare' => 'LIKE',
				];
			}
		} else {
			if ( $search ) {
				$where[] = [
					'relation' => 'OR',
					[
						'key'     => 'billing_email',
						'value'   => "%$search%",
						'compare' => 'LIKE',
					],
					[
						'key'     => 'payment_method_title',
						'value'   => "%$search%",
						'compare' => 'LIKE',
					],
				];
			}
		}

		$query = new OrderCollection( [
			'per_page' => absint( $request->get_param( 'per_page' ) ),
			'page'     => $page,
			'where'    => $where,
		], 'order' );
		$data  = [];

		foreach ( $query->get_results() as $order ) {
			$response = $this->prepare_item_for_response( $order, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		return $this->prepare_query_response( $data, $query, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param StoreEngineOrder $item
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array Links for the given order.
	 */
	protected function prepare_links( $item, WP_REST_Request $request ): array {
		$links = parent::prepare_links( $item, $request );

		if ( $item->get_user_id() ) {
			$links['customer'] = [
				'href' => rest_url( sprintf( '/%s/customers/%d', $this->namespace, $item->get_user_id() ) ),
			];
		}

		if ( $item->get_parent_id() ) {
			$links['up'] = [
				'href' => rest_url( sprintf( '/%s/orders/%d', $this->namespace, $item->get_parent_id() ) ),
			];
		}

		return $links;
	}

	/**
	 * @param StoreEngineOrder $item Order object.
	 * @param WP_REST_Request $request Requests.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$schema = $this->get_public_item_schema();
		$data   = [
			'id'          => $item->get_id(),
			'is_editable' => $item->is_editable(),
			'currency'    => $item->get_currency(),
		];

		if ( isset( $schema['properties']['status'] ) ) {
			$data['status'] = $item->get_status();
		}

		$data['paid_status'] = $item->get_paid_status();

		if ( $item->get_date_paid_gmt() ) {
			$data['date_paid_gmt'] = $item->get_date_paid_gmt()->format( 'Y-m-d H:i:s' );
		} else {
			$data['date_paid_gmt'] = null;
		}

		if ( isset( $schema['properties']['type'] ) ) {
			$data['type'] = $item->get_type();
		}

		if ( isset( $schema['properties']['tax_amount'] ) ) {
			$data['tax_amount'] = $item->get_tax_amount();
		}

		if ( isset( $schema['properties']['refunds_total'] ) ) {
			$data['refunds_total'] = $item->get_total_refunded();
		}

		if ( isset( $schema['properties']['subtotal_amount'] ) ) {
			$data['subtotal_amount'] = $item->get_subtotal();
		}

		if ( isset( $schema['properties']['date_created_gmt'] ) ) {
			if ( $item->get_date_created_gmt() ) {
				$data['date_created_gmt'] = $item->get_date_created_gmt()->format( 'Y-m-d H:i:s' );
			} else {
				$data['date_created_gmt'] = null;
			}
		}

		if ( isset( $schema['properties']['order_placed_date_gmt'] ) ) {
			if ( $item->get_order_placed_date_gmt() ) {
				$data['order_placed_date_gmt'] = $item->get_order_placed_date_gmt()->format( 'Y-m-d H:i:s' );
			} else {
				$data['order_placed_date_gmt'] = null;
			}
		}

		if ( isset( $schema['properties']['order_placed_date'] ) ) {
			if ( $item->get_order_placed_date() ) {
				$data['order_placed_date'] = $item->get_order_placed_date()->format( 'Y-m-d H:i:s' );
			} else {
				$data['order_placed_date'] = null;
			}
		}

		if ( isset( $schema['properties']['coupons'] ) ) {
			$data['coupons']        = array_values( array_map( fn( $coupon ) => [
				'id'   => $coupon->get_id(),
				'code' => $coupon->get_name(),
			], $item->get_coupons() ) );
			$data['total_discount'] = $item->get_total_discount();
		}

		$data['shipping_methods'] = array_values( array_map( fn( $shipping ) => [
			'name'    => $shipping->get_name(),
			'taxable' => $shipping->get_tax_status(),
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

		if ( isset( $schema['properties']['total_amount'] ) ) {
			$data['total_amount']    = $item->get_total_amount();
			$data['subtotal_amount'] = $item->get_subtotal();
		}

		if ( isset( $schema['properties']['customer_id'] ) ) {
			$data['customer_id'] = $item->get_customer_id();
		}

		// customer email and customer name
		if ( isset( $schema['properties']['customer_email'] ) ) {
			$data['customer_email'] = $item->get_billing_email();
		}

		if ( isset( $schema['properties']['customer_name'] ) ) {
			$data['customer_name'] = trim( $item->get_billing_first_name() . ' ' . $item->get_billing_last_name() );
		}

		if ( isset( $schema['properties']['billing_email'] ) ) {
			$data['billing_email'] = $item->get_order_email();
		}

		if ( isset( $schema['properties']['payment_method'] ) ) {
			$data['payment_method'] = $item->get_payment_method();
		}

		if ( isset( $schema['properties']['payment_method_title'] ) ) {
			$data['payment_method_title'] = $item->get_payment_method_title();
		}

		if ( isset( $schema['properties']['transaction_id'] ) ) {
			$data['transaction_id'] = $item->get_transaction_id();
		}

		if ( isset( $schema['properties']['customer_note'] ) ) {
			$data['customer_note'] = $item->get_customer_note();
		}

		$data['is_auto_complete_digital_order'] = $item->get_auto_complete_digital_order();

		if ( isset( $schema['properties']['meta'] ) ) {
			$data['meta'] = $item->get_meta_data();
		}

		// purchase_items
		if ( isset( $schema['properties']['purchase_items'] ) ) {
			$data['purchase_items'] = array_map( fn( $order_item ) => array_merge(
				[
					'id'       => $order_item->get_id(),
					'edit_url' => get_post( $order_item->get_product_id() ) ? admin_url( 'admin.php?page=storeengine-products&id=' . $order_item->get_product_id() . '&action=edit' ) : '',
				],
				$order_item->get_data(),
				[
					'formatted_metadata' => array_values( $order_item->get_all_formatted_metadata() ),
				]
			), $item->get_items() );
			$data['purchase_items'] = array_values( $data['purchase_items'] );
		}

		if ( isset( $schema['properties']['refunds'] ) ) {
			try {
				$data['refunds'] = array_map( fn( $refund ) => [
					'id'         => $refund->get_id(),
					'amount'     => $refund->get_total(),
					'refund_by'  => array(
						'user_id'      => $refund->get_refunded_by() ? $refund->get_refunded_by_user()->get_id() : null,
						'name'         => $refund->get_refunded_by() && $refund->get_refunded_by_user()->get_first_name() ? $refund->get_refunded_by_user()->get_first_name() . ' ' . $refund->get_refunded_by_user()->get_last_name() : null,
						'display_name' => $refund->get_refunded_by() ? $refund->get_refunded_by_user()->get_display_name() : null,
					),
					'created_at' => $refund->get_date_created_gmt() ? $refund->get_date_created_gmt()->format( 'Y-m-d H:i:s' ) : null,
				], $item->get_refunds() );
			} catch ( StoreEngineException $e ) {
				$data['refunds'] = [];
			}
		}

		if ( isset( $schema['properties']['billing_address'] ) ) {
			$data['billing_address'] = $item->get_address();
			if ( isset( $data['billing_address']['address_type'] ) ) {
				unset( $data['billing_address']['address_type'] );
			}
		}
		if ( isset( $schema['properties']['shipping_address'] ) ) {
			$data['shipping_address'] = $item->get_address( 'shipping' );
			if ( isset( $data['shipping_address']['address_type'] ) ) {
				unset( $data['shipping_address']['address_type'] );
			}
		}

		$data['notes'] = $item->get_order_notes();

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $item, $request ) );

		return $response;
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		do_action( 'storeengine/api/before_create_order', $request );

		try {
			$order = new StoreEngineOrder();
			$order->set_status( OrderStatus::AUTO_DRAFT );
			$order->set_created_via( 'admin-api' );
			$order->save();
			$order->read( true );

			$response = $this->prepare_item_for_response( $order, $request );

			return rest_ensure_response( $response );
		} catch ( StoreEngineException $exception ) {
			return rest_ensure_response( $exception->toWpError() );
		} catch ( Exception $exception ) {
			return rest_ensure_response( new WP_Error( 'something-went-wrong', $exception->getMessage(), [ 'status' => 500 ] ) );
		}

		// @TODO create api endpoint with the ability to add order item
		//       directly so we can trigger 'storeengine/api/after_create_order' hook for the lookup.
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found

//		if ( ! isset( $request['purchase_items'] ) || empty( $request['purchase_items'] ) ) {
//			return new WP_Error( 'purchase_items_required', __( 'Please select at least one product!', 'storeengine' ) );
//		}
//
//		$order       = new StoreEngineOrder();
//		$customer_id = $request->get_param( 'customer_id' );
//		$order->set_customer_id( $customer_id );
//
//		$order_email = $request['billing_email'] ?? '';
//		if ( ! $order_email && ! $order->get_order_email( 'edit' ) && $order->get_customer_id( 'edit' ) ) {
//			$order_email = $order->get_customer()->get_email( 'edit' );
//		}
//
//		if ( $order_email && $order_email !== $order->get_order_email( 'edit' ) ) {
//			$order->set_order_email( $order_email );
//		}
//
//		$payment_gateway = null;
//		if ( isset( $request['payment_method'] ) ) {
//			$payment_gateway = Helper::get_payment_gateway( $request['payment_method'] );
//
//			if ( ! $payment_gateway ) {
//				return rest_ensure_response( new WP_Error( 'payment_gateway_error', __( 'Payment method not found.', 'storeengine' ), [ 'status' => 400 ] ) );
//			}
//		}
//
//		$order->set_status( $request['status'] ?? Constants::ORDER_STATUS_PENDING_PAYMENT );
//		$order->set_order_email( $order_email );
//		$order->set_payment_method( $payment_gateway );
//		$order->set_customer_note( $request['order_note'] ?? '' );
//		$order->set_transaction_id( $request['transaction_id'] ?? '' );
//
//		if ( ! empty( $request['billing_address'] ) && is_array( $request['billing_address'] ) ) {
//			$order->set_billing_address( $request['billing_address'] );
//		}
//
//		if ( ! empty( $request['shipping_address'] ) && is_array( $request['shipping_address'] ) ) {
//			$order->set_shipping_address( $request['shipping_address'] );
//		}
//
//		foreach ( $request['purchase_items'] as $purchase_item ) {
//			$order->add_product( $purchase_item['price_id'], $purchase_item['quantity'], array_merge( $purchase_item, [ 'order' => $order ] ) );
//		}
//
//		if ( ! empty( $request['coupons'] ) && is_array( $request['coupons'] ) ) {
//			foreach ( $request['coupons'] as $coupon ) {
//				if ( StringUtil::is_null_or_whitespace( $coupon ) ) {
//					continue;
//				}
//				$coupon   = new Coupon( $coupon );
//				$is_valid = $coupon->validate_coupon();
//				if ( $is_valid ) {
//					$order->apply_coupon( $coupon );
//				}
//			}
//		}
//
//		$order->save();
//		$order->recalculate_coupons();
//		$order->calculate();
//		$order->save();
//
//		do_action( 'storeengine/api/after_create_order', $order );
//
//		return rest_ensure_response( $this->prepare_item_for_response( $order, $request ) );
	}

	public function get_item( $request ) {
		try {
			$order = Helper::get_order( $request['id'] );

			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$response = $this->prepare_item_for_response( $order, $request );

			return rest_ensure_response( $response );
		} catch ( StoreEngineException $exception ) {
			return rest_ensure_response( $exception->toWpError() );
		} catch ( Exception $exception ) {
			return rest_ensure_response( new WP_Error( 'something-went-wrong', $exception->getMessage(), [ 'status' => 500 ] ) );
		}
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws StoreEngineException
	 */
	public function update_item( $request ) {
		do_action( 'storeengine/api/before_update_order', $request );
		$order_id = $request->get_param( 'id' );
		$order    = Helper::get_order( $order_id );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! $order ) {
			return new WP_Error( 'invalid_order_id', __( 'Invalid order id!', 'storeengine' ) );
		}

		// Status can be updated even if order is no longer editable.

		$previous_status = $order->get_status( 'edit' );
		$body            = $request->get_json_params();
		$order           = $this->set_core_order( $order, $body );

		$order_email = $request['billing_email'] ?? ( $body['billing_email'] ?? '' );
		if ( ! $order_email && ! $order->get_order_email( 'edit' ) && $order->get_customer_id( 'edit' ) ) {
			$order_email = $order->get_customer()->get_email( 'edit' );
		}

		if ( $order_email && $order_email !== $order->get_order_email( 'edit' ) ) {
			$order->set_order_email( $order_email );
		}

		if ( array_key_exists( 'billing_address', $body ) ) {
			$order = $this->set_order_address( $order, $body['billing_address'] );
		}

		if ( array_key_exists( 'shipping_address', $body ) ) {
			$order = $this->set_order_address( $order, $body['shipping_address'], 'shipping' );
		}

		$order->save();
		$order->recalculate_coupons();
		$order->calculate();
		$order->save();

		$response = $this->prepare_item_for_response( $order, $request );

		if ( 'auto-draft' === $previous_status && $previous_status !== $order->get_status( 'edit' ) ) {
			do_action( 'storeengine/api/after_create_order', $order );
		} else {
			do_action( 'storeengine/api/after_update_order', $order );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * @param StoreEngineOrder $order Order Object.
	 * @param array $data Data.
	 *
	 * @return StoreEngineOrder
	 * @throws StoreEngineException
	 */
	protected function set_core_order( StoreEngineOrder $order, array $data ): StoreEngineOrder {
		if ( array_key_exists( 'billing_email', $data ) ) {
			$order->set_order_email( $data['billing_email'] );
		}

		if ( array_key_exists( 'currency', $data ) ) {
			$order->set_currency( $data['currency'] );
		}

		if ( array_key_exists( 'customer_id', $data ) ) {
			$order->set_customer_id( $data['customer_id'] );
		}

		if ( array_key_exists( 'payment_method', $data ) ) {
			$order->set_payment_method( $data['payment_method'] );
		}

		if ( array_key_exists( 'payment_method_title', $data ) ) {
			$order->set_payment_method_title( $data['payment_method_title'] );
		}

		if ( array_key_exists( 'status', $data ) ) {
			$order->set_status( $data['status'] );
		}

		if ( array_key_exists( 'transaction_id', $data ) ) {
			$order->set_transaction_id( (string) $data['transaction_id'] );
		}

		return $order;
	}

	/**
	 * @param StoreEngineOrder $order Order Object.
	 * @param array $address Address Data.
	 * @param string $type Address Type.
	 *
	 * @return StoreEngineOrder
	 */
	protected function set_order_address( StoreEngineOrder $order, array $address, string $type = 'billing' ): StoreEngineOrder {
		$fields = array(
			'first_name',
			'last_name',
			'email',
			'phone',
			'address_1',
			'address_2',
			'state',
			'city',
			'country',
			'postcode',
		);
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $address ) ) {
				$order->{"set_{$type}_{$field}"}( $address[ $field ] ?? '' );
			}
		}

		return $order;
	}

	public function delete_item( $request ) {
		$force    = (bool) $request['force'];
		$order_id = (int) $request['id'];
		$object   = Helper::get_order( $order_id );

		if ( is_wp_error( $object ) ) {
			return $object;
		}

		if ( ! $object || ! $object->get_id() ) {
			return new WP_Error( 'invalid_item_id', __( 'Invalid ID!', 'storeengine' ), [ 'status' => 404 ] );
		}

		$result         = false;
		$supports_trash = EMPTY_TRASH_DAYS > 0 && is_callable( [ $object, 'get_status' ] ) && $object->is_trashable();

		/**
		 * Filter whether an object is trashable.
		 *
		 * Return false to disable trash support for the object.
		 *
		 * @param boolean $supports_trash Whether the object type support trashing.
		 * @param AbstractEntity $object The object being considered for trashing support.
		 */
		$supports_trash = apply_filters( "storeengine/api/{$object->get_object_type()}/object_trashable", $supports_trash, $object );

		// Maybe check additional permissions.

		do_action( 'storeengine/api/before_delete_order', $request );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $object, $request );

		if ( $force ) {
			$object->delete( true );
			$result = 0 === $object->get_id();
		} else {
			// If we don't support trashing for this type, error out.
			if ( ! $supports_trash ) {
				/* translators: %s: post type */
				return new WP_Error( 'storeengine_rest_trash_not_supported', sprintf( __( 'The %s does not support trashing.', 'storeengine' ), $object->get_object_type() ), [ 'status' => 501 ] );
			}

			// Otherwise, only trash if we haven't already.
			if ( is_callable( [ $object, 'get_status' ] ) ) {
				if ( 'trash' === $object->get_status() ) {
					/* translators: %s: post type */
					return new WP_Error( 'storeengine_rest_already_trashed', sprintf( __( 'The %s has already been trashed.', 'storeengine' ), $object->get_object_type() ), [ 'status' => 410 ] );
				}

				$object->trash();
				$result = 'trash' === $object->get_status();
			}
		}

		if ( ! $result ) {
			/* translators: %s: post type */
			return new WP_Error( 'storeengine_rest_cannot_delete', sprintf( __( 'Failed to delete %s.', 'storeengine' ), $object->get_object_type() ), [ 'status' => 500 ] );
		}

		/**
		 * Fires after a single object is deleted or trashed via the REST API.
		 *
		 * @param AbstractEntity $object The deleted or trashed object.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( "storeengine/api/{$object->get_object_type()}delete__object", $object, $response, $request );

		do_action( 'storeengine/api/after_delete_order', $object, $order_id, $force );

		return rest_ensure_response( [
			'previous' => $response,
			'id'       => $order_id,
			'force'    => $force,
		] );
	}

	public function get_states( WP_REST_Request $request ): WP_REST_Response {
		$cc     = strtoupper( $request->get_param( 'cc' ) );
		$states = Countries::init()->get_states( $cc );

		return new WP_REST_Response( [
			'cc'     => $cc,
			'states' => $states ? $states : false,
		], 200 );
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * @param $cart
	 *
	 * @return array
	 *
	 * @deprecated
	 */
	protected function prepare_purchase_items_data( $cart ): array {
		$purchase_items = [];
		foreach ( $cart->get_cart_items() as $product ) {
			$purchase_items[] = [
				'product_id'          => $product['id'],
				'variation_id'        => 0,
				'price_id'            => $product['price_id'],
				'product_qty'         => $product['quantity'],
				'price'               => $product['price'],
				'coupon_amount'       => 0,
				'tax_amount'          => 0,
				'shipping_amount'     => 0,
				'shipping_tax_amount' => 0,
			];
		}

		return $purchase_items;
	}

	/**
	 * @param $request
	 *
	 * @return array
	 *
	 * @deprecated
	 */
	protected function prepare_item_for_database( $request ): array {
		// Insert purchase items into cart with for each loop.
		$request['customer_id'] = empty( $request['customer_id'] ) ? $request['id'] : $request['customer_id'];
		$cart_model             = new \StoreEngine\Models\Cart( $request['customer_id'] );
		$purchase_items         = $this->prepare_purchase_items_data( $cart_model );

		return [
			'status'               => OrderStatus::PAYMENT_PENDING,
			'currency'             => Helper::get_settings( 'store_currency', 'USD' ),
			'type'                 => 'onetime',
			'customer_id'          => $request['customer_id'],
			'billing_email'        => $request['billing_email'],
			'payment_method'       => $request['payment_method'] ?? '',
			'payment_method_title' => $request['payment_method'] ?? '',
			'customer_note'        => $request['order_note'] ?? '',
			'transaction_id'       => $request['transaction_id'] ?? '',
			'meta'                 => [],
			'purchase_items'       => $purchase_items,
			'billing_address'      => [
				'first_name' => $request['billing_address']['first_name'] ?? '',
				'last_name'  => $request['billing_address']['last_name'] ?? '',
				'company'    => $request['billing_address']['company'] ?? '',
				'address_1'  => $request['billing_address']['address_1'] ?? '',
				'address_2'  => $request['billing_address']['address_2'] ?? '',
				'city'       => $request['billing_address']['city'] ?? '',
				'state'      => $request['billing_address']['state'] ?? '',
				'postcode'   => $request['billing_address']['postcode'] ?? '',
				'country'    => $request['billing_address']['country'] ?? '',
				'email'      => $request['billing_address']['email'] ?? '',
				'phone'      => $request['billing_address']['phone'] ?? '',
			],
			'shipping_address'     => [
				'first_name' => $request['shipping_address']['first_name'] ?? '',
				'last_name'  => $request['shipping_address']['last_name'] ?? '',
				'company'    => $request['shipping_address']['company'] ?? '',
				'address_1'  => $request['shipping_address']['address_1'] ?? '',
				'address_2'  => $request['shipping_address']['address_2'] ?? '',
				'city'       => $request['shipping_address']['city'] ?? '',
				'state'      => $request['shipping_address']['state'] ?? '',
				'postcode'   => $request['shipping_address']['postcode'] ?? '',
				'country'    => $request['shipping_address']['country'] ?? '',
				'email'      => $request['shipping_address']['email'] ?? '',
				'phone'      => $request['shipping_address']['phone'] ?? '',
			],
		];
	}

	/**
	 * @param array $schema
	 * @param $order_purchase_items
	 *
	 * @return array
	 * @deprecated
	 */
	protected function _prepare_order_purchase_items_for_database( array $schema, $order_purchase_items ): array {
		$order_details = [];
		$keys          = [
			'product_id'          => 'int',
			'variation_id'        => 'int',
			'product_qty'         => 'int',
			'coupon_amount'       => 'float',
			'tax_amount'          => 'float',
			'shipping_amount'     => 'float',
			'shipping_tax_amount' => 'float',
		];

		foreach ( $order_purchase_items as $key => $order_item ) {
			foreach ( $keys as $field => $type ) {
				if ( ! empty( $schema['purchase_items']['properties'][ $field ] ) && isset( $order_item[ $field ] ) ) {
					settype( $order_item[ $field ], $type );
					$order_details[ $key ][ $field ] = $order_item[ $field ];
				}
			}
		}

		return $order_details;
	}

	/**
	 * @param array $schema
	 * @param $request_billing_address
	 *
	 * @return array
	 * @deprecated
	 */
	protected function _prepare_order_billing_address_for_database( array $schema, $request_billing_address ): array {
		$billing_address = [];
		$keys            = [
			'first_name' => 'string',
			'last_name'  => 'string',
			'company'    => 'string',
			'address_1'  => 'string',
			'address_2'  => 'string',
			'city'       => 'string',
			'state'      => 'string',
			'postcode'   => 'string',
			'country'    => 'string',
			'email'      => 'string',
			'phone'      => 'string',
		];

		foreach ( $keys as $key => $type ) {
			if ( ! empty( $schema['billing_address']['properties'][ $key ] ) && isset( $request_billing_address[ $key ] ) ) {
				$value = $request_billing_address[ $key ];
				settype( $value, $type );
				$billing_address[ $key ] = $value;
			}
		}

		return $billing_address;
	}
}
