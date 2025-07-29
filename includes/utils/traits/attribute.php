<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Classes\Attributes;

trait Attribute {

	public static function get_product_attribute( int $attribute_id ) {
		return ( new \StoreEngine\Classes\Attribute( $attribute_id ) )->get();
	}

	/**
	 * @param array $args
	 *
	 * @return \StoreEngine\Classes\Attribute[]
	 */
	public static function get_product_attributes( array $args = [] ): array {
		$args = wp_parse_args( $args, [
			'per_page' => 10,
			'page'     => 1,
			'search'   => '',
		] );

		return ( new Attributes( $args['page'], $args['per_page'] ) )->get( $args['search'] );
	}

	public static function get_total_product_attributes_count( string $search = '' ): int {
		return ( new Attributes() )->get_total_count( $search );
	}

	public static function delete_product_attribute( int $attribute_id ): bool {
		$attribute = self::get_product_attribute( $attribute_id );
		if ( ! $attribute ) {
			return false;
		}

		return $attribute->delete();
	}

	public static function get_attribute_taxonomy_name( string $name ): string {
		// @TODO add migration for old prefix
		//return STOREENGINE_PLUGIN_SLUG . '_pa_' . $name;
		//return 'storeengine_pa_' . $name;
		return 'se_pa_' . $name;
	}

	public static function strip_attribute_taxonomy_name( string $name ): string {
		return preg_replace( '/^se_pa\_/', '', $name );
	}

	/**
	 * Sanitize taxonomy names. Slug format (no spaces, lowercase).
	 * Urldecode is used to reverse munging of UTF8 characters.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	public static function sanitize_taxonomy_name( string $taxonomy ): string {
		return apply_filters( 'storeengine/sanitize_taxonomy_name', urldecode( sanitize_title( urldecode( $taxonomy ) ) ), $taxonomy );
	}

	/**
	 * Check if attribute name is reserved.
	 * https://codex.wordpress.org/Function_Reference/register_taxonomy#Reserved_Terms.
	 *
	 * @param string $attribute_name Attribute name.
	 *
	 * @return bool
	 */
	public static function check_if_attribute_name_is_reserved( string $attribute_name ): bool {
		// Forbidden attribute names.
		$reserved_terms = [
			'attachment',
			'attachment_id',
			'author',
			'author_name',
			'calendar',
			'cat',
			'category',
			'category__and',
			'category__in',
			'category__not_in',
			'category_name',
			'comments_per_page',
			'comments_popup',
			'cpage',
			'day',
			'debug',
			'error',
			'exact',
			'feed',
			'hour',
			'link_category',
			'm',
			'minute',
			'monthnum',
			'more',
			'name',
			'nav_menu',
			'nopaging',
			'offset',
			'order',
			'orderby',
			'p',
			'page',
			'page_id',
			'paged',
			'pagename',
			'pb',
			'perm',
			'post',
			'post__in',
			'post__not_in',
			'post_format',
			'post_mime_type',
			'post_status',
			'post_tag',
			'post_type',
			'posts',
			'posts_per_archive_page',
			'posts_per_page',
			'preview',
			'robots',
			's',
			'search',
			'second',
			'sentence',
			'showposts',
			'static',
			'subpost',
			'subpost_id',
			'tag',
			'tag__and',
			'tag__in',
			'tag__not_in',
			'tag_id',
			'tag_slug__and',
			'tag_slug__in',
			'taxonomy',
			'tb',
			'term',
			'type',
			'w',
			'withcomments',
			'withoutcomments',
			'year',
		];

		return in_array( $attribute_name, $reserved_terms, true );
	}
}
