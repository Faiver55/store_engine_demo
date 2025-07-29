<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Entitlements;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class FeatureService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of features.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Entitlements\Feature>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/entitlements/features', $params, $opts);
    }

    /**
     * Creates a feature.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Entitlements\Feature
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/entitlements/features', $params, $opts);
    }

    /**
     * Retrieves a feature.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Entitlements\Feature
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/entitlements/features/%s', $id), $params, $opts);
    }

    /**
     * Update a feature’s metadata or permanently deactivate it.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Entitlements\Feature
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/entitlements/features/%s', $id), $params, $opts);
    }
}
