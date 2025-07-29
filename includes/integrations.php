<?php
namespace StoreEngine;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Integrations\CourseBundle;
use StoreEngine\Integrations\TutorBooking;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Integrations\AbstractIntegration;
use StoreEngine\Integrations\AcademyLms;
use StoreEngine\Integrations\MembershipAddon;

class Integrations {
	use Singleton;

	private array $integrations = [];

	protected function __construct() {
		add_action( 'init', [ $this, 'register_integrations' ], -1 );
	}

	public function register_integrations() {
		$integrations = [
			AcademyLms::class,
			MembershipAddon::class,
			TutorBooking::class,
			CourseBundle::class,
		];

		$integrations = apply_filters( 'storeengine/integrations/registry', $integrations );

		foreach ( $integrations as $integration ) {
			/** @var AbstractIntegration $integration */
			$integration = new $integration();

			// Add to repository.
			$this->integrations[ $integration->get_id() ] = $integration;
		}

		do_action( 'storeengine/integrations/loaded' );
	}

	/**
	 * @param string $provider
	 *
	 * @return AbstractIntegration
	 * @throws StoreEngineException
	 */
	public function get_integration( string $provider ): AbstractIntegration {
		if ( ! did_action( 'storeengine/integrations/loaded' ) ) {
			_doing_it_wrong( __CLASS__ . '::integrations', 'Trying to get integrations before integrations are loaded', '1.0.0' );
		}

		if ( array_key_exists( $provider, $this->integrations ) ) {
			return $this->integrations[ $provider ];
		}

		throw new StoreEngineException( __( 'Integration does not exist.', 'storeengine' ), 'unknown-integration', [ 'provider' => $provider ] );
	}

	/**
	 * @return AbstractIntegration[]
	 */
	public function get_integrations(): array {
		if ( ! did_action( 'storeengine/integrations/loaded' ) ) {
			_doing_it_wrong( __CLASS__ . '::integrations', 'Trying to get integrations before integrations are loaded', '1.0.0' );
		}

		return $this->integrations;
	}
}
