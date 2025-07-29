<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Classes\Data\IntegrationRepositoryData;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\IntegrationRepository;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;

trait Integration {

	/**
	 * @param int $price_id
	 *
	 * @return \StoreEngine\Classes\Integration[]
	 * @throws StoreEngineException
	 */
	public static function get_integrations_by_price_id( int $price_id ): array {
		try {
			return ( new Price( $price_id ) )->get_integrations();
		} catch ( StoreEngineException $e ) {
			return [];
		}
	}

	/**
	 * @param int $product_id
	 *
	 * @return IntegrationRepositoryData[]
	 * @throws StoreEngineException
	 */
	public static function get_integrations_by_product_id( int $product_id ): array {
		return ( new SimpleProduct( $product_id ) )->get_integrations();
	}

	/**
	 * @param string $provider
	 * @param int $integration_id
	 *
	 * @return IntegrationRepositoryData[]
	 */
	public static function get_integration_repository_by_id( string $provider, int $integration_id ): array {
		return ( new IntegrationRepository( $provider ) )->get_by_id( $integration_id )->get_objects();
	}

	/**
	 * @param string $provider
	 * @param int $price_id
	 *
	 * @return ?IntegrationRepositoryData
	 */
	public static function get_integration_repository_by_price_id( string $provider, int $price_id ): ?IntegrationRepositoryData {
		return ( new IntegrationRepository( $provider ) )->get_by_price_id( $price_id );
	}

	/**
	 * @param string $provider
	 * @param array $args
	 *
	 * @return IntegrationRepositoryData[]
	 */
	public static function get_integration_repository_by_provider( string $provider, array $args = [] ): array {
		return ( new IntegrationRepository( $provider ) )->get_by_provider( $args )->get_objects();
	}

}
