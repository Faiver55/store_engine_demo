<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer {
	public static function sanitize_payload( array $schema, array $payload ): array {
		$sanitized_payload = [];

		foreach ( $schema as $key => $type ) {
			if ( array_key_exists( $key, $payload ) ) {
				if ( is_array( $type ) ) {
					if ( is_string( $payload[ $key ] ) ) {
						$payload[ $key ] = json_decode( $payload[ $key ], true );
					}

					if ( ! is_array( $payload[ $key ] ) ) {
						continue;
					}

					$sanitized_payload[ $key ] = self::sanitize_payload( $type, $payload[ $key ] );
				} else {
					$value = is_null( $payload[ $key ] ) ? '' : $payload[ $key ];
					if ( str_contains( $type, '|' ) ) {
						list( $data_type, $type ) = explode( '|', $type, 2 );
						if ( 'array' === $data_type ) {
							$value = json_decode( wp_unslash( $value ), true );
						}
						if ( 'json' === $data_type ) {
							$value = json_decode( wp_unslash( $value ) );
						}
					}

					$sanitize_value = function ( $value ) use ( $type ) {
						switch ( strtolower( $type ) ) {
							case 'id':
								return absint( sanitize_text_field( $value ) );
							case 'integer':
								return intval( sanitize_text_field( $value ) );
							case 'float':
								return floatval( sanitize_text_field( $value ) );
							case 'url':
								return esc_url_raw( $value );
							case 'boolean':
								return (bool) filter_var( sanitize_text_field( $value ), FILTER_VALIDATE_BOOLEAN );
							case 'post':
								return wp_kses_post( $value );
							case 'email':
								return sanitize_email( $value );
							case 'string':
								return sanitize_text_field( $value );
							default:
								if ( is_array( $value ) ) {
									return wp_kses_post_deep( $value );
								}

								return wp_kses_post( trim( stripslashes( $value ) ) );
						}
					};

					if ( is_array( $value ) || is_object( $value ) ) {
						$sanitized_payload[ $key ] = map_deep( $value, $sanitize_value );
					} else {
						$sanitized_payload[ $key ] = $sanitize_value( $value );
					}
				}
			}
		}

		return $sanitized_payload;
	}

	public static function sanitize_json_form_data( $data, $schema = [] ) {
		$data = is_array( $data ) ? $data : json_decode( stripslashes( $data ) );
		if ( is_array( $data ) ) {
			$results    = [];
			$has_schema = count( $schema );
			foreach ( $data as $key => $value ) {
				if ( $has_schema && ! isset( $schema[ $key ] ) ) {
					continue;
				}
				if ( is_array( $value ) || is_object( $value ) ) {
					$value       = (array) $value;
					$child_array = [];
					foreach ( $value as $child_key => $child_value ) {
						$child_array[ sanitize_key( $child_key ) ] = sanitize_text_field( $child_value );
					}
					$results[] = $child_array;
				} else {
					$results[ sanitize_key( $key ) ] = sanitize_text_field( $value );
				}
			}

			return $results;
		}

		return sanitize_text_field( $data );
	}

	public static function sanitize_array_field( $array_data ) {
		$array_data = is_array( $array_data ) ? $array_data : json_decode( stripslashes( $array_data ) );
		$boolean    = [ 'true', 'false', '1', '0' ];
		if ( is_array( $array_data ) ) {
			foreach ( $array_data as $key => &$value ) {
				if ( is_array( $value ) ) {
					$value = self::sanitize_array_field( $value );
				} else {
					$value = in_array( $value, $boolean, true ) || is_bool( $value ) ? self::sanitize_checkbox_field( $value ) : sanitize_text_field( $value );
				}
			}
		}

		return $array_data;
	}

	public static function sanitize_checkbox_field( $boolean ) {
		return (bool) filter_var( sanitize_text_field( $boolean ), FILTER_VALIDATE_BOOLEAN );
	}
}
