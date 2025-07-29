<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateSessions {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'sessions';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
              `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `session_key` char(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `session_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `session_expiry` bigint(20) unsigned NOT NULL,
              PRIMARY KEY (`session_id`),
              UNIQUE KEY `session_key` (`session_key`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

