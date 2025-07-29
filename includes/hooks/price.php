<?php

namespace StoreEngine\hooks;

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Product\SimpleProduct;
use WP_Post;

class Price {


	public static function init() {
		$self = new self();
		add_action('deleted_post', [ $self, 'delete_prices_and_integrations' ], 10, 2);
	}

	public function delete_prices_and_integrations( int $post_id, WP_Post $post ) {
		if ( 'storeengine_product' !== $post->post_type ) {
			return;
		}

		$product   = new SimpleProduct($post_id);
		$prices    = $product->get_prices();
		$price_ids = array_map(fn( $price) => $price->get_id(), $prices);
		if ( empty( $price_ids ) ) {
			return;
		}
		$placeholders = implode(',', array_fill(0, count($prices), '%d'));

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->delete($wpdb->prefix . 'storeengine_product_price', [ 'product_id' => $post_id ], [ '%d' ]);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_integrations WHERE price_id in ($placeholders)",
				...$price_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// clear cache.
		wp_cache_flush_group(AbstractProduct::CACHE_GROUP);
		wp_cache_flush_group(Integration::CACHE_GROUP);
	}

}
