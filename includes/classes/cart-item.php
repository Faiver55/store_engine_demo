<?php
/**
 * Cart Item.
 *
 * @noinspection PhpMissingFieldTypeInspection
 */

namespace StoreEngine\Classes;

use stdClass;

/**
 * CartItem Data representation.
 *
 * Cart item id/key...
 *
 * @property string $key
 *
 * CartItem Core Data...
 *
 * @property int $product_id;
 * @property int $variation_id;
 * @property array $variation;
 * @property int $price_id;
 * @property string $name;
 * @property string $price_name;
 * @property string $price_type;
 * @property float|int $price;
 * @property float|int $compare_price;
 * @property int $quantity;
 * @property float|int $line_subtotal;
 *
 * Price Settings...
 *
 * @property bool $setup_fee;
 * @property string $setup_fee_name;
 * @property float|int $setup_fee_price;
 * @property string $setup_fee_type;
 * @property bool $trial;
 * @property int $trial_days;
 * @property bool $expire;
 * @property int $expire_days;
 * @property int $payment_duration;
 * @property string $payment_duration_type;
 */
#[\AllowDynamicProperties]
class CartItem {
	// CartItem Key.
	public ?string $key = null;

	// CartItem Core data.
	public ?int $product_id = null;

	public ?int $variation_id = null;

	public ?array $variation = null;

	public ?int $price_id = null;

	public ?string $name = null;

	public ?string $price_name = null;

	public ?string $price_type = null;

	/**
	 * @var int|float|null
	 */
	public $price = null;

	/**
	 * @var int|float|null
	 */
	public $compare_price = null;

	public ?int $quantity = null;

	public ?array $item_data = null;

	/**
	 * @var int|float|null
	 */
	public $line_subtotal = null;

	// Cart total.
	public ?string $tax_class = null;

	/**
	 * @var int|float|null
	 */
	public $line_total = null;

	/**
	 * @var int|float|null
	 */
	public $line_subtotal_tax = null;

	/**
	 * @var int|float|null
	 */
	public $line_tax = null;

	/**
	 * @var array|null
	 */
	public ?array $line_tax_data = [
		'subtotal' => [],
		'total'    => [],
	];

	// Price Settings.
	public bool $setup_fee = false;

	public ?string $setup_fee_name = null;

	/**
	 * @var int|float|null
	 */
	public $setup_fee_price = null;

	public ?string $setup_fee_type = 'fee';

	public bool $trial = false;

	public ?int $trial_days = null;

	public bool $expire = false;

	public ?int $expire_days = null;

	public ?int $payment_duration = 0;

	public ?string $payment_duration_type = null;

	public bool $upgradeable = false;

	/**
	 * @param ?string $key
	 * @param array|stdClass|null $data
	 */
	public function __construct( ?string $key = null, $data = null ) {
		if ( $key ) {
			$this->key = $key;
		}

		if ( $data ) {
			if ( is_object( $data ) ) {
				$data = get_object_vars( $data );
			}

			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * @param array|object $data
	 *
	 * @return $this
	 */
	public function set_data( $data ): CartItem {
		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}

		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}

		return $this;
	}

	public function __set( string $name, $value ) {
		$this->$name = $value;
	}

	public function __get( string $name ) {
		return property_exists( $this, $name ) ? $this->$name : null;
	}

	public function get_price() {
		return apply_filters( 'storeengine/cart/item_price', $this->price, $this );
	}

	public function __isset( string $name ) {
		return property_exists( $this, $name );
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}
}

// End of file cart-item.php
