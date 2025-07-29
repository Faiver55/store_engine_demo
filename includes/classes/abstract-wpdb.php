<?php

namespace StoreEngine\Classes;

abstract class AbstractWpdb {
	protected $wpdb;
	protected string $table;
	protected string $primary_key = 'id';

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}
}
