<?php

namespace StoreEngine\Classes\Order;

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;

/**
 * @see \WC_Order_Item_Product
 * @see \WC_Order_Item_Product_Data_Store
 */
class OrderItemProduct extends AbstractOrderItem {

	protected array $meta_key_to_props = [
		'_product_id'            => 'product_id',
		'_variation_id'          => 'variation_id',
		'_price_id'              => 'price_id',
		'_price_name'            => 'price_name',
		'_quantity'              => 'quantity',
		'_price'                 => 'price',
		'_tax_class'             => 'tax_class',
		'_line_subtotal'         => 'subtotal',
		'_line_subtotal_tax'     => 'subtotal_tax',
		'_line_total'            => 'total',
		'_line_tax'              => 'total_tax',
		'_line_tax_data'         => 'taxes',
		'_tax_status'            => 'tax_status',
		'_product_type'          => 'product_type',
		'_shipping_type'         => 'shipping_type',
		'_digital_auto_complete' => 'digital_auto_complete',
		'_price_type'            => 'price_type',
		'_setup_fee'             => 'setup_fee',
		'_setup_fee_name'        => 'setup_fee_name',
		'_setup_fee_price'       => 'setup_fee_price',
		'_setup_fee_type'        => 'setup_fee_type',
		'_trial'                 => 'trial',
		'_trial_days'            => 'trial_days',
		'_expire'                => 'expire',
		'_expire_days'           => 'expire_days',
		'_payment_duration'      => 'payment_duration',
		'_payment_duration_type' => 'payment_duration_type',
		'_upgradeable'           => 'upgradeable',
	];

	/**
	 * Data stored in meta keys.
	 *
	 * @var array
	 */
	protected array $internal_meta_keys = [
		'_product_id',
		'_variation_id',
		'_price_id',
		'_price_name',
		'_quantity',
		'_tax_class',
		'_line_subtotal',
		'_line_subtotal_tax',
		'_line_total',
		'_line_tax',
		'_line_tax_data',
		'_tax_status',
		'_product_type',
		'_shipping_type',
		'_digital_auto_complete',
		'_price_type',
		'_price',
		'_setup_fee',
		'_setup_fee_name',
		'_setup_fee_price',
		'_setup_fee_type',
		'_trial',
		'_trial_days',
		'_expire',
		'_expire_days',
		'_payment_duration',
		'_payment_duration_type',
		'_upgradeable',
	];

	/**
	 * Order Data array. This is the core order data exposed in APIs
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'product_id'            => 0,
		'variation_id'          => 0,
		'quantity'              => 1,
		'tax_class'             => '',
		'subtotal'              => 0,
		'subtotal_tax'          => 0,
		'total'                 => 0,
		'total_tax'             => 0,
		'taxes'                 => [
			'subtotal' => [],
			'total'    => [],
		],
		'tax_status'            => ProductTaxStatus::TAXABLE,
		'product_type'          => '',
		'shipping_type'         => 'physical',
		'digital_auto_complete' => false,
		'price_type'            => '',
		'price_id'              => 0,
		'price_name'            => '',
		'price'                 => 0.00,
		'setup_fee'             => false,
		'setup_fee_name'        => '',
		'setup_fee_price'       => '',
		'setup_fee_type'        => '',
		'trial'                 => false,
		'trial_days'            => '',
		'expire'                => false,
		'expire_days'           => 0,
		'payment_duration'      => 0,
		'payment_duration_type' => '',
		'upgradeable'           => false,
	];

	/**
	 * Read/populate data properties specific to this order item.
	 *
	 * @throws StoreEngineException
	 */
	protected function read_data(): array {
		return array_merge( parent::read_data(), [
			'product_id'            => $this->get_metadata( '_product_id' ),
			'variation_id'          => $this->get_metadata( '_variation_id' ),
			'product_type'          => $this->get_metadata( '_product_type' ),
			'shipping_type'         => $this->get_metadata( '_shipping_type' ),
			'digital_auto_complete' => $this->get_metadata( '_digital_auto_complete' ),
			'price_type'            => $this->get_metadata( '_price_type' ),
			'price_id'              => $this->get_metadata( '_price_id' ),
			'price_name'            => $this->get_metadata( '_price_name' ),
			'price'                 => $this->get_metadata( '_price' ),
			'quantity'              => $this->get_metadata( '_quantity' ),
			'tax_class'             => $this->get_metadata( '_tax_class' ),
			'subtotal'              => $this->get_metadata( '_line_subtotal' ),
			'total'                 => $this->get_metadata( '_line_total' ),
			'taxes'                 => $this->get_metadata( '_line_tax_data' ),
			'trial'                 => $this->get_metadata( '_trial' ),
			'trial_days'            => $this->get_metadata( '_trial_days' ),
			'setup_fee'             => $this->get_metadata( '_setup_fee' ),
			'setup_fee_name'        => $this->get_metadata( '_setup_fee_name' ),
			'setup_fee_price'       => $this->get_metadata( '_setup_fee_price' ),
			'setup_fee_type'        => $this->get_metadata( '_setup_fee_type' ),
			'expire'                => $this->get_metadata( '_expire' ),
			'expire_days'           => $this->get_metadata( '_expire_days' ),
			'payment_duration'      => $this->get_metadata( '_payment_duration' ),
			'payment_duration_type' => $this->get_metadata( '_payment_duration_type' ),
			'upgradeable'           => $this->get_metadata( '_upgradeable' ),
		] );
	}

