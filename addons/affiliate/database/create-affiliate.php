<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAffiliate {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliates';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `affiliate_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT(20) UNSIGNED NOT NULL,
            `commission_type` ENUM('percentage', 'flat') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `commission_rate` INT(3) UNSIGNED NOT NULL,
            `status` ENUM('active', 'inactive', 'pending') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`affiliate_id`),
            UNIQUE KEY `user_id` (`user_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
