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
class AlertService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Reactivates this alert, allowing it to trigger again.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Alert
     */
    public function activate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/alerts/%s/activate', $id), $params, $opts);
    }

    /**
     * Lists billing active and inactive alerts.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Billing\Alert>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/billing/alerts', $params, $opts);
    }

    /**
     * Archives this alert, removing it from the list view and APIs. This is
     * non-reversible.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Alert
     */
    public function archive($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/alerts/%s/archive', $id), $params, $opts);
    }

    /**
     * Creates a billing alert.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Alert
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/billing/alerts', $params, $opts);
    }

    /**
     * Deactivates this alert, preventing it from triggering.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Alert
     */
    public function deactivate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/alerts/%s/deactivate', $id), $params, $opts);
    }

    /**
     * Retrieves a billing alert given an ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Alert
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/billing/alerts/%s', $id), $params, $opts);
    }
}
