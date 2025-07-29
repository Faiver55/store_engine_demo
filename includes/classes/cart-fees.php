<?php
/**
 * Cart fees API.
 *
 * Developers can add fees to the cart via StoreEngine::init()->cart->fees_api() which will reference this class.
 *
 * We suggest using the action woocommerce_cart_calculate_fees hook for adding fees.
 */

namespace StoreEngine\Classes;

use stdClass;
use StoreEngine\Utils\Formatting;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * CartFees class.
 *
 * @see WC_Cart_Fees
 */
final class CartFees {

	/**
	 * An array of fee objects.
	 *
	 * @var object[]
	 */
	private array $fees = [];

	/**
	 * New fees are made out of these props.
	 *
	 * @var array
	 */
	private array $default_fee_props = [
		'id'        => '',
		'name'      => '',
		'tax_class' => '',
		'taxable'   => false,
		'amount'    => 0,
		'total'     => 0,
	];

	/**
	 * Constructor. Reference to the cart.
	 */
	public function __construct() {
	}

	/**
	 * Register methods for this object on the appropriate WordPress hooks.
	 */
	public function init() {
	}

	/**
	 * Add a fee. Fee IDs must be unique.
	 *
	 * @param array|stdClass $args Array of fee properties.
	 *
	 * @return object Either a fee object if added, or a WP_Error if it failed.
	 */
	public function add_fee( $args = [] ) {
		$fee_props            = (object) wp_parse_args( $args, $this->default_fee_props );
		$fee_props->name      = $fee_props->name ? $fee_props->name : __( 'Fee', 'storeengine' );
		$fee_props->tax_class = in_array( $fee_props->tax_class, array_merge( Tax::get_tax_classes(), Tax::get_tax_class_slugs() ), true ) ? $fee_props->tax_class : '';
		$fee_props->taxable   = Formatting::string_to_bool( $fee_props->taxable );
		$fee_props->amount    = Formatting::format_decimal( $fee_props->amount );

		if ( empty( $fee_props->id ) ) {
			$fee_props->id = $this->generate_id( $fee_props );
		}

		if ( array_key_exists( $fee_props->id, $this->fees ) ) {
			return new WP_Error( 'fee_exists', __( 'Fee has already been added.', 'storeengine' ) );
		}

		$this->fees[ $fee_props->id ] = $fee_props;

		return $this->fees[ $fee_props->id ];
	}

	public function remove_fee( $id ) {
		if ( isset( $this->fees[ $id ] ) ) {
			unset( $this->fees[ $id ] );
		}
	}

	/**
	 * Get fees.
	 *
	 * @return array
	 */
	public function get_fees(): array {
		uasort( $this->fees, [ $this, 'sort_fees_callback' ] );

		return $this->fees;
	}

	/**
	 * Set fees.
	 *
	 * @param array[]|stdClass[] $raw_fees Array of fees.
	 */
	public function set_fees( array $raw_fees = [] ) {
		$this->fees = [];

		foreach ( $raw_fees as $raw_fee ) {
			$this->add_fee( $raw_fee );
		}
	}

	/**
	 * Remove all fees.
	 */
	public function remove_all_fees() {
		$this->set_fees();
	}

	/**
	 * Sort fees by amount.
	 *
	 * @param stdClass|object $a Fee object.
	 * @param stdClass|object $b Fee object.
	 *
	 * @return int
	 */
	protected function sort_fees_callback( $a, $b ): int {
		/**
		 * Filter sort fees callback.
		 *
		 * @param int $sort Sort order, -1 or 1.
		 * @param stdClass $a Fee object.
		 * @param stdClass $b Fee object.
		 */
		return apply_filters( 'storeengine/sort_fees_callback', $a->amount > $b->amount ? - 1 : 1, $a, $b );
	}

	/**
	 * Generate a unique ID for the fee being added.
	 *
	 * @param stdClass|object $fee Fee object.
	 *
	 * @return string fee key.
	 */
	private function generate_id( $fee ): string {
		return sanitize_title( $fee->name );
	}
}
