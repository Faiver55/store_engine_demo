<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateIntegration {
	public static $table_name = 'storeengine_integrations';
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			product_id bigint(20) unsigned NOT NULL,
			price_id bigint(20) unsigned NOT NULL,
			integration_id bigint(20) unsigned NOT NULL,
			provider varchar(155) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			variation_id bigint(20) unsigned,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX product_id (product_id),
			INDEX price_id (price_id),
			INDEX integration_id (integration_id),
			INDEX variation_id (variation_id)
		) $charset_collate;";
		dbDelta( $sql );
	}
}
