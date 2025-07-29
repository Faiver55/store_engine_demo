<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @var string $item_key;
 * @var int $quantity;
 */
?>
<div class="storeengine-cart-product-quantity">
	<button type="button" class="storeengine-cart-product-quantity__btn storeengine-cart-product-quantity__btn--decrement" aria-label="<?php esc_attr_e( 'Decrease quantity by 1', 'storeengine' ); ?>"<?php disabled( $disabled ?? false ); ?>>
		<i class="storeengine-icon storeengine-icon--minus" aria-hidden="true"></i>
	</button>
	<input class="storeengine-cart-product-quantity__input" type="number" max="<?php echo esc_attr( $max_qty ?? '' ); ?>" min="<?php echo esc_attr( $min_qty ?? '' ); ?>" name="quantity[<?php echo esc_attr( $item_key ); ?>]" value="<?php echo esc_attr( $quantity ); ?>" aria-label="<?php esc_attr_e( 'Item quantity', 'storeengine' ); ?>"<?php disabled( $disabled ?? false ); ?>>
	<button type="button" class="storeengine-cart-product-quantity__btn storeengine-cart-product-quantity__btn--increment" aria-label="<?php esc_attr_e( 'Increase quantity by 1', 'storeengine' ); ?>"<?php disabled( $disabled ?? false ); ?>>
		<i class="storeengine-icon storeengine-icon--add" aria-hidden="true"></i>
	</button>
</div>
