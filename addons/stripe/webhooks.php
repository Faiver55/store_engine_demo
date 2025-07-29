<?php

namespace StoreEngine\Addons\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Webhooks {
	public static function push_webhook_to_stripe() {
		// check the option table if there is any webhook id stored
		// if not create a webhook and store the id in the option table
		// @TODO Store webhook secret received from stripe.
		//       If we have webhook id but no secret, delete and recreate it.
		//       Without the secret we can't verify the request.
		// @TODO implement webhook endpoint. the url below is doesn't exists.
		/** @see WC_Stripe_Webhook_Handler::validate_request */
		/** @see WC_Stripe_Webhook_Handler::process_webhook */
		// @link https://docs.stripe.com/api/webhook_endpoints
		if ( ! get_option( 'storeengine_stripe_webhook_id' ) ) {
			$stripe_service = StripeService::init();
			$events         = [
				'invoice.created',
				'invoice.payment_succeeded',
			];
			$api_url        = get_site_url() . '/wp-json/storeengine/v1/stripe/trigger';
			$webhook        = $stripe_service->create_webhook( $events, $api_url );
			update_option( 'storeengine_stripe_webhook_id', $webhook->id );
		}
	}
}
