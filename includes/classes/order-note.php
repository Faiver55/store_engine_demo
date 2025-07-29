<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderNote extends AbstractEntity {
	protected string $table       = 'comments';
	protected string $meta_type   = 'comment';
	protected string $primary_key = 'comment_ID';

	protected array $data = [
		'date_created'     => '0000-00-00 00:00:00',
		'date_created_gmt' => '0000-00-00 00:00:00',
		'content'          => '',
		'is_customer_note' => false,
		'added_by'         => '',
		'user_id'          => 0,
		'user_email'       => '',
		'user_agent'       => '',
		'is_added_by_user' => false,
		'order_id'         => 0,
	];

	protected array $internal_meta_keys = [ 'is_customer_note' ];

	protected function read_data(): array {
		$data = get_comment( $this->get_id() );

		if ( ! $data ) {
			if ( $this->wpdb->last_error ) {
				/* translators: %s: Error message. */
				throw new StoreEngineException( sprintf( __( 'Error reading order note from database. Error: %s', 'storeengine' ), $this->wpdb->last_error ), 'db-read-error', [ 'id' => $this->get_id() ], 500 );
			}

			/* translators: %s: Data object type (order-note). */
			throw new StoreEngineException( sprintf( __( 'Order note (%s) not found.', 'storeengine' ), 'order-note' ), 'error-no-record', [ 'id' => $this->get_id() ], 404 );
		}

		return apply_filters( 'storeengine/get_order_note', [
			'date_created'     => Formatting::string_to_datetime( $data->comment_date ),
			'date_created_gmt' => Formatting::string_to_datetime( $data->comment_date_gmt ),
			'content'          => $data->comment_content,
			'is_customer_note' => (bool) $this->get_metadata( 'is_customer_note' ),
			'added_by'         => _x( 'StoreEngine', 'System Comment Author', 'storeengine' ) === $data->comment_author ? 'system' : $data->comment_author,
			'user_id'          => absint( $data->user_id ),
			'user_email'       => $data->comment_author_email,
			'user_agent'       => 'StoreEngine' === $data->comment_agent ? 'system' : $data->comment_agent,
			'is_added_by_user' => 'StoreEngine' !== $data->comment_agent,
			'order_id'         => absint( $data->comment_post_ID ),
		], $data );
	}

	public function create() {
		if ( is_user_logged_in() && current_user_can( 'edit_shop_orders', $this->get_id() ) && $this->get_is_added_by_user( 'edit' ) ) {
			$user                 = get_user_by( 'id', get_current_user_id() );
			$comment_author       = $user->display_name;
			$comment_author_email = $user->user_email;
		} else {
			$comment_author       = _x( 'StoreEngine', 'System Comment Author', 'storeengine' );
			$comment_author_email = strtolower( $comment_author ) . '@' . str_replace( 'www.', '', get_site_url( null, '', 'relative' ) ); // WPCS: input var ok.
			$comment_author_email = sanitize_email( $comment_author_email );
		}

		$comment_data = apply_filters(
			'storeengine/new_order_note_data',
			[
				'comment_post_ID'      => $this->get_order_id(),
				'comment_author'       => $comment_author,
				'comment_author_email' => $comment_author_email,
				'comment_author_url'   => '',
				'comment_content'      => $this->get_content( 'edit' ),
				'comment_agent'        => 'StoreEngine',
				'comment_type'         => 'order_note',
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			],
			[
				'order_id'         => $this->get_order_id(),
				'is_customer_note' => $this->get_is_customer_note( 'edit' ),
			]
		);

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id && $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
		}

		$this->set_id( $comment_id );
		$this->save_meta_data();
		$this->apply_changes();
		$this->clear_cache();

		if ( $this->get_is_customer_note( 'edit' ) ) {
			add_comment_meta( $comment_id, 'is_customer_note', 1 );
			do_action( 'storeengine/new_customer_note', [
				'order_id'      => $this->get_order_id(),
				'customer_note' => $comment_data['comment_content'],
			] );
		}
	}

	public function update() {
		if ( ! $this->get_id() ) {
			return;
		}

		$this->save_meta_data();

		$update = wp_update_comment( [
			'comment_ID'      => $this->get_id(),
			'comment_content' => $this->get_content( 'edit' ),
		], true );

		if ( is_wp_error( $update ) ) {
			if ( 'db_update_error' === $update->get_error_code() ) {
				throw new StoreEngineException( __( 'Could not update order note in the database.', 'storeengine' ), $update->get_error_code() );
			}

			throw StoreEngineException::from_wp_error( $update );
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
		}

		$this->apply_changes();
		$this->clear_cache();
	}

	// getters

	public function get_date_created( string $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	public function get_date_created_gmt( string $context = 'view' ) {
		return $this->get_prop( 'date_created_gmt', $context );
	}

	public function get_content( string $context = 'view' ) {
		return $this->get_prop( 'content', $context );
	}

	public function get_is_added_by_user( string $context = 'view' ) {
		return Formatting::string_to_bool( $this->get_prop( 'is_added_by_user', $context ) );
	}

	public function get_is_customer_note( string $context = 'view' ) {
		return Formatting::string_to_bool( $this->get_prop( 'is_customer_note', $context ) );
	}

	public function get_added_by( string $context = 'view' ) {
		return $this->get_prop( 'added_by', $context );
	}

	public function get_user_id( string $context = 'view' ) {
		return $this->get_prop( 'user_id', $context );
	}

	public function get_user_email( string $context = 'view' ) {
		return $this->get_prop( 'user_email', $context );
	}

	public function get_user_agent( string $context = 'view' ) {
		return $this->get_prop( 'user_agent', $context );
	}

	public function get_order_id( string $context = 'view' ) {
		return $this->get_prop( 'order_id', $context );
	}


	// setters

	public function set_content( $value ) {
		$this->set_prop( 'content', $value );
	}

	public function set_is_added_by_user( $value ) {
		if ( $this->get_is_added_by_user() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Can not remove is-added-by-user flag from order note.', 'storeengine' ) );
		}

		$this->set_prop( 'is_added_by_user', Formatting::string_to_bool( $value ) );
	}

	public function set_is_customer_note( $value ) {
		if ( $this->get_is_customer_note() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Can not remove is-customer-note flag from order note.', 'storeengine' ) );
		}

		$this->set_prop( 'is_customer_note', Formatting::string_to_bool( $value ) );
	}

	public function set_added_by( $value ) {
		if ( $this->get_added_by() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Order note added-by can not be changed.', 'storeengine' ) );
		}

		$this->set_prop( 'added_by', $value );
	}

	public function set_user_id( $value ) {
		if ( $this->get_user_id() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Order not user-id can not be changed.', 'storeengine' ) );
		}

		$this->set_prop( 'user_id', absint( $value ) );
	}

	public function set_user_email( string $value ) {
		if ( $this->get_user_email() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Order not user-email can not be changed.', 'storeengine' ) );
		}
		if ( $value && ! is_email( $value ) ) {
			$this->error( 'order_note_email', __( 'Invalid order note user email address.', 'storeengine' ) );
		}

		$this->set_prop( 'user_email', sanitize_email( $value ) );
	}

	public function set_user_agent( string $value ) {
		if ( $this->get_user_agent() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Order not user-agent can not be changed.', 'storeengine' ) );
		}

		$this->set_prop( 'user_agent', $value );
	}

	public function set_order_id( $value ) {
		if ( $this->get_order_id() && $this->get_id() ) {
			throw new StoreEngineException( __( 'Order ID can not be changed.', 'storeengine' ) );
		}

		$this->set_prop( 'order_id', absint( $value ) );
	}
}

// End of file order-note.php
