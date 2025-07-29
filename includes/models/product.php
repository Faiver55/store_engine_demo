<?php

namespace StoreEngine\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// @TODO: is this class needed? check post type.

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Models\Price as PriceModel;
use StoreEngine\Utils\Helper;
use WP_Query;

/**
 * @deprecated - Use `ProductFactory` to retrieve product data.
 *
 * @see ProductFactory
 * @see \StoreEngine\Classes\AbstractProduct
 */
class Product extends AbstractModel {
	public string $table       = 'posts';
	public string $primary_key = 'ID';

	public function save( array $args = [] ) {
		wp_parse_args( $args, [
			'post_title'     => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_name'      => '',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		] );

		return wp_insert_post( $args );
	}

	public function update( int $id, array $args ): bool {
		wp_parse_args( $args, [
			'ID'             => $id,
			'post_title'     => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_name'      => '',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		] );

		return wp_update_post( $args );
	}

	public function delete( ?int $id = null ): bool {
		if ( ! $id ) {
			return false;
		}

		return wp_delete_post( $id, true );
	}

	public function get_product_meta( $id ) {
		// return key value instead array
		return array_map( fn( $meta ) => $meta[0], get_post_meta( absint( $id ) ) );
	}

	public function get_products() {
		$args  = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		$query = new WP_Query( $args );

		return $query->posts;
	}

	public function get_product_by_slug( $slug ): array {
		$args  = [
			'name'           => $slug,
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		];
		$query = new WP_Query( $args );

		return $query->posts;
	}

	public function get_products_by_category( $category ): array {
		$args  = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				],
			],
		];
		$query = new WP_Query( $args );

		return $query->posts;
	}

	public function get_products_by_tag( $tag ) {
		// TODO: Implement
	}


	public function get_products_by_date_range( $date ) {
		// TODO: Implement
	}

	public function get_products_by_status( $status ) {
		// TODO: Implement
	}

	/**
	 * @deprecated
	 */
	public function is_subscription( $price_id ): bool {
		$price = $this->get_price_data_by_id( $price_id );
		if ( isset( $price->price_type ) && 'subscription' === $price->price_type ) {
			return true;
		}

		return false;
	}

	/**
	 * @deprecated
	 */
	public function duration_frequency( $price_id ): array {
		if ( $this->is_subscription( $price_id ) ) {
			$price = $this->get_price_data_by_id( $price_id );
			if ( isset( $price->settings['payment_duration'] ) && isset( $price->settings['payment_duration_type'] ) ) {
				return [
					'interval_type' => $price->settings['payment_duration_type'],
					'interval'      => $price->settings['payment_duration'],
				];
			}
		}

		return [];
	}

	public function get_subscription_product_interval_and_count( $product_id ) {
		if ( $this->is_subscription( $product_id ) ) {
			return [
				'interval'       => get_post_meta( $product_id, '_storeengine_product_repeated_payment_duration_type', true ),
				'interval_count' => get_post_meta( $product_id, '_storeengine_product_repeated_payment_duration', true ),
			];
		}
	}

	public function get_product_prices( $product_id ): array {
		return ( new PriceModel() )->get_prices_by_product_id( (int) $product_id );
	}

	public function get_product_price_details( $product_id, $meta_id ): array {
		global $wpdb;
		$price = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_id = %d;", $product_id, $meta_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $price ) {
			return array_merge( array( 'price_id' => $price->meta_id ), maybe_unserialize( $price->meta_value ) );
		}

		return [];
	}

	/**
	 * @deprecated
	 */
	public function get_price_by_id( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price ) {
			return (float) $price->price ?? 0.0;
		}

