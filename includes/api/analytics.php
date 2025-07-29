<?php

namespace StoreEngine\API;

use StoreEngine\API\Schema\AnalyticsSchema;
use StoreEngine\Utils\Helper;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

class Analytics extends WP_REST_Controller {
	use AnalyticsSchema;

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'analytics';

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => $this->get_collection_params(),
			],
		] );
	}

	public function get_permission_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public static function add_refund_statuses( array $statuses ): array {
		$statuses[] = 'refunded';

		return $statuses;
	}

	public function get_analytics( $request ): WP_REST_Response {
		// HTML datetime-local tag doesn't have seconds.
		$start_date = gmdate( 'Y-m-d H:i:00', strtotime( $request->get_param( 'start_date' ) ) );
		$end_date   = gmdate( 'Y-m-d H:i:59', strtotime( $request->get_param( 'end_date' ) ?? gmdate( 'd-m-Y h:i:s', strtotime( '-7 days' ) ) ) );

		add_filter( 'storeengine/order_paid_statuses', [ __CLASS__, 'add_refund_statuses' ] );

		// get data.
		$analytics    = new \StoreEngine\Classes\Analytics();
		$totals       = $analytics->get_orders_totals( $start_date, $end_date );
		$total_orders = (float) $totals->total_orders;
		$total_sales  = (float) $totals->total_sales;
		$total_tax    = (float) $totals->total_tax;

		$total_refunds = $analytics->get_total_refunds( $start_date, $end_date );
		$total_refunds = $total_refunds ? (float) $total_refunds->total_refunds : 0;
		$gross_sales   = $total_sales - $total_refunds;

		$product_sold        = $analytics->get_product_sold( $start_date, $end_date );
		$total_products_sold = $product_sold ? (float) $product_sold->total_products_sold : 0;
		$new_customers_count = Helper::get_new_customers_count( $start_date, $end_date );

		remove_filter( 'storeengine/order_paid_statuses', [ __CLASS__, 'add_refund_statuses' ] );

		$response = compact('total_orders', 'total_sales', 'total_refunds', 'gross_sales', 'total_tax', 'total_products_sold', 'new_customers_count');

		return new WP_REST_Response( $response, 200 );
	}

}
