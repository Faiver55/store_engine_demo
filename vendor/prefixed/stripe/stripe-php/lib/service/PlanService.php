<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class PlanService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of your plans.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Plan>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/plans', $params, $opts);
    }

    /**
     * You can now model subscriptions more flexibly using the <a href="#prices">Prices
     * API</a>. It replaces the Plans API and is backwards compatible to simplify your
     * migration.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Plan
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/plans', $params, $opts);
    }

    /**
     * Deleting plans means new subscribers can’t be added. Existing subscribers aren’t
     * affected.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Plan
     */
    public function delete($id, $params = null, $opts = null)
    {
        return $this->request('delete', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }

    /**
     * Retrieves the plan with the given ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Plan
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified plan by setting the values of the parameters passed. Any
     * parameters not provided are left unchanged. By design, you cannot change a
     * plan’s ID, amount, currency, or billing cycle.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Plan
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }
}
