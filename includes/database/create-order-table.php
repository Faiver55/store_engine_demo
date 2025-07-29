<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderTable {
	public static $table_name = 'storeengine_orders';
	public static function up( $prefix, $charset_collate ) {
		/**
		 * Filters the maximum index length in the database.
		 *
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't needed to be concerned about that.
		 * As of WP 4.2, however, they moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 *
		 * Additionally, MyISAM engine also limits the index size to 1000 bytes. We add this filter so that interested folks on InnoDB engine can increase the size till allowed 3071 bytes.
		 * Index length cannot be more than 768, which is 3078 bytes in utf8mb4 and max allowed by InnoDB engine.
		 */
		$max_index_length                   = 191;
		$composite_customer_id_email_length = 171; // (191 - 20) 8 for customer_id, 20 minimum for email.

		$table_name = $prefix . self::$table_name;
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL auto_increment,
            status varchar(20),
            currency varchar(10),
            type varchar(20),
            tax_amount decimal(26,8),
            total_amount decimal(26,8),
            customer_id bigint(20) unsigned,
            billing_email varchar(320),
            date_created_gmt datetime,
            date_updated_gmt datetime,
            parent_order_id bigint(20) unsigned,
            payment_method varchar(100),
            payment_method_title text,
            transaction_id varchar(100),
            ip_address varchar(100),
            user_agent text,
            customer_note text,
            hash varchar(255),
            PRIMARY KEY (id),
            INDEX status (status),
            INDEX date_created (date_created_gmt),
            INDEX customer_id_billing_email (customer_id, billing_email({$composite_customer_id_email_length})),
            INDEX billing_email (billing_email($max_index_length)),
            INDEX type_status_date (type,status,date_created_gmt),
            INDEX parent_order_id (parent_order_id),
            INDEX date_updated (date_updated_gmt)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
