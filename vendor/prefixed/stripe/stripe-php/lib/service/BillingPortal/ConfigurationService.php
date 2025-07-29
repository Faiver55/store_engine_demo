<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\BillingPortal;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class ConfigurationService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of configurations that describe the functionality of the customer
     * portal.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\BillingPortal\Configuration>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/billing_portal/configurations', $params, $opts);
    }

    /**
     * Creates a configuration that describes the functionality and behavior of a
     * PortalSession.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\BillingPortal\Configuration
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/billing_portal/configurations', $params, $opts);
    }

    /**
     * Retrieves a configuration that describes the functionality of the customer
     * portal.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\BillingPortal\Configuration
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/billing_portal/configurations/%s', $id), $params, $opts);
    }

    /**
     * Updates a configuration that describes the functionality of the customer portal.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\BillingPortal\Configuration
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing_portal/configurations/%s', $id), $params, $opts);
    }
}
