<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateProductDownloadDirectories {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_product_download_directories';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
						`url_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						`url` varchar(256) COLLATE utf8mb4_unicode_520_ci NOT NULL,
						`enabled` tinyint(1) NOT NULL DEFAULT '0',
						PRIMARY KEY (`url_id`),
						KEY `url` (`url`(191))
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

