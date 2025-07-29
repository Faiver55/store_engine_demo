<?php

namespace StoreEngine\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\TemplateLoader;
use StoreEngine\frontend\template\Block;
use StoreEngine\Utils\Helper;
use WP_Query;

class Template extends TemplateLoader {
	public static function init() {
		$self = new self();
		$self->dispatch_hook();
		if ( Helper::is_fse_theme() ) {
			Block::init();
		} else {
			add_filter( 'template_include', array( $self, 'template_loader' ) );
		}
	}

	public function dispatch_hook() {
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'astra_blog_post_per_page_exclusions', [ $this, 'astra_product_per_page_exclusions' ] );
		add_filter( 'comments_template', array( $this, 'load_comments_template' ) );

		// Redirect to log-in wp-screen.
		//add_action( 'template_redirect', array( $this, 'frontend_dashboard_template_redirect' ) );
	}

	public function load_comments_template( $template ) {
		if ( get_post_type() !== 'storeengine_product' ) {
			return $template;
		}

		$check_dirs = array(
			trailingslashit( get_stylesheet_directory() ) . Helper::template_path(),
			trailingslashit( get_template_directory() ) . Helper::template_path(),
			trailingslashit( get_stylesheet_directory() ),
			trailingslashit( get_template_directory() ),
			trailingslashit( Helper::plugin_path() ) . 'templates/',
		);

		if ( STOREENGINE_TEMPLATE_DEBUG_MODE ) {
			$check_dirs = array( array_pop( $check_dirs ) );
		}

		foreach ( $check_dirs as $dir ) {
			if ( file_exists( trailingslashit( $dir ) . 'single-product-reviews-and-comments.php' ) ) {
				return trailingslashit( $dir ) . 'single-product-reviews-and-comments.php';
			}
		}

		return $template;
	}

	/**
	 * Hook into pre_get_posts to do the main product query.
	 *
	 * @param WP_Query $q Query instance.
	 */
	public function pre_get_posts( WP_Query $q ) {
		if ( ! $q->is_main_query() || $q->is_feed() || is_admin() ) {
			return;
		}

		if ( is_post_type_archive( Helper::PRODUCT_POST_TYPE ) ) {
			// Astra requires `post_type` to be string for `astra_blog_post_per_page_exclusions` in_array check, we're setting array value.
			// Removing this action safely removes the per_page settings for archive template set by astra.
			remove_action( 'parse_tax_query', 'astra_blog_post_per_page' );

			if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			} else {
				$orderby = Helper::get_settings( 'product_archive_products_order', '' );
			}

			$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
			$q->set( 'post_type', apply_filters( 'storeengine/frontend/product_archive_post_types', array( Helper::PRODUCT_POST_TYPE ) ) );
			$q->set( 'posts_per_page', (int) Helper::get_settings( 'product_archive_products_per_page', 12 ) );
			$q->set( 'paged', $paged );
			$q->set( 'orderby', $orderby );
			$q->set( 'order', 'title' === $orderby ? 'ASC' : 'DESC' );
			$q->set( 'meta_query', [
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
			] );
		} elseif ( $q->is_tax() ) {
			$q->set( 'meta_query', [
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
			] );
		}
	}

	public function astra_product_per_page_exclusions( array $post_types ) {
		$post_types[] = Helper::PRODUCT_POST_TYPE;

		return $post_types;
	}

	public function frontend_dashboard_template_redirect() {
		if ( ! is_user_logged_in() && (int) Helper::get_settings( 'dashboard_page' ) === get_the_ID() ) {
			wp_safe_redirect( wp_login_url() );
			exit();
		}
	}
}
