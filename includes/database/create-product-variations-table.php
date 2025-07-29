<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

class CreateProductVariationsTable {

	public static string $table_name = Helper::DB_PREFIX . 'product_variations';

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sku VARCHAR(255) NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            price decimal(26,8),
            compare_price decimal(26,8),
            PRIMARY KEY (id)
        ) $charset_collate;";
		dbDelta( $sql );
	}

}
