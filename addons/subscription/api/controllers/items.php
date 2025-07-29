<?php

namespace StoreEngine\Addons\Subscription\API\Controllers;

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use WP_REST_Request;
use WP_REST_Response;
use StoreEngine\Addons\Subscription\API\Schema\SubscriptionSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Items extends Abstracts\SubscriptionController {
	use Traits\Helper, SubscriptionSchema;

	protected string $route = '';

	public function read( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 10 );
		$page     = max( 1, absint( $request->get_param( 'page' ) ?? 0 ) );

		$subscription_status = $request->get_param( 'status' );
		$search              = $request->get_param( 'search' );
		$where               = [ 'relation' => 'AND' ];

		if ( ! empty( $subscription_status ) && 'any' !== $subscription_status ) {
			$where[] = [
				'key'   => 'status',
				'value' => $subscription_status,
			];
		}

		if ( ! empty( $search ) ) {
			$where[] = [
				[
					'relation' => 'OR',
					[
						'key'     => 'billing_email',
						'value'   => '%' . $search . '%',
						'compare' => 'LIKE',
					],
				],
			];
		}

		$query = new SubscriptionCollection( [
			'per_page' => $per_page,
			'page'     => $page,
			'where'    => $where,
			'orderby'  => 'id',
			'order'    => 'DESC',
		] );

		$data = [];
		foreach ( $query->get_results() as $subscription ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $subscription, $request ) );
		}

		$response = rest_ensure_response( $data );

		$this->prepare_pagination_headers( $response, $request, $page, $query->get_found_results(), $query->get_max_num_pages() );

		return $response;
	}
}
