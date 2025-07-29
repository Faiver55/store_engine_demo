<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * @deprecated
 */
abstract class AbstractPaymentGateways {


	protected static $gateways = [];


	protected $id;

	protected $title;


	protected $description;

	public function __construct( $id, $title, $description ) {
		$this->id                    = $id;
		$this->title                 = $title;
		$this->description           = $description;
		self::$gateways[ $this->id ] = $this;
	}

	abstract public function process_payment( Order $order );

	/**
	 * Get payment gateway instance.
	 *
	 * @param string $id
	 *
	 * @return AbstractPaymentGateways|null
	 */
	public static function get_gateway( $id ) {
		return self::$gateways[ $id ] ?? null;
	}


	public static function get_gateways() {
		return self::$gateways;
	}
}