	/**
	 * Set quantity.
	 *
	 * @param string|int $value Quantity.
	 */
	public function set_quantity( $value ) {
		$this->set_prop( 'quantity', absint( $value ) );
	}

	/**
	 * Set price.
	 *
	 * @param string|int $value price.
	 */
	public function set_price( $value ) {
		$value = Formatting::format_decimal( $value );

		if ( ! is_numeric( $value ) ) {
			$value = 0;
		}

		$this->set_prop( 'price', $value );
	}

	/**
	 * Set tax class.
	 *
	 * @param string $value Tax class.
	 *
	 * @throws StoreEngineException
	 */
	public function set_tax_class( string $value ) {
		if ( $value && ! in_array( $value, Tax::get_tax_class_slugs(), true ) ) {
			$this->error( 'order_item_product_invalid_tax_class', __( 'Invalid tax class', 'storeengine' ) );
		}
		$this->set_prop( 'tax_class', $value );
	}

	/**
	 * Set Product ID
	 *
	 * @param string|int $value Product ID.
	 *
	 * @throws StoreEngineException
	 */
	public function set_price_id( $value ) {
		// @TODO validate price-id.
		if ( ! $this->get_product_id() ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Set the product id first.', 'storeengine' ), '1.0.0' );
		}

		$this->set_prop( 'price_id', absint( $value ) );
	}

	/**
	 * Set Product ID
	 *
	 * @param string $value Product ID.
	 */
	public function set_price_name( string $value ) {
		$this->set_prop( 'price_name', $value );
	}

	/**
	 * Set Product ID
	 *
	 * @param string|int $value Product ID.
	 *
	 * @throws StoreEngineException
	 */
	public function set_product_id( $value ) {
		if ( $value > 0 && Helper::PRODUCT_POST_TYPE !== get_post_type( absint( $value ) ) ) {
			$this->error( 'order_item_product_invalid_product_id', __( 'Invalid product ID', 'storeengine' ) );
		}

		$this->set_prop( 'product_id', absint( $value ) );
	}

	/**
	 * Set Product Type
	 *
	 * @param string $value Product type.
	 */
	public function set_product_type( string $value ) {
		$this->set_prop( 'product_type', $value );
	}

	/**
	 * Set Product Type
	 *
	 * @param string $value Product type.
	 */
	public function set_shipping_type( string $value ) {
		$this->set_prop( 'shipping_type', $value );
	}

	/**
	 * Set Product Type
	 *
	 * @param string|bool $value Product type.
	 */
	public function set_digital_auto_complete( string $value ) {
		$this->set_prop( 'digital_auto_complete', Formatting::bool_to_string( $value ) );
	}

	/**
	 * Set Product Type
	 *
	 * @param string $value Product type.
	 */
	public function set_price_type( string $value ) {
		$this->set_prop( 'price_type', $value );
	}

	/**
	 * Set variation ID.
	 *
	 * @param string|int $value Variation ID.
	 *
	 * @throws StoreEngineException
	 */
	public function set_variation_id( $value ) {
		// @TODO verify if id is variation and throw exception

		$this->set_prop( 'variation_id', absint( $value ) );
	}

	/**
	 * Line subtotal (before discounts).
	 *
	 * @param string|float $value Subtotal.
	 */
	public function set_subtotal( $value ) {
		$value = Formatting::format_decimal( $value );

		if ( ! is_numeric( $value ) ) {
			$value = 0;
		}

		$this->set_prop( 'subtotal', $value );
	}

	/**
	 * Line total (after discounts).
	 *
	 * @param string|int|float $value Total.
	 */
	public function set_total( $value ) {
		$value = Formatting::format_decimal( $value );

		if ( ! is_numeric( $value ) ) {
			$value = 0;
		}

		$this->set_prop( 'total', $value );

		// Subtotal cannot be less than total.
		if ( '' === $this->get_subtotal() || $this->get_subtotal() < $this->get_total() ) {
			$this->set_subtotal( $value );
		}
	}

