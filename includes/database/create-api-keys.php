<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// @FIXME remove  COLLATE utf8mb4_unicode_520_ci from all migrations.

class CreateApiKeys {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_api_keys';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
                `key_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` bigint(20) unsigned NOT NULL,
              `description` varchar(200) DEFAULT NULL,
              `permissions` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `consumer_key` char(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `consumer_secret` char(43) COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `nonces` longtext COLLATE utf8mb4_unicode_520_ci,
              `truncated_key` char(7) COLLATE utf8mb4_unicode_520_ci NOT NULL,
              `last_access` datetime DEFAULT NULL,
              PRIMARY KEY (`key_id`),
              KEY `consumer_key` (`consumer_key`),
              KEY `consumer_secret` (`consumer_secret`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
