<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateReferral {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_referrals';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `referral_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` INT(11) UNSIGNED NOT NULL,
            `referral_code` VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `referral_post_id` INT(11) UNSIGNED NOT NULL,
            `click_counts` INT(11) UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`referral_id`),
    		KEY `affiliate_id` (`affiliate_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
