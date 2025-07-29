<?php
/**
 * Units
 *
 * Returns a multidimensional array of measurement units and their labels.
 * Unit labels should be defined in English and translated native through localization files.
 *
 * @package StoreEngine\i18n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'weight'     => [
		'kg'  => __( 'kg', 'storeengine' ),
		'g'   => __( 'g', 'storeengine' ),
		'lbs' => __( 'lbs', 'storeengine' ),
		'oz'  => __( 'oz', 'storeengine' ),
	],
	'dimensions' => [
		'm'  => __( 'm', 'storeengine' ),
		'cm' => __( 'cm', 'storeengine' ),
		'mm' => __( 'mm', 'storeengine' ),
		'in' => __( 'in', 'storeengine' ),
		'yd' => __( 'yd', 'storeengine' ),
	],
];
