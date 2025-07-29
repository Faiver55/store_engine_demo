<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateDownloadableProductPermissions {

	public static string $table_name = 'storeengine_downloadable_product_permissions';

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL auto_increment,
			user_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			download_id varchar(100) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			price_id bigint(20) unsigned,
			variation_id bigint(20) unsigned,
			downloads_remaining int(20) unsigned,
			access_granted DATETIME,
			access_expires DATETIME,
			download_count bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (id)
		) $charset_collate;";
		dbDelta( $sql );
	}

}
