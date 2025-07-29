<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderOperationalData {
	public static $table_name = 'storeengine_order_operational_data';

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `order_id` bigint(20) unsigned DEFAULT NULL,
            `created_via` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            `storeengine_version` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            `prices_include_tax` tinyint(1) DEFAULT NULL,
            `coupon_usages_are_counted` tinyint(1) DEFAULT NULL,
            `download_permission_granted` tinyint(1) DEFAULT NULL,
            `cart_hash` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            `new_order_email_sent` tinyint(1) DEFAULT NULL,
            `order_key` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            `order_stock_reduced` tinyint(1) DEFAULT NULL,
            `date_paid_gmt` datetime DEFAULT NULL,
            `date_completed_gmt` datetime DEFAULT NULL,
            `shipping_tax_amount` decimal(26,8) DEFAULT NULL,
            `shipping_total_amount` decimal(26,8) DEFAULT NULL,
            `discount_tax_amount` decimal(26,8) DEFAULT NULL,
            `discount_total_amount` decimal(26,8) DEFAULT NULL,
            `recorded_sales` tinyint(1) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `order_id` (`order_id`),
          KEY `order_key` (`order_key`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

