<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateShippingZoneMethods {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_shipping_zone_methods';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			zone_id bigint(20) unsigned NOT NULL,
			method_id varchar(200) NOT NULL,
			name varchar(200) NOT NULL,
			description text NULL,
			settings longtext NULL,
			method_order bigint(20) unsigned NOT NULL,
			is_enabled tinyint(1) NOT NULL DEFAULT '1',
			PRIMARY KEY (id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
