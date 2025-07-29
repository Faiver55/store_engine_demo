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
class ReviewService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of <code>Review</code> objects that have <code>open</code> set to
     * <code>true</code>. The objects are sorted in descending order by creation date,
     * with the most recently created object appearing first.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Review>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/reviews', $params, $opts);
    }

    /**
     * Approves a <code>Review</code> object, closing it and removing it from the list
     * of reviews.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Review
     */
    public function approve($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/reviews/%s/approve', $id), $params, $opts);
    }

    /**
     * Retrieves a <code>Review</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Review
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/reviews/%s', $id), $params, $opts);
    }
}
