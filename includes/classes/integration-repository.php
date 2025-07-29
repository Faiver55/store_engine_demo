<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Data\IntegrationRepositoryData;

class IntegrationRepository {

	protected string $provider;

	public const CACHE_KEY = 'storeengine_integration_repository_';

	public const CACHE_GROUP = 'storeengine_integration_repositories';

	/**
	 * @var IntegrationRepositoryData[]
	 */
	protected array $objects = [];

	public function __construct( string $provider ) {
		$this->provider = $provider;
	}

	public function get_by_id( int $integration_id ): IntegrationRepository {
		$cache_key = self::CACHE_KEY . $integration_id;
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $has_cache && is_array( $has_cache ) ) {
			$this->objects = array_map( fn( $result ) => new IntegrationRepositoryData(
				( new Integration() )->set_data( $result ),
				new Price( $result->price_id )
			), $has_cache );

			return $this;
		}

		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT
				i.id,
				i.product_id,
				i.price_id,
				i.provider,
				i.integration_id,
				i.variation_id,
				i.created_at,
				i.updated_at,
				pr.price_name,
				pr.price_type,
				pr.price,
				pr.compare_price,
				pr.settings,
				pr.`order`
			FROM {$wpdb->prefix}storeengine_integrations i
                 JOIN {$wpdb->prefix}storeengine_product_price pr ON pr.id = i.price_id
         	WHERE i.provider = %s AND i.integration_id = %d
         	ORDER BY pr.`order`", $this->get_provider(), $integration_id )
		);

		wp_cache_set( $cache_key, $results, self::CACHE_GROUP );

		foreach ( $results as $result ) {
			$this->objects[] = new IntegrationRepositoryData(
				( new Integration() )->set_data( $result ),
				new Price( $result->price_id )
			);
		}


		return $this;
	}

	public function get_by_price_id( int $price_id ): ?IntegrationRepositoryData {
		$cache_key = self::CACHE_KEY . $this->get_provider() . '_price_' . $price_id;
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $has_cache ) {
			return new IntegrationRepositoryData(
				( new Integration() )->set_data( $has_cache ),
				new Price( $has_cache->price_id )
			);
		}

		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT
				i.id,
				i.product_id,
				i.price_id,
				i.provider,
				i.integration_id,
				i.variation_id,
				i.created_at,
				i.updated_at,
				pr.price_name,
				pr.price_type,
				pr.price,
				pr.compare_price,
				pr.settings,
				pr.`order`
			FROM {$wpdb->prefix}storeengine_integrations i
                 JOIN {$wpdb->prefix}storeengine_product_price pr ON pr.id = i.price_id
         	WHERE i.provider = %s AND i.price_id = %d
         	ORDER BY pr.`order`", $this->get_provider(), $price_id )
		);

		if ( ! $result ) {
			return null;
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP );

		return new IntegrationRepositoryData(
			( new Integration() )->set_data( $result ),
			new Price( $result->price_id )
		);
	}

	public function get_by_provider( array $args = [] ): IntegrationRepository {
		$cache_key = self::CACHE_KEY . $this->get_provider();
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $has_cache && is_array( $has_cache ) ) {
			$this->objects = array_map( fn( $result ) => new IntegrationRepositoryData(
				( new Integration() )->set_data( $result ),
				new Price( $result->price_id )
			), $has_cache );

			return $this;
		}

		$orderby = 'ASC';
		if ( isset( $args['orderby'] ) && 'DESC' === $args['orderby'] ) {
			$orderby = 'DESC';
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT
				i.id,
				i.product_id,
				i.price_id,
				i.provider,
				i.integration_id,
				i.created_at,
				i.updated_at,
				pr.price_name,
				pr.price_type,
				pr.price,
				pr.compare_price,
				pr.settings,
				pr.`order`
			FROM {$wpdb->prefix}storeengine_integrations i
                 JOIN {$wpdb->prefix}storeengine_product_price pr ON pr.id = i.price_id
				 JOIN $wpdb->posts p ON p.id = i.product_id AND p.post_status = 'publish'
         	WHERE i.provider = %s
         	ORDER BY i.id $orderby", $this->get_provider() )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $results, self::CACHE_GROUP );

		foreach ( $results as $result ) {
			$this->objects[] = new IntegrationRepositoryData(
				( new Integration() )->set_data( $result ),
				new Price( $result->price_id )
			);
		}


		return $this;
	}

	public function get_provider(): string {
		return $this->provider;
	}

	public function get_objects(): array {
		return $this->objects;
	}
}
