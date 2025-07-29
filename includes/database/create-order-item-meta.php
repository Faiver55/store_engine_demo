<?php

namespace StoreEngine\database;

class CreateOrderItemMeta {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_order_item_meta';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
          `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `order_item_id` bigint(20) unsigned NOT NULL,
          `meta_key` varchar(255) default NULL,
  		  `meta_value` longtext NULL,
          PRIMARY KEY (`meta_id`),
          KEY `order_item_id` (`order_item_id`),
		  KEY `meta_key` (meta_key(32))
        ) $charset_collate;";
		dbDelta( $sql );
	}

}
