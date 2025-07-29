<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Entitlements;

/**
 * Service factory class for API resources in the Entitlements namespace.
 *
 * @property ActiveEntitlementService $activeEntitlements
 * @property FeatureService $features
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class EntitlementsServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'activeEntitlements' => ActiveEntitlementService::class,
        'features' => FeatureService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
