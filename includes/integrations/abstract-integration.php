<?php

namespace StoreEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;

abstract class AbstractIntegration {
	protected static $integrations = [];
	protected $id;
	protected $label;
	protected $logo;

	public function __construct() {
		$this->id    = $this->get_id();
		$this->label = $this->get_label();
		$this->logo  = $this->get_logo();
		$this->dispatch_hooks();
		self::$integrations[ $this->id ] = $this;
	}

	public static function get_integration( $id ) {
		return self::$integrations[ $id ] ?? null;
	}

	abstract public function get_id();

	abstract public function get_label();

	abstract public function get_logo();

	abstract public function enabled();

	abstract public function get_items_label();

	abstract public function get_items( array $args = [] );

	abstract public function get_item();

	abstract protected function purchase_created( Integration $integration, Order $order );

	public function dispatch_hooks() {
		if ( $this->enabled() ) {
			add_action( 'storeengine/order/payment_status_changed', [ $this, 'order_payment_status_changed' ], 10, 2 );
			add_action( 'storeengine/subscription/status_updated', [
				$this,
				'handle_subscription_status_changed',
			], 10, 2 );
		}
	}

	public function handle_subscription_status_changed( Subscription $subscription, string $new_status ) {
		if ( 'active' === $new_status ) {
			$this->handle_subscription_paid_status( $subscription, $new_status );

			return;
		}

		$this->handle_subscription_unpaid_status( $subscription, $new_status );
	}

	protected function handle_subscription_paid_status( Subscription $subscription, string $new_status ) {
	}

	protected function handle_subscription_unpaid_status( Subscription $subscription, string $new_status ) {
	}

	public function order_payment_status_changed( Order $order, string $status ) {
		if ( 'paid' === $status ) {
			$this->handle_order_paid_status( $order, $status );

			return;
		}

		$unpaid_statuses = [ 'unpaid', 'failed', 'on_hold' ];
		if ( ! in_array( $status, $unpaid_statuses, true ) ) {
			return;
		}
		$this->handle_order_unpaid_status( $order, $status );
	}

	protected function handle_order_paid_status( Order $order, string $status ) {
		foreach ( $order->get_items( 'line_item' ) as $order_item ) {
			/** @var OrderItemProduct $order_item */
			$this->run_integration( $order_item, $order );
		}
	}

	protected function handle_order_unpaid_status( Order $order, string $status ) {
	}

	/**
	 * @param int $order_id
	 * @param string $old_status
	 * @param string $new_status
	 * @param Order $order
	 *
	 * @return void
	 * @deprecated used via storeengine/order/status_changed hook.
	 */
	public function order_status_changed( int $order_id, string $old_status, string $new_status, Order $order ) {
		$successful_order_statuses = [
			Constants::ORDER_STATUS_PAYMENT_CONFIRMED,
			Constants::ORDER_STATUS_PROCESSING,
			Constants::ORDER_STATUS_COMPLETED,
		];

		$pending_statuses = [
			OrderStatus::AUTO_DRAFT,
			OrderStatus::DRAFT,
			OrderStatus::ON_HOLD,
			OrderStatus::PAYMENT_PENDING,
		];

		// Don't run while order is restoring from trash.
		if ( in_array( $new_status, $successful_order_statuses, true ) && in_array( $old_status, $pending_statuses, true ) ) {
			foreach ( $order->get_items( 'line_item' ) as $order_item ) {
				/** @var OrderItemProduct $order_item */
				$this->run_integration( $order_item, $order );
			}
		}

		$cancelling_statuses = [
			OrderStatus::CANCELLED,
			OrderStatus::REFUNDED,
			OrderStatus::AUTO_DRAFT,
			OrderStatus::DRAFT,
			OrderStatus::PAYMENT_PENDING,
			OrderStatus::PAYMENT_FAILED,
		];

		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		if ( in_array( $new_status, $cancelling_statuses, true ) && in_array( $old_status, $successful_order_statuses, true ) ) {
			// @TODO trigger integrations and let them know order got canceled.
			//       So integrations like A.LMS can remove course access.
			// @TASK https://workspace.kodezen.com/wp-admin/admin.php?page=fluent-boards#/boards/4/tasks/4089
		}
	}

	public function run_integration( OrderItemProduct $order_item, Order $order ) {
		if ( 'subscription' === $order_item->get_price_type() ) {
			return;
		}
		$integrations = Helper::get_integrations_by_price_id( $order_item->get_price_id() );

		$handle_outside = apply_filters( 'storeengine/integrations/run_integration_outside', false, $integrations, $order_item );
		if ( $handle_outside ) {
			return;
		}

		foreach ( $integrations as $integration ) {
			if ( $integration->get_provider() !== $this->get_id() ) {
				continue;
			}
			$this->purchase_created( $integration, $order );
		}
	}

	/**
	 * @param $integration_id
	 *
	 * @return \StoreEngine\Classes\Data\IntegrationRepositoryData[]
	 */
	public function get_integration_repository( $integration_id ) {
		return Helper::get_integration_repository_by_id( $this->get_id(), $integration_id );
	}
}
