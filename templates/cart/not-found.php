<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use StoreEngine\Utils\Helper;
?>
<div class="storeengine-cart-not-found">
	<i class="storeengine-icon storeengine-icon--eye"></i>
	<h2 class="storeengine-cart-not-found__heading"><?php esc_html_e( 'Your cart is currently empty!', 'storeengine' ); ?></h2>
	<div class="storeengine-cart-not-found__check-login">
		<?php
		if ( is_user_logged_in() ) : ?>
		<a class="storeengine-shop-page-link" href="<?php echo esc_url( Helper::get_page_permalink( 'shop_page' ) ); ?>"><?php esc_html_e( 'Shop Now', 'storeengine' ); ?></a>
		<?php else : ?>
			<p><?php esc_html_e( 'Login First Then Add To Cart', 'storeengine' ); ?></p>
			<a class="storeengine-login-page-link" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Login', 'storeengine' ); ?></a>
		<?php endif;
		?>
	</div>
</div>
