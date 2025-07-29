<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe\HttpClient;

interface ClientInterface
{
    /**
     * @param 'delete'|'get'|'post' $method The HTTP method being used
     * @param string $absUrl The URL being requested, including domain and protocol
     * @param array $headers Headers to be used in the request (full strings, not KV pairs)
     * @param array $params KV pairs for parameters. Can be nested for arrays and hashes
     * @param bool $hasFile Whether or not $params references a file (via an @ prefix or
     *                         CURLFile)
     * @param 'v1'|'v2' $apiMode Specifies if this is a v1 or v2 request
     *
     * @throws \StoreEngine\Stripe\Exception\ApiConnectionException
     * @throws \StoreEngine\Stripe\Exception\UnexpectedValueException
     *
     * @return array an array whose first element is raw request body, second
     *    element is HTTP status code and third array of HTTP headers
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1');
}
