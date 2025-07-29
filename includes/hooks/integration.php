<?php

namespace StoreEngine\Hooks;

class Integration {

	public static function init() {
		$self = new self();
		add_action( 'storeengine/price_deleted', [ $self, 'remove_existing_integrations' ] );
	}

	public function remove_existing_integrations( int $price_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'storeengine_integrations', [ 'price_id' => $price_id ], [ '%d' ] );
		wp_cache_flush_group( \StoreEngine\Classes\Integration::CACHE_GROUP );
		wp_cache_flush_group( \StoreEngine\Classes\AbstractProduct::CACHE_GROUP );
	}

}
