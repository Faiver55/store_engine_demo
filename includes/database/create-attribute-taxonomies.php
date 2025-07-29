<?php

namespace StoreEngine\Database;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAttributeTaxonomies {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . Helper::DB_PREFIX . 'attribute_taxonomies';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
                `attribute_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `attribute_name` varchar(200) NOT NULL,
                `attribute_label` varchar(200) NULL,
                `attribute_type` varchar(20) NOT NULL,
                `attribute_orderby` varchar(20) NOT NULL,
                `attribute_public` int(1) NOT NULL DEFAULT 1,
                PRIMARY KEY  (`attribute_id`)
    ) $charset_collate;";
		dbDelta( $sql );
	}

}
