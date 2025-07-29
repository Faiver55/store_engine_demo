<?php

namespace StoreEngine\Classes;

use DateInterval;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

class Price extends AbstractEntity {

	protected string $table       = 'storeengine_product_price';
	protected string $object_type = 'price';

	protected array $data = [
		'price_name'            => '',
		'price_type'            => 'onetime',
		'price'                 => 0.0,
		'compare_price'         => 0.0,
		'product_id'            => 0,
		'order'                 => 0,
		// Settings.
		'setup_fee'             => false,
		'setup_fee_name'        => '',
		'setup_fee_price'       => 0.00,
		'setup_fee_type'        => '',
		'trial'                 => false,
		'trial_days'            => 0,
		'expire'                => false,
		'expire_days'           => 0,
		'payment_duration'      => 0,
		'payment_duration_type' => '',
		'upgradeable'           => false,
		'tax_status'            => ProductTaxStatus::TAXABLE,
	];

	protected ?string $product_title = null;

	protected ?string $product_type = null;

	protected ?string $shipping_type = null;

	protected ?string $digital_auto_complete = null;

	protected array $settings_props = [
		'setup_fee',
		'setup_fee_name',
		'setup_fee_price',
		'setup_fee_type',
		'trial',
		'trial_days',
		'expire',
		'expire_days',
		'payment_duration',
		'payment_duration_type',
		'upgradeable',
		'tax_status',
	];

	protected array $data_props = [
		'price_name',
		'price_type',
		'price',
		'compare_price',
		'product_id',
		'order',
	];

	protected function read_data(): array {
		$data = parent::read_data();

		if ( ! empty( $data['settings'] ) ) {
			$settings = maybe_unserialize( $data['settings'] );

			if ( ! is_array( $settings ) ) {
				$settings = json_decode( $data['settings'], true );
			}

			unset( $data['settings'] );
			if ( $settings ) {
				$data = array_merge( $data, $settings );
			}
		}

		return $data;
	}

