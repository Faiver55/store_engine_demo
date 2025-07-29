<?php

namespace StoreEngine\Addons\Subscription\Classes;

use StoreEngine\classes\AbstractWpdb;
use IteratorAggregate;
use Countable;
use ArrayIterator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated use SubscriptionCollection
 * @see SubscriptionCollection
 */
class Subscriptions extends AbstractWpdb implements IteratorAggregate, Countable {
	private int $page;
	private int $limit;
	private array $conditions = [];

	public function __construct( int $page = 1, int $per_page = 10, array $conditions = [] ) {
		$this->page       = max( 1, $page );
		$this->limit      = $per_page;
		$this->conditions = $conditions;
		parent::__construct();

		global $wpdb;
		$this->table = "{$wpdb->prefix}storeengine_orders";
	}

	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->get() );
	}

	public function count(): int {
		return $this->get_total_count();
	}

	public function get(): array {
		global $wpdb;

		$conditions = array_merge( [
			'type' => [
				'condition' => '=',
				'formatter' => '%s',
				'value'     => 'subscription',
			],
		], $this->conditions );

		$offset = ( $this->page - 1 ) * $this->limit;

		$sql_conditions = $this->generate_conditions( $conditions );
		$results        = $wpdb->get_results( $wpdb->prepare( "{$this->query()} WHERE {$sql_conditions} GROUP BY o.id ORDER BY o.id DESC LIMIT %d OFFSET %d;", $this->limit, $offset ), ARRAY_A ); // phpcs:ignore

		if ( ! $results ) {
			return [];
		}

		$orders = [];

		foreach ( $results as $result ) {
			$order    = new Subscription( $result['o_id'] );
			$orders[] = $order;
		}

		return $orders;
	}

	public function get_total_count() {
		global $wpdb;

		$conditions = array_merge( array(
			'status' => array(
				'condition' => '!=',
				'formatter' => '%s',
				'value'     => 'draft',
			),
		), $this->conditions );

		$sql_conditions = $this->generate_conditions( $conditions );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.
		$result = $wpdb->get_row( "SELECT COUNT(DISTINCT o.id) as order_totals FROM {$wpdb->prefix}storeengine_orders o
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses b ON b.order_id = o.id AND b.address_type = 'billing'
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses s ON s.order_id = o.id AND s.address_type = 'shipping'
				LEFT JOIN {$wpdb->prefix}storeengine_order_operational_data p ON p.order_id = o.id
				LEFT JOIN {$wpdb->prefix}storeengine_orders_meta m ON m.order_id = o.id WHERE $sql_conditions;" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return 0;
		}

		return $result->order_totals;
	}

	protected function generate_conditions( $conditions, $implode = true ) {
		$sql = [];
		foreach ( $conditions as $operator => $condition ) {
			if ( in_array( $operator, array( 'and', 'or' ), true ) ) {
				$operator         = strtoupper( $operator );
				$sql[ $operator ] = '(' . implode( " $operator ", $this->generate_conditions( $condition, false ) ) . ')';
				continue;
			}

			$arr              = [];
			$arr[ $operator ] = $condition;
			$sql[]            = $this->generate_sql_from_conditions( $arr );
		}

		return $implode ? implode( ' AND ', $sql ) : $sql;
	}

	protected function generate_sql_from_conditions( array $condition ): string {
		global $wpdb;

		$column = array_keys( $condition );
		$column = reset( $column );

		$condition = array_values( $condition )[0];

		$place_holder = "$column {$condition['condition']} {$condition['formatter']}";

		return $wpdb->prepare( $place_holder, $condition['value'] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- returning prepared statements.
	}

	protected function query(): string {
		global $wpdb;

		return "
			SELECT
				o.id as o_id,
				o.parent_order_id as parent_order_id,
				o.*,
				b.id as b_id,
				s.id as s_id,
				p.id as operational_id,
				b.first_name as b_first_name,
				b.last_name as b_last_name,
				b.company as b_company,
				b.address_1 as b_address_1,
				b.address_2 as b_address_2,
				b.city as b_city,
				b.state as b_state,
				b.postcode as b_postcode,
				b.country as b_country,
				b.email as b_email,
				b.phone as b_phone,
				s.first_name as s_first_name,
				s.last_name as s_last_name,
				s.company as s_company,
				s.address_1 as s_address_1,
				s.address_2 as s_address_2,
				s.city as s_city,
				s.state as s_state,
				s.postcode as s_postcode,
				s.country as s_country,
				s.email as s_email,
				s.phone as s_phone,
				p.*
			FROM {$wpdb->prefix}storeengine_orders o
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses b ON b.order_id = o.id AND b.address_type = 'billing'
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses s ON s.order_id = o.id AND s.address_type = 'shipping'
				LEFT JOIN {$wpdb->prefix}storeengine_order_operational_data p ON p.order_id = o.id
				LEFT JOIN {$wpdb->prefix}storeengine_orders_meta m ON m.order_id = o.id
		";
	}
}
