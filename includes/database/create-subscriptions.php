<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateSubscriptions {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_subscriptions';
		$sql        = "CREATE TABLE {$table_name} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `product_id` INT(11) NOT NULL,
            `price_id` INT(11) NOT NULL,
            `status` VARCHAR(255) NOT NULL,
            `interval` INT(11) NOT NULL,
            `interval_type` VARCHAR(255) NOT NULL,
            `hook` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