	/**
	 * Line subtotal tax (before discounts).
	 *
	 * @param string|float $value Subtotal tax.
	 */
	public function set_subtotal_tax( $value ) {
		$this->set_prop( 'subtotal_tax', Formatting::format_decimal( $value ) );
	}

	/**
	 * Line total tax (after discounts).
	 *
	 * @param string|float $value Total tax.
	 */
	public function set_total_tax( $value ) {
		$this->set_prop( 'total_tax', Formatting::format_decimal( $value ) );
	}

	/**
	 * Set line taxes and totals for passed in taxes.
	 *
	 * @param string|array $raw_tax_data Raw tax data.
	 */
	public function set_taxes( $raw_tax_data ) {
		$raw_tax_data = maybe_unserialize( $raw_tax_data );
		$tax_data     = [
			'total'    => [],
			'subtotal' => [],
		];
		if ( ! empty( $raw_tax_data['total'] ) && ! empty( $raw_tax_data['subtotal'] ) ) {
			$tax_data['subtotal'] = array_map( [ Formatting::class, 'format_decimal' ], $raw_tax_data['subtotal'] );
			$tax_data['total']    = array_map( [ Formatting::class, 'format_decimal' ], $raw_tax_data['total'] );

			// Subtotal cannot be less than total!
			if ( NumberUtil::array_sum( $tax_data['subtotal'] ) < NumberUtil::array_sum( $tax_data['total'] ) ) {
				$tax_data['subtotal'] = $tax_data['total'];
			}
		}
		$this->set_prop( 'taxes', $tax_data );

		if ( TaxUtil::tax_round_at_subtotal() ) {
			$this->set_total_tax( NumberUtil::array_sum( $tax_data['total'] ) );
			$this->set_subtotal_tax( NumberUtil::array_sum( $tax_data['subtotal'] ) );
		} else {
			$this->set_total_tax( NumberUtil::uarray_sum( $tax_data['total'], [
				Formatting::class,
				'round_tax_total',
			] ) );
			$this->set_subtotal_tax( NumberUtil::uarray_sum( $tax_data['subtotal'], [
				Formatting::class,
				'round_tax_total',
			] ) );
		}
	}

