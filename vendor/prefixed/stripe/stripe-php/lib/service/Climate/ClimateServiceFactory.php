<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Climate;

/**
 * Service factory class for API resources in the Climate namespace.
 *
 * @property OrderService $orders
 * @property ProductService $products
 * @property SupplierService $suppliers
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class ClimateServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'orders' => OrderService::class,
        'products' => ProductService::class,
        'suppliers' => SupplierService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
