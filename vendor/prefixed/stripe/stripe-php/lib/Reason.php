<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe;

/**
 * @property string $id Unique identifier for the event.
 * @property string $idempotency_key
 */
class Reason
{
    public $id;
    public $idempotency_key;
}
