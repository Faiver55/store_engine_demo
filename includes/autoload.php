<?php
namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Autoload {

	/**
	 * Instance
	 *
	 * @access private
	 * @var ?Autoload Class Instance.
	 * @since 1.0.0
	 */
	private static ?Autoload $instance = null;

	/**
	 * Autoload directories for different namespaces.
	 *
	 * @var array
	 */
	private array $autoload_directories = [
		'StoreEngine'                  => STOREENGINE_ROOT_DIR_PATH . 'includes/',
		'StoreEngine\Payment\Gateways' => STOREENGINE_INCLUDES_DIR_PATH . 'payment-gateways/',
	];

	/**
	 * Initiator
	 */
	public static function get_instance(): Autoload {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register autoload directories for namespaces.
	 *
	 * @param string $namespace Namespace to autoload.
	 * @param string $directory Directory path for the namespace.
	 */
	public function add_namespace_directory( string $namespace, string $directory ) {
		$this->autoload_directories[ $namespace ] = trailingslashit( $directory );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class Class name.
	 */
	public function autoload( $class ) {
		foreach ( $this->autoload_directories as $namespace => $directory ) {
			if ( 0 === strpos( $class, $namespace ) ) {
				$class_to_load = $class;
				$filename      = strtolower(
					preg_replace(
						[ '/^' . preg_quote( $namespace, '\\\/' ) . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
						[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
						$class_to_load
					)
				);

				$file = $directory . $filename . '.php';

				if ( strpos( $file, 'includes/addons/' ) !== false ) {
					$file = str_replace( 'includes/addons/', 'addons/', $file );
				}

				// If the file is readable, include it.
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
	}

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );
	}
}

Autoload::get_instance();
