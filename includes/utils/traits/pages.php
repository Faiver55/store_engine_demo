<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Utils\Helper;

trait Pages {
	public static function create_initial_pages() {
		global $storeengine_settings;

		// WordPress sets fresh_site to 0 after a page gets published.
		// Prevent fresh_site option from being set to 0 so that we can use it for further customizations.
		remove_action( 'publish_page', '_delete_option_fresh_site', 0 );

		// Set the locale to the store locale to ensure pages are created in the correct language.
		Helper::switch_to_site_locale();

		// Prepare settings data for update.
		$settings = (array) $storeengine_settings;

		/**
		 * Determines which pages are created during install.
		 *
		 * @since 0.0.5
		 */
		$page_lists = [
			'shop_page'                   => [
				'name'    => _x( 'store-shop', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Shop', 'Page title', 'storeengine' ),
				'content' => '',
			],
			'cart_page'                   => [
				'name'    => _x( 'store-cart', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Cart', 'Page title', 'storeengine' ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content' => file_get_contents( STOREENGINE_TEMPLATE_PATH . 'page-content/cart.php' ),
			],
			'checkout_page'               => [
				'name'    => _x( 'store-checkout', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Checkout', 'Page title', 'storeengine' ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content' => file_get_contents( STOREENGINE_TEMPLATE_PATH . 'page-content/checkout.php' ),
			],
			'thankyou_page'               => [
				'name'    => _x( 'store-thank-you', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Thank You', 'Page title', 'storeengine' ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content' => file_get_contents( STOREENGINE_TEMPLATE_PATH . 'page-content/thankyou.php' ),
			],
			'dashboard_page'              => [
				'name'    => _x( 'store-dashboard', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Dashboard', 'Page title', 'storeengine' ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content' => file_get_contents( STOREENGINE_TEMPLATE_PATH . 'page-content/dashboard.php' ),
			],
			'affiliate_registration_page' => [
				'name'    => _x( 'store-affiliate-registration', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Affiliate Registration', 'Page title', 'storeengine' ),
				'content' => '<!-- wp:shortcode -->[storeengine_affiliate_application]<!-- /wp:shortcode -->',
			],
			'membership_pricing_page'     => [
				'name'    => _x( 'store-membership-pricing', 'Page slug', 'storeengine' ),
				'title'   => _x( 'Store Membership Pricing', 'Page title', 'storeengine' ),
				'content' => '<!-- wp:shortcode -->[storeengine_membership_pricing]<!-- /wp:shortcode -->',
			],
		];

		foreach ( $page_lists as $key => $page ) {
			$post_id = self::create_page(
				esc_sql( $page['name'] ),
				$key,
				$page['title'],
				$page['content'],
				! empty( $page['parent'] ) ? Helper::get_settings( $page['parent'], 0 ) : 0,
				! empty( $page['post_status'] ) ? $page['post_status'] : 'publish'
			);

			if ( $post_id ) {
				$settings[ $key ] = $post_id;
			}
		}

		Helper::restore_locale();

		BaseSettings::save_settings( $settings );
	}

	protected static function create_page( $slug, $settings_key, $title, $content = '', $parent = 0, $status = 'publish' ): int {
		global $storeengine_settings, $wpdb;
		$settings = (array) $storeengine_settings;

		$settings_value = (int) ( $settings[ $settings_key ] ?? 0 );
		if ( $settings_value > 0 ) {
			$page_object = get_post( $settings_value );

			if ( $page_object && 'page' === $page_object->post_type && ! in_array( $page_object->post_status, [ 'pending', 'trash', 'future', 'auto-draft' ], true ) ) {
				// Valid page is already in place.
				return $settings_value;
			}
		}

		if ( strlen( $content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$shortcode        = str_replace( [ '<!-- storeengine:shortcode -->', '<!-- /storeengine:shortcode -->' ], '', $content );
			$valid_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$shortcode}%" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Search for an existing page with the specified page slug.
			$valid_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' )  AND post_name = %s LIMIT 1;", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( $valid_page_found ) {
			return $valid_page_found;
		}

		// Search for a matching valid trashed page.
		if ( strlen( $content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$trashed_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$content}%" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Search for an existing page with the specified page slug.
			$trashed_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( $trashed_page_found ) {
			$page_id = $trashed_page_found;
			wp_update_post( [
				'ID'             => $page_id,
				'post_status'    => $status,
				'comment_status' => 'closed',
			] );
		} else {
			$page_data = [
				'post_status'    => $status,
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => $slug,
				'post_title'     => $title,
				'post_content'   => $content,
				'post_parent'    => $parent,
				'comment_status' => 'closed',
			];
			$page_id   = wp_insert_post( $page_data );

			/**
			 * Fire once StoreEngine page as been created.
			 *
			 * @since 0.0.5
			 *
			 * @param int $page_id Post ID
			 * @param array $page_data Post data.
			 */
			do_action( 'storeengine/page_created', $page_id, $page_data );
		}

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			// Set template for handling block-theme compatibility.
			update_post_meta( $page_id, '_wp_page_template', 'storeengine-canvas.php' );

			return $page_id;
		}

		return 0;
	}
}
