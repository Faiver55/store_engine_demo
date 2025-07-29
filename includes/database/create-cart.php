<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateCart {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_cart';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`cart_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) unsigned NOT NULL,
			`cart_data` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`cart_hash` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`cart_id`),
			KEY `cart_hash` (`cart_hash`),
			KEY `user_id` (`user_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

