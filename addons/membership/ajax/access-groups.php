<?php

namespace StoreEngine\Addons\Membership\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;

class AccessGroups extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'get_excluded_items_list' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_excluded_items_list' ],
				'fields'     => [
					'include' => 'array-string',
					'keyword' => 'string',
				],
			],
		];
	}

	public function get_excluded_items_list( $payload ) {
		$post_types = ! empty( $payload['include'] ) ? $payload['include'] : [];
		$keyword    = ! empty( $payload['keyword'] ) ? $payload['keyword'] : '';

		wp_send_json_success( $this->get_excluded_contents( $post_types, $keyword ) );
	}

	protected function get_excluded_contents( array $post_types = [], string $keyword = '' ): array {
		$results = [];

		foreach ( $post_types as $post_type ) {
			if ( 'basic-global' === $post_type ) {
				$results = $this->get_entire_website_contents( $keyword );
			}
			if ( 'post|all|taxarchive|category' === $post_type ) {
				$results = $this->get_all_taxonomies_content( $keyword );
			}
			if ( 'post|all|taxarchive|post_tag' === $post_type ) {
				$results = $this->get_all_tags_contents( $keyword );
			}
			if ( 'post|all' === $post_type ) {
				$results = $this->get_all_posts_contents( $keyword );
			}
			if ( 'page|all' === $post_type ) {
				$results = $this->get_all_pages_contents( $keyword );
			}
			if ( 'specifics' === $post_type ) {
				$results = $this->get_entire_website_contents( $keyword );
			}
		}

		return $results;
	}

	protected function get_entire_website_contents( string $keyword = '' ): array {
		return array_merge(
			$this->get_all_taxonomies_content( $keyword ),
			$this->get_all_posts_contents( $keyword ),
			$this->get_all_pages_contents( $keyword ),
			$this->get_all_tags_contents( $keyword )
		);
	}

	protected function get_all_taxonomies_content( string $keyword = '' ): array {
		$taxonomies = get_taxonomies();
		$terms      = get_terms( [
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'search'     => $keyword,
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return array_map( function ( $term ) {
			return [
				'value' => 'tax-' . $term->term_id . '--single-' . $term->taxonomy,
				'label' => $term->name . ' - ' . ucfirst( $term->taxonomy ),
			];
		}, $terms );
	}

	protected function get_all_posts_contents( string $keyword = '' ): array {
		$post_types = get_post_types();
		unset( $post_types['page'] );

		$args = array(
			'post_type'      => $post_types,
			'posts_per_page' => - 1,
		);

		if ( ! empty( $keyword ) ) {
			$args['s'] = $keyword;
		}

		$results = array();

		$posts = get_posts( $args );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$post_type_label = str_replace( '_', ' ', $post->post_type );
				$results[]       = array(
					'value' => 'post-' . $post->ID . '-|',
					'label' => $post->post_title . ' - ' . $post_type_label,
				);
			}
		}

		return $results;
	}

	protected function get_all_pages_contents( string $keyword = '' ): array {
		$args = [
			'post_type'      => 'page',
			'posts_per_page' => - 1,
		];

		if ( ! empty( $keyword ) ) {
			$args['s'] = $keyword;
		}

		$results = array();

		$posts = get_posts( $args );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$results[] = array(
					'value' => 'post-' . $post->ID . '-|',
					'label' => $post->post_title . ' - page',
				);
			}
		}

		return $results;
	}

	protected function get_all_tags_contents( string $keyword = '' ) {
		$args = [
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
			'search'     => $keyword,
		];

		$tags = get_tags( $args );

		$tags_array = array();
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tags_array[] = array(
					'value' => 'tax-' . $tag->term_id . '-single-post_tag',
					'label' => $tag->name . ' - tag',
				);
			}
		}

		return $tags_array;
	}
}
