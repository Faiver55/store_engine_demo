<?php

namespace StoreEngine\Utils;

use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutUtils {
	use Singleton;
	protected static ?array $fields = null;


}

// End of file checkout-utils.php.
