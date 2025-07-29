<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<a href="<?php echo esc_url( Helper::get_checkout_url() ); ?>" class="storeengine-btn storeengine-btn--proceed-to-checkout"><?php esc_html_e( 'Proceed to checkout', 'storeengine' ); ?></a>
