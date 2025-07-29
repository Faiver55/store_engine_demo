<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use Exception;
use WP_Error;

/**
 * WP_Error compatible exception.
 *
 * @deprecated moved to StoreEngine\Classes\Exceptions\StoreEngineException;
 */
class StoreEngineException extends Exception {

	protected $wp_code;

	protected $data = '';

	public function __construct( $message, $wp_code, $data = null, $code = 0, ?Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->wp_code = $wp_code;
		if ( $data ) {
			$this->data = $data;
		}
	}

	public function get_wp_code() {
		return $this->wp_code;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_wp_error() {
		return new WP_Error( $this->wp_code, $this->getMessage(), $this->data );
	}
}
