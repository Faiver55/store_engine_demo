<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

/**
 * @see \WC_Order_Refund
 * @see \WC_Order_Refund_Data_Store_CPT
 */
class Refund extends AbstractOrder {

	/**
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected string $object_type = 'refund_order';

	protected array $internal_meta_keys = [
		'_reason',
		'_refunded_by',
		'_refunded_payment',
		'_total',
	];

	protected array $meta_key_to_props = [
		'_reason'           => 'reason',
		'_refunded_by'      => 'refunded_by',
		'_refunded_payment' => 'refunded_payment',
	];

	/**
	 * Order meta-data with key => value.
	 *
	 * @var array
	 *
	 * @TODO move to extra.
	 *       this is a placeholder for meta-data objects not k->v pair.
	 */
	protected array $extra_data = [
		'amount'           => '',
		'reason'           => '',
		'refunded_by'      => 0,
		'refunded_payment' => false,
	];

	protected bool $allow_trash = false;

	/**
	 * Get status - always completed for refunds.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_status( string $context = 'view' ): ?string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return OrderStatus::COMPLETED;
	}

	/**
	 * Get refunded amount.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return float|string
	 */
	public function get_amount( string $context = 'view' ): float {
		return $this->get_total_amount( $context );
	}

	public function get_total_amount( string $context = 'view' ) {
		return $this->get_total( $context );
	}

	public function get_tax_amount( string $context = 'view' ) {
		return $this->get_total_tax( $context );
	}


	/**
	 * Get refund reason.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_reason( string $context = 'view' ): string {
		return (string) $this->get_prop( 'reason', $context );
	}

	/**
	 * Get ID of user who did the refund.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return int
	 */
	public function get_refunded_by( string $context = 'view' ): int {
		return (int) $this->get_prop( 'refunded_by', $context );
	}

	protected ?Customer $user = null;

	public function get_refunded_by_user() {
		if ( ! $this->user || ( is_a( $this->user, '\StoreEngine\Classes\Customer' ) && $this->user->get_id() !== $this->get_refunded_by() ) ) {
			$this->user = Helper::get_customer( $this->get_refunded_by() );
		}

		return $this->user;
	}

	/**
	 * Return if the payment was refunded via API.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool
	 */
	public function get_refunded_payment( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'refunded_payment', $context ) );
	}

	/**
	 * Get formatted refunded amount.
	 *
	 * @return string
	 */
	public function get_formatted_refund_amount(): string {
		return apply_filters( 'storeengine/formatted_refund_amount', Formatting::price( $this->get_amount(), [ 'currency' => $this->get_currency() ] ), $this );
	}

	/**
	 * Set refunded amount.
	 *
	 * @param string|float|int $value Value to set.
	 *
	 * @throws StoreEngineException Exception if the amount is invalid.
	 */
	public function set_amount( $value ) {
		$this->set_total_amount( $value );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function set_total_amount( $amount ) {
		$this->set_total( $amount );
	}

	/**
	 * Set refund reason.
	 *
	 * @param string $value Value to set.
	 *
	 * @throws StoreEngineException Exception if the amount is invalid.
	 */
	public function set_reason( string $value ) {
		$this->set_prop( 'reason', $value );
	}

	/**
	 * Set refunded by.
	 *
	 * @param int|string $value Value to set.
	 *
	 * @throws StoreEngineException Exception if the amount is invalid.
	 */
	public function set_refunded_by( $value ) {
		$this->set_prop( 'refunded_by', absint( $value ) );
	}

	/**
	 * Set if the payment was refunded via API.
	 *
	 * @param bool|string $value Value to set. (yes|no|1|0|true|false)
	 *
	 * @throws StoreEngineException
	 */
	public function set_refunded_payment( $value ) {
		$this->set_prop( 'refunded_payment', Formatting::string_to_bool( $value ) );
	}

	protected function query(): ?string {
		global $wpdb;

		return "
			SELECT
			o.id as o_id,
			o.parent_order_id as parent_order_id,
			o.*,
			p.id as operational_id,
			p.*
		FROM {$wpdb->prefix}storeengine_orders o
			LEFT JOIN {$wpdb->prefix}storeengine_order_operational_data p ON p.order_id = o.id
			LEFT JOIN {$wpdb->prefix}storeengine_orders_meta m ON m.order_id = o.id
		";
	}

	protected function prepare_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		$props = [
			'status',
			'currency',
			'type',
			'tax_amount',
			'total_amount',
			'customer_id',
			'date_created_gmt',
			'date_updated_gmt',
			'parent_order_id',
		];

		if ( 'create' === $context ) {
			$this->set_date_prop( 'date_created_gmt', current_time( 'mysql', 1 ) );
		}

		// Always set.
		$this->set_date_prop( 'date_updated_gmt', current_time( 'mysql', 1 ) );

		foreach ( $props as $prop ) {
			$value = $this->{"get_$prop"}( 'edit' );
			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $prop );
			}

			$format[]      = $this->predict_format( $prop, $value );
			$data[ $prop ] = $value;
		}

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	protected function prepare_operational_data_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		$props = [
			'storeengine_version'         => 'version',
			'prices_include_tax'          => 'prices_include_tax',
			'coupon_usages_are_counted'   => 'coupon_usages_are_counted',
			'download_permission_granted' => 'download_permission_granted',
			'cart_hash'                   => 'cart_hash',
			'new_order_email_sent'        => 'new_order_email_sent',
			'order_key'                   => 'order_key',
			'order_stock_reduced'         => 'order_stock_reduced',
			'date_paid_gmt'               => 'date_paid_gmt',
			'date_completed_gmt'          => 'date_completed_gmt',
			'shipping_tax_amount'         => 'shipping_tax_amount',
			'shipping_total_amount'       => 'shipping_total_amount',
			'discount_tax_amount'         => 'discount_tax_amount',
			'discount_total_amount'       => 'discount_total_amount',
			'recorded_sales'              => 'recorded_sales',
		];

		if ( 'create' === $context ) {
			$props['order_id'] = 'id';
		}

		foreach ( $props as $key => $prop ) {
			$value = $this->{"get_$prop"}( 'edit' );
			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $prop );
			}

			$format[]     = $this->predict_format( $key, $value );
			$data[ $key ] = $value;
		}

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '_operational_data/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}
}
