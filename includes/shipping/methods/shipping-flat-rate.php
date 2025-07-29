<?php
/**
 * Flat rate shipping method.
 *
 * @package StoreEngine
 */

namespace StoreEngine\Shipping\Methods;

use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\EvalMath\EvalMath;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Shipping_Method
 */
class ShippingFlatRate extends ShippingMethod {

	const ID = 'flat_rate';

	/**
	 * Cost passed to [fee] shortcode.
	 *
	 * @var string Cost.
	 */
	protected string $fee_cost = '';

	public function __construct( $read = 0 ) {
		$this->set_method_id( self::ID );
		$this->method_title       = __( 'Flat rate', 'storeengine' );
		$this->method_description = __( 'Lets you charge a fixed rate for shipping.', 'storeengine' );
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];
		$this->settings           = array_merge( $this->settings, [ 'cost' => 0 ] );
		$this->init_admin_fields();

		parent::__construct( $read );
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'name'       => [
				'label'    => __( 'Name', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Your customers will see the name of this shipping method during checkout.', 'storeengine' ),
				'default'  => __( 'Flat rate', 'storeengine' ),
				'priority' => 0,
			],
			'tax_status' => [
				'label'    => __( 'Tax status', 'storeengine' ),
				'type'     => 'select',
				'options'  => [
					ProductTaxStatus::TAXABLE => __( 'Taxable', 'storeengine' ),
					ProductTaxStatus::NONE    => __( 'None', 'storeengine' ),
				],
				'default'  => ProductTaxStatus::TAXABLE,
				'priority' => 0,
			],
			'cost'       => [
				'label'    => __( 'Cost', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Enter a cost (excl. tax) or sum, e.g. 10.00 * [qty].Use [qty] for the number of items, [cost] for the total cost of items, and [fee percent="10" min_fee="20" max_fee=""] for percentage based fees.', 'storeengine' ),
				'default'  => 0.00,
				'priority' => 0,
			],
		];
	}

	public function calculate_shipping( array $package = [] ) {
		$rate = array(
			'id'      => $this->get_rate_id(),
			'label'   => $this->get_name(),
			'cost'    => 0,
			'package' => $package,
		);

		// Calculate the costs.
		$has_costs = false; // True when a cost is set. False if all costs are blank strings.
		$cost      = $this->get_cost();

		if ( $cost > 0 ) {
			$has_costs    = true;
			$rate['cost'] = $this->evaluate_cost(
				$cost,
				array(
					'qty'  => $this->get_package_item_qty( $package ),
					'cost' => $package['contents_cost'],
				)
			);
		}

		// @TODO: Add shipping class costs here.

		if ( $has_costs ) {
			$this->add_rate( $rate );
		}

		/**
		 * Developers can add additional flat rates based on this one via this action.
		 *
		 * Previously there were (overly complex) options to add additional rates however this was not user.
		 * friendly and goes against what Flat Rate Shipping was originally intended for.
		 */
		do_action( 'storeengine/shipping/' . $this->get_method_id() . '_add_rate', $this, $rate );
	}

	/**
	 * Get items in package.
	 *
	 * @param array $package Package of items from cart.
	 * @return int
	 */
	public function get_package_item_qty( array $package ): int {
		$total_quantity = 0;
		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values->quantity > 0 ) {
				$total_quantity += $values->quantity;
			}
		}
		return $total_quantity;
	}

	/**
	 * Evaluate a cost from a sum/string.
	 *
	 * @param string $sum Sum of shipping.
	 * @param array $args Args, must contain `cost` and `qty` keys. Having `array()` as default is for back compat reasons.
	 * @return string
	 */
	protected function evaluate_cost( string $sum, array $args = array() ) {
		// Add warning for subclasses.
		if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('$args must contain `cost` and `qty` keys.');
		}

		// Allow 3rd parties to process shipping cost arguments.
		$args           = apply_filters( 'storeengine/shipping/evaluate_cost_args', $args, $sum, $this );
		$locale         = localeconv();
		$decimals       = array( Helper::get_settings('store_currency_decimal_separator', '.'), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
		$this->fee_cost = $args['cost'];

		// Expand shortcodes.
		add_shortcode( 'fee', array( $this, 'fee' ) );

		$sum = do_shortcode(
			str_replace(
				array(
					'[qty]',
					'[cost]',
				),
				array(
					$args['qty'],
					$args['cost'],
				),
				$sum
			)
		);

		remove_shortcode( 'fee');

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? EvalMath::evaluate( $sum ) : 0;
	}

	/**
	 * Workout fee (shortcode).
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function fee( array $atts ) {
		$atts = shortcode_atts(
			array(
				'percent' => '',
				'min_fee' => '',
				'max_fee' => '',
			),
			$atts,
			'fee'
		);

		$calculated_fee = 0;

		if ( $atts['percent'] ) {
			$calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
		}

		if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
			$calculated_fee = $atts['min_fee'];
		}

		if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
			$calculated_fee = $atts['max_fee'];
		}

		return $calculated_fee;
	}

	/**
	 * @param string $context
	 *
	 * @return string
	 */
	public function get_cost( string $context = 'view' ): string {
		return $this->get_prop( 'cost', $context );
	}

	public function set_cost( string $value ) {
		$this->set_prop('cost', $value);
	}

	public function handle_save_request( array $payload ) {
		if ( ! isset( $payload['cost'] ) ) {
			throw new StoreEngineException( 'Cost is required!' );
		}
		$this->set_cost( $payload['cost'] );
		$this->set_method_id( self::ID );

		parent::handle_save_request($payload);
	}
}

// End of file shipping-flat-rate.php.
