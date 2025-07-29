<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Terminal;

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
     * Returns a list of <code>Configuration</code> objects.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Terminal\Configuration>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/terminal/configurations', $params, $opts);
    }

    /**
     * Creates a new <code>Configuration</code> object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Terminal\Configuration
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/terminal/configurations', $params, $opts);
    }

    /**
     * Deletes a <code>Configuration</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Terminal\Configuration
     */
    public function delete($id, $params = null, $opts = null)
    {
        return $this->request('delete', $this->buildPath('/v1/terminal/configurations/%s', $id), $params, $opts);
    }

    /**
     * Retrieves a <code>Configuration</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Terminal\Configuration
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/terminal/configurations/%s', $id), $params, $opts);
    }

    /**
     * Updates a new <code>Configuration</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Terminal\Configuration
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/terminal/configurations/%s', $id), $params, $opts);
    }
}
