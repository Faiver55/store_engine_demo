<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateCommission {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_commissions';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `commission_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` INT(11) UNSIGNED NOT NULL,
            `order_id` INT(11) UNSIGNED NOT NULL,
            `commission_amount` DECIMAL(10,2) NOT NULL,
            `status` ENUM('pending', 'approved', 'paid') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`commission_id`),
    		KEY `affiliate_id` (`affiliate_id`),
    		KEY `order_id` (`order_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
