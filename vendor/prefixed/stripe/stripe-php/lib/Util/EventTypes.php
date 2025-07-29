<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe\Util;

class EventTypes
{
    const thinEventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \StoreEngine\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::LOOKUP_TYPE => \StoreEngine\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::class,
        \StoreEngine\Stripe\Events\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \StoreEngine\Stripe\Events\V1BillingMeterNoMeterFoundEvent::class,
        // The end of the section generated from our OpenAPI spec
    ];
}
