<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class CreditBalanceSummaryService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Retrieves the credit balance summary for a customer.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditBalanceSummary
     */
    public function retrieve($params = null, $opts = null)
    {
        return $this->request('get', '/v1/billing/credit_balance_summary', $params, $opts);
    }
}
