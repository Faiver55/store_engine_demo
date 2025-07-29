<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Issuing;

/**
 * A Physical Bundle represents the bundle of physical items - card stock, carrier letter, and envelope - that is shipped to a cardholder when you create a physical card.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \StoreEngine\Stripe\StripeObject $features
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property string $name Friendly display name.
 * @property string $status Whether this physical bundle can be used to create cards.
 * @property string $type Whether this physical bundle is a standard Stripe offering or custom-made for you.
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class PhysicalBundle extends \StoreEngine\Stripe\ApiResource
{
    const OBJECT_NAME = 'issuing.physical_bundle';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_REVIEW = 'review';

    const TYPE_CUSTOM = 'custom';
    const TYPE_STANDARD = 'standard';

    /**
     * Returns a list of physical bundle objects. The objects are sorted in descending
     * order by creation date, with the most recently created object appearing first.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Issuing\PhysicalBundle> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \StoreEngine\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a physical bundle object.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Issuing\PhysicalBundle
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \StoreEngine\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }
}
