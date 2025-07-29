<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class FrontendDashboard {
	public function __construct() {
		add_shortcode( 'storeengine_dashboard', array( $this, 'frontend_dashboard' ) );
	}
	public function frontend_dashboard() {
		ob_start();

		do_action( 'storeengine/shortcode/before_storeengine_dashboard' );

		if ( ! is_user_logged_in() ) {
			echo do_shortcode('[storeengine_login_form]');
		} else {
			Helper::get_template( 'shortcode/frontend-dashboard.php' );
		}

		do_action( 'storeengine/shortcode/after_storeengine_dashboard' );

		return apply_filters( 'storeengine/templates/shortcode/dashboard', Helper::remove_tag_space( Helper::remove_line_break( ob_get_clean() ) ) );
	}
}
