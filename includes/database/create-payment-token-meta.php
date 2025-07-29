<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatePaymentTokenMeta {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_payment_tokenmeta';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`payment_token_id` bigint(20) unsigned NOT NULL,
			`meta_key` varchar(255) default NULL,
  			`meta_value` longtext NULL,
			PRIMARY KEY (`meta_id`),
			KEY `payment_token_id` (`payment_token_id`),
			KEY `meta_key` (`meta_key`(32))
        ) $charset_collate;";
		dbDelta( $sql );
	}
}

