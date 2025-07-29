<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Utils\Formatting;

trait Currency {

	private static array $currency_symbols = [
		'AED' => '&#x62f;.&#x625;',
		'AFN' => '&#x60b;',
		'ALL' => 'L',
		'AMD' => 'AMD',
		'ANG' => '&fnof;',
		'AOA' => 'Kz',
		'ARS' => '&#36;',
		'AUD' => '&#36;',
		'AWG' => 'Afl.',
		'AZN' => '&#8380;',
		'BAM' => 'KM',
		'BBD' => '&#36;',
		'BDT' => '&#2547;&nbsp;',
		'BGN' => '&#1083;&#1074;.',
		'BHD' => '.&#x62f;.&#x628;',
		'BIF' => 'Fr',
		'BMD' => '&#36;',
		'BND' => '&#36;',
		'BOB' => 'Bs.',
		'BRL' => '&#82;&#36;',
		'BSD' => '&#36;',
		'BTC' => '&#3647;',
		'BTN' => 'Nu.',
		'BWP' => 'P',
		'BYR' => 'Br',
		'BYN' => 'Br',
		'BZD' => '&#36;',
		'CAD' => '&#36;',
		'CDF' => 'Fr',
		'CHF' => '&#67;&#72;&#70;',
		'CLP' => '&#36;',
		'CNY' => '&yen;',
		'COP' => '&#36;',
		'CRC' => '&#x20a1;',
		'CUC' => '&#36;',
		'CUP' => '&#36;',
		'CVE' => '&#36;',
		'CZK' => '&#75;&#269;',
		'DJF' => 'Fr',
		'DKK' => 'kr.',
		'DOP' => 'RD&#36;',
		'DZD' => '&#x62f;.&#x62c;',
		'EGP' => 'EGP',
		'ERN' => 'Nfk',
		'ETB' => 'Br',
		'EUR' => '&euro;',
		'FJD' => '&#36;',
		'FKP' => '&pound;',
		'GBP' => '&pound;',
		'GEL' => '&#x20be;',
		'GGP' => '&pound;',
		'GHS' => '&#x20b5;',
		'GIP' => '&pound;',
		'GMD' => 'D',
		'GNF' => 'Fr',
		'GTQ' => 'Q',
		'GYD' => '&#36;',
		'HKD' => '&#36;',
		'HNL' => 'L',
		'HRK' => 'kn',
		'HTG' => 'G',
		'HUF' => '&#70;&#116;',
		'IDR' => 'Rp',
		'ILS' => '&#8362;',
		'IMP' => '&pound;',
		'INR' => '&#8377;',
		'IQD' => '&#x62f;.&#x639;',
		'IRR' => '&#xfdfc;',
		'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
		'ISK' => 'kr.',
		'JEP' => '&pound;',
		'JMD' => '&#36;',
		'JOD' => '&#x62f;.&#x627;',
		'JPY' => '&yen;',
		'KES' => 'KSh',
		'KGS' => '&#x441;&#x43e;&#x43c;',
		'KHR' => '&#x17db;',
		'KMF' => 'Fr',
		'KPW' => '&#x20a9;',
		'KRW' => '&#8361;',
		'KWD' => '&#x62f;.&#x643;',
		'KYD' => '&#36;',
		'KZT' => '&#8376;',
		'LAK' => '&#8365;',
		'LBP' => '&#x644;.&#x644;',
		'LKR' => '&#xdbb;&#xdd4;',
		'LRD' => '&#36;',
		'LSL' => 'L',
		'LYD' => '&#x62f;.&#x644;',
		'MAD' => '&#x62f;.&#x645;.',
		'MDL' => 'MDL',
		'MGA' => 'Ar',
		'MKD' => '&#x434;&#x435;&#x43d;',
		'MMK' => 'Ks',
		'MNT' => '&#x20ae;',
		'MOP' => 'P',
		'MRU' => 'UM',
		'MUR' => '&#x20a8;',
		'MVR' => '.&#x783;',
		'MWK' => 'MK',
		'MXN' => '&#36;',
		'MYR' => '&#82;&#77;',
		'MZN' => 'MT',
		'NAD' => 'N&#36;',
		'NGN' => '&#8358;',
		'NIO' => 'C&#36;',
		'NOK' => '&#107;&#114;',
		'NPR' => '&#8360;',
		'NZD' => '&#36;',
		'OMR' => '&#x631;.&#x639;.',
		'PAB' => 'B/.',
		'PEN' => 'S/',
		'PGK' => 'K',
		'PHP' => '&#8369;',
		'PKR' => '&#8360;',
		'PLN' => '&#122;&#322;',
		'PRB' => '&#x440;.',
		'PYG' => '&#8370;',
		'QAR' => '&#x631;.&#x642;',
		'RMB' => '&yen;',
		'RON' => 'lei',
		'RSD' => '&#1088;&#1089;&#1076;',
		'RUB' => '&#8381;',
		'RWF' => 'Fr',
		'SAR' => '&#x631;.&#x633;',
		'SBD' => '&#36;',
		'SCR' => '&#x20a8;',
		'SDG' => '&#x62c;.&#x633;.',
		'SEK' => '&#107;&#114;',
		'SGD' => '&#36;',
		'SHP' => '&pound;',
		'SLL' => 'Le',
		'SOS' => 'Sh',
		'SRD' => '&#36;',
		'SSP' => '&pound;',
		'STN' => 'Db',
		'SYP' => '&#x644;.&#x633;',
		'SZL' => 'E',
		'THB' => '&#3647;',
		'TJS' => '&#x405;&#x41c;',
		'TMT' => 'm',
		'TND' => '&#x62f;.&#x62a;',
		'TOP' => 'T&#36;',
		'TRY' => '&#8378;',
		'TTD' => '&#36;',
		'TWD' => '&#78;&#84;&#36;',
		'TZS' => 'Sh',
		'UAH' => '&#8372;',
		'UGX' => 'UGX',
		'USD' => '&#36;',
		'UYU' => '&#36;',
		'UZS' => 'UZS',
		'VEF' => 'Bs F',
		'VES' => 'Bs.',
		'VND' => '&#8363;',
		'VUV' => 'Vt',
		'WST' => 'T',
		'XAF' => 'CFA',
		'XCD' => '&#36;',
		'XOF' => 'CFA',
		'XPF' => 'XPF',
		'YER' => '&#xfdfc;',
		'ZAR' => '&#82;',
		'ZMW' => 'ZK',
	];

