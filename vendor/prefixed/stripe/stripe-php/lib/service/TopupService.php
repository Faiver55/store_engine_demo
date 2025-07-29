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
class TopupService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of top-ups.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Topup>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/topups', $params, $opts);
    }

    /**
     * Cancels a top-up. Only pending top-ups can be canceled.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Topup
     */
    public function cancel($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/topups/%s/cancel', $id), $params, $opts);
    }

    /**
     * Top up the balance of an account.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Topup
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/topups', $params, $opts);
    }

    /**
     * Retrieves the details of a top-up that has previously been created. Supply the
     * unique top-up ID that was returned from your previous request, and Stripe will
     * return the corresponding top-up information.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Topup
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/topups/%s', $id), $params, $opts);
    }

    /**
     * Updates the metadata of a top-up. Other top-up details are not editable by
     * design.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Topup
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/topups/%s', $id), $params, $opts);
    }
}
