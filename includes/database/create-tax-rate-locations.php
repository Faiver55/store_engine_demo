<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateTaxRateLocations {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_tax_rate_locations';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`location_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`location_code` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`tax_rate_id` bigint(20) unsigned NOT NULL,
			`location_type` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			PRIMARY KEY (`location_id`),
			KEY `tax_rate_id` (`tax_rate_id`),
			KEY `location_type_code` (`location_type`(10),`location_code`(20))
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

