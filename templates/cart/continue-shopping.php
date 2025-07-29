<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
global $storeengine_settings;
$shop_url = get_permalink( $storeengine_settings->shop_page );
?>

<a href="<?php echo esc_url( $shop_url ?? '/' ); ?>" class="storeengine-btn storeengine-btn--continue-shopping"><?php esc_html_e( 'Continue Shopping', 'storeengine' ); ?></a>
