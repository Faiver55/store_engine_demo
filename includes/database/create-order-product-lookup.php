<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderProductLookup {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_order_product_lookup';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `order_item_id` bigint(20) unsigned NOT NULL,
            `order_id` bigint(20) unsigned NOT NULL,
            `product_id` bigint(20) unsigned NOT NULL,
            `variation_id` bigint(20) unsigned NOT NULL,
            `price_id` bigint(20) unsigned NOT NULL,
            `price` double NOT NULL DEFAULT '0',
            `customer_id` bigint(20) unsigned DEFAULT NULL,
            `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `product_qty` int(11) NOT NULL,
            `product_net_revenue` double NOT NULL DEFAULT '0',
            `product_gross_revenue` double NOT NULL DEFAULT '0',
            `coupon_amount` double NOT NULL DEFAULT '0',
            `tax_amount` double NOT NULL DEFAULT '0',
            `shipping_amount` double NOT NULL DEFAULT '0',
            `shipping_tax_amount` double NOT NULL DEFAULT '0',
    		`shipping_status` varchar(255) NOT NULL DEFAULT '',
    		`expire_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (`order_item_id`),
            KEY `order_id` (`order_id`),
            KEY `product_id` (`product_id`),
            KEY `customer_id` (`customer_id`),
            KEY `date_created` (`date_created`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
