<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Events;

/**
 * @property \StoreEngine\Stripe\RelatedObject $related_object Object containing the reference to API resource relevant to the event
 * @property \StoreEngine\Stripe\EventData\V1BillingMeterErrorReportTriggeredEventData $data data associated with the event
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class V1BillingMeterErrorReportTriggeredEvent extends \StoreEngine\Stripe\V2\Event
{
    const LOOKUP_TYPE = 'v1.billing.meter.error_report_triggered';

    /**
     * Retrieves the related object from the API. Make an API request on every call.
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function fetchRelatedObject()
    {
        $apiMode = \StoreEngine\Stripe\Util\Util::getApiMode($this->related_object->url);
        list($object, $options) = $this->_request(
            'get',
            $this->related_object->url,
            [],
            ['stripe_account' => $this->context],
            [],
            $apiMode
        );

        return \StoreEngine\Stripe\Util\Util::convertToStripeObject($object, $options, $apiMode);
    }

    public static function constructFrom($values, $opts = null, $apiMode = 'v2')
    {
        $evt = parent::constructFrom($values, $opts, $apiMode);
        if (null !== $evt->data) {
            $evt->data = \StoreEngine\Stripe\EventData\V1BillingMeterErrorReportTriggeredEventData::constructFrom($evt->data, $opts, $apiMode);
        }

        return $evt;
    }
}
