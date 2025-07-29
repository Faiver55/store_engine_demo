<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateReferralTrack {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_referrals_tracks';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `track_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `referral_id` INT(11) UNSIGNED NOT NULL,
            `referral_ip` VARCHAR(45) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `status` ENUM('pending', 'converted', 'rejected') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`track_id`),
    		KEY `referral_id` (`referral_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
