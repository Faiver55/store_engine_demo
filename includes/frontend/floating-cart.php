<?php

namespace StoreEngine\Frontend;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FloatingCart {
	use Singleton;

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'display_cart_button' ] );
	}

	public function display_cart_button() {
		$count = storeengine_cart()->get_count();
		if ( 0 === $count || Helper::is_cart() ) {
			return;
		}
		$cart_page = Helper::get_page_permalink('cart_page');
		?>
		<a href="<?php echo esc_url($cart_page); ?>" class="storeengine-floating-cart">
			<div class="storeengine-floating-cart-button">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
					<path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
				</svg>
				<span class="storeengine-floating-cart-count"><?php echo esc_html($count); ?></span>
			</div>
		</a>
		<?php
	}
}
