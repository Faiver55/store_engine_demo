<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;

class Migration {

	protected int $limit = 20;

	public function __construct() {
	}

	public static function migrate( $sse ): void {
		$sse->emitEvent( [
			'type'    => 'message',
			'message' => esc_html__( 'Preparing...', 'storeengine' ),
		] );

		$self = new self();


		$attribute_count = $self->get_attributes_data( true );
		$product_count   = $self->get_product_data( true );
		$coupon_count    = $self->get_coupon_data( true );
		$orders_count    = $self->get_order_data( true );
		$total_count     = $attribute_count + $product_count + $coupon_count + $orders_count;

		$sse->emitEvent( [
			'type'    => 'message',
			'message' => sprintf(
				esc_html__( 'Total %1$d items found (%2$s, %3$s, %4$s and %5$s).', 'storeengine' ),
				$total_count,
				sprintf( _n( '%d attribute', '%d attributes', $attribute_count, 'storeengine' ), $attribute_count ),
				sprintf( _n( '%d product', '%d products', $product_count, 'storeengine' ), $product_count ),
				sprintf( _n( '%d coupon', '%d coupons', $coupon_count, 'storeengine' ), $coupon_count ),
				sprintf( _n( '%d order', '%d orders', $orders_count, 'storeengine' ), $orders_count )
			),
		] );

		sleep( 2 );

		$max_attribute_loop = 1;
		$max_product_loop   = 1;
		$max_coupon_loop    = 1;
		$max_order_loop     = 1;

		if ( $self->limit ) {
			$max_product_loop   = (int) ceil( $product_count / $self->limit );
			$max_attribute_loop = (int) ceil( $attribute_count / $self->limit );
			$max_coupon_loop    = (int) ceil( $coupon_count / $self->limit );
			$max_order_loop     = (int) ceil( $orders_count / $self->limit );
		}

		// Import Attributes.
		if ( $attribute_count ) {
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $attribute_count,
				'migrated' => 0,
				'message'  => sprintf( _n( 'Migrating %d attribute', 'Migrating %d attributes', $attribute_count, 'storeengine' ), $attribute_count ),
			] );

			sleep( 2 );

			$imported = 0;

			if ( 1 === $max_attribute_loop ) {
				$self->import_attributes( $sse, $attribute_count, $imported );
			} else {
				for ( $page = 0; $page < $max_attribute_loop; $page ++ ) {
					$self->import_attributes( $sse, $attribute_count, $imported );
				}
			}

