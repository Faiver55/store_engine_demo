<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Checkout;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class SessionService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of Checkout Sessions.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Checkout\Session>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/checkout/sessions', $params, $opts);
    }

    /**
     * When retrieving a Checkout Session, there is an includable
     * <strong>line_items</strong> property containing the first handful of those
     * items. There is also a URL where you can retrieve the full (paginated) list of
     * line items.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\LineItem>
     */
    public function allLineItems($id, $params = null, $opts = null)
    {
        return $this->requestCollection('get', $this->buildPath('/v1/checkout/sessions/%s/line_items', $id), $params, $opts);
    }

    /**
     * Creates a Session object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Checkout\Session
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/checkout/sessions', $params, $opts);
    }

    /**
     * A Session can be expired when it is in one of these statuses: <code>open</code>.
     *
     * After it expires, a customer can’t complete a Session and customers loading the
     * Session see a message saying the Session is expired.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Checkout\Session
     */
    public function expire($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/checkout/sessions/%s/expire', $id), $params, $opts);
    }

    /**
     * Retrieves a Session object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Checkout\Session
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/checkout/sessions/%s', $id), $params, $opts);
    }

    /**
     * Updates a Session object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Checkout\Session
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/checkout/sessions/%s', $id), $params, $opts);
    }
}
