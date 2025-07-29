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
class CreditBalanceTransactionService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of credit balance transactions.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Billing\CreditBalanceTransaction>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/billing/credit_balance_transactions', $params, $opts);
    }

    /**
     * Retrieves a credit balance transaction.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditBalanceTransaction
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/billing/credit_balance_transactions/%s', $id), $params, $opts);
    }
}
