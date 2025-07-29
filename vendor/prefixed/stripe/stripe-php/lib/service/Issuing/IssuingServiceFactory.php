<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Issuing;

/**
 * Service factory class for API resources in the Issuing namespace.
 *
 * @property AuthorizationService $authorizations
 * @property CardholderService $cardholders
 * @property CardService $cards
 * @property DisputeService $disputes
 * @property PersonalizationDesignService $personalizationDesigns
 * @property PhysicalBundleService $physicalBundles
 * @property TokenService $tokens
 * @property TransactionService $transactions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class IssuingServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'authorizations' => AuthorizationService::class,
        'cardholders' => CardholderService::class,
        'cards' => CardService::class,
        'disputes' => DisputeService::class,
        'personalizationDesigns' => PersonalizationDesignService::class,
        'physicalBundles' => PhysicalBundleService::class,
        'tokens' => TokenService::class,
        'transactions' => TransactionService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
