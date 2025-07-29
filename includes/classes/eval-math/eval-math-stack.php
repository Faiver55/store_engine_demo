<?php

namespace StoreEngine\Classes\EvalMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EvalMathStack
 *
 * @see WC_Eval_Math_Stack
 */
class EvalMathStack {

	/**
	 * Stack array.
	 *
	 * @var array
	 */
	public $stack = array();

	/**
	 * Stack counter.
	 *
	 * @var integer
	 */
	public $count = 0;

	/**
	 * Push value into stack.
	 *
	 * @param  mixed $val
	 */
	public function push( $val ) {
		$this->stack[ $this->count ] = $val;
		$this->count++;
	}

	/**
	 * Pop value from stack.
	 *
	 * @return mixed
	 */
	public function pop() {
		if ( $this->count > 0 ) {
			$this->count--;
			return $this->stack[ $this->count ];
		}
		return null;
	}

	/**
	 * Get last value from stack.
	 *
	 * @param  int $n
	 *
	 * @return mixed
	 */
	public function last( $n = 1 ) {
		$key = $this->count - $n;
		return array_key_exists( $key, $this->stack ) ? $this->stack[ $key ] : null;
	}
}
