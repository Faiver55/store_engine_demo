<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\TestHelpers\Terminal;

/**
 * Service factory class for API resources in the Terminal namespace.
 *
 * @property ReaderService $readers
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class TerminalServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'readers' => ReaderService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
