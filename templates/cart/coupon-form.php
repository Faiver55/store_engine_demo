<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<form id="storeengine-ajax-apply-coupon-form" action="#" class="storeengine-ajax-apply-coupon-form" method="post">
	<input type="text" name="coupon_code" placeholder="<?php esc_attr_e( 'Coupon Code', 'storeengine' ); ?>" aria-label="<?php esc_attr_e( 'Coupon Code', 'storeengine' ); ?>"/>
	<input type="submit" name="apply_coupon" value="<?php esc_attr_e( 'Apply', 'storeengine' ); ?>" aria-label="<?php esc_attr_e( 'Apply Coupon', 'storeengine' ); ?>"/>
</form>
