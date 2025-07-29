<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe\Exception\OAuth;

/**
 * InvalidClientException is thrown when the client_id does not belong to you,
 * the stripe_user_id does not exist or is not connected to your application,
 * or the API key mode (live or test mode) does not match the client_id mode.
 */
class InvalidClientException extends OAuthErrorException
{
}
