<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Treasury;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class FinancialAccountService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of FinancialAccounts.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Treasury\FinancialAccount>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/treasury/financial_accounts', $params, $opts);
    }

    /**
     * Closes a FinancialAccount. A FinancialAccount can only be closed if it has a
     * zero balance, has no pending InboundTransfers, and has canceled all attached
     * Issuing cards.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccount
     */
    public function close($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/treasury/financial_accounts/%s/close', $id), $params, $opts);
    }

    /**
     * Creates a new FinancialAccount. For now, each connected account can only have
     * one FinancialAccount.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccount
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/treasury/financial_accounts', $params, $opts);
    }

    /**
     * Retrieves the details of a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccount
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/treasury/financial_accounts/%s', $id), $params, $opts);
    }

    /**
     * Retrieves Features information associated with the FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccountFeatures
     */
    public function retrieveFeatures($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/treasury/financial_accounts/%s/features', $id), $params, $opts);
    }

    /**
     * Updates the details of a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccount
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/treasury/financial_accounts/%s', $id), $params, $opts);
    }

    /**
     * Updates the Features associated with a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Treasury\FinancialAccountFeatures
     */
    public function updateFeatures($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/treasury/financial_accounts/%s/features', $id), $params, $opts);
    }
}
