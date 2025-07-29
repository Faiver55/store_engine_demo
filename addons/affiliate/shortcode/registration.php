<?php

namespace StoreEngine\Addons\Affiliate\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\models\Affiliate;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class Registration {

	public function __construct() {
		add_shortcode( 'storeengine_affiliate_application', [ $this, 'registration_form' ] );
	}

	public function registration_form( $atts ) {
		$attributes = shortcode_atts( [
			'form_title'                => esc_html__( 'Affiliate Registration Form', 'storeengine' ),
			'alert_text'                => esc_html__( 'Please login to apply for affiliate', 'storeengine' ),
			'button_text'               => esc_html__( 'Register', 'storeengine' ),
			'first_name_label'          => esc_html__( 'First Name', 'storeengine' ),
			'first_name_placeholder'    => esc_html__( 'First Name', 'storeengine' ),
			'last_name_label'           => esc_html__( 'Last Name', 'storeengine' ),
			'last_name_placeholder'     => esc_html__( 'Last Name', 'storeengine' ),
			'email_label'               => esc_html__( 'Email', 'storeengine' ),
			'email_placeholder'         => esc_html__( 'Email', 'storeengine' ),
			'password_label'            => esc_html__( 'Password', 'storeengine' ),
			'password_placeholder'      => esc_html__( 'Password', 'storeengine' ),
			'registration_button_label' => esc_html__( 'Registration', 'storeengine' ),
			'show_logged_in_message'    => true,
		], $atts );

		$affiliate_pending = false;
		if ( is_user_logged_in() ) {
			$affiliate_details = Affiliate::get_affiliates( [ 'user_id' => get_current_user_id() ] );
			$affiliate_pending = isset( $affiliate_details['status'] ) && 'pending' === $affiliate_details['status'];
		}
		$attributes = wp_parse_args( $attributes, [ 'affiliate_pending' => $affiliate_pending ] );

		ob_start();
		Template::get_template( 'affiliate/registration.php', $attributes);
		return apply_filters( 'storeengine_affiliate/templates/shortcode/registration', ob_get_clean() );
	}
}
