<?php

namespace StoreEngine;

use StoreEngine;
use StoreEngine\Admin\Settings\Base;
use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Database\CreateShippingZoneLocations;
use StoreEngine\Utils\Helper;
use StoreEngine\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Migration {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'run_migration' ] );
	}

	public function run_migration() {
		$storeengine_version = get_option( 'storeengine_version' );
		if ( ! get_option( 'storeengine_db_version' ) ) {
			Database::create_initial_custom_table();
		}
		// Force update existence templates with new shortcodes.
		$this->migrate_1_beta_5_1( $storeengine_version );
		$this->migrate_1_beta_6( $storeengine_version );
		$this->migrate_stable_1( $storeengine_version );
		$this->migrate_1_0_2( $storeengine_version );
		$this->migrate_1_2_1( $storeengine_version );
		$this->migrate_1_3_0( $storeengine_version );

		if ( STOREENGINE_VERSION !== $storeengine_version ) {
			Settings::save_settings();
			update_option( 'storeengine_version', STOREENGINE_VERSION );
		}

		// current user have administrator role and not have storeengine_shop_manager role then assign storeengine_shop_manager role
		$user = new \WP_User( get_current_user_id() );
		if ( in_array( 'administrator', $user->roles, true ) && ! in_array( 'storeengine_shop_manager', $user->roles, true ) ) {
			$user->add_role( 'storeengine_shop_manager' );
		}
	}

	public function migrate_1_beta_5_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0-beta-5.1', '<' ) ) {
			StoreEngine::init()->load_cart();
			Helper::create_initial_pages();

			$pages = [ 'cart_page', 'checkout_page', 'thankyou_page', 'dashboard_page' ];
			foreach ( $pages as $page ) {
				$page_id = Helper::get_settings( $page );
				if ( $page_id ) {
					$page_content_path = STOREENGINE_TEMPLATE_PATH . 'page-content/' . str_replace( '_page', '', $page ) . '.php';
					if ( file_exists( $page_content_path ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$page_content = file_get_contents( $page_content_path );
						wp_update_post( [
							'ID'           => $page_id,
							'post_content' => $page_content,
						] );
					}
				}
			}
		}
	}

	public function migrate_1_beta_6( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0-beta-6', '<' ) ) {
			global $wpdb;
			$method_table = $wpdb->prefix . 'storeengine_shipping_zone_methods';
			$zone_table   = $wpdb->prefix . 'storeengine_shipping_zones';

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE $method_table DROP COLUMN `type`, DROP COLUMN `cost`, DROP COLUMN `tax`" );
			$wpdb->query( "ALTER TABLE $method_table
				ADD COLUMN `method_id` varchar(200) NOT NULL AFTER `zone_id`,
				ADD COLUMN `settings` longtext NULL AFTER `description`,
				ADD COLUMN `method_order` bigint(20) UNSIGNED NOT NULL AFTER `method_id`" );
			$wpdb->query( "ALTER TABLE $zone_table DROP COLUMN `region`" );
			$wpdb->query( "ALTER TABLE $zone_table ADD COLUMN `zone_order` BIGINT(20) UNSIGNED NOT NULL AFTER `zone_name`" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			CreateShippingZoneLocations::up( $wpdb->prefix, $wpdb->get_charset_collate() );

			$page    = 'thankyou_page';
			$page_id = Helper::get_settings( $page );

			if ( $page_id ) {
				$page_content_path = STOREENGINE_TEMPLATE_PATH . 'page-content/' . str_replace( '_page', '', $page ) . '.php';
				if ( file_exists( $page_content_path ) ) {
					$page_content = file_get_contents( $page_content_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					wp_update_post( [
						'ID'           => $page_id,
						'post_content' => $page_content,
					] );
				}
			}
		}
	}

	public function migrate_stable_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0', '<' ) ) {
			$settings = get_option( STOREENGINE_SETTINGS_NAME );
			$settings = json_decode( $settings, true );

			if ( 'leftWithSpace' === $settings['store_currency_position'] ) {
				$settings['store_currency_position'] = 'left_space';
			}
			if ( 'rightWithSpace' === $settings['store_currency_position'] ) {
				$settings['store_currency_position'] = 'right_space';
			}

			if ( isset( $settings['store_zip'] ) ) {
				unset( $settings['store_zip'] );
			}

			update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( $settings ) );
		}
	}

	public function migrate_1_0_2( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.2', '<' ) ) {
			global $wpdb;
			$users_metadata = $wpdb->get_results( "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = '_storeengine_purchased_membership_ids'" );

			foreach ( $users_metadata as $user_meta ) {
				$membership_ids = maybe_unserialize( $user_meta->meta_value );
				if ( is_array( $membership_ids ) ) {
					continue;
				}
				$membership_ids = maybe_unserialize( $membership_ids );
				update_user_meta( $user_meta->user_id, '_storeengine_purchased_membership_ids', $membership_ids );
			}

			if ( ! Helper::get_settings( 'product_archive_filters' ) ) {
				Settings::save_settings();
				Base::save_settings( [
					'product_archive_filters' => [
						[
							'slug'   => 'search',
							'status' => true,
							'order'  => 0,
						],
						[
							'slug'   => 'category',
							'status' => true,
							'order'  => 1,
						],
						[
							'slug'   => 'tags',
							'status' => true,
							'order'  => 2,
						],
					],
				] );
			}
		}
	}

	public function migrate_1_2_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.2.1', '<' ) ) {
			Settings::save_settings();
			Base::save_settings( [
				'product_archive_filters' => [
					'search'   => [
						'status' => true,
						'order'  => 0,
					],
					'category' => [
						'status' => true,
						'order'  => 1,
					],
					'tags'     => [
						'status' => true,
						'order'  => 2,
					],
				],
				'auth_redirect_type'      => 'storeengine',
				'auth_redirect_url'       => '',
			] );
		}
	}

	public function migrate_1_3_0( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.3.0', '<' ) ) {
			global $wpdb;

			// Update taxonomy terms for new attribute taxonomy prefix.
			$wpdb->query( "UPDATE $wpdb->term_taxonomy SET `taxonomy` = REGEXP_REPLACE(`taxonomy`, '^storeengine_pa_', 'se_pa_') WHERE taxonomy LIKE 'storeengine_pa_%'" );

			// Update attribute order data with new attribute taxonomy prefix.
			$results = $wpdb->get_results( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_storeengine_product_attributes_order' AND meta_value LIKE '%storeengine_pa_%'" );

			foreach ( $results as $meta ) {
				$meta_value = maybe_unserialize( $meta->meta_value );
				if ( ! is_array( $meta_value ) ) {
					$meta_value = json_decode( $meta->meta_value, true );
				}

				foreach ( $meta_value as $k => $v ) {
					$meta_value[ $k ] = Helper::get_attribute_taxonomy_name( preg_replace( '/^storeengine_pa\_/', '', $v ) );
				}

				$wpdb->update(
					$wpdb->postmeta,
					[ 'meta_value' => maybe_serialize( $meta_value ) ],
					[ 'meta_id' => $meta->meta_id ],
				);
			}
		}
	}

	public function migrate_1_3_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.3.1', '<' ) ) {
			wp_cache_flush();
		}
	}
}
