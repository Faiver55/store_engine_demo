<?php
/**
 * A SQL Transaction Handler to assist with starting, commiting and rolling back transactions.
 * This class also closes off an active transaction before shutdown to allow for shutdown processes to write to the database.
 */

namespace StoreEngine;

use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;

defined( 'ABSPATH' ) || exit;

class SqlTransaction {

	/**
	 * The query to run when a fatal shutdown occurs.
	 *
	 * @var string
	 */
	public string $on_fatal = '';

	/**
	 * The query to run if the PHP request ends without error.
	 *
	 * @var string
	 */
	public string $on_shutdown = '';

	/**
	 * Whether there's an active MYSQL transaction.
	 *
	 * @var bool
	 */
	public bool $active_transaction = false;

	/**
	 * Constructor
	 *
	 * @param string $on_fatal Optional. The type of query to run on fatal shutdown if this transaction is still active. Can be 'rollback' or 'commit'. Default is 'rollback'.
	 * @param string $on_shutdown Optional. The type of query to run if a non-error shutdown occurs but there's still an active transaction. Can be 'rollback' or 'commit'. Default is 'commit'.
	 */
	public function __construct( $on_fatal = 'rollback', $on_shutdown = 'commit' ) {

		// Validate the $on_fatal and $on_shutdown parameters.
		if ( 'commit' !== $on_fatal && 'rollback' !== $on_fatal ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'This method was called with an invalid parameter. The first argument ($on_fatal) should be "rollback" or "commit"', 'storeengine' ), '1.0.0' );
		}

		if ( 'commit' !== $on_shutdown && 'rollback' !== $on_shutdown ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'This method was called with an invalid parameter. The second argument ($on_shutdown) should be "rollback" or "commit"', 'storeengine' ), '1.0.0' );
		}

		$this->on_fatal    = $on_fatal;
		$this->on_shutdown = $on_shutdown;

		// Ensure we close off this transaction on shutdown to allow other shutdown processes to save changes to the DB.
		add_action( 'shutdown', [ $this, 'handle_shutdown' ], - 100 );
	}

	/**
	 * Starts a MYSQL Transction.
	 */
	public function start() {
		self::transaction_query( 'start' );
		$this->active_transaction = true;
	}

	/**
	 * Commits the MYSQL Transction.
	 */
	public function commit() {
		self::transaction_query( 'commit' );
		$this->active_transaction = false;
	}

	/**
	 * Rolls back any changes made during the MYSQL Transction.
	 */
	public function rollback() {
		self::transaction_query( 'rollback' );
		$this->active_transaction = false;
	}

	/**
	 * Closes out an active transaction depending on the type of shutdown.
	 *
	 * Shutdowns caused by a fatal will be rolledback or commited @see $this->on_fatal.
	 * Shutdowns caused by a natural PHP termination (no error) will be rolledback or commited. @see $this->on_shutdown.
	 */
	public function handle_shutdown() {
		if ( ! $this->active_transaction ) {
			return;
		}

		$error = error_get_last();
		$types = [
			E_ERROR,
			E_PARSE,
			E_COMPILE_ERROR,
			E_USER_ERROR,
			E_RECOVERABLE_ERROR,
		];

		if ( $error && in_array( $error['type'], $types, true ) ) {
			$this->{$this->on_fatal}();
		} else {
			$this->{$this->on_shutdown}();
		}
	}

	/**
	 * Run a MySQL transaction query, if supported.
	 *
	 * @param string $type Types: start (default), commit, rollback.
	 * @param bool $force use of transactions.
	 */
	public static function transaction_query( string $type = 'start', bool $force = false ) {
		global $wpdb;

		$wpdb->hide_errors();

		Helper::maybe_define_constant( 'STOREENGINE_USE_TRANSACTIONS', true );

		if ( Constants::is_true( 'STOREENGINE_USE_TRANSACTIONS' ) || $force ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			switch ( $type ) {
				case 'commit':
					$wpdb->query( 'COMMIT' );
					break;
				case 'rollback':
					$wpdb->query( 'ROLLBACK' );
					break;
				default:
					$wpdb->query( 'START TRANSACTION' );
					break;
			}
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
