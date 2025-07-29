<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Billing;

/**
 * Indicates the billing credit balance for billing credits granted to a customer.
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \StoreEngine\Stripe\StripeObject[] $balances The billing credit balances. One entry per credit grant currency. If a customer only has credit grants in a single currency, then this will have a single balance entry.
 * @property string|\StoreEngine\Stripe\Customer $customer The customer the balance is for.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class CreditBalanceSummary extends \StoreEngine\Stripe\SingletonApiResource
{
    const OBJECT_NAME = 'billing.credit_balance_summary';

    /**
     * Retrieves the credit balance summary for a customer.
     *
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditBalanceSummary
     */
    public static function retrieve($opts = null)
    {
        $opts = \StoreEngine\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static(null, $opts);
        $instance->refresh();

        return $instance;
    }
}
