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
class PersonalizationDesignService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of personalization design objects. The objects are sorted in
     * descending order by creation date, with the most recently created object
     * appearing first.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Issuing\PersonalizationDesign>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/issuing/personalization_designs', $params, $opts);
    }

    /**
     * Creates a personalization design object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/issuing/personalization_designs', $params, $opts);
    }

    /**
     * Retrieves a personalization design object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/issuing/personalization_designs/%s', $id), $params, $opts);
    }

    /**
     * Updates a card personalization object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/issuing/personalization_designs/%s', $id), $params, $opts);
    }
}
