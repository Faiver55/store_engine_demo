<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe;

use StoreEngine\Stripe\Util\CaseInsensitiveArray;

/**
 * Class ApiResponse.
 */
class ApiResponse
{
    /**
     * @var null|array|CaseInsensitiveArray
     */
    public $headers;

    /**
     * @var string
     */
    public $body;

    /**
     * @var null|array
     */
    public $json;

    /**
     * @var int
     */
    public $code;

    /**
     * @param string $body
     * @param int $code
     * @param null|array|CaseInsensitiveArray $headers
     * @param null|array $json
     */
    public function __construct($body, $code, $headers, $json)
    {
        $this->body = $body;
        $this->code = $code;
        $this->headers = $headers;
        $this->json = $json;
    }
}
