<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\FinancialConnections;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class SessionService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * To launch the Financial Connections authorization flow, create a
     * <code>Session</code>. The session’s <code>client_secret</code> can be used to
     * launch the flow using Stripe.js.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\FinancialConnections\Session
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/financial_connections/sessions', $params, $opts);
    }

    /**
     * Retrieves the details of a Financial Connections <code>Session</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\FinancialConnections\Session
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/financial_connections/sessions/%s', $id), $params, $opts);
    }
}
