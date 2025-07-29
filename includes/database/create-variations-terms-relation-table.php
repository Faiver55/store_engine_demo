<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

class CreateVariationsTermsRelationTable {

	public static string $table_name = Helper::DB_PREFIX . 'variation_term_relations';

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            variation_id BIGINT UNSIGNED,
            term_id BIGINT UNSIGNED,
            term_order INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";
		dbDelta( $sql );
	}

}
