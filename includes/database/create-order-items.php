<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderItems {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_order_items';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
          `order_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `order_item_name` text NOT NULL,
          `order_item_type` varchar(200) NOT NULL DEFAULT '',
          `order_id` bigint(20) unsigned NOT NULL,
          PRIMARY KEY (`order_item_id`),
          KEY `order_id` (`order_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}


}
