<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe;

/**
 * Account Links are the means by which a Connect platform grants a connected account permission to access
 * Stripe-hosted applications, such as Connect Onboarding.
 *
 * Related guide: <a href="https://stripe.com/docs/connect/custom/hosted-onboarding">Connect Onboarding</a>
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property int $expires_at The timestamp at which this account link will expire.
 * @property string $url The URL for the account link.
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class AccountLink extends ApiResource
{
    const OBJECT_NAME = 'account_link';

    /**
     * Creates an AccountLink object that includes a single-use Stripe URL that the
     * platform can redirect their user to in order to take them through the Connect
     * Onboarding flow.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\AccountLink the created resource
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
}
