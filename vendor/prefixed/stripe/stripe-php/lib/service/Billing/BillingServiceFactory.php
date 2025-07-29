<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Billing;

/**
 * Service factory class for API resources in the Billing namespace.
 *
 * @property AlertService $alerts
 * @property CreditBalanceSummaryService $creditBalanceSummary
 * @property CreditBalanceTransactionService $creditBalanceTransactions
 * @property CreditGrantService $creditGrants
 * @property MeterEventAdjustmentService $meterEventAdjustments
 * @property MeterEventService $meterEvents
 * @property MeterService $meters
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class BillingServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'alerts' => AlertService::class,
        'creditBalanceSummary' => CreditBalanceSummaryService::class,
        'creditBalanceTransactions' => CreditBalanceTransactionService::class,
        'creditGrants' => CreditGrantService::class,
        'meterEventAdjustments' => MeterEventAdjustmentService::class,
        'meterEvents' => MeterEventService::class,
        'meters' => MeterService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
