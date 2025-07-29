<?php

namespace StoreEngine\shortcode;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class ArchiveHeaderFilter {

	public function __construct() {
		add_shortcode( 'storeengine_archive_header_filter', array( $this, 'render' ) );
	}

	public function render(): string {
		ob_start();

		Template::get_template( 'archive/header.php' );

		$output = Helper::remove_line_break( ob_get_clean() );

		return Helper::remove_tag_space( $output );
	}

}
