<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe\Service;

/**
 * Abstract base class for all services.
 */
abstract class AbstractService
{
    /**
     * @var \StoreEngine\Stripe\StripeClientInterface
     */
    protected $client;

    /**
     * @var \StoreEngine\Stripe\StripeStreamingClientInterface
     */
    protected $streamingClient;

    /**
     * Initializes a new instance of the {@link AbstractService} class.
     *
     * @param \StoreEngine\Stripe\StripeClientInterface $client
     */
    public function __construct($client)
    {
        $this->client = $client;
        $this->streamingClient = $client;
    }

    /**
     * Gets the client used by this service to send requests.
     *
     * @return \StoreEngine\Stripe\StripeClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Gets the client used by this service to send requests.
     *
     * @return \StoreEngine\Stripe\StripeStreamingClientInterface
     */
    public function getStreamingClient()
    {
        return $this->streamingClient;
    }

    /**
     * Translate null values to empty strings. For service methods,
     * we interpret null as a request to unset the field, which
     * corresponds to sending an empty string for the field to the
     * API.
     *
     * @param null|array $params
     */
    private static function formatParams($params)
    {
        if (null === $params) {
            return null;
        }
        \array_walk_recursive($params, function (&$value, $key) {
            if (null === $value) {
                $value = '';
            }
        });

        return $params;
    }

    protected function request($method, $path, $params, $opts)
    {
        return $this->getClient()->request($method, $path, self::formatParams($params), $opts);
    }

    protected function requestStream($method, $path, $readBodyChunkCallable, $params, $opts)
    {
        // TODO (MAJOR): Add this method to StripeClientInterface
        // @phpstan-ignore-next-line
        return $this->getStreamingClient()->requestStream($method, $path, $readBodyChunkCallable, self::formatParams($params), $opts);
    }

    protected function requestCollection($method, $path, $params, $opts)
    {
        // TODO (MAJOR): Add this method to StripeClientInterface
        // @phpstan-ignore-next-line
        return $this->getClient()->requestCollection($method, $path, self::formatParams($params), $opts);
    }

    protected function requestSearchResult($method, $path, $params, $opts)
    {
        // TODO (MAJOR): Add this method to StripeClientInterface
        // @phpstan-ignore-next-line
        return $this->getClient()->requestSearchResult($method, $path, self::formatParams($params), $opts);
    }

    protected function buildPath($basePath, ...$ids)
    {
        foreach ($ids as $id) {
            if (null === $id || '' === \trim($id)) {
                $msg = 'The resource ID cannot be null or whitespace.';

                throw new \StoreEngine\Stripe\Exception\InvalidArgumentException($msg);
            }
        }

        return \sprintf($basePath, ...\array_map('\urlencode', $ids));
    }
}
