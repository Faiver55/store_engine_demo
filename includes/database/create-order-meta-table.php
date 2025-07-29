<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderMetaTable {
	public static function up( $prefix, $charset_collate ) {
		/**
		 * @see CreateOrderTable::up()
		 */
		$composite_meta_value_index_length = 73; // (191 - 8 - 100 - 10) 8 for order_id, 100 for meta_key, 10 minimum for meta_value.
		$table_name                        = $prefix . 'storeengine_orders_meta';
		$sql                               = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `order_id` bigint(20) unsigned NOT NULL,
            `meta_key` varchar(255) default NULL,
  			`meta_value` longtext NULL,
            PRIMARY KEY (meta_id),
            INDEX order_id_index (order_id),
            INDEX meta_key_index (meta_key),
            KEY meta_key_value (meta_key(100), meta_value($composite_meta_value_index_length)),
            KEY order_id_meta_key_meta_value (order_id, meta_key(100), meta_value($composite_meta_value_index_length))
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
