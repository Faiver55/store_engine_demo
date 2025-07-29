<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateShippingZoneLocations {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_shipping_zone_locations';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			location_id bigint(20) unsigned NOT NULL auto_increment,
			zone_id bigint(20) unsigned NOT NULL,
			location_code varchar(200) NOT NULL,
			location_type varchar(40) NOT NULL,
			PRIMARY KEY  (location_id),
			KEY zone_id (zone_id),
			KEY location_type_code (location_type(10),location_code(20))
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
