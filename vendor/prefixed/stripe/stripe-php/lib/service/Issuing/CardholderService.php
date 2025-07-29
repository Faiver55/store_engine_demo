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
class CardholderService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of Issuing <code>Cardholder</code> objects. The objects are
     * sorted in descending order by creation date, with the most recently created
     * object appearing first.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Issuing\Cardholder>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/issuing/cardholders', $params, $opts);
    }

    /**
     * Creates a new Issuing <code>Cardholder</code> object that can be issued cards.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Cardholder
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/issuing/cardholders', $params, $opts);
    }

    /**
     * Retrieves an Issuing <code>Cardholder</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Cardholder
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/issuing/cardholders/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified Issuing <code>Cardholder</code> object by setting the
     * values of the parameters passed. Any parameters not provided will be left
     * unchanged.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\Cardholder
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/issuing/cardholders/%s', $id), $params, $opts);
    }
}
