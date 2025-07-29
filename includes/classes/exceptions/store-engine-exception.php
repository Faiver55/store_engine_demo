<?php

namespace StoreEngine\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use Exception;
use Throwable;
use WP_Error;

/**
 * WP_Error compatible exception.
 */
class StoreEngineException extends Exception {

	protected string $wp_code = '';

	protected $data = [];

	/**
	 * @var Throwable|StoreEngineException|null
	 */
	protected ?Throwable $previous = null;

	/**
	 * @param string $message
	 * @param string $wp_code
	 * @param mixed $data
	 * @param int $code
	 * @param ?Throwable $previous
	 */
	public function __construct( string $message, string $wp_code = '', $data = null, int $code = 0, ?Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->wp_code  = $wp_code ?: sanitize_title( $this->get_class_code() );
		$this->previous = $previous;

		if ( is_numeric( $data ) && $data > 0 && ! $code ) {
			$code = $data;
			$data = [ 'status' => $data ];
		}

		if ( ! $data ) {
			$data = [];
		}

		if ( empty( $data['status'] ) ) {
			$data['status'] = $code ? $code : 500;
		}

		$this->data = $data;
	}

	/**
	 * @throws self
	 */
	public static function throw( string $message, $data = null, int $code = 0, ?Throwable $previous = null ) {
		throw new self( $message, '', $data, $code, $previous );
	}

	protected function get_class_code(): string {
		$code = str_replace( [ __NAMESPACE__, 'StoreEngine', 'Exception' ], '', __CLASS__ );

		return $code ?: 'UNKNOWN-ERROR';
	}

	public function get_wp_error_code(): string {
		return $this->wp_code;
	}

	public function get_data( ?string $key = null ) {
		if ( $key ) {
			if ( isset( $this->data[ $key ] ) ) {
				return $this->data[ $key ];
			}

			return null;
		}

		return $this->data;
	}

	public function set_data( array $data ) {
		$this->data = $data;
	}

	public function set_message( string $message ) {
		if ( $this->message ) {
			// Keep a track of old messages.
			if ( ! isset( $this->data['old_message'] ) ) {
				$this->data['old_message'] = [];
			}

			$this->data['old_message'][] = $this->message;
		}

		$this->message = $message;
	}

	public function add_data( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	public function get_wp_error(): WP_Error {
		return new WP_Error( $this->wp_code, $this->getMessage(), $this->data );
	}

	/**
	 * @alias get_wp_error()
	 * @return WP_Error
	 */
	public function toWpError(): WP_Error {
		return $this->get_wp_error();
	}

	public function get_previous_data(): ?array {
		if ( ! $this->previous ) {
			return null;
		}

		if ( is_a( $this->previous, self::class ) && method_exists( $this->previous, 'to_array' ) ) {
			return $this->previous->to_array();
		}

		$data = [
			'code'    => $this->previous,
			'type'    => get_class( $this->previous ),
			'message' => $this->previous->getMessage(),
			'data'    => null,
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$data['trace'] = $this->previous->getTrace();
		}

		return $data;
	}

	public function to_array(): array {
		$data = [
			'code'     => $this->wp_code,
			'type'     => get_class( $this ),
			'message'  => $this->getMessage(),
			'data'     => $this->data,
			'trace'    => $this->getTrace(),
			'previous' => $this->get_previous_data(),
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$data['trace'] = $this->previous->getTrace();
		}

		return $data;
	}

	public function __toJsonString( $flags = 0, $depth = 512 ): string {
		return wp_json_encode( $this->to_array(), $flags, $depth );
	}

	public static function from_wp_error( WP_Error $wp_error ): self {
		return new self( $wp_error->get_error_message(), $wp_error->get_error_code(), $wp_error->get_error_data() );
	}

	public static function convert_exception( Exception $exception, string $error_code = null, $data = null ): self {
		$data = $data ?? [];
		return new self(
			$exception->getMessage(),
			$error_code ?: __( 'Something went wrong.', 'storeengine' ),
			$data,
			$exception->getCode(),
			$exception
		);
	}
}
