<?php

namespace StoreEngine\Addons\Affiliate;

use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Hooks {
	public static function init() {
		$self = new self();
		add_action( 'storeengine/frontend/dashboard_affiliate-partner_endpoint', [ $self, 'dashboard_affiliate_partner_content' ] );
		add_filter( 'storeengine/frontend_dashboard_menu_items', [ $self, 'dashboard_menu_items' ] );
		add_filter( 'storeengine/admin_menu_list', [ $self, 'admin_menu_items' ] );
		add_filter( 'storeengine/api/settings', [ $self, 'integrate_affiliate_settings' ] );
		add_action( 'storeengine/order/payment_status_changed', [ $self, 'auto_approve_commission' ], 10, 2 );

		// Add affiliate pages to tools.
		add_filter( 'storeengine/settings/tools/pages', [ $self, 'add_pages_to_tools' ] );
		add_filter( 'display_post_states', array( $self, 'add_display_post_states' ), 10, 2 );
	}

	public function auto_approve_commission( Order $order, string $status ) {
		if ( ! HelperAddon::get_affiliate_setting('allow_auto_commission') && 'paid' !== $status ) {
			return;
		}

		$commission = Commission::get_commission( [ 'order_id' => $order->get_id() ] );

		if ( ! $commission || empty( $commission['commission_id'] ) ) {
			return;
		}

		$update = Commission::update( $commission['commission_id'], [ 'status' => 'approved' ] );

		if ( $update ) {
			HelperAddon::update_affiliate_commission( $commission['affiliate_id'], $commission['commission_amount'] );
		}
	}

	public function dashboard_menu_items( array $items ): array {
		return array_merge( $items, [
			'affiliate-partner' => [
				'label'    => __( 'Affiliate', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--affiliate',
				'public'   => current_user_can( 'storeengine_affiliate' ),
				'priority' => 15,
			],
			'payment-settings'  => [
				'label'    => __( 'Payment Settings', 'storeengine' ),
				'public'   => false,
				'priority' => 16,
			],
		] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-affiliates' => [
				'title'      => __( 'Affiliates', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 70,
				'sub_items'  => [
					[
						'slug'  => '',
						'title' => __( 'All Affiliates', 'storeengine' ),
					],
					[
						'slug'  => 'commissions',
						'title' => __( 'Commissions', 'storeengine' ),
					],
					[
						'slug'  => 'payouts',
						'title' => __( 'Payouts', 'storeengine' ),
					],
				],
			],
		] );
	}

	public function dashboard_affiliate_partner_content() {
		$user_id                 = get_current_user_id();
		$payment_history         = Payout::get_payouts([ 'user_id' => $user_id ]);
		$affiliate_data          = AffiliateModel::get_affiliates( [ 'user_id' => $user_id ] );
		$total_earning           = $affiliate_data ? $affiliate_data['total_commissions'] : 0;
		$available_balance       = $affiliate_data ? $affiliate_data['current_balance'] : 0;
		$selected_payment_method = get_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', true ) ?? '';
		$payment_settings_url    = storeengine_get_dashboard_endpoint_url( 'payment-settings' ) ?? '';
		$affiliate_settings      = Affiliate::get_settings_saved_data();
		$minimum_withdraw_amount = $affiliate_settings['minimum_withdraw_amount'] ?? 0;
		$show_withdraw_button    = (int) $available_balance > (int) $minimum_withdraw_amount;

		Template::get_template(
			'frontend-dashboard/pages/affiliate-partner.php',
			array(
				'total_amount'         => Formatting::price( $total_earning ),
				'available_amount'     => Formatting::price( $available_balance ),
				'withdraw_history'     => $payment_history,
				'withdraw_method_type' => $selected_payment_method,
				'current_user_id'      => $user_id,
				'payment_settings_url' => $payment_settings_url,
				'show_withdraw_button' => $show_withdraw_button,
			)
		);
	}

	public function integrate_affiliate_settings( $settings ) {
		$settings->affiliate = Affiliate::get_settings_saved_data();

		return $settings;
	}

	public function add_pages_to_tools( $pages ) {
		$pages['affiliate_registration_page'] = __( 'Store Affiliate Registration', 'storeengine' );

		return $pages;
	}

	public function add_display_post_states( $post_states, $post ) {
		if ( (int) Helper::get_settings( 'affiliate_registration_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_affiliate_registration'] = __( 'StoreEngine Affiliate Registration Page', 'storeengine' );
		}

		return $post_states;
	}
}
