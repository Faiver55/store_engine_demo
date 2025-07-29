<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe\HttpClient;

interface StreamingClientInterface
{
    /**
     * @param 'delete'|'get'|'post' $method The HTTP method being used
     * @param string $absUrl The URL being requested, including domain and protocol
     * @param array $headers Headers to be used in the request (full strings, not KV pairs)
     * @param array $params KV pairs for parameters. Can be nested for arrays and hashes
     * @param bool $hasFile Whether or not $params references a file (via an @ prefix or
     *                         CURLFile)
     * @param callable $readBodyChunkCallable a function that will be called with chunks of bytes from the body if the request is successful
     *
     * @throws \StoreEngine\Stripe\Exception\ApiConnectionException
     * @throws \StoreEngine\Stripe\Exception\UnexpectedValueException
     *
     * @return array an array whose first element is raw request body, second
     *    element is HTTP status code and third array of HTTP headers
     */
    public function requestStream($method, $absUrl, $headers, $params, $hasFile, $readBodyChunkCallable);
}
