<?php
namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Ajax\Settings;
use StoreEngine\Addons\Affiliate\Ajax\AffiliateAjax;
use StoreEngine\Addons\Affiliate\Ajax\ReferralAjax;
use StoreEngine\Addons\Affiliate\Ajax\ReferralTrackAjax;
use StoreEngine\Addons\Affiliate\Ajax\CommissionAjax;
use StoreEngine\Addons\Affiliate\Ajax\PayoutAjax;
use StoreEngine\Addons\Affiliate\Ajax\AffiliateReportAjax;

class Ajax {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}

	public function dispatch_hooks() {
		( new Settings() )->dispatch_actions();
		( new AffiliateAjax() )->dispatch_actions();
		( new ReferralAjax() )->dispatch_actions();
		( new ReferralTrackAjax() )->dispatch_actions();
		( new CommissionAjax() )->dispatch_actions();
		( new PayoutAjax() )->dispatch_actions();
		( new AffiliateReportAjax() )->dispatch_actions();
	}

}
