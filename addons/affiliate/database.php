<?php
namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoreEngine\Addons\Affiliate\Database\{CreateAffiliate,
	CreatePayout,
	CreateCommission,
	CreateReferral,
	CreateReferralTrack,
	CreateAffiliateReport};

use StoreEngine\Utils\Helper;

class Database {
	public static function init() {
		$self = new self();
		$self->create_initial_custom_table();
	}

	public static function create_initial_custom_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$prefix          = $wpdb->prefix . Helper::DB_PREFIX;
		$charset_collate = $wpdb->get_charset_collate();
		CreateAffiliate::up( $prefix, $charset_collate );
		CreateReferral::up( $prefix, $charset_collate );
		CreateReferralTrack::up( $prefix, $charset_collate );
		CreateCommission::up( $prefix, $charset_collate );
		CreatePayout::up( $prefix, $charset_collate );
		CreateAffiliateReport::up( $prefix, $charset_collate );
	}

}
