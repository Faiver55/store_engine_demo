<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateShippingZones {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_shipping_zones';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			zone_name varchar(200) NOT NULL,
			zone_order bigint(20) unsigned NOT NULL,
			PRIMARY KEY (`id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
