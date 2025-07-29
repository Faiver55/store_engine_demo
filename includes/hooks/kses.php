<?php

namespace StoreEngine\hooks;

class Kses {

	public static function init() {
		$self = new self();
		add_filter('safecss_filter_attr_allow_css', [ $self, 'allow_rgb' ], 10, 2);
	}

	public function allow_rgb( $allow_css, $css_test ) {
		$css = explode(':', $css_test);
		if ( 2 !== count($css) ) {
			return $allow_css;
		}
		$value = trim($css[1]);
		if ( strpos($value, 'rgb') !== false ) {
			if ( preg_match('/^rgba?\(\s*(\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3})(?:,\s*(0(\.\d+)?|1(\.0+)?))?\s*\)$/', $value) ) {
				return true;
			} else {
				return false;
			}
		}

		return $allow_css;
	}

}
