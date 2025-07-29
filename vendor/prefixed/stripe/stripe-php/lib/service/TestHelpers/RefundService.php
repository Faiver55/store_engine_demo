<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\TestHelpers;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class RefundService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Expire a refund with a status of <code>requires_action</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Refund
     */
    public function expire($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/refunds/%s/expire', $id), $params, $opts);
    }
}
