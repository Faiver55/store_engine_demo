<?php
/**
 * Tax calculation and rate finding class.
 */

namespace StoreEngine\Classes\Tax;

use StoreEngine\Classes\AbstractModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxRate extends AbstractModel {

	protected string $table = 'storeengine_tax_rates';

	protected int $tax_rate_id         = 0;
	protected string $tax_rate_country = '';
	protected string $tax_rate_state   = '';
	protected float $tax_rate          = 0.0;
	protected string $tax_rate_name    = '';
	protected int $tax_rate_priority   = 0;
	protected int $tax_rate_compound   = 0;
	protected int $tax_rate_shipping   = 0;
	protected int $tax_rate_order      = 0;
	protected string $tax_rate_class   = '';

	protected array $formats = [
		'tax_rate_id'       => '%d',
		'tax_rate_country'  => '%s',
		'tax_rate_state'    => '%s',
		'tax_rate'          => '%f',
		'tax_rate_name'     => '%s',
		'tax_rate_priority' => '%d',
		'tax_rate_compound' => '%d',
		'tax_rate_shipping' => '%d',
		'tax_rate_order'    => '%d',
		'tax_rate_class'    => '%s',
	];

	public function save( array $args = [] ) {
		return $this->create_item( $args, $this->formats );
	}

	public function update( int $id, array $args ) {
		return $this->update_item( $args, [ 'tax_rate_id' => $id ], $this->formats, [ '%d' ] );
	}

	public function delete( ?int $id = null ) {
		if ( ! $id ) {
			return false;
		}

		return $this->delete_item( [ 'tax_rate_id' => $id ], [ '%d' ] );
	}

	public function get_tax_rate_class(): string {
		return $this->tax_rate_class;
	}

	public function set_tax_rate_class( string $tax_rate_class ): void {
		$this->tax_rate_class = $tax_rate_class;
	}

	public function get_tax_rate_order(): int {
		return $this->tax_rate_order;
	}

	public function set_tax_rate_order( int $tax_rate_order ): void {
		$this->tax_rate_order = $tax_rate_order;
	}

	public function get_tax_rate_shipping(): int {
		return $this->tax_rate_shipping;
	}

	public function set_tax_rate_shipping( int $tax_rate_shipping ): void {
		$this->tax_rate_shipping = $tax_rate_shipping;
	}

	public function get_tax_rate_compound(): int {
		return $this->tax_rate_compound;
	}

	public function set_tax_rate_compound( int $tax_rate_compound ): void {
		$this->tax_rate_compound = $tax_rate_compound;
	}

	public function get_tax_rate_priority(): int {
		return $this->tax_rate_priority;
	}

	public function set_tax_rate_priority( int $tax_rate_priority ): void {
		$this->tax_rate_priority = $tax_rate_priority;
	}

	public function get_tax_rate_name(): string {
		return $this->tax_rate_name;
	}

	public function set_tax_rate_name( string $tax_rate_name ): void {
		$this->tax_rate_name = $tax_rate_name;
	}

	public function get_tax_rate(): float {
		return $this->tax_rate;
	}

	public function set_tax_rate( float $tax_rate ): void {
		$this->tax_rate = $tax_rate;
	}

	public function get_tax_rate_state(): string {
		return $this->tax_rate_state;
	}

	public function set_tax_rate_state( string $tax_rate_state ): void {
		$this->tax_rate_state = $tax_rate_state;
	}

	public function get_tax_rate_country(): string {
		return $this->tax_rate_country;
	}

	public function set_tax_rate_country( string $tax_rate_country ): void {
		$this->tax_rate_country = $tax_rate_country;
	}

	public function get_tax_rate_id(): int {
		return $this->tax_rate_id;
	}

	public function set_tax_rate_id( int $tax_rate_id ): void {
		$this->tax_rate_id = $tax_rate_id;
	}

	public function get_id(): int {
		return $this->get_tax_rate_id();
	}

	public function set_id( int $tax_rate_id ): void {
		$this->set_tax_rate_id( $tax_rate_id );
	}
}

// End of file tax-rate.php