			sleep( 2 );
		}

		// Import Products.
		if ( $product_count ) {
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $product_count,
				'migrated' => 0,
				'message'  => sprintf( _n( 'Migrating %d product', 'Migrating %d products', $product_count, 'storeengine' ), $product_count ),
			] );

			sleep( 2 );

			$imported = 0;

			if ( 1 === $max_product_loop ) {
				$self->import_products( $sse, $product_count, $imported );
			} else {
				for ( $page = 0; $page < $max_product_loop; $page ++ ) {
					$self->import_products( $sse, $product_count, $imported );
				}
			}

			sleep( 2 );
		}

		// Import Coupons.
		if ( $coupon_count ) {
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $coupon_count,
				'migrated' => 0,
				'message'  => sprintf( _n( 'Migrating %d coupon', 'Migrating %d coupons', $coupon_count, 'storeengine' ), $coupon_count ),
			] );

			sleep( 2 );

			$imported = 0;

			if ( 1 === $max_coupon_loop ) {
				$self->import_coupons( $sse, $coupon_count, $imported );
			} else {
				for ( $page = 0; $page < $max_coupon_loop; $page ++ ) {
					$self->import_coupons( $sse, $coupon_count, $imported );
				}
			}

			sleep( 2 );
		}

		// Import Orders.
		if ( $orders_count ) {
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $orders_count,
				'migrated' => 0,
				'message'  => sprintf( _n( 'Migrating %d order', 'Migrating %d orders', $orders_count, 'storeengine' ), $orders_count ),
			] );

			sleep( 2 );

			$imported = 0;

			if ( 1 === $max_order_loop ) {
				$self->import_orders( $sse, $orders_count, $imported );
			} else {
				for ( $page = 0; $page < $max_order_loop; $page ++ ) {
					$self->import_orders( $sse, $orders_count, $imported );
				}
			}

			sleep( 2 );
		}
	}

	private function import_attributes( $sse, $total_count, &$imported ): void {
		foreach ( $this->get_attributes_data() as $attribute ) {
			( new AttributeMigration( $attribute ) )->migrate();
			$imported ++;
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $total_count - $imported,
				'migrated' => $imported,
				'message'  => sprintf(
				// translators: %1$d. Product progress count, %2$d Product progress left.
					_n( 'Migrated %1$d out of %2$d attribute.', 'Migrated %1$d out of %2$d attributes.', $total_count, 'storeengine' ),
					$imported,
					$total_count
				),
			] );
			sleep( 1 );
		}
	}

	private function import_products( $sse, $total_count, &$imported ): void {
		foreach ( $this->get_product_data() as $product ) {
			( new ProductMigration( $product ) )->migrate();
			$imported ++;
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $total_count - $imported,
				'migrated' => $imported,
				'$product' => $product,
				'message'  => sprintf(
				// translators: %1$d. Product progress count, %2$d Product progress left.
					_n( 'Migrated %1$d out of %2$d product.', 'Migrated %1$d out of %2$d products.', $total_count, 'storeengine' ),
					$imported,
					$total_count
				),
			] );
			sleep( 1 );
		}
	}

	private function import_coupons( $sse, $total_count, &$imported ): void {
		foreach ( $this->get_coupon_data() as $coupon ) {
			( new CouponMigration( $coupon ) )->migrate();
			$imported ++;
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $total_count - $imported,
				'migrated' => $imported,
				'message'  => sprintf(
				// translators: %1$d. Coupon progress count, %2$d Coupon progress left.
					_n( 'Migrated %1$d out of %2$d coupon.', 'Migrated %1$d out of %2$d coupons.', $total_count, 'storeengine' ),
					$imported,
					$total_count
				),
			] );
			sleep( 1 );
		}
	}

	private function import_orders( $sse, $total_count, &$imported ): void {
		foreach ( $this->get_order_data() as $order ) {
			( new OrderMigration( $order ) )->migrate();
			$imported ++;
			$sse->emitEvent( [
				'type'     => 'progress',
				'left'     => $total_count - $imported,
				'migrated' => $imported,
				'message'  => sprintf(
				// translators: %1$d. Order progress count, %2$d Order progress left.
					_n( 'Migrated %1$d out of %2$d order.', 'Migrated %1$d out of %2$d orders.', $total_count, 'storeengine' ),
					$imported,
					$total_count
				),
			] );
			sleep( 1 );
		}
	}

	private function get_attributes_data( bool $count = false ) {
		global $wpdb;
		$select = $count ? 'COUNT(*)' : 'at.attribute_id';
		/** @noinspection SqlRedundantOrderingDirection */
		$query = "
		SELECT {$select}
		FROM {$wpdb->prefix}woocommerce_attribute_taxonomies at
		LIMIT $this->limit;
		";

		if ( $count ) {
			return (int) $wpdb->get_var( $query );
		}

		return $this->prepare_db_ids( $wpdb->get_col( $query ) );
	}

	private function get_product_data( bool $count = false ) {
		global $wpdb;
		$select = $count ? 'COUNT(*)' : 'w.ID';
		/** @noinspection SqlRedundantOrderingDirection */
		$query = "
		SELECT {$select}
		FROM {$wpdb->posts} w
		WHERE
			w.post_type = 'product' AND
			w.post_status NOT IN ('trash', 'auto-draft') AND
			w.ID NOT IN (
				-- Ignoring already imported products.
				SELECT sem.meta_value AS PID
				FROM {$wpdb->postmeta} sem
				INNER JOIN {$wpdb->posts} se ON se.ID = sem.post_id
				WHERE
					se.post_type = 'storeengine_product' AND
					sem.meta_key = '_wc_to_se_pid'
				UNION ALL
				-- Ignoring external & group products.
				SELECT wcm.post_id AS PID
				FROM {$wpdb->postmeta} wcm
				WHERE
					( wcm.meta_key = '_children' AND wcm.meta_value <> '' )
					OR
					( wcm.meta_key = '_product_url' AND wcm.meta_value <> '' )
			)
		ORDER BY w.ID ASC
		LIMIT {$this->limit};
		";


		if ( $count ) {
			return (int) $wpdb->get_var( $query );
		}

		return $this->prepare_db_ids( $wpdb->get_col( $query ) );
	}

	private function get_coupon_data( bool $count = false ) {
		global $wpdb;
		$select = $count ? 'COUNT(*)' : 'w.ID';
		/** @noinspection SqlRedundantOrderingDirection */
		$query = "
		SELECT {$select}
		FROM {$wpdb->posts} w
		WHERE
			w.post_type = 'shop_coupon' AND
			w.post_status NOT IN ('trash', 'auto-draft') AND
			w.post_title NOT IN (
				SELECT UPPER(m.meta_value)
				FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = '_storeengine_coupon_name'
			)
		ORDER BY w.ID ASC
		LIMIT {$this->limit};
		";


		if ( $count ) {
			return (int) $wpdb->get_var( $query );
		}

		return $this->prepare_db_ids( $wpdb->get_col( $query ) );
	}

	private function get_order_data( bool $count = false ) {
		global $wpdb;
		$select = $count ? 'COUNT(*)' : 'o.id';
		/** @noinspection SqlRedundantOrderingDirection */
		$query = "
		SELECT {$select}
		FROM {$wpdb->prefix}wc_orders o
		WHERE
			o.type = 'shop_order' AND
			o.status NOT IN ('trash', 'draft') AND
			o.id NOT IN (
				SELECT meta_value
				FROM {$wpdb->prefix}storeengine_orders_meta m
				WHERE m.meta_key = '_wc_to_se_oid'
			)
		ORDER BY id ASC, parent_order_id ASC
		LIMIT {$this->limit};
		";

		if ( $count ) {
			return (int) $wpdb->get_var( $query );
		}

		return $this->prepare_db_ids( $wpdb->get_col( $query ) );
	}

	protected function prepare_db_ids( ?array $ids ): array {
		if ( ! $ids ) {
			return [];
		}

		return array_unique( array_filter( array_map( 'absint', $ids ) ) );
	}
}
