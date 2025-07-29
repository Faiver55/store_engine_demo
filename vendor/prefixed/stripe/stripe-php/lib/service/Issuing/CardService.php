<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Issuing;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class CardService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of Issuing <code>Card</code> objects. The objects are sorted in
     * descending order by creation date, with the most recently created object
     * appearing first.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Issuing\Card>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/issuing/cards', $params, $opts);
    }

    /**
     * Creates an Issuing <code>Card</code> object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Card
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/issuing/cards', $params, $opts);
    }

    /**
     * Retrieves an Issuing <code>Card</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Card
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/issuing/cards/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified Issuing <code>Card</code> object by setting the values of
     * the parameters passed. Any parameters not provided will be left unchanged.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Card
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/issuing/cards/%s', $id), $params, $opts);
    }
}