		return null;
	}

	/**
	 * @param int|string $price_id
	 *
	 * @return ?object
	 * @deprecated
	 */
	public function get_price_data_by_id( $price_id ): ?object {
		return ( new PriceModel() )->get( absint( $price_id ) );
	}


	/**
	 * @deprecated
	 */
	public function get_compare_price( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price->compare_price ) {
			return (float) $price->compare_price;
		}

		return null;
	}

	/**
	 * @deprecated
	 */
	public function get_price_name( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price->price_name ) {
			return $price->price_name;
		}

		return null;
	}

	/**
	 * @deprecated
	 */
	public function get_price_duration( $price_id ): string {
		$price            = $this->get_price_data_by_id( $price_id );
		$payment_duration = $price->settings['payment_duration'];

		// @TODO use array of duration types & i18n properly.
		if ( 1 === $payment_duration ) {
			return Helper::currency_format( $price->price ) . ' / Every ' . ucfirst( $price->settings['payment_duration_type'] );
		}

		// @FIXME use _n for plural translation.
		return Helper::currency_format( $price->price ) . ' / ' . $payment_duration . '-' . $price->settings['payment_duration_type'] . 's';
	}

	/**
	 * @deprecated
	 */
	public function next_payment_date( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price ) {
			$interval = $this->duration_frequency( $price_id );
			if ( $interval ) {
				return strtotime( '+' . $interval['interval'] . ' ' . $interval['interval_type'] );
			}
		}

		return null;
	}

	public function is_all_product_product_autocomplete( $purchase_items ): bool {
		$is_auto_complete = false;
		foreach ( $purchase_items as $item ) {
			$product_id = $item->product_id;
			$product    = $this->get_product_meta( $product_id );
			if ( $product['_storeengine_product_digital_auto_complete'] && 'digital' === $product['_storeengine_product_shipping_type'] ) {
				$is_auto_complete = true;
			} else {
				$is_auto_complete = false;
				break;
			}
		}

		return $is_auto_complete;
	}

	/**
	 * @deprecated
	 */
	public function get_setup_fee( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price->settings['setup_fee_price'] ) {
			return (float) $price->settings['setup_fee_price'];
		}

		return null;
	}

	/**
	 * @deprecated
	 */
	public function get_trial_days( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price->settings['trial_days'] ) {
			return (int) $price->settings['trial_days'];
		}

		return null;
	}

	/**
	 * @deprecated
	 */
	public function trial_end_date( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price->settings['trial_days'] ) {
			return strtotime( '+' . $price->settings['trial_days'] . ' days' );
		}

		return null;
	}

	/**
	 * @deprecated
	 */
	public function get_price_expiry_date( $price_id ) {
		$price = $this->get_price_data_by_id( $price_id );
		if ( $price ) {
			if ( $price->settings['expire'] ) {
				return wp_date( 'Y-m-d H:i:s', strtotime( '+' . $price->settings['expire_days'] . 'days' ) );
			}
		}

		return null;
	}

	public static function get_product_rating( $product_id ) {
		global $wpdb;

		$ratings = array(
			'rating_count'   => 0,
			'rating_sum'     => 0,
			'rating_avg'     => 0.00,
			'count_by_value' => array(
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 0,
			),
		);

		$rating = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(meta_value) AS rating_count,
					SUM(meta_value) AS rating_sum
			FROM	{$wpdb->comments}
					INNER JOIN {$wpdb->commentmeta}
							ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id
			WHERE 	{$wpdb->comments}.comment_post_ID = %d
					AND {$wpdb->comments}.comment_type = %s
					AND meta_key = %s;
			",
				$product_id,
				'storeengine_product',
				'storeengine_rating'
			)
		);

		if ( $rating->rating_count ) {
			$avg_rating = number_format( ( $rating->rating_sum / $rating->rating_count ), 1 );

			$stars = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT commentmeta.meta_value AS rating,
						COUNT(commentmeta.meta_value) as rating_count
				FROM	{$wpdb->comments} comments
						INNER JOIN {$wpdb->commentmeta} commentmeta
								ON comments.comment_ID = commentmeta.comment_id
				WHERE	comments.comment_post_ID = %d
						AND comments.comment_type = %s
						AND commentmeta.meta_key = %s
				GROUP BY commentmeta.meta_value;
				",
					$product_id,
					'storeengine_product',
					'storeengine_rating'
				)
			);

			$ratings = array(
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 0,
			);
			foreach ( $stars as $star ) {
				$index = (int) $star->rating;
				array_key_exists( $index, $ratings ) ? $ratings[ $index ] = $star->rating_count : 0;
			}

			$ratings = array(
				'rating_count'   => $rating->rating_count,
				'rating_sum'     => $rating->rating_sum,
				'rating_avg'     => $avg_rating,
				'count_by_value' => $ratings,
			);
		}//end if

		return (object) $ratings;
	}
}
