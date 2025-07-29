<?php

namespace StoreEngine\Models;

use StoreEngine\Classes\AbstractModel;
use StoreEngine\traits\Subscription as SubscriptionTrait;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated
 */
class Subscription extends AbstractModel {
	use SubscriptionTrait;

	protected string $table = 'storeengine_subscriptions';

	public function get_subscription_by_primary_key( $subscription_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_subscription WHERE id = %d", $subscription_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function get_user_subscription_by_price_id( $user_id, $price_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_subscriptions WHERE user_id = %d AND price_id = %d", $user_id, $price_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function save( array $args = [] ) {
		if ( empty( $args['user_id'] ) || empty( $args['product_id'] || $args['price_id'] ) ) {
			return false;
		}

		// Create the subscription data
		global $wpdb;
		$table_name = $wpdb->prefix . 'storeengine_subscriptions';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name,
			[
				'user_id'       => $args['user_id'],
				'product_id'    => $args['product_id'],
				'price_id'      => $args['price_id'],
				'status'        => $args['status'],
				'interval'      => $args['interval'],
				'interval_type' => $args['interval_type'],
				'hook'          => $this->subscription_renewal_hook_name,
			]
		);

		return $wpdb->insert_id;
	}

	public function update( int $id, array $args ) {
		// TODO: Implement update() method.
	}

	public function delete( ?int $id = null ) {
		// TODO: Implement delete() method.
	}


	public function get_subscriptions() {
		global $wpdb;
		$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT {$wpdb->users}.user_login as username,
				    {$wpdb->posts}.post_title as product,
				    s.status,
				    wp_postmeta.meta_value as price_name,
				    s.created_at
				FROM {$wpdb->prefix}storeengine_subscriptions as s
				JOIN {$wpdb->users} ON s.user_id = wp_users.ID
				JOIN {$wpdb->posts} ON s.product_id = wp_posts.ID
				JOIN {$wpdb->postmeta} ON s.price_id = wp_postmeta.meta_id"
		);
		foreach ( $result as $key => $value ) {
			$value->price_name = unserialize( $value->price_name )['price_name'];  // phpcs:ignore
		}

		return $result;
	}

	public function update_subscription( $subscription_id, $args ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'storeengine_subscriptions',
			$args,
			[ 'id' => $subscription_id ]
		);
	}
}
