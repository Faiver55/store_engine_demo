<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateCustomerLookup {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_customer_lookup';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
                `customer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) unsigned DEFAULT NULL,
                `first_name` varchar(100) COLLATE utf8mb4_unicode_520_ci,
                `last_name` varchar(100) COLLATE utf8mb4_unicode_520_ci,
                `email` varchar(100) COLLATE utf8mb4_unicode_520_ci,
                `date_last_active` timestamp NULL DEFAULT NULL,`date_registered` timestamp NULL DEFAULT NULL,
                `country` varchar(20) COLLATE utf8mb4_unicode_520_ci,
                `postcode` varchar(20) COLLATE utf8mb4_unicode_520_ci,
                `city` varchar(100) COLLATE utf8mb4_unicode_520_ci,
                `state` varchar(100) COLLATE utf8mb4_unicode_520_ci,
                PRIMARY KEY (`customer_id`),
                UNIQUE KEY `user_id` (`user_id`),
                UNIQUE KEY `email` (`email`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
