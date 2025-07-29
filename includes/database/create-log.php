<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateLog {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_log';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
				`log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`timestamp` datetime NOT NULL,
				`level` smallint(4) NOT NULL,
				`source` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
				`message` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
				`context` longtext COLLATE utf8mb4_unicode_520_ci,
				PRIMARY KEY (`log_id`),
				KEY `level` (`level`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