	public static function get_currency_symbols(): array {
		return apply_filters( 'storeengine/currency_symbols', self::$currency_symbols );
	}

	/**
	 * Get Currency symbol.
	 *
	 * Currency symbols and names should follow the Unicode CLDR recommendation (https://cldr.unicode.org/translation/currency-names-and-symbols)
	 *
	 * @param string $currency Currency. (default: '').
	 * @return string
	 */
	public static function get_currency_symbol( string $currency = '' ): string {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		$symbols = self::get_currency_symbols();
		$symbol  = $currency ? ( $symbols[ $currency ] ?? '' ) : '';

		return apply_filters( 'storeengine/currency_symbol', $symbol, $currency );
	}

	/**
	 * Get full list of currency codes.
	 *
	 * Currency symbols and names should follow the Unicode CLDR recommendation (https://cldr.unicode.org/translation/currency-names-and-symbols)
	 *
	 * @return array
	 */
	public static function get_currencies(): array {
		static $currencies;

		if ( ! isset( $currencies ) ) {
			$currencies = array_unique( apply_filters( 'storeengine/currencies', [
				'AED' => __( 'United Arab Emirates dirham', 'storeengine' ),
				'AFN' => __( 'Afghan afghani', 'storeengine' ),
				'ALL' => __( 'Albanian lek', 'storeengine' ),
				'AMD' => __( 'Armenian dram', 'storeengine' ),
				'ANG' => __( 'Netherlands Antillean guilder', 'storeengine' ),
				'AOA' => __( 'Angolan kwanza', 'storeengine' ),
				'ARS' => __( 'Argentine peso', 'storeengine' ),
				'AUD' => __( 'Australian dollar', 'storeengine' ),
				'AWG' => __( 'Aruban florin', 'storeengine' ),
				'AZN' => __( 'Azerbaijani manat', 'storeengine' ),
				'BAM' => __( 'Bosnia and Herzegovina convertible mark', 'storeengine' ),
				'BBD' => __( 'Barbadian dollar', 'storeengine' ),
				'BDT' => __( 'Bangladeshi taka', 'storeengine' ),
				'BGN' => __( 'Bulgarian lev', 'storeengine' ),
				'BHD' => __( 'Bahraini dinar', 'storeengine' ),
				'BIF' => __( 'Burundian franc', 'storeengine' ),
				'BMD' => __( 'Bermudian dollar', 'storeengine' ),
				'BND' => __( 'Brunei dollar', 'storeengine' ),
				'BOB' => __( 'Bolivian boliviano', 'storeengine' ),
				'BRL' => __( 'Brazilian real', 'storeengine' ),
				'BSD' => __( 'Bahamian dollar', 'storeengine' ),
				'BTC' => __( 'Bitcoin', 'storeengine' ),
				'BTN' => __( 'Bhutanese ngultrum', 'storeengine' ),
				'BWP' => __( 'Botswana pula', 'storeengine' ),
				'BYR' => __( 'Belarusian ruble (old)', 'storeengine' ),
				'BYN' => __( 'Belarusian ruble', 'storeengine' ),
				'BZD' => __( 'Belize dollar', 'storeengine' ),
				'CAD' => __( 'Canadian dollar', 'storeengine' ),
				'CDF' => __( 'Congolese franc', 'storeengine' ),
				'CHF' => __( 'Swiss franc', 'storeengine' ),
				'CLP' => __( 'Chilean peso', 'storeengine' ),
				'CNY' => __( 'Chinese yuan', 'storeengine' ),
				'COP' => __( 'Colombian peso', 'storeengine' ),
				'CRC' => __( 'Costa Rican col&oacute;n', 'storeengine' ),
				'CUC' => __( 'Cuban convertible peso', 'storeengine' ),
				'CUP' => __( 'Cuban peso', 'storeengine' ),
				'CVE' => __( 'Cape Verdean escudo', 'storeengine' ),
				'CZK' => __( 'Czech koruna', 'storeengine' ),
				'DJF' => __( 'Djiboutian franc', 'storeengine' ),
				'DKK' => __( 'Danish krone', 'storeengine' ),
				'DOP' => __( 'Dominican peso', 'storeengine' ),
				'DZD' => __( 'Algerian dinar', 'storeengine' ),
				'EGP' => __( 'Egyptian pound', 'storeengine' ),
				'ERN' => __( 'Eritrean nakfa', 'storeengine' ),
				'ETB' => __( 'Ethiopian birr', 'storeengine' ),
				'EUR' => __( 'Euro', 'storeengine' ),
				'FJD' => __( 'Fijian dollar', 'storeengine' ),
				'FKP' => __( 'Falkland Islands pound', 'storeengine' ),
				'GBP' => __( 'Pound sterling', 'storeengine' ),
				'GEL' => __( 'Georgian lari', 'storeengine' ),
				'GGP' => __( 'Guernsey pound', 'storeengine' ),
				'GHS' => __( 'Ghana cedi', 'storeengine' ),
				'GIP' => __( 'Gibraltar pound', 'storeengine' ),
				'GMD' => __( 'Gambian dalasi', 'storeengine' ),
				'GNF' => __( 'Guinean franc', 'storeengine' ),
				'GTQ' => __( 'Guatemalan quetzal', 'storeengine' ),
				'GYD' => __( 'Guyanese dollar', 'storeengine' ),
				'HKD' => __( 'Hong Kong dollar', 'storeengine' ),
				'HNL' => __( 'Honduran lempira', 'storeengine' ),
				'HRK' => __( 'Croatian kuna', 'storeengine' ),
				'HTG' => __( 'Haitian gourde', 'storeengine' ),
				'HUF' => __( 'Hungarian forint', 'storeengine' ),
				'IDR' => __( 'Indonesian rupiah', 'storeengine' ),
				'ILS' => __( 'Israeli new shekel', 'storeengine' ),
				'IMP' => __( 'Manx pound', 'storeengine' ),
				'INR' => __( 'Indian rupee', 'storeengine' ),
				'IQD' => __( 'Iraqi dinar', 'storeengine' ),
				'IRR' => __( 'Iranian rial', 'storeengine' ),
				'IRT' => __( 'Iranian toman', 'storeengine' ),
				'ISK' => __( 'Icelandic kr&oacute;na', 'storeengine' ),
				'JEP' => __( 'Jersey pound', 'storeengine' ),
				'JMD' => __( 'Jamaican dollar', 'storeengine' ),
				'JOD' => __( 'Jordanian dinar', 'storeengine' ),
				'JPY' => __( 'Japanese yen', 'storeengine' ),
				'KES' => __( 'Kenyan shilling', 'storeengine' ),
				'KGS' => __( 'Kyrgyzstani som', 'storeengine' ),
				'KHR' => __( 'Cambodian riel', 'storeengine' ),
				'KMF' => __( 'Comorian franc', 'storeengine' ),
				'KPW' => __( 'North Korean won', 'storeengine' ),
				'KRW' => __( 'South Korean won', 'storeengine' ),
				'KWD' => __( 'Kuwaiti dinar', 'storeengine' ),
				'KYD' => __( 'Cayman Islands dollar', 'storeengine' ),
				'KZT' => __( 'Kazakhstani tenge', 'storeengine' ),
				'LAK' => __( 'Lao kip', 'storeengine' ),
				'LBP' => __( 'Lebanese pound', 'storeengine' ),
				'LKR' => __( 'Sri Lankan rupee', 'storeengine' ),
				'LRD' => __( 'Liberian dollar', 'storeengine' ),
				'LSL' => __( 'Lesotho loti', 'storeengine' ),
				'LYD' => __( 'Libyan dinar', 'storeengine' ),
				'MAD' => __( 'Moroccan dirham', 'storeengine' ),
				'MDL' => __( 'Moldovan leu', 'storeengine' ),
				'MGA' => __( 'Malagasy ariary', 'storeengine' ),
				'MKD' => __( 'Macedonian denar', 'storeengine' ),
				'MMK' => __( 'Burmese kyat', 'storeengine' ),
				'MNT' => __( 'Mongolian t&ouml;gr&ouml;g', 'storeengine' ),
				'MOP' => __( 'Macanese pataca', 'storeengine' ),
				'MRU' => __( 'Mauritanian ouguiya', 'storeengine' ),
				'MUR' => __( 'Mauritian rupee', 'storeengine' ),
				'MVR' => __( 'Maldivian rufiyaa', 'storeengine' ),
				'MWK' => __( 'Malawian kwacha', 'storeengine' ),
				'MXN' => __( 'Mexican peso', 'storeengine' ),
				'MYR' => __( 'Malaysian ringgit', 'storeengine' ),
				'MZN' => __( 'Mozambican metical', 'storeengine' ),
				'NAD' => __( 'Namibian dollar', 'storeengine' ),
				'NGN' => __( 'Nigerian naira', 'storeengine' ),
				'NIO' => __( 'Nicaraguan c&oacute;rdoba', 'storeengine' ),
				'NOK' => __( 'Norwegian krone', 'storeengine' ),
				'NPR' => __( 'Nepalese rupee', 'storeengine' ),
				'NZD' => __( 'New Zealand dollar', 'storeengine' ),
				'OMR' => __( 'Omani rial', 'storeengine' ),
				'PAB' => __( 'Panamanian balboa', 'storeengine' ),
				'PEN' => __( 'Sol', 'storeengine' ),
				'PGK' => __( 'Papua New Guinean kina', 'storeengine' ),
				'PHP' => __( 'Philippine peso', 'storeengine' ),
				'PKR' => __( 'Pakistani rupee', 'storeengine' ),
				'PLN' => __( 'Polish z&#x142;oty', 'storeengine' ),
				'PRB' => __( 'Transnistrian ruble', 'storeengine' ),
				'PYG' => __( 'Paraguayan guaran&iacute;', 'storeengine' ),
				'QAR' => __( 'Qatari riyal', 'storeengine' ),
				'RON' => __( 'Romanian leu', 'storeengine' ),
				'RSD' => __( 'Serbian dinar', 'storeengine' ),
				'RUB' => __( 'Russian ruble', 'storeengine' ),
				'RWF' => __( 'Rwandan franc', 'storeengine' ),
				'SAR' => __( 'Saudi riyal', 'storeengine' ),
				'SBD' => __( 'Solomon Islands dollar', 'storeengine' ),
				'SCR' => __( 'Seychellois rupee', 'storeengine' ),
				'SDG' => __( 'Sudanese pound', 'storeengine' ),
				'SEK' => __( 'Swedish krona', 'storeengine' ),
				'SGD' => __( 'Singapore dollar', 'storeengine' ),
				'SHP' => __( 'Saint Helena pound', 'storeengine' ),
				'SLL' => __( 'Sierra Leonean leone', 'storeengine' ),
				'SOS' => __( 'Somali shilling', 'storeengine' ),
				'SRD' => __( 'Surinamese dollar', 'storeengine' ),
				'SSP' => __( 'South Sudanese pound', 'storeengine' ),
				'STN' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra', 'storeengine' ),
				'SYP' => __( 'Syrian pound', 'storeengine' ),
				'SZL' => __( 'Swazi lilangeni', 'storeengine' ),
				'THB' => __( 'Thai baht', 'storeengine' ),
				'TJS' => __( 'Tajikistani somoni', 'storeengine' ),
				'TMT' => __( 'Turkmenistan manat', 'storeengine' ),
				'TND' => __( 'Tunisian dinar', 'storeengine' ),
				'TOP' => __( 'Tongan pa&#x2bb;anga', 'storeengine' ),
				'TRY' => __( 'Turkish lira', 'storeengine' ),
				'TTD' => __( 'Trinidad and Tobago dollar', 'storeengine' ),
				'TWD' => __( 'New Taiwan dollar', 'storeengine' ),
				'TZS' => __( 'Tanzanian shilling', 'storeengine' ),
				'UAH' => __( 'Ukrainian hryvnia', 'storeengine' ),
				'UGX' => __( 'Ugandan shilling', 'storeengine' ),
				'USD' => __( 'United States (US) dollar', 'storeengine' ),
				'UYU' => __( 'Uruguayan peso', 'storeengine' ),
				'UZS' => __( 'Uzbekistani som', 'storeengine' ),
				'VEF' => __( 'Venezuelan bol&iacute;var (2008–2018)', 'storeengine' ),
				'VES' => __( 'Venezuelan bol&iacute;var', 'storeengine' ),
				'VND' => __( 'Vietnamese &#x111;&#x1ed3;ng', 'storeengine' ),
				'VUV' => __( 'Vanuatu vatu', 'storeengine' ),
				'WST' => __( 'Samoan t&#x101;l&#x101;', 'storeengine' ),
				'XAF' => __( 'Central African CFA franc', 'storeengine' ),
				'XCD' => __( 'East Caribbean dollar', 'storeengine' ),
				'XOF' => __( 'West African CFA franc', 'storeengine' ),
				'XPF' => __( 'CFP franc', 'storeengine' ),
				'YER' => __( 'Yemeni rial', 'storeengine' ),
				'ZAR' => __( 'South African rand', 'storeengine' ),
				'ZMW' => __( 'Zambian kwacha', 'storeengine' ),
			] ) );
		}

		return $currencies;
	}

