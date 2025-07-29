<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;

class Posts extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'fetch_posts' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_posts' ],
				'fields'     => [
					'postType' => 'string',
					'postId'   => 'id',
					'keyword'  => 'string',
				],
			],
		];
	}

	protected function fetch_posts( $payload ) {
		$post_type = ! empty( $payload['postType'] ) ? $payload['postType'] : 'page';
		$postId    = ! empty( $payload['postId'] ) ? $payload['postId'] : 0;
		$keyword   = ! empty( $payload['keyword'] ) ? $payload['keyword'] : '';

		if ( $postId ) {
			$args = [
				'post_type' => $post_type,
				'p'         => $postId,
			];
		} else {
			$args = [
				'post_type'      => $post_type,
				'posts_per_page' => 10,
			];
			if ( ! empty( $keyword ) ) {
				$args['s'] = $keyword;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				$args['author'] = get_current_user_id();
			}
		}

		$results = [];
		$posts   = get_posts( $args );

		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$results[] = [
					'value' => (int) esc_attr( $post->ID ),
					'label' => esc_html( $post->post_title ),
				];
			}
		}

		wp_send_json_success( $results );
	}
}
