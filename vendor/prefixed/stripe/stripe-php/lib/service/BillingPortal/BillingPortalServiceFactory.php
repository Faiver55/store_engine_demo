<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\BillingPortal;

/**
 * Service factory class for API resources in the BillingPortal namespace.
 *
 * @property ConfigurationService $configurations
 * @property SessionService $sessions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class BillingPortalServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'configurations' => ConfigurationService::class,
        'sessions' => SessionService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
