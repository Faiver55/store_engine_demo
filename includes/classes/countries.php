<?php
/**
 * StoreEngine countries
 *
 * @package StoreEngine\l10n
 */

namespace StoreEngine\Classes;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

/** @define "STOREENGINE_ROOT_DIR_PATH" "./../../" */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Countries {

	use Singleton;

	/**
	 * Locales list.
	 *
	 * @var array
	 */
	public array $locale = [];

	/**
	 * List of address formats for locales.
	 *
	 * @var array
	 */
	public array $address_formats = [];

	/**
	 * Cache of geographical regions.
	 *
	 * Only to be used by the get_* and load_* methods, as other methods may expect the regions to be
	 * loaded on demand.
	 *
	 * @var array
	 */
	private array $geo_cache = [];

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key Key.
	 *
	 * @return array|false
	 */
	public function __get( $key ) {
		if ( 'countries' === $key ) {
			return $this->get_countries();
		} elseif ( 'states' === $key ) {
			return $this->get_states();
		} elseif ( 'continents' === $key ) {
			return $this->get_continents();
		}

		return false;
	}

	/**
	 * Get all countries.
	 *
	 * @return array
	 */
	public function get_countries(): array {
		if ( empty( $this->geo_cache['countries'] ) ) {
			/**
			 * Allows filtering of the list of countries in StoreEngine.
			 *
			 * @param array $countries
			 */
			$this->geo_cache['countries'] = apply_filters( 'storeengine/countries', include STOREENGINE_ROOT_DIR_PATH . 'i18n/countries.php' );
			if ( apply_filters( 'storeengine/sort_countries', true ) ) {
				Helper::asort_by_locale( $this->geo_cache['countries'] );
			}
		}

		return $this->geo_cache['countries'];
	}

	/**
	 * @param string $cc
	 *
	 * @return string|null
	 */
	public function get_country( string $cc ): ?string {
		return $this->get_countries()[ $cc ] ?? null;
	}

	/**
	 * Check if a given code represents a valid ISO 3166-1 alpha-2 code for a country known to us.
	 *
	 * @param string $country_code The country code to check as a ISO 3166-1 alpha-2 code.
	 *
	 * @return bool True if the country is known to us, false otherwise.
	 */
	public function country_exists( string $country_code ): bool {
		return isset( $this->get_countries()[ $country_code ] );
	}

	/**
	 * Get all continents.
	 *
	 * @return array
	 */
	public function get_continents(): array {
		if ( empty( $this->geo_cache['continents'] ) ) {
			/**
			 * Allows filtering of continents in StoreEngine.
			 *
			 * @param array[array] $continents
			 */
			$this->geo_cache['continents'] = apply_filters( 'storeengine/continents', include STOREENGINE_ROOT_DIR_PATH . 'i18n/continents.php' );
		}

		return $this->geo_cache['continents'];
	}

	/**
	 * Get continent code for a country code.
	 *
	 * @param string $cc Country code.
	 *
	 * @return string
	 */
	public function get_continent_code_for_country( string $cc ): string {
		$cc                 = trim( strtoupper( $cc ) );
		$continents         = $this->get_continents();
		$continents_and_ccs = wp_list_pluck( $continents, 'countries' );
		foreach ( $continents_and_ccs as $continent_code => $countries ) {
			if ( false !== array_search( $cc, $countries, true ) ) {
				return $continent_code;
			}
		}

		return '';
	}

	/**
	 * Get calling code for a country code.
	 *
	 * @param string $cc Country code.
	 *
	 * @return string|array Some countries have multiple. The code will be stripped of - and spaces and always be prefixed with +.
	 */
	public function get_country_calling_code( string $cc ) {
		$codes = wp_cache_get( 'calling-codes', 'countries' );

		if ( ! $codes ) {
			$codes = include STOREENGINE_ROOT_DIR_PATH . 'i18n/phone.php';
			wp_cache_set( 'calling-codes', $codes, 'countries' );
		}

		$calling_code = $codes[ $cc ] ?? '';

		if ( is_array( $calling_code ) ) {
			$calling_code = $calling_code[0];
		}

		return $calling_code;
	}

	/**
	 * Get continents that the store ships to.
	 *
	 * @return array
	 */
	public function get_shipping_continents(): array {
		$continents             = $this->get_continents();
		$shipping_countries     = $this->get_shipping_countries();
		$shipping_country_codes = array_keys( $shipping_countries );
		$shipping_continents    = [];

		foreach ( $continents as $continent_code => $continent ) {
			if ( count( array_intersect( $continent['countries'], $shipping_country_codes ) ) ) {
				$shipping_continents[ $continent_code ] = $continent;
			}
		}

		return $shipping_continents;
	}

	/**
	 * Get the states for a country.
	 *
	 * @param string|null $cc Country code.
	 *
	 * @return false|array of states
	 */
	public function get_states( ?string $cc = null ) {
		if ( ! isset( $this->geo_cache['states'] ) ) {
			/**
			 * Allows filtering of country states in StoreEngine.
			 *
			 * @param array $states
			 */
			$this->geo_cache['states'] = apply_filters( 'storeengine/states', include STOREENGINE_ROOT_DIR_PATH . 'i18n/states.php' );
		}

		if ( ! is_null( $cc ) ) {
			return isset( $this->geo_cache['states'][ $cc ] ) ? $this->geo_cache['states'][ $cc ] : false;
		} else {
			return $this->geo_cache['states'];
		}
	}

	/**
	 * Get the base address (first line) for the store.
	 *
	 * @return string
	 */
	public function get_base_address(): string {
		$base_address = Helper::get_settings( 'store_address', '' );

		return apply_filters( 'storeengine/countries_base_address', $base_address );
	}

	/**
	 * Get the base address (second line) for the store.
	 *
	 * @return string
	 */
	public function get_base_address_2(): string {
		$base_address_2 = Helper::get_settings( 'store_address_2', '' );

		return apply_filters( 'storeengine/countries_base_address_2', $base_address_2 );
	}

	/**
	 * Get the base country for the store.
	 *
	 * @return string
	 */
	public function get_base_country(): string {
		return (string) apply_filters( 'storeengine/countries_base_country', Helper::get_settings( 'store_country', '' ) );
	}

	/**
	 * Get the base state for the store.
	 *
	 * @return string
	 */
	public function get_base_state(): string {
		return (string) apply_filters( 'storeengine/countries_base_state', Helper::get_settings( 'store_state', '' ) );
	}

	/**
	 * Get the base city for the store.
	 *
	 * @return string
	 * @version 3.1.1
	 */
	public function get_base_city(): string {
		return (string) apply_filters( 'storeengine/countries_base_city', Helper::get_settings( 'store_city', '' ) );
	}

	/**
	 * Get the base postcode for the store.
	 *
	 * @return string
	 */
	public function get_base_postcode(): string {
		return (string) apply_filters( 'storeengine/countries_base_postcode', Helper::get_settings( 'store_postcode', '' ) );
	}

	/**
	 * Get countries that the store sells to.
	 *
	 * @return array
	 */
	public function get_allowed_countries(): array {
		$countries         = $this->countries;
		$allowed_countries = Helper::get_settings( 'allowed_countries' );

		if ( 'all_except' === $allowed_countries ) {
			$except_countries = Helper::get_settings( 'all_except_countries', [] );

			if ( $except_countries ) {
				foreach ( $except_countries as $country ) {
					unset( $countries[ $country ] );
				}
			}
		} elseif ( 'specific' === $allowed_countries ) {
			$countries     = [];
			$raw_countries = Helper::get_settings( 'specific_allowed_countries', [] );

			if ( $raw_countries ) {
				foreach ( $raw_countries as $country ) {
					$countries[ $country ] = $this->countries[ $country ];
				}
			}
		}

		/**
		 * Filter the list of allowed selling countries.
		 *
		 * @param array $countries
		 */
		return apply_filters( 'storeengine/countries_allowed_countries', $countries );
	}

	/**
	 * Get countries that the store ships to.
	 *
	 * @return array
	 */
	public function get_shipping_countries(): array {
		// If shipping is disabled, return an empty array.
		if ( 'disabled' === Helper::get_settings( 'ship_to_countries' ) ) {
			return [];
		}

		// Default to selling countries.
		$countries = $this->get_allowed_countries();

		// All indicates that all countries are allowed, regardless of where you sell to.
		if ( 'all' === Helper::get_settings( 'ship_to_countries' ) ) {
			$countries = $this->get_countries();
		} elseif ( 'specific' === Helper::get_settings( 'ship_to_countries' ) ) {
			$countries     = [];
			$raw_countries = Helper::get_settings( 'specific_ship_to_countries', [] );

			if ( $raw_countries ) {
				foreach ( $raw_countries as $country ) {
					$countries[ $country ] = $this->countries[ $country ];
				}
			}
		}

		/**
		 * Filter the list of allowed selling countries.
		 *
		 * @param array $countries
		 */
		return apply_filters( 'storeengine/shipping/countries', $countries );
	}

	/**
	 * Get allowed country states.
	 *
	 * @return array
	 */
	public function get_allowed_country_states(): array {
		if ( Helper::get_settings( 'allowed_countries' ) !== 'specific' ) {
			return $this->states;
		}

		$states = [];

		$raw_countries = Helper::get_settings( 'specific_allowed_countries' );

		if ( $raw_countries ) {
			foreach ( $raw_countries as $country ) {
				if ( isset( $this->states[ $country ] ) ) {
					$states[ $country ] = $this->states[ $country ];
				}
			}
		}

		return apply_filters( 'storeengine/countries_allowed_country_states', $states );
	}

	/**
	 * Get shipping country states.
	 *
	 * @return array
	 */
	public function get_shipping_country_states(): array {
		if ( Helper::get_settings( 'ship_to_countries' ) === '' ) {
			return $this->get_allowed_country_states();
		}

		if ( Helper::get_settings( 'ship_to_countries' ) !== 'specific' ) {
			return $this->states;
		}

		$states = [];

		$raw_countries = Helper::get_settings( 'specific_ship_to_countries' );

		if ( $raw_countries ) {
			foreach ( $raw_countries as $country ) {
				if ( ! empty( $this->states[ $country ] ) ) {
					$states[ $country ] = $this->states[ $country ];
				}
			}
		}

		return apply_filters( 'storeengine/countries_shipping_country_states', $states );
	}

	/**
	 * Gets an array of countries in the EU.
	 *
	 * @param string $type Type of countries to retrieve. Blank for EU member countries. eu_vat for EU VAT countries.
	 *
	 * @return string[]
	 */
	public function get_european_union_countries( $type = '' ) {
		$countries = [
			'AT',
			'BE',
			'BG',
			'CY',
			'CZ',
			'DE',
			'DK',
			'EE',
			'ES',
			'FI',
			'FR',
			'GR',
			'HR',
			'HU',
			'IE',
			'IT',
			'LT',
			'LU',
			'LV',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SE',
			'SI',
			'SK',
		];

		if ( 'eu_vat' === $type ) {
			$countries[] = 'MC';
		}

		return apply_filters( 'storeengine/european_union_countries', $countries, $type );
	}

	/**
	 * Gets an array of countries using VAT.
	 *
	 * @return string[] of country codes.
	 */
	public function get_vat_countries() {
		$eu_countries  = $this->get_european_union_countries();
		$vat_countries = [
			'AE',
			'AL',
			'AR',
			'AZ',
			'BB',
			'BH',
			'BO',
			'BS',
			'BY',
			'CL',
			'CO',
			'EC',
			'EG',
			'ET',
			'FJ',
			'GB',
			'GH',
			'GM',
			'GT',
			'IL',
			'IM',
			'IN',
			'IR',
			'KN',
			'KR',
			'KZ',
			'LK',
			'MC',
			'MD',
			'ME',
			'MK',
			'MN',
			'MU',
			'MX',
			'NA',
			'NG',
			'NO',
			'NP',
			'PS',
			'PY',
			'RS',
			'RU',
			'RW',
			'SA',
			'SV',
			'TH',
			'TR',
			'UA',
			'UY',
			'UZ',
			'VE',
			'VN',
			'ZA',
		];

		return apply_filters( 'storeengine/vat_countries', array_merge( $eu_countries, $vat_countries ) );
	}

	/**
	 * Gets the correct string for shipping - either 'to the' or 'to'.
	 *
	 * @param string $country_code Country code.
	 *
	 * @return string
	 */
	public function shipping_to_prefix( $country_code = '' ) {
		if ( ! $country_code ) {
			$customer     = Helper::get_customer();
			$country_code = $customer ? $customer->get_shipping_country() ?? ( $customer->get_billing_country() ?? '' ) : '';
		}

		$countries = [ 'AE', 'CZ', 'DO', 'GB', 'NL', 'PH', 'US', 'USAF' ];
		$return    = in_array( $country_code, $countries, true ) ? _x( 'to the', 'shipping country prefix', 'storeengine' ) : _x( 'to', 'shipping country prefix', 'storeengine' );

		return apply_filters( 'storeengine/countries_shipping_to_prefix', $return, $country_code );
	}

	/**
	 * Prefix certain countries with 'the'.
	 *
	 * @param string $country_code Country code.
	 *
	 * @return string
	 */
	public function estimated_for_prefix( $country_code = '' ) {
		$country_code = $country_code ? $country_code : $this->get_base_country();
		$countries    = [ 'AE', 'CZ', 'DO', 'GB', 'NL', 'PH', 'US', 'USAF' ];
		$return       = in_array( $country_code, $countries, true ) ? __( 'the', 'storeengine' ) . ' ' : '';

		return apply_filters( 'storeengine/countries_estimated_for_prefix', $return, $country_code );
	}

	/**
	 * Correctly name tax in some countries VAT on the frontend.
	 *
	 * @return string
	 */
	public function tax_or_vat() {
		$return = in_array( $this->get_base_country(), $this->get_vat_countries(), true ) ? __( 'VAT', 'storeengine' ) : __( 'Tax', 'storeengine' );

		return apply_filters( 'storeengine/countries_tax_or_vat', $return );
	}

	/**
	 * Include the Inc Tax label.
	 *
	 * @return string
	 */
	public function inc_tax_or_vat() {
		$return = in_array( $this->get_base_country(), $this->get_vat_countries(), true ) ? __( '(incl. VAT)', 'storeengine' ) : __( '(incl. tax)', 'storeengine' );

		return apply_filters( 'storeengine/countries_inc_tax_or_vat', $return );
	}

	/**
	 * Include the Ex Tax label.
	 *
	 * @return string
	 */
	public function ex_tax_or_vat(): string {
		$return = in_array( $this->get_base_country(), $this->get_vat_countries(), true ) ? __( '(ex. VAT)', 'storeengine' ) : __( '(ex. tax)', 'storeengine' );

		return apply_filters( 'storeengine/countries_ex_tax_or_vat', $return );
	}

	/**
	 * Outputs the list of countries and states for use in dropdown boxes.
	 *
	 * @param string $selected_country Selected country.
	 * @param string $selected_state Selected state.
	 * @param bool $escape If we should escape HTML.
	 */
	public function country_dropdown_options( string $selected_country = '', string $selected_state = '', bool $escape = false ) {
		if ( $this->countries ) {
			foreach ( $this->countries as $key => $value ) {
				$states = $this->get_states( $key );
				if ( $states ) {
					// Maybe default the selected state as the first one.
					if ( $selected_country === $key && '*' === $selected_state ) {
						$selected_state = key( $states ) ?? '*';
					}

					echo '<optgroup label="' . esc_attr( $value ) . '">';
					foreach ( $states as $state_key => $state_value ) {
						echo '<option value="' . esc_attr( $key ) . ':' . esc_attr( $state_key ) . '"';

						if ( $selected_country === $key && $selected_state === $state_key ) {
							echo ' selected="selected"';
						}

						echo '>' . esc_html( $value ) . ' &mdash; ' . ( $escape ? esc_html( $state_value ) : $state_value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</optgroup>';
				} else {
					echo '<option';
					if ( $selected_country === $key && '*' === $selected_state ) {
						echo ' selected="selected"';
					}
					echo ' value="' . esc_attr( $key ) . '">' . ( $escape ? esc_html( $value ) : $value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}
	}

	/**
	 * Get country address formats.
	 *
	 * These define how addresses are formatted for display in various countries.
	 *
	 * @return array
	 */
	public function get_address_formats(): array {
		if ( empty( $this->address_formats ) ) {
			$this->address_formats = apply_filters(
				'storeengine/localisation_address_formats',
				[
					'default' => "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}",
					'AT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'AU'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
					'BE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'CA'      => "{company}\n{name}\n{address_1}\n{address_2}\n{city} {state_code} {postcode}\n{country}",
					'CH'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'CL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{state}\n{postcode} {city}\n{country}",
					'CN'      => "{country} {postcode}\n{state}, {city}, {address_2}, {address_1}\n{company}\n{name}",
					'CZ'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'DE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'DK'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'EE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'ES'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city}\n{state}\n{country}",
					'FI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'FR'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city_upper}\n{country}",
					'HK'      => "{company}\n{first_name} {last_name_upper}\n{address_1}\n{address_2}\n{city_upper}\n{state_upper}\n{country}",
					'HU'      => "{last_name} {first_name}\n{company}\n{city}\n{address_1}\n{address_2}\n{postcode}\n{country}",
					'IN'      => "{company}\n{name}\n{address_1}\n{address_2}\n{city} {postcode}\n{state}, {country}",
					'IS'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'IT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode}\n{city}\n{state_upper}\n{country}",
					'JM'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode_upper}\n{country}",
					'JP'      => "{postcode}\n{state} {city} {address_1}\n{address_2}\n{company}\n{last_name} {first_name}\n{country}",
					'LI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'NL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'NO'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'NZ'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city} {postcode}\n{country}",
					'PL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'PR'      => "{company}\n{name}\n{address_1} {address_2}\n{city} \n{country} {postcode}",
					'PT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'RS'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'SE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'SI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'SK'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
					'TR'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city} {state}\n{country}",
					'TW'      => "{company}\n{last_name} {first_name}\n{address_1}\n{address_2}\n{state}, {city} {postcode}\n{country}",
					'UG'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}, {country}",
					'US'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city}, {state_code} {postcode}\n{country}",
					'VN'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city} {postcode}\n{country}",
				]
			);
		}

		return $this->address_formats;
	}

	/**
	 * Get country address format.
	 *
	 * @param array $args Arguments.
	 * @param string $separator How to separate address lines.
	 *
	 * @return string
	 */
	public function get_formatted_address( $args = [], $separator = '<br/>' ): string {
		$default_args = [
			'first_name' => '',
			'last_name'  => '',
			'company'    => '',
			'address_1'  => '',
			'address_2'  => '',
			'city'       => '',
			'state'      => '',
			'postcode'   => '',
			'country'    => '',
		];

		$args    = array_map( fn( $v ) => trim( $v ?? '' ), wp_parse_args( $args, $default_args ) );
		$state   = $args['state'];
		$country = $args['country'];

		// Get all formats.
		$formats = $this->get_address_formats();

		// Get format for the address' country.
		$format = ( $country && isset( $formats[ $country ] ) ) ? $formats[ $country ] : $formats['default'];

		// Handle full country name.
		$full_country = ( isset( $this->countries[ $country ] ) ) ? $this->countries[ $country ] : $country;

		// Country is not needed if the same as base.
		if ( $country === $this->get_base_country() && ! apply_filters( 'storeengine/formatted_address_force_country_display', false ) ) {
			$format = str_replace( '{country}', '', $format );
		}

		// Handle full state name.
		$full_state = ( $country && $state && isset( $this->states[ $country ][ $state ] ) ) ? $this->states[ $country ][ $state ] : $state;

		// Substitute address parts into the string.
		$replace = array_map(
			'esc_html',
			apply_filters(
				'storeengine/formatted_address_replacements',
				[
					'{first_name}'       => $args['first_name'],
					'{last_name}'        => $args['last_name'],
					'{name}'             => sprintf(
					/* translators: 1: first name 2: last name */
						_x( '%1$s %2$s', 'full name', 'storeengine' ),
						$args['first_name'],
						$args['last_name']
					),
					'{company}'          => $args['company'],
					'{address_1}'        => $args['address_1'],
					'{address_2}'        => $args['address_2'],
					'{city}'             => $args['city'],
					'{state}'            => $full_state,
					'{postcode}'         => $args['postcode'],
					'{country}'          => $full_country,
					'{first_name_upper}' => Formatting::strtoupper( $args['first_name'] ),
					'{last_name_upper}'  => Formatting::strtoupper( $args['last_name'] ),
					'{name_upper}'       => Formatting::strtoupper(
						sprintf(
						/* translators: 1: first name 2: last name */
							_x( '%1$s %2$s', 'full name', 'storeengine' ),
							$args['first_name'],
							$args['last_name']
						)
					),
					'{company_upper}'    => Formatting::strtoupper( $args['company'] ),
					'{address_1_upper}'  => Formatting::strtoupper( $args['address_1'] ),
					'{address_2_upper}'  => Formatting::strtoupper( $args['address_2'] ),
					'{city_upper}'       => Formatting::strtoupper( $args['city'] ),
					'{state_upper}'      => Formatting::strtoupper( $full_state ),
					'{state_code}'       => Formatting::strtoupper( $state ),
					'{postcode_upper}'   => Formatting::strtoupper( $args['postcode'] ),
					'{country_upper}'    => Formatting::strtoupper( $full_country ),
				],
				$args
			)
		);

		$formatted_address = str_replace( array_keys( $replace ), $replace, $format );

		// Clean up white space.
		$formatted_address = preg_replace( '/  +/', ' ', trim( $formatted_address ) );
		$formatted_address = preg_replace( '/\n\n+/', "\n", $formatted_address );

		// Break newlines apart and remove empty lines/trim commas and white space.
		$formatted_address = explode( "\n", $formatted_address );
		$formatted_address = array_filter( array_map( [ $this, 'trim_formatted_address_line' ], $formatted_address ) );

		// Add html breaks.
		// We're done!
		return implode( $separator, $formatted_address );
	}

	/**
	 * Trim white space and commas off a line.
	 *
	 * @param string $line Line.
	 *
	 * @return string
	 */
	private function trim_formatted_address_line( string $line ): string {
		return trim( $line, ', ' );
	}

	/**
	 * Returns the fields we show by default. This can be filtered later on.
	 *
	 * @return array
	 */
	public function get_default_address_fields(): array {
		$address_2_label = __( 'Apartment, suite, unit, etc.', 'storeengine' );

		// If necessary, append '(optional)' to the placeholder: we don't need to worry about the
		// label, though, as storeengine/form_field() takes care of that.
		if ( 'optional' === Helper::get_settings( 'checkout_address_2_field', 'optional' ) ) {
			$address_2_placeholder = __( 'Apartment, suite, unit, etc. (optional)', 'storeengine' );
		} else {
			$address_2_placeholder = $address_2_label;
		}

		$fields = [
			'first_name' => [
				'label'        => __( 'First name', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-6' ],
				'autocomplete' => 'given-name',
				'priority'     => 10,
			],
			'last_name'  => [
				'label'        => __( 'Last name', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-6' ],
				'autocomplete' => 'family-name',
				'priority'     => 20,
			],
			'company'    => [
				'label'        => __( 'Company name', 'storeengine' ),
				'class'        => [ 'storeengine-col-12' ],
				'autocomplete' => 'organization',
				'priority'     => 30,
				'required'     => 'required' === Helper::get_settings( 'checkout_company_field', 'optional' ),
			],
			'country'    => [
				'type'         => 'country',
				'label'        => __( 'Country / Region', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-12', 'address-field', 'update_totals_on_change' ],
				'autocomplete' => 'country',
				'priority'     => 40,
			],
			'address_1'  => [
				'label'        => __( 'Street address', 'storeengine' ),
				/* translators: use local order of street name and house number. */
				'placeholder'  => esc_attr__( 'House number and street name', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-12', 'address-field' ],
				'autocomplete' => 'address-line1',
				'priority'     => 50,
			],
			'address_2'  => [
				'label'        => $address_2_label,
				'label_class'  => [ 'screen-reader-text' ],
				'placeholder'  => esc_attr( $address_2_placeholder ),
				'class'        => [ 'storeengine-col-12', 'address-field' ],
				'autocomplete' => 'address-line2',
				'priority'     => 60,
				'required'     => 'required' === Helper::get_settings( 'checkout_address_2_field', 'optional' ),
			],
			'city'       => [
				'label'        => __( 'Town / City', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-12', 'address-field' ],
				'autocomplete' => 'address-level2',
				'priority'     => 70,
			],
			'state'      => [
				'type'         => 'state',
				'label'        => __( 'State / County', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-12', 'address-field' ],
				'validate'     => [ 'state' ],
				'autocomplete' => 'address-level1',
				'priority'     => 80,
			],
			'postcode'   => [
				'label'        => __( 'Postcode / ZIP', 'storeengine' ),
				'required'     => true,
				'class'        => [ 'storeengine-col-12', 'address-field' ],
				'validate'     => [ 'postcode' ],
				'autocomplete' => 'postal-code',
				'priority'     => 90,
			],
			'phone'      => [
				'label'        => __( 'Phone', 'storeengine' ),
				'required'     => false,
				'type'         => 'tel',
				'class'        => [ 'storeengine-col-12' ],
				'validate'     => [ 'phone' ],
				'autocomplete' => 'tel',
				'priority'     => 100,
			],
			'email'      => [
				'label'        => __( 'Email address', 'storeengine' ),
				'required'     => false,
				'type'         => 'email',
				'class'        => [ 'storeengine-col-12' ],
				'validate'     => [ 'email' ],
				'autocomplete' => 'email',
				'priority'     => 110,
			],
		];

		if ( 'hidden' === Helper::get_settings( 'checkout_company_field', 'optional' ) ) {
			unset( $fields['company'] );
		}

		if ( 'hidden' === Helper::get_settings( 'checkout_address_2_field', 'optional' ) ) {
			unset( $fields['address_2'] );
		}

		$default_address_fields = apply_filters( 'storeengine/default_address_fields', $fields );

		// Sort each of the fields based on priority.
		uasort( $default_address_fields, [ Helper::class, 'checkout_fields_uasort_comparison' ] );

		return $default_address_fields;
	}

	/**
	 * Get JS selectors for fields which are shown/hidden depending on the locale.
	 *
	 * @return array
	 */
	public function get_country_locale_field_selectors(): array {
		return apply_filters( 'storeengine/country_locale_field_selectors', [
			'address_1' => '#billing_address_1_field, #shipping_address_1_field',
			'address_2' => '#billing_address_2_field, #shipping_address_2_field',
			'state'     => '#billing_state_field, #shipping_state_field, #calc_shipping_state_field',
			'postcode'  => '#billing_postcode_field, #shipping_postcode_field, #calc_shipping_postcode_field',
			'city'      => '#billing_city_field, #shipping_city_field, #calc_shipping_city_field',
		] );
	}

	/**
	 * Get country locale settings.
	 *
	 * These locales override the default country selections after a country is chosen.
	 *
	 * @return array
	 */
	public function get_country_locale(): array {
		if ( empty( $this->locale ) ) {
			$this->locale = apply_filters(
				'storeengine/get_country_locale',
				[
					'AE' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'required' => false,
						],
					],
					'AF' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'AL' => [
						'state' => [
							'label' => __( 'County', 'storeengine' ),
						],
					],
					'AO' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'AT' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'AU' => [
						'city'     => [
							'label' => __( 'Suburb', 'storeengine' ),
						],
						'postcode' => [
							'label' => __( 'Postcode', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'State', 'storeengine' ),
						],
					],
					'AX' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'BA' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'label'    => __( 'Canton', 'storeengine' ),
							'required' => false,
							'hidden'   => true,
						],
					],
					'BD' => [
						'postcode' => [
							'required' => false,
						],
						'state'    => [
							'label' => __( 'District', 'storeengine' ),
						],
					],
					'BE' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'BG' => [
						'state' => [
							'required' => false,
						],
					],
					'BH' => [
						'postcode' => [
							'required' => false,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'BI' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'BO' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'BS' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'BZ' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'required' => false,
						],
					],
					'CA' => [
						'postcode' => [
							'label' => __( 'Postal code', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'CH' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'label'    => __( 'Canton', 'storeengine' ),
							'required' => false,
						],
					],
					'CL' => [
						'city'     => [
							'required' => true,
						],
						'postcode' => [
							'required' => false,
							// Hidden for stores within Chile. @see https://github.com/woocommerce/woocommerce/issues/36546.
							'hidden'   => 'CL' === $this->get_base_country(),
						],
						'state'    => [
							'label' => __( 'Region', 'storeengine' ),
						],
					],
					'CN' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'CO' => [
						'postcode' => [
							'required' => false,
						],
						'state'    => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'CR' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'CW' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'required' => false,
						],
					],
					'CY' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'CZ' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'DE' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
						],
					],
					'DK' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'DO' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'EC' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'EE' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'ET' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'FI' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'FR' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'GG' => [
						'state' => [
							'required' => false,
							'label'    => __( 'Parish', 'storeengine' ),
						],
					],
					'GH' => [
						'postcode' => [
							'required' => false,
						],
						'state'    => [
							'label' => __( 'Region', 'storeengine' ),
						],
					],
					'GP' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'GF' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'GR' => [
						'state' => [
							'required' => false,
						],
					],
					'GT' => [
						'postcode' => [
							'required' => false,
						],
						'state'    => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'HK' => [
						'postcode' => [
							'required' => false,
						],
						'city'     => [
							'label' => __( 'Town / District', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'Region', 'storeengine' ),
						],
					],
					'HN' => [
						'state' => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'HU' => [
						'last_name'  => [
							'class'    => [ 'storeengine-col-6' ],
							'priority' => 10,
						],
						'first_name' => [
							'class'    => [ 'storeengine-col-6' ],
							'priority' => 20,
						],
						'postcode'   => [
							'class'    => [ 'storeengine-col-6', 'address-field' ],
							'priority' => 65,
						],
						'city'       => [
							'class' => [ 'storeengine-col-6', 'address-field' ],
						],
						'address_1'  => [
							'priority' => 71,
						],
						'address_2'  => [
							'priority' => 72,
						],
						'state'      => [
							'label'    => __( 'County', 'storeengine' ),
							'required' => false,
						],
					],
					'ID' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'IE' => [
						'postcode' => [
							'required' => false,
							'label'    => __( 'Eircode', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'County', 'storeengine' ),
						],
					],
					'IS' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'IL' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'IM' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'IN' => [
						'postcode' => [
							'label' => __( 'PIN Code', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'State', 'storeengine' ),
						],
					],
					'IR' => [
						'state'     => [
							'priority' => 50,
						],
						'city'      => [
							'priority' => 60,
						],
						'address_1' => [
							'priority' => 70,
						],
						'address_2' => [
							'priority' => 80,
						],
					],
					'IT' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => true,
							'label'    => __( 'Province', 'storeengine' ),
						],
					],
					'JM' => [
						'city'     => [
							'label' => __( 'Town / City / Post Office', 'storeengine' ),
						],
						'postcode' => [
							'required' => false,
							'label'    => __( 'Postal Code', 'storeengine' ),
						],
						'state'    => [
							'required' => true,
							'label'    => __( 'Parish', 'storeengine' ),
						],
					],
					'JP' => [
						'last_name'  => [
							'class'    => [ 'storeengine-col-6' ],
							'priority' => 10,
						],
						'first_name' => [
							'class'    => [ 'storeengine-col-6' ],
							'priority' => 20,
						],
						'postcode'   => [
							'class'    => [ 'storeengine-col-6', 'address-field' ],
							'priority' => 65,
						],
						'state'      => [
							'label'    => __( 'Prefecture', 'storeengine' ),
							'class'    => [ 'storeengine-col-6', 'address-field' ],
							'priority' => 66,
						],
						'city'       => [
							'priority' => 67,
						],
						'address_1'  => [
							'priority' => 68,
						],
						'address_2'  => [
							'priority' => 69,
						],
					],
					'KN' => [
						'postcode' => [
							'required' => false,
							'label'    => __( 'Postal code', 'storeengine' ),
						],
						'state'    => [
							'required' => true,
							'label'    => __( 'Parish', 'storeengine' ),
						],
					],
					'KR' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'KW' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'LV' => [
						'state' => [
							'label'    => __( 'Municipality', 'storeengine' ),
							'required' => false,
						],
					],
					'LB' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'MF' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'MQ' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'MT' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'MZ' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'NI' => [
						'state' => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'NL' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'NG' => [
						'postcode' => [
							'label'    => __( 'Postcode', 'storeengine' ),
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'label' => __( 'State', 'storeengine' ),
						],
					],
					'NZ' => [
						'postcode' => [
							'label' => __( 'Postcode', 'storeengine' ),
						],
						'state'    => [
							'required' => false,
							'label'    => __( 'Region', 'storeengine' ),
						],
					],
					'NO' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'NP' => [
						'state'    => [
							'label' => __( 'State / Zone', 'storeengine' ),
						],
						'postcode' => [
							'required' => false,
						],
					],
					'PA' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'PL' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'PR' => [
						'city'  => [
							'label' => __( 'Municipality', 'storeengine' ),
						],
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'PT' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'PY' => [
						'state' => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'RE' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'RO' => [
						'state' => [
							'label'    => __( 'County', 'storeengine' ),
							'required' => true,
						],
					],
					'RS' => [
						'city'     => [
							'required' => true,
						],
						'postcode' => [
							'required' => true,
						],
						'state'    => [
							'label'    => __( 'District', 'storeengine' ),
							'required' => false,
						],
					],
					'RW' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'SG' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
						'city'  => [
							'required' => false,
						],
					],
					'SK' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'SI' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'SR' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'SV' => [
						'state' => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'ES' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'LI' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'LK' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'LU' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'MD' => [
						'state' => [
							'label' => __( 'Municipality / District', 'storeengine' ),
						],
					],
					'SE' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'TR' => [
						'postcode' => [
							'priority' => 65,
						],
						'state'    => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'UG' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'city'     => [
							'label'    => __( 'Town / Village', 'storeengine' ),
							'required' => true,
						],
						'state'    => [
							'label'    => __( 'District', 'storeengine' ),
							'required' => true,
						],
					],
					'US' => [
						'postcode' => [
							'label' => __( 'ZIP Code', 'storeengine' ),
						],
						'state'    => [
							'label' => __( 'State', 'storeengine' ),
						],
					],
					'UY' => [
						'state' => [
							'label' => __( 'Department', 'storeengine' ),
						],
					],
					'GB' => [
						'postcode' => [
							'label' => __( 'Postcode', 'storeengine' ),
						],
						'state'    => [
							'label'    => __( 'County', 'storeengine' ),
							'required' => false,
						],
					],
					'ST' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
						'state'    => [
							'label' => __( 'District', 'storeengine' ),
						],
					],
					'VN' => [
						'state'     => [
							'required' => false,
							'hidden'   => true,
						],
						'postcode'  => [
							'priority' => 65,
							'required' => false,
							'hidden'   => false,
						],
						'address_2' => [
							'required' => false,
							'hidden'   => false,
						],
					],
					'WS' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'YT' => [
						'state' => [
							'required' => false,
							'hidden'   => true,
						],
					],
					'ZA' => [
						'state' => [
							'label' => __( 'Province', 'storeengine' ),
						],
					],
					'ZW' => [
						'postcode' => [
							'required' => false,
							'hidden'   => true,
						],
					],
				]
			);

			$this->locale = array_intersect_key( $this->locale, array_merge( $this->get_allowed_countries(), $this->get_shipping_countries() ) );

			// Default Locale Can be filtered to override fields in get_address_fields(). Countries with no specific locale will use default.
			$this->locale['default'] = apply_filters( 'storeengine/get_country_locale_default', $this->get_default_address_fields() );

			// Filter default AND shop base locales to allow overrides via a single function. These will be used when changing countries on the checkout.
			if ( ! isset( $this->locale[ $this->get_base_country() ] ) ) {
				$this->locale[ $this->get_base_country() ] = $this->locale['default'];
			}

			$this->locale['default']                   = apply_filters( 'storeengine/get_country_locale_base', $this->locale['default'] );
			$this->locale[ $this->get_base_country() ] = apply_filters( 'storeengine/get_country_locale_base', $this->locale[ $this->get_base_country() ] );
		}

		return $this->locale;
	}

	/**
	 * Apply locale and get address fields.
	 *
	 * @param string $country Country.
	 * @param string $type Address type, defaults to 'billing_'.
	 *
	 * @return array
	 */
	public function get_address_fields( string $country = '', string $type = 'billing_' ): array {
		if ( ! $country ) {
			$country = $this->get_base_country();
		}

		$fields = $this->get_default_address_fields();
		$locale = $this->get_country_locale();

		if ( isset( $locale[ $country ] ) ) {
			$fields = Helper::array_overlay( $fields, $locale[ $country ] );
		}

		// Prepend field keys.
		$address_fields = [];

		foreach ( $fields as $key => $value ) {
			if ( 'state' === $key ) {
				$value['country_field'] = $type . 'country';
				$value['country']       = $country;
			}
			$address_fields[ $type . $key ] = $value;
		}

		// Add email and phone fields.
		if ( 'billing_' === $type ) {
			$address_fields['billing_phone']['required'] = 'required' === Helper::get_settings( 'checkout_phone_field', 'required' );
			$address_fields['billing_email']['required'] = true;
		}

		/**
		 * Important note on this filter: Changes to address fields can and will be overridden by
		 * the storeengine/default_address_fields. The locales/default locales apply on top based
		 * on country selection. If you want to change things like the required status of an
		 * address field, filter storeengine/default_address_fields instead.
		 */
		$address_fields = apply_filters( 'storeengine/' . $type . 'fields', $address_fields, $country );

		// Sort each of the fields based on priority.
		uasort( $address_fields, [ Helper::class, 'checkout_fields_uasort_comparison' ] );

		return $address_fields;
	}
}
