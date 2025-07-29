<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatePayout {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_payouts';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `payout_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` BIGINT(20) UNSIGNED NOT NULL,
            `payout_amount` DECIMAL(10,2) NOT NULL,
            `payment_method` ENUM('PayPal', 'Bank Transfer', 'Stripe', 'Check Payment', 'E-Check') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `transaction_id` VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `status` ENUM('completed', 'pending') COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`payout_id`),
    		KEY `affiliate_id` (`affiliate_id`)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
