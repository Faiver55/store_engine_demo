<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @method array<Order|Refund> get_results()
 */
class OrderCollection extends AbstractCollection {
	protected string $table = 'storeengine_orders';

	protected string $object_type = 'order';

	protected string $meta_type = 'order';

	protected string $primary_key = 'id';

	protected string $parent_key = 'parent_order_id';

	protected string $returnType = Order::class; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected array $must_where = [
		'relation' => 'AND',
		[
			'relation' => 'OR',
			[
				'key'     => 'type',
				'value'   => 'order',
				'compare' => '=',
			],
			[
				'key'     => 'type',
				'value'   => 'refund',
				'compare' => '=',
			],
		],
	];

	/**
	 * @param array|string $query
	 * @param ?string<order|refund> $type
	 *
	 * @throws StoreEngineInvalidArgumentException
	 */
	public function __construct( $query = '', string $type = null ) {
		if ( in_array( $type, [ 'order', 'refund' ], true ) ) {
			$this->must_where = [
				'relation' => 'AND',
				[
					'key'     => 'type',
					'value'   => $type,
					'compare' => '=',
				],
			];
		}

		parent::__construct( $query );
	}

	protected function map_result( $result ) {
		if ( isset( $result->type ) && 'refund' === $result->type ) {
			return new Refund( $result );
		}

		return new Order( $result );
	}

	/**
	 * Get purchase history for customer.
	 *
	 * @param int $customer_id
	 *
	 * @return array
	 */
	public static function get_purchase_history( int $customer_id ): array {
		global $wpdb;
		// @FIXME sort & limit to return last item.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT product_id, SUM(product_qty) as total_qty
				FROM {$wpdb->prefix}storeengine_order_product_lookup
				WHERE order_id IN (
					SELECT id
					FROM {$wpdb->prefix}storeengine_orders
					WHERE customer_id = %d
             	)
				GROUP BY product_id
				",
				$customer_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$products = [];
		foreach ( $results as $product ) {
			$products[] = [
				'product_id'     => $product['product_id'],
				'product_name'   => get_the_title( $product['product_id'] ),
				'total_quantity' => $product['total_qty'],
			];
		}

		return $products;
	}

	/**
	 * Get customer's total spent based on existing order data.
	 *
	 * @param int $customer_id
	 *
	 * @return float
	 */
	public static function get_total_spent( int $customer_id ): float {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT SUM(
				CASE
					WHEN status = 'completed' THEN total_amount
			        WHEN status = 'refunded' THEN -total_amount
			        ELSE 0
			    END
		       ) AS total_spent
			FROM {$wpdb->prefix}storeengine_orders
			WHERE customer_id = %d;
			",
				$customer_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

// End of file order-collection.php.
