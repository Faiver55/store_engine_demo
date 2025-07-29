<?php

namespace StoreEngine\Addons\Webhooks\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPublished {
	protected array $args = [
		'post_type'   => 'storeengine_webhook',
		'post_status' => 'publish',
		'numberposts' => -1,
	];

	public static function all(): array {
		$ins    = new self();
		$events = [];

		foreach ( $ins->all_published_webhooks() as $hook_data ) {
			$events[] = [
				'id'     => (int) $hook_data->ID,
				'events' => $ins->get_events( (int) $hook_data->ID ),
			];
		}

		return $events;
	}

	public function all_published_webhooks(): array {
		return get_posts( apply_filters( 'storeengine_webhooks_query_args', $this->args ) );
	}

	public function get_events( int $id ): array {
		$events = get_post_meta( $id, '_storeengine_webhook_events', true );

		if ( ! empty( $events ) && is_array( $events ) && is_array( $events[0] ) ) {
			// @TODO Unnecessary, as schema is changes and this suppose to be array.. Remove this before final release.
			$events = array_filter( array_unique( array_map( fn( $event ) => $event['value'] ?? null, $events ) ) );
			update_post_meta( $id, '_storeengine_webhook_events', $events );
		}

		return is_array( $events ) ? $events : [];
	}
}
