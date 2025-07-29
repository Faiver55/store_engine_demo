<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\TestHelpers\Issuing;

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
     * Updates the <code>status</code> of the specified testmode personalization design
     * object to <code>active</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function activate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/activate', $id), $params, $opts);
    }

    /**
     * Updates the <code>status</code> of the specified testmode personalization design
     * object to <code>inactive</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function deactivate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/deactivate', $id), $params, $opts);
    }

    /**
     * Updates the <code>status</code> of the specified testmode personalization design
     * object to <code>rejected</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PersonalizationDesign
     */
    public function reject($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/reject', $id), $params, $opts);
    }
}
