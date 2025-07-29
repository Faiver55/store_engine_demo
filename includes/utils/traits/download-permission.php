<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Classes\DownloadPermissionRepository;

trait DownloadPermission {

	public static function get_download_permission( int $id ) {
		return ( new \StoreEngine\Classes\DownloadPermission($id) )->get();
	}

	/**
	 * @param int $order_id
	 * @return \StoreEngine\Classes\DownloadPermission[]
	 */
	public static function get_download_permissions_by_order_id( int $order_id ): array {
		return ( new DownloadPermissionRepository() )->get_by_order( $order_id );
	}

	/**
	 * @param int $page
	 * @param int $customer_id
	 * @return \StoreEngine\Classes\DownloadPermission[]
	 */
	public static function get_download_permissions_by_customer_id( int $page = 1, int $customer_id = 0 ): array {
		if ( 0 === $customer_id ) {
			$customer_id = get_current_user_id();
		}

		return ( new DownloadPermissionRepository() )->with_pagination($page)->get_by_customer_id( $customer_id );
	}

	public static function get_download_permissions_count_by_customer_id( int $customer_id = 0 ) {
		if ( 0 === $customer_id ) {
			$customer_id = get_current_user_id();
		}

		return ( new DownloadPermissionRepository() )->total_count_by_customer_id( $customer_id );
	}

}
