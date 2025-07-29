<?php
namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role {
	public static function add_customer_role() {
		remove_role( 'storeengine_customer' );
		add_role( 'storeengine_customer', esc_html__( 'StoreEngine Customer', 'storeengine' ), array() );

		// @FIXME remove edit_posts permission, customer only needs read permission.
		// @TODO rename storeengine_customer into customer to make it compatible with woocommerce.
		// @TODO add more permissions for different admin types.
		// @TODO check permission in react using global variable.

		$role_permission = [ 'read', 'edit_posts' ];
		$customer        = get_role( 'storeengine_customer' );

		if ( $customer ) {
			foreach ( $role_permission as $cap ) {
				$customer->add_cap( $cap );
			}
		}
	}

	public static function add_shop_manager_role() {
		remove_role( 'storeengine_shop_manager' );

		add_role( 'storeengine_shop_manager', esc_html__( 'StoreEngine Shop manager', 'storeengine' ), array() );
		$role_permission = array(
			'manage_storeengine_shop_manager',
			// product
			'edit_storeengine_product',
			'read_storeengine_product',
			'delete_storeengine_product',
			'delete_storeengine_products',
			'edit_storeengine_products',
			'edit_others_storeengine_products',
			'read_private_storeengine_products',
			'edit_storeengine_products',
			// coupon
			'edit_storeengine_coupon',
			'read_storeengine_coupon',
			'delete_storeengine_coupon',
			'delete_storeengine_coupons',
			'edit_storeengine_coupons',
			'edit_others_storeengine_coupons',
			'read_private_storeengine_coupons',
			'edit_storeengine_coupons',
			// membership
			'edit_storeengine_group',
			'read_storeengine_group',
			'delete_storeengine_group',
			'delete_storeengine_groups',
			'edit_storeengine_groups',
			'edit_others_storeengine_groups',
			'publish_storeengine_group',
			'publish_storeengine_groups',
			'read_private_storeengine_groups',
			// common
			'edit_post',
			'edit_posts',
			'read',
			'upload_files',
			'edit_others_posts',
		);

		$shop_manager = get_role( 'storeengine_shop_manager' );
		if ( $shop_manager ) {
			$can_publish_product = false;
			if ( $can_publish_product ) {
				$role_permission[] = 'publish_storeengine_products';
				$role_permission[] = 'publish_storeengine_coupons';
			}
			foreach ( $role_permission as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_storeengine_shop_manager' );
			$administrator->add_cap( 'publish_storeengine_products' );
			$administrator->add_cap( 'publish_storeengine_coupons' );
			$administrator->add_cap( 'publish_storeengine_groups' );
			// Fix some server conflicts
			$administrator->add_cap( 'read_private_storeengine_products' );
			$administrator->add_cap( 'read_private_storeengine_coupons' );
			$administrator->add_cap( 'read_private_storeengine_groups' );
		}

		if ( current_user_can( 'administrator' ) ) {
			$user_id      = get_current_user_id();
			$shop_manager = new \WP_User( $user_id );
			$shop_manager->add_role( 'storeengine_shop_manager' );
		}
	}
}
