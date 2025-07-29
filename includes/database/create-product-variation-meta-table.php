<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

class CreateProductVariationMetaTable {

	public static string $table_name = Helper::DB_PREFIX . 'product_variation_meta';

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            variation_id BIGINT(20) UNSIGNED,
            meta_key VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            meta_value VARCHAR(9999) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            PRIMARY KEY (id),
            INDEX order_id_index (variation_id),
            INDEX meta_key_index (meta_key),
            INDEX meta_value_index (meta_value)
        ) $charset_collate;";
		dbDelta( $sql );
	}

}
