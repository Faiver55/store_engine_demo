<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\FinancialConnections;

/**
 * Service factory class for API resources in the FinancialConnections namespace.
 *
 * @property AccountService $accounts
 * @property SessionService $sessions
 * @property TransactionService $transactions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class FinancialConnectionsServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'accounts' => AccountService::class,
        'sessions' => SessionService::class,
        'transactions' => TransactionService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
