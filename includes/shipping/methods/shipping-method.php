<?php
/**
 * Abstract shipping method.
 *
 * Extended by shipping methods to handle shipping calculations etc.
 *
 * @package StoreEngine
 */

namespace StoreEngine\Shipping\Methods;

use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Classes\Tax;
use StoreEngine\Shipping\ShippingRate;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Shipping_Method
 */
abstract class ShippingMethod extends AbstractEntity {

	protected string $table = 'storeengine_shipping_zone_methods';

	/**
	 * Method title.
	 *
	 * @var string
	 */
	protected string $method_title = '';

	/**
	 * Method description.
	 *
	 * @var string
	 */
	protected string $method_description = '';

	/**
	 * Features this method supports. Possible features used by core:
	 * - shipping-zones Shipping zone functionality + instances
	 * - instance-settings Instance settings screens.
	 * - settings Non-instance settings screens. Enabled by default for BW compatibility with methods before instances existed.
	 *
	 * @var array
	 */
	public array $supports = [ 'settings' ];

	/**
	 * This is an array of rates - methods must populate this array to register shipping costs.
	 *
	 * @var array
	 */
	public array $rates = [];

	protected array $data = [
		'zone_id'      => 0,
		'method_id'    => '',
		'name'         => '',
		'description'  => '',
		'method_order' => 0,
		'is_enabled'   => 1,
	];

	protected array $settings = [
		'tax_status' => ProductTaxStatus::TAXABLE,
	];

	/**
	 * Admin field schema.
	 *
	 * @var array
	 */
	protected array $admin_fields = [];

	public function __construct( $read = 0 ) {
		$this->data = array_merge( $this->data, $this->settings );
		parent::__construct( $read );
	}

	/**
	 * Check if a shipping method supports a given feature.
	 *
	 * Methods should override this to declare support (or lack of support) for a feature.
	 *
	 * @param string $feature The name of a feature to test support for.
	 *
	 * @return bool True if the shipping method supports the feature, false otherwise.
	 */
	public function supports( string $feature ): bool {
		return apply_filters(
			'storeengine/shipping/method_supports',
			in_array( $feature, $this->supports, true ), $feature, $this
		);
	}

	/**
	 * Read DB Record.
	 *
	 * @return array
	 * @throws StoreEngineException
	 */
	protected function read_data(): array {
		$data = parent::read_data();

		if ( isset( $data['settings'] ) ) {
			$settings = maybe_unserialize( $data['settings'] );
			unset( $data['settings'] );
			$data = array_merge( $data, $settings );
		}

		return $data;
	}

	protected function prepare_for_db( string $context = 'create' ): array {
		$data           = [];
		$settings       = [];
		$settings_props = array_keys( $this->settings );
		$format         = [];

		// @XXX maybe just get data form change set when context is 'create'
		$raw_data = array_merge( $this->settings, $this->get_data(), $this->get_changes() );

		if ( empty( $raw_data ) && 'create' === $context ) {
			$raw_data = $this->get_data(); // get defaults.
		}

		foreach ( $raw_data as $key => $value ) {
			if ( in_array( $key, $settings_props, true ) ) {
				$settings[ $key ] = $value;
				continue;
			}

			if ( 'method_id' === $key ) {
				$value = $this->get_type( 'edit' );
			}

			if ( 'update' === $context && ( str_contains( 'date_created', $key ) || str_contains( 'created_at', $key ) ) ) {
				continue;
			}

			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $key );
			}