	/**
	 * Set variation data (stored as meta data - write only).
	 *
	 * @param array $data Key/Value pairs.
	 */
	public function set_variation( array $data = [] ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$this->add_meta_data( str_replace( 'attribute_', '', $key ), $value, true );
			}
		}
	}

	/**
	 * Set properties based on passed in product object.
	 *
	 * @param AbstractProduct $product Product instance.
	 *
	 * @throws StoreEngineException
	 */
	public function set_product( $product ) {
		if ( ! is_a( $product, AbstractProduct::class ) ) {
			$this->error( 'order_item_product_invalid_product', __( 'Invalid product', 'storeengine' ) );
		}
		// @TODO store variation id.
		$this->set_product_id( $product->get_id() );
		$this->set_name( $product->get_name() );
		$this->set_tax_class( $product->get_tax_class() );
	}

	/**
	 * Set meta data for backordered products.
	 */
	public function set_backorder_meta() {
		// @TODO backorder
	}

	public function set_trial( $value ) {
		$this->set_prop( 'trial', $value );
	}

	public function set_trial_days( $value ) {
		$this->set_prop( 'trial_days', $value );
	}

	public function set_setup_fee( $value ) {
		$this->set_prop( 'setup_fee', $value );
	}

	public function set_setup_fee_name( $value ) {
		$this->set_prop( 'setup_fee_name', $value );
	}

	public function set_setup_fee_price( $value ) {
		$this->set_prop( 'setup_fee_price', $value );
	}

	public function set_setup_fee_type( $value ) {
		$this->set_prop( 'setup_fee_type', $value );
	}

	public function set_expire( $value ) {
		$this->set_prop( 'expire', $value );
	}

	public function set_expire_days( $value ) {
		$this->set_prop( 'expire_days', $value );
	}

	public function set_payment_duration( $value ) {
		$this->set_prop( 'payment_duration', $value );
	}

	public function set_payment_duration_type( $value ) {
		$this->set_prop( 'payment_duration_type', $value );
	}

	public function set_upgradeable( $value ) {
		$this->set_prop( 'upgradeable', $value );
	}

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'line_item';
	}

	/**
	 * Get product ID.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_price_id( string $context = 'view' ): int {
		return $this->get_prop( 'price_id', $context );
	}

	/**
	 * Get product ID.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_price_name( string $context = 'view' ): string {
		return $this->get_prop( 'price_name', $context );
	}

	/**
	 * Get product ID.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_product_id( string $context = 'view' ): int {
		return $this->get_prop( 'product_id', $context );
	}

	/**
	 * Get product type.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_product_type( string $context = 'view' ): string {
		return $this->get_prop( 'product_type', $context );
	}

	/**
	 * Get product type.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_shipping_type( string $context = 'view' ): string {
		return $this->get_prop( 'shipping_type', $context );
	}

	/**
	 * Get product type.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return bool
	 */
	public function get_digital_auto_complete( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'digital_auto_complete', $context ) );
	}

	/**
	 * Get product type.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_price_type( string $context = 'view' ): string {
		return $this->get_prop( 'price_type', $context );
	}

	/**
	 * Get variation ID.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_variation_id( string $context = 'view' ): int {
		return $this->get_prop( 'variation_id', $context );
	}

	/**
	 * Get quantity.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_quantity( string $context = 'view' ): int {
		return $this->get_prop( 'quantity', $context );
	}

	/**
	 * Get price.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_price( string $context = 'view' ) {
		return $this->get_prop( 'price', $context );
	}

	/**
	 * Get tax class.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_tax_class( string $context = 'view' ): string {
		return $this->get_prop( 'tax_class', $context );
	}

	/**
	 * Gets the item subtotal. This is the price of the item times the quantity
	 * excluding taxes before coupon discounts.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_subtotal( string $context = 'view' ) {
		return $this->get_prop( 'subtotal', $context );
	}

	/**
	 * Get subtotal tax.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_subtotal_tax( string $context = 'view' ) {
		return $this->get_prop( 'subtotal_tax', $context );
	}

	/**
	 * Gets the item total. This is the price of the item times the quantity
	 * excluding taxes after coupon discounts.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_total( string $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get total tax.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string|float
	 */
	public function get_total_tax( string $context = 'view' ) {
		return $this->get_prop( 'total_tax', $context );
	}

	/**
	 * Get taxes.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return array{total:int[]|float[],subtotal:int[]|float[]}
	 */
	public function get_taxes( string $context = 'view' ): array {
		return $this->get_prop( 'taxes', $context );
	}

	/**
	 * Get the associated product.
	 *
	 * @return AbstractProduct|bool
	 */
	public function get_product() {
		$product = Helper::get_product( $this->get_product_id() );

		return apply_filters( 'storeengine/order_item_product', $product, $this );
	}

	/**
	 * Get the Download URL.
	 *
	 * @param int|string $download_id Download ID.
	 *
	 * @return string
	 */
	public function get_item_download_url( $download_id ): string {
		$order = $this->get_order();

		return $order->get_id() ? add_query_arg(
			[
				'download_file' => $this->get_variation_id() ? $this->get_variation_id() : $this->get_product_id(),
				'order'         => $order->get_order_key(),
				'email'         => rawurlencode( $order->get_billing_email() ),
				'key'           => absint( $download_id ),
			],
			trailingslashit( home_url() )
		) : '';
	}

	/**
	 * Get any associated downloadable files.
	 *
	 * @return array
	 */
	public function get_item_downloads(): array {
		$files = [];
		$order = $this->get_order();

		return apply_filters( 'storeengine/get_item_downloads', $files, $this, $order );
	}

	/**
	 * Get tax status.
	 *
	 * @return string
	 * @throws StoreEngineException
	 */
	public function get_tax_status(): string {
		$product = $this->get_product();

		return $product ? $product->get_tax_status() : ProductTaxStatus::TAXABLE;
	}

	public function get_trial( string $context = 'view' ) {
		return $this->get_prop( 'trial', $context );
	}

	public function is_trial( string $context = 'view' ): bool {
		return (bool) $this->get_trial( $context );
	}

	public function get_trial_days( string $context = 'view' ) {
		return $this->get_prop( 'trial_days', $context );
	}

	public function get_setup_fee( string $context = 'view' ) {
		return $this->get_prop( 'setup_fee', $context );
	}

	public function get_setup_fee_name( string $context = 'view' ) {
		return $this->get_prop( 'setup_fee_name', $context );
	}

	public function get_setup_fee_price( string $context = 'view' ) {
		return $this->get_prop( 'setup_fee_price', $context );
	}

	public function get_setup_fee_type( string $context = 'view' ) {
		return $this->get_prop( 'setup_fee_type', $context );
	}

	public function get_expire( string $context = 'view' ) {
		return $this->get_prop( 'expire', $context );
	}

	public function get_expire_days( string $context = 'view' ) {
		return $this->get_prop( 'expire_days', $context );
	}

	public function get_payment_duration( string $context = 'view' ) {
		return $this->get_prop( 'payment_duration', $context );
	}

	public function get_payment_duration_type( string $context = 'view' ) {
		return $this->get_prop( 'payment_duration_type', $context );
	}

	public function get_upgradeable( string $context = 'view' ) {
		return $this->get_prop( 'upgradeable', $context );
	}
}
