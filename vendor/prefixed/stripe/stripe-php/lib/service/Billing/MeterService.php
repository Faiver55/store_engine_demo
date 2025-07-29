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
class MeterService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of billing meters.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Billing\Meter>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/billing/meters', $params, $opts);
    }

    /**
     * Retrieve a list of billing meter event summaries.
     *
     * @param string $parentId
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Billing\MeterEventSummary>
     */
    public function allEventSummaries($parentId, $params = null, $opts = null)
    {
        return $this->requestCollection('get', $this->buildPath('/v1/billing/meters/%s/event_summaries', $parentId), $params, $opts);
    }

    /**
     * Creates a billing meter.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/billing/meters', $params, $opts);
    }

    /**
     * When a meter is deactivated, no more meter events will be accepted for this
     * meter. You can’t attach a deactivated meter to a price.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function deactivate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s/deactivate', $id), $params, $opts);
    }

    /**
     * When a meter is reactivated, events for this meter can be accepted and you can
     * attach the meter to a price.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function reactivate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s/reactivate', $id), $params, $opts);
    }

    /**
     * Retrieves a billing meter given an ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/billing/meters/%s', $id), $params, $opts);
    }

    /**
     * Updates a billing meter.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\Meter
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s', $id), $params, $opts);
    }
}
