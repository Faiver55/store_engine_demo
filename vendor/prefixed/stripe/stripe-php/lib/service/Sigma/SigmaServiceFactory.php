<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Sigma;

/**
 * Service factory class for API resources in the Sigma namespace.
 *
 * @property ScheduledQueryRunService $scheduledQueryRuns
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class SigmaServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'scheduledQueryRuns' => ScheduledQueryRunService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
