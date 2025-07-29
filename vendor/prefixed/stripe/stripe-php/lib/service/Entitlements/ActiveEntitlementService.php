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
class ActiveEntitlementService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of active entitlements for a customer.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Entitlements\ActiveEntitlement>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/entitlements/active_entitlements', $params, $opts);
    }

    /**
     * Retrieve an active entitlement.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Entitlements\ActiveEntitlement
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/entitlements/active_entitlements/%s', $id), $params, $opts);
    }
}
