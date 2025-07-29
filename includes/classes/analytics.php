<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	public function get_orders_totals( string $start, string $end ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT
			    COUNT(*) as total_orders,
			    SUM(total_amount) as total_sales,
			    SUM(tax_amount) as total_tax
			FROM
			    {$wpdb->prefix}storeengine_orders
			WHERE
			    date_created_gmt BETWEEN %s AND %s AND type = %s AND status IN {$this->get_paid_statuses()}",
			$start, $end, 'order'
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function get_total_refunds( string $start, string $end ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT
			    ABS(SUM(o.total_amount)) as total_refunds
			FROM
			    {$wpdb->prefix}storeengine_orders o
			JOIN {$wpdb->prefix}storeengine_orders co ON o.parent_order_id = co.id
			WHERE
			    co.date_created_gmt BETWEEN %s AND %s AND o.type = %s AND co.status in {$this->get_paid_statuses()}",
			$start, $end, 'refund_order'
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function get_product_sold( string $start, string $end ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(p.product_qty) as total_products_sold
					FROM {$wpdb->prefix}storeengine_orders o
					JOIN {$wpdb->prefix}storeengine_order_product_lookup p ON p.order_id = o.id
					WHERE o.date_created_gmt BETWEEN %s AND %s AND o.type = %s AND o.status IN {$this->get_paid_statuses()}",
			$start, $end, 'order'
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function get_top_products(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$products = $wpdb->get_results(
			"SELECT op.product_id,
		SUM(op.product_qty) AS total_sales,
		p.post_title AS product_name,
		pm1.meta_value AS thumbnail,
		pm2.meta_value AS gallery,
		SUM(op.product_net_revenue) AS total_net_revenue
		FROM {$wpdb->prefix}storeengine_order_product_lookup op
		INNER JOIN {$wpdb->prefix}storeengine_orders o ON o.id = op.order_id AND o.status IN {$this->get_paid_statuses()}
		INNER JOIN $wpdb->posts p ON p.ID = op.product_id
		LEFT JOIN $wpdb->postmeta pm1 ON pm1.post_id = op.product_id AND pm1.meta_key = '_thumbnail_id'
		LEFT JOIN $wpdb->postmeta pm2 ON pm2.post_id = op.product_id AND pm2.meta_key = '_storeengine_product_gallery_ids'
		GROUP BY op.product_id, p.post_title
		ORDER BY total_sales DESC
		LIMIT 5" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_products = [];


		foreach ( $products as $product ) {
			if ( ! $product->thumbnail && $product->gallery ) {
				$gallery = maybe_unserialize( $product->gallery );
				if ( ! empty( $gallery ) && is_array( $gallery ) ) {
					$product->thumbnail = $gallery[0];
				}
			}

			$top_products[] = [
				'product_id'        => (int) $product->product_id,
				'product_name'      => $product->product_name,
				'product_image'     => $product->thumbnail ? wp_get_attachment_image_url( $product->thumbnail ) : null,
				'total_sales'       => (int) $product->total_sales,
				'total_net_revenue' => (float) $product->total_net_revenue,
			];
		}

		return $top_products;
	}

	/**
	 * @return string
	 */
	private function get_paid_statuses(): string {
		global $wpdb;

		$paid_statuses = Helper::get_order_paid_statuses();
		$placeholders  = array_fill(0, count($paid_statuses), '%s');
		$placeholders  = '(' . implode(',', $placeholders) . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Sanitizing dynamic range of value.
		return $wpdb->prepare($placeholders, $paid_statuses);
	}
}
