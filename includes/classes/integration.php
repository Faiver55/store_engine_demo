<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

class Integration {

	protected string $table;

	protected int $id;

	protected array $data     = [
		'product_id'     => 0,
		'price_id'       => 0,
		'integration_id' => 0,
		'provider'       => '',
	];
	protected array $new_data = [];

	public const CACHE_KEY   = 'storeengine_integration_';
	public const CACHE_GROUP = 'storeengine_integrations';

	public function __construct( int $id = 0 ) {
		global $wpdb;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'integrations';
		$this->id    = $id;
	}

	public function get() {
		$has_cache = wp_cache_get( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		if ( $has_cache ) {
			return $this->set_data( $has_cache );
		}

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE id = %d", $this->id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $result ) {
			return false;
		}

		return $this->set_data( $result );
	}

	public function set_data( $data ): Integration {
		wp_cache_set( self::CACHE_KEY . $this->id, $data, self::CACHE_GROUP );

		$this->id   = (int) $data->id;
		$this->data = [
			'product_id'     => (int) $data->product_id,
			'price_id'       => (int) $data->price_id,
			'integration_id' => (int) $data->integration_id,
			'provider'       => $data->provider,
		];

		return $this;
	}

	public function get_data(): array {
		return array_merge( [ 'id' => $this->id ], $this->data );
	}

	public function save() {
		if ( empty( $this->new_data ) ) {
			return;
		}

		global $wpdb;
		$data        = array_merge( $this->data, $this->new_data );
		$placeholder = [ '%d', '%d', '%d', '%s' ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 0 === $this->id ) {
			$wpdb->insert( $this->table, $data, $placeholder );
			$this->id = $wpdb->insert_id;
			do_action( 'storeengine/integrations/created', $this );
		} else {
			$wpdb->update( $this->table, $data, [ 'id' => $this->get_id() ], $placeholder, [ '%d' ] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->delete_cache();
	}

	public function delete(): bool {
		if ( 0 === $this->get_id() ) {
			return false;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, [ 'id' => $this->get_id() ], [ '%d' ] );
		$this->delete_cache();

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	public function delete_by_price_and_integration(): bool {
		if ( 0 === $this->get_price_id() || empty( $this->get_provider() ) || 0 === $this->get_integration_id() ) {
			return false;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, [
			'price_id'       => $this->get_price_id(),
			'provider'       => $this->get_provider(),
			'integration_id' => $this->get_integration_id(),
		], [ '%d', '%s', '%d' ] );
		$this->delete_cache();

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_product_id(): int {
		return $this->get_prop( 'product_id', 0 );
	}

	public function get_price_id(): int {
		return $this->get_prop( 'price_id', 0 );
	}

	public function get_integration_id(): int {
		return $this->get_prop( 'integration_id', 0 );
	}

	public function get_provider(): string {
		return $this->get_prop( 'provider' );
	}

	public function set_product_id( int $value ) {
		$this->new_data['product_id'] = $value;
	}

	public function set_price_id( int $value ) {
		$this->new_data['price_id'] = $value;
	}

	public function set_integration_id( int $value ) {
		$this->new_data['integration_id'] = $value;
	}

	public function set_provider( string $value ) {
		$this->new_data['provider'] = $value;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	/**
	 * @return void
	 */
	private function delete_cache(): void {
		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		wp_cache_delete( AbstractProduct::CACHE_KEY . $this->get_product_id() . '_integrations', AbstractProduct::CACHE_GROUP );
		wp_cache_delete( 'storeengine_price_' . $this->get_price_id() . '_integrations_integrations', 'storeengine_product_price' );
		wp_cache_flush_group( IntegrationRepository::CACHE_GROUP );
	}

}
