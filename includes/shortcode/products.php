<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Term;

class Products {
	public function __construct() {
		add_shortcode( 'storeengine_products', [ $this, 'render_products' ] );
	}

	public function render_products( $atts, $content = '' ): string {
		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 1,
		] );

		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$orderby = Helper::get_settings( 'product_archive_products_order', '' );
		}

		$atts = shortcode_atts( [
			'ids'            => '',
			'exclude_ids'    => '',
			'category'       => '',
			'cat_not_in'     => '',
			'tag'            => '',
			'tag_not_in'     => '',
			'course_level'   => '',
			'price_type'     => '',
			'orderby'        => $orderby,
			'order'          => '',
			'count'          => (int) Helper::get_settings( 'product_archive_products_per_page' ) ?? 12,
			'column_per_row' => (int) $products_per_row->desktop ?? 3,
			'has_pagination' => false,
		], $atts, 'storeengine_products' );

		// Query Args.
		$args = [
			'post_type'   => Helper::PRODUCT_POST_TYPE,
			'post_status' => 'publish',
			'meta_query'  => [
				'relation' => 'OR',
				[
					'key'     => '_storeengine_product_hide',
					'value'   => true,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_storeengine_product_hide',
					'value'   => true,
					'compare' => '!=',
				],
			],
		];

		if ( ! empty( $atts['ids'] ) ) {
			$args['post__in'] = (array) explode( ',', $atts['ids'] );
		}

		if ( ! empty( $atts['exclude_ids'] ) ) {
			$args['post__not_in'] = (array) explode( ',', $atts['exclude_ids'] );
		}

		// taxonomy
		$tax_query = [];
		if ( ! empty( $atts['category'] ) ) {
			$category = (array) explode( ',', $atts['category'] );
			if ( ! empty( $category ) ) {
				$tax_query[] = [
					'taxonomy' => Helper::PRODUCT_CATEGORY_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $category,
					'operator' => 'IN',
				];
			}
		} elseif ( is_tax( Helper::PRODUCT_CATEGORY_TAXONOMY ) ) {
			$tax_query[] = [
				'taxonomy' => Helper::PRODUCT_CATEGORY_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => [ get_queried_object_id() ],
				'operator' => 'IN',
			];
		}

		if ( ! empty( $atts['cat_not_in'] ) ) {
			$cat_not_in = (array) explode( ',', $atts['cat_not_in'] );
			if ( ! empty( $cat_not_in ) ) {
				$tax_query[] = [
					'taxonomy' => Helper::PRODUCT_CATEGORY_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $cat_not_in,
					'operator' => 'NOT IN',
				];
			}
		}

		if ( ! empty( $atts['tag'] ) ) {
			$tag = (array) explode( ',', $atts['tag'] );
			if ( ! $tag ) {
				$tax_query[] = [
					'taxonomy' => Helper::PRODUCT_TAG_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $tag,
					'operator' => 'IN',
				];
			}
		} elseif ( is_tax( Helper::PRODUCT_TAG_TAXONOMY ) ) {
			$tax_query[] = [
				'taxonomy' => Helper::PRODUCT_TAG_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => [ get_queried_object_id() ],
				'operator' => 'IN',
			];
		}

		if ( ! empty( $atts['tag_not_in'] ) ) {
			$tag_not_in = (array) explode( ',', $atts['tag_not_in'] );
			if ( ! empty( $tag_not_in ) ) {
				$tax_query[] = [
					'taxonomy' => Helper::PRODUCT_TAG_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $tag_not_in,
					'operator' => 'NOT IN',
				];
			}
		}

		if ( count( $tax_query ) > 0 ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}

			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$args['order'] = ! empty( $atts['order'] ) ? $atts['order'] : 'DESC';
		if ( ! empty( $atts['orderby'] ) && empty( $atts['order'] ) ) {
			switch ( $atts['orderby'] ) {
				case 'title':
					$args['orderby'] = 'post_title';
					$args['order']   = 'ASC';
					break;
				case 'date':
					$args['orderby'] = 'publish_date';
					$args['order']   = 'DESC';
					break;
				case 'modified':
					$args['orderby'] = 'modified';
					break;
				case '':
				default:
					$args['orderby'] = 'ID';
			}
		}

		$args['posts_per_page'] = (int) $atts['count'];

		$grid_class = Helper::get_responsive_column( [
			'desktop' => (int) $atts['column_per_row'],
			'tablet'  => 2,
			'mobile'  => 1,
		] );

		$attr_str = '';
		foreach (
			[
				'column_per_row',
				'exclude_ids',
				'ids',
				'count',
				'order',
				'orderby',
				'category',
				'cat_not_in',
				'tag',
				'tag_not_in',
			] as $attribute
		) {
			if ( ! empty( $atts[ $attribute ] ) ) {
				$attribute = 'column_per_row' === $atts[ $attribute ] ? 'per_row' : $attribute;
				$attribute = 'tag' === $atts[ $attribute ] ? 'tags' : $attribute;
				// Add to data attribute string.
				$attr_str .= ' data-' . esc_attr( $attribute ) . '="' . esc_attr( $atts[ $attribute ] ) . '"';
			}
		}

		// @TODO use new WP_Query or override the default query with action/filter hook.
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- using post_query function directly.
		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
		query_posts( apply_filters( 'storeengine_products_shortcode_args', $args ) );

		ob_start();

		echo '<div class="storeengine-products storeengine-products--grid"' . $attr_str . '>'; //phpcs:ignore
		echo '<div class="storeengine-products__body">';
		echo '<div class="storeengine-row">';
		if ( have_posts() ) {
			// Load posts loop.
			while ( have_posts() ) {
				the_post();
				Template::get_template( 'content-product.php', array( 'grid_class' => $grid_class ) );
			}
			wp_reset_postdata();
			if ( $atts['has_pagination'] ) {
				Template::get_template( 'archive/pagination.php' );
			}
		} else {
			Template::get_template( 'archive/product-none.php' );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';

		$output = ob_get_clean();
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- using post_query function directly.

		return $output;
	}
}
