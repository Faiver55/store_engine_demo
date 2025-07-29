<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Billing;

/**
 * A credit grant is an API resource that documents the allocation of some billing credits to a customer.
 *
 * Related guide: <a href="https://docs.stripe.com/billing/subscriptions/usage-based/billing-credits">Billing credits</a>
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \StoreEngine\Stripe\StripeObject $amount
 * @property \StoreEngine\Stripe\StripeObject $applicability_config
 * @property string $category The category of this credit grant. This is for tracking purposes and isn't displayed to the customer.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string|\StoreEngine\Stripe\Customer $customer ID of the customer receiving the billing credits.
 * @property null|int $effective_at The time when the billing credits become effective-when they're eligible for use.
 * @property null|int $expires_at The time when the billing credits expire. If not present, the billing credits don't expire.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \StoreEngine\Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property null|string $name A descriptive name shown in dashboard.
 * @property null|int $priority The priority for applying this credit grant. The highest priority is 0 and the lowest is 100.
 * @property null|string|\StoreEngine\Stripe\TestHelpers\TestClock $test_clock ID of the test clock this credit grant belongs to.
 * @property int $updated Time at which the object was last updated. Measured in seconds since the Unix epoch.
 * @property null|int $voided_at The time when this credit grant was voided. If not present, the credit grant hasn't been voided.
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class CreditGrant extends \StoreEngine\Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.credit_grant';

    use \StoreEngine\Stripe\ApiOperations\Update;

    const CATEGORY_PAID = 'paid';
    const CATEGORY_PROMOTIONAL = 'promotional';

    /**
     * Creates a credit grant.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditGrant the created resource
     */
    public static function create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = \StoreEngine\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Retrieve a list of credit grants.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Billing\CreditGrant> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \StoreEngine\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a credit grant.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditGrant
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \StoreEngine\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates a credit grant.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditGrant the updated resource
     */
    public static function update($id, $params = null, $opts = null)
    {
        self::_validateParams($params);
        $url = static::resourceUrl($id);

        list($response, $opts) = static::_staticRequest('post', $url, $params, $opts);
        $obj = \StoreEngine\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditGrant the expired credit grant
     */
    public function expire($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/expire';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Billing\CreditGrant the voided credit grant
     */
    public function voidGrant($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/void';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