	public function prepare_for_db( string $context = 'create' ): array {
		$data     = [];
		$settings = [];
		$format   = [];

		foreach ( $this->data_props as $prop ) {
			$value         = $this->{"get_$prop"}( 'edit' );
			$format[]      = $this->predict_format( $prop, $value );
			$data[ $prop ] = $value;
		}

		foreach ( $this->settings_props as $prop ) {
			$settings[ $prop ] = $this->{"get_$prop"}( 'edit' );
		}

		$data['settings'] = maybe_serialize( $settings );
		$format[]         = '%s';

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	public function delete( bool $force_delete = true ): bool {
		$id = $this->get_id();

		$deleted = parent::delete( $force_delete );
		if ( $deleted ) {
			/**
			 * @deprecated
			 * Use 'storeengine/price/deleted'
			 * @see parent::delete()
			 */
			do_action( 'storeengine/price_deleted', $id, $this, $force_delete );
		}

		return $deleted;
	}

	public function clear_cache() {
		parent::clear_cache();
		wp_cache_delete( 'storeengine_product_' . $this->get_product_id() . '_prices' );
		wp_cache_delete( 'storeengine_price_' . $this->get_id() . '_integrations' );
	}

	// Getters

	public function get_price_name( string $context = 'view' ) {
		return $this->get_prop( 'price_name', $context );
	}

	public function get_name(): string {
		return $this->get_price_name();
	}

	public function get_price_type( string $context = 'view' ) {
		return $this->get_prop( 'price_type', $context );
	}

	public function get_type( string $context = 'view' ): string {
		return $this->get_price_type( $context );
	}

	public function get_price( string $context = 'view' ): float {
		return (float) $this->get_prop( 'price', $context );
	}

	public function get_compare_price( string $context = 'view' ): ?float {
		$compare_price = $this->get_prop( 'compare_price', $context );

		return $compare_price ? (float) $compare_price : null;
	}

	public function get_product_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'product_id', $context );
	}

	public function get_order( string $context = 'view' ): int {
		return (int) $this->get_prop( 'order', $context );
	}

	public function get_menu_order( string $context = 'view' ): int {
		return $this->get_order( $context );
	}

	public function get_setup_fee( string $context = 'view' ): bool {
		return (bool) $this->get_prop( 'setup_fee', $context );
	}

	public function has_setup_fee(): bool {
		return $this->get_setup_fee();
	}

	public function is_setup_fee(): bool {
		return $this->get_setup_fee();
	}

	public function get_setup_fee_name( string $context = 'view' ) {
		return $this->get_prop( 'setup_fee_name', $context );
	}

	public function get_setup_fee_price( string $context = 'view' ): float {
		return (float) $this->get_prop( 'setup_fee_price', $context );
	}

	public function get_setup_fee_type( string $context = 'view' ) {
		$value = $this->get_prop( 'setup_fee_type', $context );
		if ( 'fixed' === $value ) {
			$value = 'fee';
		}

		return $value;
	}

	public function get_trial( string $context = 'view' ) {
		return $this->get_prop( 'trial', $context );
	}

	public function is_trial(): bool {
		return (bool) $this->get_trial() && $this->get_trial_days() > 0;
	}

	public function get_trial_days( string $context = 'view' ): int {
		return (int) $this->get_prop( 'trial_days', $context );
	}

	public function get_expire( string $context = 'view' ): bool {
		return (bool) $this->get_prop( 'expire', $context );
	}

	public function get_expire_days( string $context = 'view' ): int {
		return (int) $this->get_prop( 'expire_days', $context );
	}

	public function is_expire(): bool {
		return $this->get_expire() && $this->get_expire_days();
	}

	/**
	 * @param Order $order
	 *
	 * @return ?StoreengineDatetime
	 */
	public function get_access_expire_for_order( Order $order ): ?StoreengineDatetime {
		if ( ! $this->is_expire() || ! $order->get_date_created_gmt() ) {
			return null;
		}

		return $order->get_date_created_gmt()->add( new DateInterval( 'P' . $this->get_expire_days() . 'D' ) );
	}

	public function get_payment_duration( string $context = 'view' ): int {
		return (int) $this->get_prop( 'payment_duration', $context );
	}

	public function get_payment_duration_type( string $context = 'view' ): string {
		$value = $this->get_prop( 'payment_duration_type', $context );
		if ( ! in_array( $value, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$old_values = [
				'days'   => 'day',
				'weeks'  => 'week',
				'months' => 'month',
				'years'  => 'year',
			];
			$value      = $old_values[ $value ] ?? 'month';
		}

		return $value;
	}

	public function get_upgradeable( string $context = 'view' ): bool {
		return (bool) $this->get_prop( 'upgradeable', $context );
	}

	public function get_tax_status() {
		$tax_status = ProductTaxStatus::TAXABLE;

		if ( $this->get_product()->get_id() ) {
			$tax_status = $this->get_product()->get_tax_status();
		}

		return apply_filters( 'storeengine/price/tax_status', $tax_status, $this );
	}

	/**
	 * Returns whether the product-pricing is taxable.
	 *
	 * @return bool
	 */
	public function is_taxable(): bool {
		/**
		 * Filters whether a product is taxable.
		 *
		 * @param bool          $taxable Whether the product is taxable.
		 * @param Price    $price Product object.
		 */
		return apply_filters( 'storeengine/price/is_taxable', $this->get_product()->is_taxable(), $this );
	}

	public function is_subscription(): bool {
		return 'subscription' === $this->get_type();
	}

	public function is_upgradeable(): bool {
		return $this->get_upgradeable();
	}

	// Setters.
	public function set_price_name( string $value ) {
		$this->set_prop( 'price_name', $value );
	}

	public function set_name( string $value ) {
		$this->set_price_name( $value );
	}

	public function set_price_type( string $value ) {
		$this->set_prop( 'price_type', $value );
	}

	public function set_type( string $value ) {
		$this->set_price_type( $value );
	}

	public function set_price( $value ) {
		$compare_price = $this->get_compare_price( 'edit' );
		if ( $compare_price ) {
			if ( $value && $value >= $compare_price ) {
				throw new \StoreEngine\Classes\Exceptions\StoreEngineException(
					__( 'Price must be less than compare-price.', 'storeengine' ),
					'price-eqg-compare-price',
					[
						'compare_price' => $compare_price,
						'price'         => $value,
					]
				);
			}
		}

		$this->set_prop( 'price', abs( floatval( $value ) ) );
	}

	public function set_compare_price( $value ) {
		$price = $this->get_price( 'edit' );

		if ( $price ) {
			if ( $value && $price >= $value ) {
				throw new \StoreEngine\Classes\Exceptions\StoreEngineException(
					__( 'Compare price must be greater than price.', 'storeengine' ),
					'compare-price eql price',
					[
						'compare_price' => $value,
						'price'         => $price,
					]
				);
			}
		}

		if ( $value ) {
			$this->set_prop( 'compare_price', abs( floatval( $value ) ) );
		} else {
			$this->set_prop( 'compare_price', null );
		}
	}

	public function set_product_id( $value ) {
		$this->set_prop( 'product_id', absint( $value ) );
	}

	public function set_order( $value ) {
		// set sort order.
		$this->set_prop( 'order', absint( $value ) );
	}

	public function set_menu_order( $value ) {
		// set sort order.
		$this->set_prop( 'order', absint( $value ) );
	}

	public function set_setup_fee( bool $value ) {
		$this->set_prop( 'setup_fee', $value );
	}

	public function set_setup_fee_name( string $value ) {
		$this->set_prop( 'setup_fee_name', $value );
	}

	public function set_setup_fee_price( $value ) {
		$this->set_prop( 'setup_fee_price', abs( floatval( $value ) ) );
	}

	public function set_setup_fee_type( string $value ) {
		if ( 'fixed' === $value ) {
			$value = 'fee';
		}

		$this->set_prop( 'setup_fee_type', $value );
	}

	public function set_trial( bool $value ) {
		$this->set_prop( 'trial', $value );
	}

	public function set_trial_days( $value ) {
		$this->set_prop( 'trial_days', absint( $value ) );
	}

	public function set_expire( bool $value ) {
		$this->set_prop( 'expire', $value );
	}

	public function set_expire_days( $value ) {
		$this->set_prop( 'expire_days', absint( $value ) );
	}

	public function set_payment_duration( $value ) {
		$this->set_prop( 'payment_duration', absint( $value ) );
	}

	public function set_payment_duration_type( string $value ) {
		if ( ! in_array( $value, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$old_values = [
				'days'   => 'day',
				'weeks'  => 'week',
				'months' => 'month',
				'years'  => 'year',
			];
			$value      = $old_values[ $value ] ?? 'month';
		}

		$this->set_prop( 'payment_duration_type', $value );
	}

	public function set_upgradeable( bool $value ) {
		$this->set_prop( 'upgradeable', $value );
	}


	// Extras

	public function get_settings(): array {
		return $this->get_props( $this->settings_props );
	}

	public function get_product() {
		return ( new ProductFactory() )->get_product( $this->get_product_id() );
	}

	public function get_product_title(): string {
		if ( null === $this->product_title && $this->get_product_id() ) {
			// Do not load in the read_data.
			// If we load this inside read_data method, then we need to clear
			// every price cache for the product from object cache.
			// @TODO convert product class into entity class to take advantage of the cache.
			$this->product_title = get_the_title( $this->get_product_id() );
		}

		return $this->product_title;
	}

	public function get_post_title(): string {
		return $this->get_product_title();
	}

	/**
	 * Allow setting temp product title custom item.
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	public function set_product_title( string $value ) {
		$this->product_title = trim( $value );
	}

	public function get_product_type(): ?string {
		if ( null === $this->product_type && $this->get_product_id() ) {
			// Do not load in the read_data.
			// If we load this inside read_data method, then we need to clear
			// every price cache for the product from object cache.
			// @TODO convert product class into entity class to take advantage of the cache.
			$this->product_type = get_post_meta( $this->get_product_id(), '_storeengine_product_type', true );
		}

		return $this->product_type;
	}

	public function get_shipping_type(): ?string {
		if ( null === $this->shipping_type && $this->get_product_id() ) {
			// Do not load in the read_data.
			// If we load this inside read_data method, then we need to clear
			// every price cache for the product from object cache.
			// @TODO convert product class into entity class to take advantage of the cache.
			$this->shipping_type = get_post_meta( $this->get_product_id(), '_storeengine_product_shipping_type', true );
		}

		return $this->shipping_type;
	}

	public function get_digital_auto_complete(): bool {
		if ( null === $this->digital_auto_complete && $this->get_product_id() ) {
			// Do not load in the read_data.
			// If we load this inside read_data method, then we need to clear
			// every price cache for the product from object cache.
			// @TODO convert product class into entity class to take advantage of the cache.
			$this->digital_auto_complete = Formatting::string_to_bool( get_post_meta( $this->get_product_id(), '_storeengine_product_digital_auto_complete', true ) );
		}

		return 'digital' === $this->get_shipping_type() && $this->digital_auto_complete;
	}

	/**
	 * Allow setting temp product title custom item.
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	public function set_product_type( string $value ) {
		$this->product_type = trim( $value );
	}

	/**
	 * @return Integration[]
	 */
	public function get_integrations(): array {
		if ( 0 === $this->id ) {
			return [];
		}

		$key    = 'storeengine_price_' . $this->get_id() . '_integrations';
		$cached = wp_cache_get( $key, $this->cache_group );
		if ( $cached && is_array( $cached ) ) {
			return array_map( fn( $result ) => ( new Integration() )->set_data( $result ), $cached );
		}

		global $wpdb;
		$table        = $wpdb->prefix . Helper::DB_PREFIX . 'integrations';
		$integrations = [];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE price_id = %d", $this->get_id() ) );
		if ( ! $results ) {
			return $integrations;
		}

		wp_cache_set( $key . $this->id . '_integrations', $results, $this->cache_group );

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery

		return array_map( fn( $result ) => ( new Integration() )->set_data( $result ), $results );
	}

	/**
	 * @param string $provider
	 * @param int $integration_id
	 *
	 * @return false|Integration
	 */
	public function add_integration( string $provider, int $integration_id ) {
		$integration = new Integration();
		$integration->set_product_id( $this->get_product_id() );
		$integration->set_price_id( $this->get_id() );
		$integration->set_integration_id( $integration_id );
		$integration->set_provider( $provider );
		$integration->save();
		if ( 0 === $integration->get_id() ) {
			return false;
		}

		return $integration;
	}

	public function remove_integration( string $provider, int $integration_id ): bool {
		$integration = new Integration();
		$integration->set_product_id( $this->get_product_id() );
		$integration->set_price_id( $this->get_id() );
		$integration->set_integration_id( $integration_id );
		$integration->set_provider( $provider );

		return $integration->delete_by_price_and_integration();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function get_formatted_payment_duration(): string {
		$payment_duration = $this->get_payment_duration();

		// @TODO use array of duration types & i18n properly.
		if ( empty( $payment_duration ) || 1 === $payment_duration ) {
			return Formatting::price( $this->get_price() ) . ' / Every ' . ucfirst( $this->get_payment_duration_type() );
		}

		return Formatting::price( $this->get_price() ) . ' / ' . $payment_duration . '-' . $this->get_payment_duration_type() . 's';
	}

	/**
	 * Get the suffix to display after prices > 0.
	 *
	 * This is skipped if the suffix
	 * has dynamic values such as {price_excluding_tax} for variable products.
	 *
	 * @param string|float $price to calculate, left blank to just use get_price().
	 * @param int $qty passed on to get_price_including_tax() or get_price_excluding_tax().
	 *
	 * @return string
	 * @see get_price_html for an explanation as to why.
	 */
	public function get_price_suffix( $price = '', int $qty = 1 ): string {
		$html = '';

		$suffix = Helper::get_settings( 'price_display_suffix' );

		if ( $suffix && TaxUtil::is_tax_enabled() && ProductTaxStatus::TAXABLE === $this->get_tax_status() ) {
			if ( '' === $price ) {
				$price = $this->get_price();
			}

			$replacements = [
				'{price_including_tax}' => Formatting::price( Formatting::get_price_including_tax( $this->get_price(), $this->get_id(), $this->get_product_id(), [
					'qty'   => $qty,
					'price' => $price,
				] ) ),
				// @phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				'{price_excluding_tax}' => Formatting::price( Formatting::get_price_excluding_tax( $this->get_price(), $this->get_id(), $this->get_product_id(), [
					'qty'   => $qty,
					'price' => $price,
				] ) ),
				// @phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			];
			$html = str_replace( array_keys( $replacements ), array_values( $replacements ), ' <small class="storeengine-price-suffix">' . wp_kses_post( $suffix ) . '</small>' );
		}

		return apply_filters( 'storeengine/get_price_suffix', $html, $this, $price, $qty );
	}

	public function is_product_synced(): bool {
		return false;
	}

	public function get_formatted_price(): string {
		if ( empty( $this->get_price() ) ) {
			$price = apply_filters( 'storeengine/empty_price_html', '', $this );
		} elseif ( ! empty( $this->get_compare_price() ) ) {
			$price = Formatting::format_sale_price(
				Formatting::get_price_to_display( $this->get_compare_price(), $this->get_id(), $this->get_product_id() ),
				Formatting::get_price_to_display( $this->get_price(), $this->get_id(), $this->get_product_id() )
			);
		} else {
			$price = Formatting::price( Formatting::get_price_to_display( $this->get_price(), $this->get_id(), $this->get_product_id() ) );
		}

		$price = $price ? $price . $this->get_price_suffix() : $price;

		return apply_filters( 'storeengine/formatted_price', $price, $this );
	}

	public function get_price_html() {
		return apply_filters( 'storeengine/get_price_html', $this->get_formatted_price(), $this );
	}

	public function print_price_html() {
		echo wp_kses_post( $this->get_price_html() );
	}

	public function get_price_summery_html() {
		return apply_filters( 'storeengine/get_price_summery_html', $this->get_formatted_price(), $this );
	}

	public function get_formatted_setup_fee(): string {
		if ( $this->has_setup_fee() ) {
			$fee_name = $this->get_setup_fee_name() ? $this->get_setup_fee_name() : __( 'Setup Fee', 'storeengine' );
			return sprintf(
				'<span class="storeengine-price-setup-fee">%s</span> %s',
				Formatting::price( Formatting::get_price_to_display( $this->get_setup_fee_price(), $this->get_id(), $this->get_product_id() ) ),
				esc_html( $fee_name ),
			);
		}
		return '';
	}

	public function get_formatted_price_meta(): array {
		$price_meta = [
			'setup-fee' => $this->get_formatted_setup_fee(),
		];

		return array_filter( apply_filters( 'storeengine/get_formatted_price_meta', $price_meta, $this ) );
	}

	public function get_formatted_price_meta_html() {
		$details_html = [];
		foreach ( $this->get_formatted_price_meta() as $key => $detail ) {
			if ( ! $detail ) {
				continue;
			}

			$details_html[] = '<span class="storeengine-price-meta-' . esc_attr( $key ) . '">' . $detail . '</span>';
		}

		$details_html = implode( '', $details_html );

		return apply_filters( 'storeengine/get_formatted_price_meta_html', $details_html, $this );
	}

	public function print_price_summery_html() {
		echo wp_kses_post( $this->get_price_summery_html() );
	}

	public function print_formatted_price_meta_html() {
		echo wp_kses_post( $this->get_formatted_price_meta_html() );
	}
}
