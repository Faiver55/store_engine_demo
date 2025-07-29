<?php

namespace StoreEngine\Classes;

abstract class AbstractModel {
	protected $wpdb;
	protected string $table;
	protected string $query;
	protected string $prefix;
	protected string $primary_key = 'ID';

	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix;
		$this->table  = $this->prefix . $this->table;
	}

	abstract public function save( array $args = [] );

	abstract public function update( int $id, array $args );

	abstract public function delete( ?int $id = null );

	protected function create_item( array $data, ?array $format = null ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->wpdb->insert( $this->table, $data, $format );
	}

	protected function update_item( array $data, array $where, ?array $format = null, ?array $where_format = null ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->wpdb->update( $this->table, $data, $where, $format, $where_format );
	}

	protected function delete_item( array $where, ?array $format = null ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->wpdb->delete( $this->table, $where, $format );
	}
}
