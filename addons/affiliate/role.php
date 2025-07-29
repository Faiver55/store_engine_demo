<?php
namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role {

	public static function add_affiliate_role() {
		remove_role( 'storeengine_affiliate' );

		add_role( 'storeengine_affiliate', esc_html__( 'StoreEngine Affiliate', 'storeengine' ));
		$role_permission = array(
			'manage_storeengine_affiliate',
			// affiliate
			'edit_storeengine_affiliate',
			'read_storeengine_affiliate',
			'delete_storeengine_affiliate',
			'delete_storeengine_affiliates',
			'edit_storeengine_affiliates',
			'edit_others_storeengine_affiliates',
			'read_private_storeengine_affiliates',
			'edit_storeengine_affiliates',
			// common
			'edit_post',
			'edit_posts',
			'read',
			'edit_others_posts',
		);

		$affiliate = get_role( 'storeengine_affiliate' );
		if ( $affiliate ) {
			foreach ( $role_permission as $cap ) {
				$affiliate->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_storeengine_affiliate' );
		}

		if ( current_user_can( 'administrator' ) ) {
			$user_id   = get_current_user_id();
			$affiliate = new \WP_User( $user_id );
			$affiliate->add_role( 'storeengine_affiliate' );
		}
	}
}
