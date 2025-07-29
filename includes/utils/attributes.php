<?php

namespace StoreEngine\Utils;

class Attributes {
	private $attributes = [];

	public function add_attribute( $element, $key = null, $value = null, $overwrite = false ) {
		if ( is_array( $element ) && is_null( $key ) && is_null( $value ) ) {
			// If the first argument is an array, assume it's an associative array of attributes.
			foreach ( $element as $attrKey => $attrValue ) {
				$this->add_attribute( $attrKey, $attrValue, null, $overwrite );
			}
		} else {
			// If the first argument is a string, assume it's the element name.
			if ( ! isset( $this->attributes[ $element ] ) || $overwrite ) {
				if ( is_array( $key ) ) {
					// If $key is an array, assume it's an associative array of attributes.
					$this->attributes[ $element ] = array_merge( $this->attributes[ $element ] ?? [], $key );
				} else {
					// If $key is a string, assume it's a single attribute.
					$this->attributes[ $element ][ $key ] = $value;
				}
			}
		}

		return $this;
	}

	public function get_attribute_string( $element ) {
		$attributes = $this->attributes[ $element ] ?? [];

		$attributeString = '';
		foreach ( $attributes as $key => $value ) {
			$attributeString .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
		return $attributeString;
	}
}
