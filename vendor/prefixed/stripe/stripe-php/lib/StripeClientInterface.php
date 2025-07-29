<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe;

/**
 * Interface for a Stripe client.
 */
interface StripeClientInterface extends BaseStripeClientInterface
{
    /**
     * Sends a request to Stripe's API.
     *
     * @param 'delete'|'get'|'post' $method the HTTP method
     * @param string $path the path of the request
     * @param array $params the parameters of the request
     * @param array|\StoreEngine\Stripe\Util\RequestOptions $opts the special modifiers of the request
     *
     * @return \StoreEngine\Stripe\StripeObject the object returned by Stripe's API
     */
    public function request($method, $path, $params, $opts);
}