	/**
	 * @return array
	 * @deprecated Use individual utility methods.
	 */
	public static function get_currency_options(): array {
		return apply_filters( 'storeengine/currency_options', [
			'currency'                    => Formatting::get_currency(),
			'currency_position'           => Formatting::get_currency_position(),
			'currency_thousand_separator' => Formatting::get_price_thousand_separator(),
			'currency_decimal_separator'  => Formatting::get_price_decimal_separator(),
			'currency_decimal_limit'      => Formatting::get_price_decimals(),
			'currency_symbol'             => self::get_currency_symbol( Formatting::get_currency() ),
		] );
	}

	/**
	 * Get Base Currency Code.
	 *
	 * @return string
	 *
	 * @deprecated
	 */
	public static function get_currency(): string {
		return Formatting::get_currency();
	}

	/**
	 * @param string|float|int $price
	 * @param array $args
	 *
	 * @deprecated
	 * @use \StoreEngine\Utils\Formatting::price()
	 *
	 * @return string
	 */
	public static function currency_format( $price, array $args = [] ): string {
		if ( ! isset( $price ) ) {
			return '';
		}
		$currency_options  = self::get_currency_options();
		$currency_symbol   = $currency_options['currency_symbol'];
		$currency_position = $currency_options['currency_position'];
		$price             = number_format(
			(float) $price,
			$currency_options['currency_decimal_limit'],
			$currency_options['currency_decimal_separator'],
			$currency_options['currency_thousand_separator']
		);

		return 'left' === $currency_position ? $currency_symbol . $price : $price . $currency_symbol;
	}
}
