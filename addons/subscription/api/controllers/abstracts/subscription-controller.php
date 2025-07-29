<?php

namespace StoreEngine\Addons\Subscription\API\Controllers\Abstracts;

use StoreEngine\API\AbstractRestApiController;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class SubscriptionController extends AbstractRestApiController {

	protected $rest_base = 'subscription';

	protected string $route;

	protected array $ignore_action = [];

	protected array $available_actions = [
		'read'   => WP_REST_Server::READABLE,
		'create' => WP_REST_Server::CREATABLE,
		'edit'   => WP_REST_Server::EDITABLE,
		'delete' => WP_REST_Server::DELETABLE,
	];

	public static function init() {
		$self = new static();
		$self->register_routes();
	}

	public function register_routes(): void {
		$route = implode( '/', [ $this->rest_base, $this->route ] );
		register_rest_route( $this->namespace, $route, array_merge( [ 'args' => $this->args() ], $this->handlers() ) );
	}

	abstract protected function args(): array;

	abstract protected function permission_check(): bool;

	protected function handlers(): array {
		$handlers = [];
		foreach ( $this->available_actions as $action => $method ) {
			if ( method_exists( $this, $action ) && ! in_array( $action, $this->ignore_action, true ) ) {
				$permission = method_exists( $this, "{$action}_permission_check" ) ? [ $this, "{$action}_permission_check" ] : 'permission_check';
				$args       = method_exists( $this, "{$action}_args" ) ? [ $this, "{$action}_args" ]() : [];
				$handlers[] = [
					'methods'             => $method,
					'callback'            => [ $this, $action ],
					'permission_callback' => [ $this, $permission ],
					'args'                => $args,
				];
			}
		}

		return $handlers;
	}
}

