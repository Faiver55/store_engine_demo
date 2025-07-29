<?php

namespace StoreEngine\Addons\Membership\Frontend;

use StoreEngine\Utils\Helper;

class Template {

	public function __construct() {
		if ( Helper::is_fse_theme() ) {
			add_filter( 'page_template_hierarchy', array( $this, 'update_hierarchy' ) );
		} else {
			add_filter( 'the_content', array( $this, 'assign_shortcode_to_page_content' ) );
		}
	}

	public static function update_hierarchy( array $templates ): array {
		global $storeengine_settings;

		$membership_pricing_page = get_post_field( 'post_name', $storeengine_settings->membership_pricing_page );
		if ( in_array( "page-$membership_pricing_page.php", $templates, true ) ) {
			return array_merge( array( 'membership-pricing-storeengine.php' ), $templates );
		}

		return $templates;
	}

	public function assign_shortcode_to_page_content( string $content ): string {
		// if content have any data then render that content
		if ( ! empty( $content ) ) {
			return $content;
		}

		$membership_pricing_page_id = (int) Helper::get_settings( 'membership_pricing_page' );

		if ( get_the_ID() === $membership_pricing_page_id ) {
			return '[storeengine_membership_pricing]';
		}

		return $content;
	}

}
