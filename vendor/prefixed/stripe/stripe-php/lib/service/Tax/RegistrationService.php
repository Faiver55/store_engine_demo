<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Tax;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class RegistrationService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of Tax <code>Registration</code> objects.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Tax\Registration>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/tax/registrations', $params, $opts);
    }

    /**
     * Creates a new Tax <code>Registration</code> object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Tax\Registration
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/tax/registrations', $params, $opts);
    }

    /**
     * Returns a Tax <code>Registration</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Tax\Registration
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/tax/registrations/%s', $id), $params, $opts);
    }

    /**
     * Updates an existing Tax <code>Registration</code> object.
     *
     * A registration cannot be deleted after it has been created. If you wish to end a
     * registration you may do so by setting <code>expires_at</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Tax\Registration
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/tax/registrations/%s', $id), $params, $opts);
    }
}
