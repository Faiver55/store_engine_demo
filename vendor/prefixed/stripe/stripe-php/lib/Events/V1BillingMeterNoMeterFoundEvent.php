<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Events;

/**
 * @property \StoreEngine\Stripe\EventData\V1BillingMeterNoMeterFoundEventData $data data associated with the event
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class V1BillingMeterNoMeterFoundEvent extends \StoreEngine\Stripe\V2\Event
{
    const LOOKUP_TYPE = 'v1.billing.meter.no_meter_found';

    public static function constructFrom($values, $opts = null, $apiMode = 'v2')
    {
        $evt = parent::constructFrom($values, $opts, $apiMode);
        if (null !== $evt->data) {
            $evt->data = \StoreEngine\Stripe\EventData\V1BillingMeterNoMeterFoundEventData::constructFrom($evt->data, $opts, $apiMode);
        }

        return $evt;
    }
}
