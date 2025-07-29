<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Apps;

/**
 * Service factory class for API resources in the Apps namespace.
 *
 * @property SecretService $secrets
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class AppsServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'secrets' => SecretService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
