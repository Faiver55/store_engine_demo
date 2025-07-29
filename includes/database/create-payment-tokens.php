<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatePaymentTokens {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_payment_tokens';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`token_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`gateway_id` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`token` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`type` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`is_default` tinyint(1) NOT NULL DEFAULT '0',
			PRIMARY KEY (`token_id`),
			KEY `user_id` (`user_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