			$format[]     = $this->predict_format( $key, $value );
			$data[ $key ] = $value;
		}

		$data['settings'] = maybe_serialize( $settings );
		$format[]         = '%s';

		// Not using any filter for handling format.
		// If necessary update format for column in $wpdb::$field_types.

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	public function save() {
		Caching::get_transient_version('shipping', true);
		return parent::save();
	}

	public function delete( bool $force_delete = true ): bool {
		Caching::get_transient_version('shipping', true);
		return parent::delete( $force_delete );
	}

	/**
	 * Return calculated rates for a package.
	 *
	 * @param array $package Package array.
	 *
	 * @return array
	 */
	public function get_rates_for_package( array $package ): array {
		$this->rates = [];
		if ( $this->is_available( $package ) ) {
			$this->calculate_shipping( $package );
		}

		return $this->rates;
	}

	public function get_admin_fields(): array {
		return $this->admin_fields;
	}

	/**
	 * @param string $context
	 *
	 * @return string
	 */
	public function get_tax_status( string $context = 'view' ): string {
		return $this->get_prop( 'tax_status', $context );
	}

	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 *
	 * @param array $package Package array.
	 */
	public function calculate_shipping( array $package = [] ) {
	}

	/**
	 * Whether we need to calculate tax on top of the shipping rate.
	 *
	 * @return boolean
	 */
	public function is_taxable(): bool {
		return Helper::get_settings( 'enable_product_tax' ) && ProductTaxStatus::TAXABLE === $this->get_tax_status();
	}

	/**
	 * Add a shipping rate. If taxes are not set they will be calculated based on cost.
	 *
	 * @param array $args Arguments (default: array()).
	 */
	public function add_rate( array $args = array() ) {
		$args = apply_filters(
			'storeengine/shipping/add_rate_args',
			wp_parse_args(
				$args,
				[
					'id'             => $this->get_rate_id(),
					// ID for the rate. If not passed, this id:instance default will be used.
					'label'          => '',
					// Label for the rate.
					'cost'           => '0',
					// Amount or array of costs (per item shipping).
					'taxes'          => '',
					// Pass taxes, or leave empty to have it calculated for you, or 'false' to disable calculations.
					'calc_tax'       => 'per_order',
					// Calc tax per_order or per_item. Per item needs an array of costs.
					'meta_data'      => [],
					// Array of misc metadata to store along with this rate - key value pairs.
					'package'        => false,
					// Package array this rate was generated for @since 2.6.0.
					'price_decimals' => Formatting::get_price_decimals(),
				]
			),
			$this
		);

		// ID and label are required.
		if ( ! $args['id'] || ! $args['label'] ) {
			return;
		}

		// Total up the cost.
		$total_cost = is_array( $args['cost'] ) ? array_sum( $args['cost'] ) : $args['cost'];
		$taxes      = $args['taxes'];

		// Taxes - if not an array and not set to false, calc tax based on cost and passed calc_tax variable. This saves shipping methods having to do complex tax calculations.
		if ( ! is_array( $taxes ) && false !== $taxes && $total_cost > 0 && $this->is_taxable() ) {
			$taxes = 'per_item' === $args['calc_tax'] ? $this->get_taxes_per_item( $args['cost'] ) : Tax::calc_shipping_tax( $total_cost, Tax::get_shipping_tax_rates() );
		}

		// Round the total cost after taxes have been calculated.
		$total_cost = Formatting::format_decimal( $total_cost, $args['price_decimals'] );

		// Create rate object.
		$rate = new ShippingRate();
		$rate->set_id( $args['id'] );
		$rate->set_method_id( $this->get_method_id() );
		$rate->set_instance_id( $this->get_instance_id() );
		$rate->set_label( $args['label'] );
		$rate->set_cost( $total_cost );
		$rate->set_taxes( $taxes );
		$rate->set_tax_status( $this->get_tax_status() );

		if ( ! empty( $args['meta_data'] ) ) {
			foreach ( $args['meta_data'] as $key => $value ) {
				$rate->add_meta_data( $key, $value );
			}
		}

		// Store package data.
		if ( $args['package'] ) {
			$items_in_package = array();
			foreach ( $args['package']['contents'] as $item ) {
				$items_in_package[] = $item->name . ' &times; ' . $item->quantity;
			}
			$rate->add_meta_data( __( 'Items', 'storeengine' ), implode( ', ', $items_in_package ) );
		}

		$this->rates[ $args['id'] ] = apply_filters( 'storeengine/shipping/add_rate', $rate, $args, $this );
	}

	/**
	 * Calc taxes per item being shipping in costs array.
	 *
	 * @param array $costs Costs.
	 *
	 * @return array of taxes
	 */
	protected function get_taxes_per_item( array $costs ): array {
		$taxes = [];

		$cart = Helper::cart();
		foreach ( $costs as $cost_key => $amount ) {
			if ( ! isset( $cart[ $cost_key ] ) ) {
				continue;
			}

			$item_taxes = Tax::calc_shipping_tax( $amount, Tax::get_shipping_tax_rates( $cart[ $cost_key ]['data']->get_tax_class() ) );

			// Sum the item taxes.
			foreach ( array_keys( $taxes + $item_taxes ) as $key ) {
				$taxes[ $key ] = ( isset( $item_taxes[ $key ] ) ? $item_taxes[ $key ] : 0 ) + ( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 );
			}
		}

		// Add any cost for the order - order costs are in the key 'order'.
		if ( isset( $costs['order'] ) ) {
			$item_taxes = Tax::calc_shipping_tax( $costs['order'], Tax::get_shipping_tax_rates() );

			// Sum the item taxes.
			foreach ( array_keys( $taxes + $item_taxes ) as $key ) {
				$taxes[ $key ] = ( isset( $item_taxes[ $key ] ) ? $item_taxes[ $key ] : 0 ) + ( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 );
			}
		}

		return $taxes;
	}

	/**
	 * Is this method available?
	 *
	 * @param array $package Package.
	 *
	 * @return bool
	 */
	public function is_available( array $package ): bool {
		return apply_filters( 'storeengine/shipping/' . $this->get_method_id() . '_is_available', true, $package, $this );
	}

	public function get_instance_id(): int {
		return $this->get_id();
	}

	public function get_zone_id( string $context = 'view' ) {
		return $this->get_prop( 'zone_id', $context );
	}

	/**
	 * Returns a rate ID based on this methods ID and instance, with an optional
	 * suffix if distinguishing between multiple rates.
	 *
	 * @param string $suffix Suffix.
	 *
	 * @return string
	 */
	public function get_rate_id( string $suffix = '' ): string {
		$rate_id = array( $this->get_method_id() );

		if ( $this->get_instance_id() ) {
			$rate_id[] = $this->get_instance_id();
		}

		if ( $suffix ) {
			$rate_id[] = $suffix;
		}

		return implode( ':', $rate_id );
	}

	public function get_method_title(): string {
		return $this->method_title;
	}

	public function get_method_description(): string {
		return $this->method_description;
	}

	/**
	 * @param string $context
	 *
	 * @return string
	 */
	public function get_method_id( string $context = 'view' ): string {
		return $this->get_prop( 'method_id', $context );
	}

	public function get_type( string $context = 'view' ): string {
		return $this->get_method_id( $context );
	}

	/**
	 * @param string $context
	 *
	 * @return string
	 */
	public function get_name( string $context = 'view' ) {
		return $this->get_prop( 'name', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return ?string
	 */
	public function get_description( string $context = 'view' ) {
		return $this->get_prop( 'description', $context );
	}

	/**
	 * @param string $context
	 *
	 * @return int
	 */
	public function get_method_order( string $context = 'view' ) {
		return $this->get_prop( 'method_order', $context );
	}

	public function get_settings(): array {
		return array_filter( $this->data, fn( $data_key ) => array_key_exists( $data_key, $this->settings ), ARRAY_FILTER_USE_KEY );
	}

	public function is_enabled( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'is_enabled', $context ) );
	}

	/**
	 * @param string|int $value
	 *
	 * @return void
	 */
	public function set_zone_id( $value ) {
		$this->set_prop( 'zone_id', absint( $value ) );
	}

	public function set_method_id( string $value ) {
		$this->set_prop( 'method_id', $value );
	}

	public function set_name( string $value ) {
		$this->set_prop( 'name', $value );
	}

	public function set_description( ?string $value = null ) {
		$this->set_prop( 'description', $value );
	}

	/**
	 * @param int|string $value
	 *
	 * @return void
	 */
	public function set_method_order( $value ) {
		$this->set_prop( 'method_order', absint( $value ) );
	}

	/**
	 * @param string|int|bool $value
	 *
	 * @return void
	 */
	public function set_is_enabled( $value ) {
		$this->set_prop( 'is_enabled', Formatting::string_to_bool( $value ) );
	}

	public function set_tax_status( $value ) {
		$this->set_prop( 'tax_status', $value );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function handle_save_request( array $payload ) {
		if ( ! isset( $payload['zone_id'], $payload['method_order'], $payload['name'] ) ) {
			throw new StoreEngineException( 'Zone ID or Method Order or Name is missing.' );
		}

		$this->set_zone_id( absint( $payload['zone_id'] ) );
		$this->set_name( sanitize_text_field( $payload['name'] ) );
		$this->set_description( isset( $payload['description'] ) ? sanitize_text_field( $payload['description'] ) : null );
		$this->set_tax_status( isset( $payload['tax_status'] ) ? sanitize_text_field( $payload['tax_status'] ) : ProductTaxStatus::TAXABLE );
		$this->set_method_order( absint( $payload['method_order'] ) );
		$this->set_is_enabled( (bool) ( $payload['is_enabled'] ?? false ) );
		$this->save();

		do_action_ref_array( 'storeengine/shipping/' . $this->get_method_id() . '_save_settings', [ &$this ] );
	}
}

// End of file shipping-method.php.
