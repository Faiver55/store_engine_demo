<?php

namespace StoreEngine\Addons\Subscription;

use StoreEngine\Addons\Subscription\Ajax\UpdateStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ajax {

	protected array $ajax_classes = [
		UpdateStatus::class,
	];

	public static function init(): void {
		$self = new self();
		$self->dispatch();
	}

	public function dispatch(): void {
		foreach ( $this->ajax_classes as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->dispatch_actions();
			}
		}
	}
}
