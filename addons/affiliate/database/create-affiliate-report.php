<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAffiliateReport {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_report';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `report_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` INT(11) UNSIGNED NOT NULL,
            `referral_id` INT(11) UNSIGNED NOT NULL,
            `total_clicks` BIGINT(20) UNSIGNED NOT NULL,
            `total_sales` DECIMAL(15,2) NOT NULL,
            `total_commissions` DECIMAL(10,2) NOT NULL,
            `current_balance` DECIMAL(10,2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`report_id`),
    		UNIQUE KEY `affiliate_id` (`affiliate_id`),
    		KEY `referral_id` (`referral_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
