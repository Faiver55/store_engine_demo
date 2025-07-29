<?php

namespace StoreEngine;

use StoreEngine\Classes\FrontendRequestHandler;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\OrderStatus\Processing;
use StoreEngine\Hooks\Integration;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\hooks\Kses;
use StoreEngine\hooks\Payment;
use WP_Post;

class Hooks {

	public static function init() {
		Formatting::init_hooks();
		Kses::init();
		Payment::init();
		Hooks\DownloadPermissionHooks::init();
		Hooks\Price::init();
		Integration::init();

		/**
		 * @see \WC_Comments::exclude_order_comments()
		 * @see \WC_Comments::exclude_order_comments_from_feed_where()
		 */
		add_filter( 'comments_clauses', [ __CLASS__, 'exclude_order_comments' ] );
		add_filter( 'comment_feed_where', [ __CLASS__, 'exclude_order_comments_from_feed_where' ] );

		FrontendRequestHandler::init();

		add_filter( 'do_shortcode_tag', [ __CLASS__, 'remove_empty_spaces' ], 10, 2 );

		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 2 );
		add_action( 'delete_post', [ __CLASS__, 'delete_post' ], 10, 2 );
		add_action( 'storeengine/customer/billing_country', [ __CLASS__, 'set_customer_default_country' ] );
		add_action( 'storeengine/customer/shipping_country', [ __CLASS__, 'set_customer_default_country' ] );

		self::handle_cache_last_changed();
	}

	/**
	 * @see add_metadata()
	 * @see update_metadata()
	 * @see delete_metadata()
	 * @return void
	 */
	protected static function handle_cache_last_changed() {
		$cache_groups = [
			'payment_token' => 'storeengine_payment_tokenmeta',
			'order_item'    => 'storeengine_order_item_meta',
			'order'         => 'storeengine_orders_meta',
		];

		foreach ( $cache_groups as $meta_type => $cache_group ) {
			add_action( "added_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
			add_action( "updated_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
			add_action( "deleted_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
		}
	}

	public static function set_customer_default_country( $value ) {
		if ( ! $value && ( Helper::is_checkout() || Helper::is_edit_address_page() ) ) {
			return Helper::get_settings( 'checkout_default_country' );
		}

		return $value;
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * This code should exclude shop_order comments from queries. Some queries (like the recent comments widget on the dashboard) are hardcoded.
	 * and are not filtered, however, the code current_user_can( 'read_post', $comment->comment_post_ID ) should keep them safe since only admin and.
	 * shop managers can view orders anyway.
	 *
	 * The frontend view order pages get around this filter by using remove_filter('comments_clauses', array( 'WC_Comments' ,'exclude_order_comments'), 10, 1 );
	 *
	 * @param array $clauses A compacted array of comment query clauses.
	 *
	 * @return array
	 */
	public static function exclude_order_comments( array $clauses ): array {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'order_note' ";

		return $clauses;
	}

	public static function include_order_comments( array $clauses ): array {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type = 'order_note' AND comment_agent = 'StoreEngine' ";

		return $clauses;
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * @param string $where The WHERE clause of the query.
	 *
	 * @return string
	 */
	public static function exclude_order_comments_from_feed_where( string $where ): string {
		return $where . ( $where ? ' AND ' : '' ) . " comment_type != 'order_note' ";
	}

	/**
	 * Cleanup empty extra whitespaces.
	 *
	 * @param mixed|string $content
	 * @param string $tag
	 *
	 * @return mixed|string
	 */
	public static function remove_empty_spaces( $content, string $tag ) {
		if ( ! Helper::is_fse_theme() || ( ! str_starts_with( $tag, 'storeengine_' ) && ! str_starts_with( $tag, 'academy_' ) ) ) {
			return $content;
		}

		return Formatting::clean_html_whitespaces( (string) $content );
	}

	public static function save_post( $post_id, WP_Post $post ) {
		wp_cache_set( 'storeengine:get_page_by_title:' . sanitize_title( $post->post_title ), absint( $post_id ), $post->post_type );
	}

	public static function delete_post( $post_id, WP_Post $post ) {
		wp_cache_delete( 'storeengine:get_page_by_title:' . sanitize_title( $post->post_title ), $post->post_type );
	}
}
