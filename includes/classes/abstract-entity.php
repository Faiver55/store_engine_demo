<?php

namespace StoreEngine\Classes;

use Exception;
use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
abstract class AbstractEntity {
	/**
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * Table name where the entity data stored.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Cache group for the entity.
	 * Default to the table name (without db prefix)
	 * used with wp_cache_set_last_changed
	 *
	 * @see wp_cache_set_last_changed
	 * @var ?string
	 */
	protected ?string $cache_group = null;

	/**
	 * Primary key column name for the entity.
	 *
	 * @var string
	 */
	protected string $primary_key = 'id';

	/**
	 * Meta key to entity property keys.
	 *
	 * @var array
	 */
	protected array $meta_key_to_props = [];

	/**
	 * Meta type. This should match up with
	 * the types available at https://developer.wordpress.org/reference/functions/add_metadata/.
	 * WP defines 'post', 'user', 'comment', and 'term'.
	 *
	 * @var string
	 */
	protected string $meta_type = '';

	/**
	 * ID for this object.
	 *
	 * @var int
	 */
	protected int $id = 0;

	/**
	 * Core data for this object. Name value pairs (name + default value).
	 *
	 * @var array
	 */
	protected array $data = [];

	protected array $readable_fields = [];

	protected array $data_format = [];

	/**
	 * Core data changes for this object.
	 *
	 * @var array
	 */
	protected array $changes = [];

	/**
	 * Extra data for this object. Name value pairs (name + default value).
	 * Used as a standard way for sub classes (like product types) to add
	 * additional information to an inherited class.
	 *
	 * @var array
	 */
	protected array $extra_data = [];

	/**
	 * If we have already saved our extra data, don't do automatic / default handling.
	 *
	 * @var bool
	 */
	protected bool $extra_data_saved = false;

	protected bool $read_extra_data_separately = true;

	protected array $updated_props = [];

	protected array $extra_data_format = [];

	/**
	 * Set to _data on construct so we can track and reset data if needed.
	 *
	 * @var array
	 */
	protected array $default_data = [];

	protected array $default_data_format = [];

	protected ?array $meta_data = null;

	/**
	 * This only needs set if you are using a custom metadata type (for example payment tokens.
	 * This should be the name of the field your table uses for associating meta with objects.
	 * For example, in payment_tokenmeta, this would be payment_token_id.
	 *
	 * @var string
	 */
	protected string $object_id_field_for_meta = '';

	/**
	 * Data stored in meta keys, but not considered "meta" for an object.
	 *
	 * @var array
	 */
	protected array $internal_meta_keys = [];

	/**
	 * Meta data which should exist in the DB, even if empty.
	 *
	 * @var array
	 */
	protected $must_exist_meta_keys = [];

	/**
	 * This is false until the object is read from the DB.
	 *
	 * @var bool
	 */
	protected bool $object_read = false;

	/**
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected string $object_type = 'data';

	/**
	 * Flag for allowing to trash without delete.
	 *
	 * @var bool
	 */
	protected bool $allow_trash = false;

	/**
	 * Default constructor.
	 *
	 * @param int|object|array $read ID to load from the DB (optional) or already queried data.
	 *
	 * @throws StoreEngineException
	 */
	public function __construct( $read = 0 ) {
		global $wpdb;
		$this->wpdb = $wpdb;

		$table = str_replace( $wpdb->prefix, '', $this->table );
		if ( ! $this->cache_group ) {
			$this->cache_group = $table;
		}

		$this->table        = $wpdb->prefix . $table;
		$this->data         = array_merge( $this->data, $this->extra_data );
		$this->data_format  = array_merge( $this->data_format, $this->extra_data_format );
		$this->default_data = $this->data;

		if ( empty( $this->default_format ) ) {
			foreach ( $this->default_data as $key => $value ) {
				$this->data_format[] = $this->predict_format( $key, $value );
			}
		}

		$this->default_data_format = $this->data_format;


		if ( is_numeric( $read ) && $read > 0 ) {
			$this->set_id( $read );
		} elseif ( $read instanceof self ) {
			$this->set_id( $read->get_id() );
		} elseif ( is_object( $read ) && ( ! empty( $read->ID ) || ! empty( $read->{$this->primary_key} ) ) ) {
			if ( ! empty( $read->ID ) ) {
				$this->set_id( $read->ID );
			} else {
				$this->set_id( $read->{$this->primary_key} );
			}
		} elseif ( is_array( $read ) && ( ! empty( $read['ID'] ) || ! empty( $read[ $this->primary_key ] ) ) ) {
			if ( ! empty( $read['ID'] ) ) {
				$this->set_id( $read['ID'] );
			} else {
				$this->set_id( $read[ $this->primary_key ] );
			}
		} else {
			$this->set_object_read( true );
		}

		if ( $this->get_id() > 0 ) {
			$this->read();
		}
	}

	/**
	 * Only store the object ID to avoid serializing the data object instance.
	 *
	 * @return array
	 */
	public function __sleep() {
		return [ 'id' ];
	}

	/**
	 * Re-run the constructor with the object ID.
	 *
	 * If the object no longer exists, remove the ID.
	 */
	public function __wakeup() {
		try {
			$this->__construct( absint( $this->id ) );
		} catch ( Exception $e ) {
			$this->set_id( 0 );
			$this->set_object_read( true );
		}
	}

	/**
	 * When the object is cloned, make sure meta is duplicated correctly.
	 */
	public function __clone() {
		$this->id = 0;
		$this->maybe_read_meta_data();
		if ( ! empty( $this->meta_data ) ) {
			foreach ( $this->meta_data as $array_key => $meta ) {
				$this->meta_data[ $array_key ] = clone $meta;
				if ( ! empty( $meta->id ) ) {
					$this->meta_data[ $array_key ]->id = null;
				}
			}
		}
	}

	/**
	 * Returns the unique ID for this object.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	public function __get( string $key ) {
		if ( in_array( $key, [ 'id', 'ID', $this->primary_key ], true ) ) {
			// Compatibility for AbstractCollection.
			return $this->get_id();
		}

		$getter = "get_$key";

		if ( is_callable( [ $this, $getter ] ) ) {
			return $this->{$getter}();
		}

		return null;
	}

	/**
	 * Set ID.
	 *
	 * @param int|string $id ID.
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Save should create or update based on object existence.
	 *
	 * @return int|WP_Error
	 */
	public function save() {
		try {
			$create = ! $this->get_id();

			/**
			 * Trigger action before saving to the DB. Allows you to adjust object props before save.
			 *
			 * @param AbstractEntity $this The object being saved.
			 * @param bool           $create If the object is being creating/updating.
			 */
			do_action( 'storeengine/' . $this->object_type . '/before/object_save', $this, $create );

			if ( ! $this->get_id() ) {
				$this->create();
			} else {
				$this->update();
			}

			/**
			 * Trigger action after saving to the DB.
			 *
			 * @param AbstractEntity $this The object being saved.
			 * @param bool           $create If the object is being created/updated.
			 */
			do_action( 'storeengine/' . $this->object_type . '/after/object_save', $this, $create );

			return $this->get_id();
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	/**
	 * Create DB Record.
	 *
	 * @throws StoreEngineException
	 */
	public function create() {
		[ 'data' => $data, 'format' => $format ] = $this->prepare_for_db();

		if ( $this->wpdb->insert( $this->table, $data, $format ) ) {
			$this->set_id( $this->wpdb->insert_id );
			$this->save_meta_data();
			$this->save_item_data();
			$this->update_object_meta();
			$this->save_extra_data();
			$this->apply_changes();
			$this->clear_cache();
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
		}
	}

	/**
	 * Get and store terms from a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name e.g. product_cat.
	 *
	 * @return array of terms
	 */
	protected function get_terms( string $taxonomy ): array {
		$terms = get_the_terms( $this->get_id(), $taxonomy );
		if ( false === $terms || is_wp_error( $terms ) ) {
			return [];
		}

		return $terms;
	}

	/**
	 * Get and store terms ids from a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name e.g. product_cat.
	 *
	 * @return int[] of terms
	 */
	protected function get_term_ids( string $taxonomy ): array {
		$terms = get_the_terms( $this->get_id(), $taxonomy );
		if ( false === $terms || is_wp_error( $terms ) ) {
			return [];
		}

		return wp_list_pluck( $terms, 'term_id' );
	}

	/**
	 * Table structure is slightly different between meta types, this function will return what we need to know.
	 *
	 * @return array{table:string,object_id_field:string,meta_id_field:string}|bool Array elements: table, object_id_field, meta_id_field
	 */
	protected function get_db_info() {
		global $wpdb;

		if ( ! $this->meta_type ) {
			return false;
		}

		$meta_id_field = 'meta_id'; // for some reason users calls this umeta_id so we need to track this as well.

		if ( isset( $wpdb->{$this->meta_type . 'meta'} ) ) {
			$table = $wpdb->{$this->meta_type . 'meta'};
		} else {
			$table = $wpdb->prefix;
			// If we are dealing with a type of metadata that is not a core type, the table should be prefixed.
			if ( ! in_array( $this->meta_type, [ 'post', 'user', 'comment', 'term' ], true ) ) {
				$table .= 'storeengine_';
			}

			$table .= $this->meta_type . '_meta';
		}

		$object_id_field = $this->meta_type . '_id';

		// Figure out our field names.
		if ( 'user' === $this->meta_type ) {
			$meta_id_field = 'umeta_id';
			$table         = $wpdb->usermeta;
		}
		if ( 'order' === $this->meta_type ) {
			$object_id_field = 'order_id';
			$table           = $wpdb->prefix . 'storeengine_orders_meta';
		}

		if ( ! empty( $this->object_id_field_for_meta ) ) {
			$object_id_field = $this->object_id_field_for_meta;
		}

		return [
			'table'           => $table,
			'object_id_field' => $object_id_field,
			'meta_id_field'   => $meta_id_field,
		];
	}

	/**
	 * Internal meta keys we don't want exposed as part of meta_data. This is in
	 * addition to all data props with _ prefix.
	 *
	 * @param string $key Prefix to be added to meta keys.
	 *
	 * @return string
	 */
	protected function prefix_key( string $key ): string {
		return '_' === substr( $key, 0, 1 ) ? $key : '_' . $key;
	}

	/**
	 * Returns an array of meta for an object.
	 *
	 * @return array
	 */
	public function read_meta(): ?array {
		$db_info = $this->get_db_info();

		if ( ! $db_info ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw_meta_data = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT {$db_info['meta_id_field']} as meta_id, meta_key, meta_value
				FROM {$db_info['table']}
				WHERE {$db_info['object_id_field']} = %d
				ORDER BY {$db_info['meta_id_field']}",
				$this->get_id()
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->filter_raw_meta_data( $raw_meta_data );
	}

	/**
	 * Helper method to filter internal meta keys from all meta data rows for the object.
	 *
	 * @param ?array $raw_meta_data Array of std object of meta data to be filtered.
	 *
	 * @return ?array
	 */
	public function filter_raw_meta_data( ?array $raw_meta_data ): ?array {
		if ( ! $raw_meta_data ) {
			return null;
		}

		$this->internal_meta_keys = array_unique(
			array_merge(
				array_map(
					[
						$this,
						'prefix_key',
					],
					$this->get_data_keys()
				),
				$this->internal_meta_keys
			)
		);
		$meta_data                = array_filter( $raw_meta_data, [ $this, 'exclude_internal_meta_keys' ] );

		return apply_filters( "storeengine/{$this->meta_type}_read_meta", $meta_data, $this );
	}

	/**
	 * Callback to remove unwanted meta data.
	 *
	 * @param object $meta Meta object to check if it should be excluded or not.
	 *
	 * @return bool
	 */
	protected function exclude_internal_meta_keys( object $meta ): bool {
		return ! in_array( $meta->meta_key, $this->internal_meta_keys, true ) && 0 !== stripos( $meta->meta_key, 'wp_' );
	}


	/**
	 * Callback to remove unwanted meta data.
	 *
	 * @param object $meta Meta object to check if it should be excluded or not.
	 *
	 * @return bool
	 */
	protected function include_extra_meta_keys( object $meta ): bool {
		return array_key_exists( $meta->meta_key, $this->meta_key_to_props ) && 0 !== stripos( $meta->meta_key, 'wp_' );
	}

	/**
	 * Gets a list of props and meta keys that need updated based on change state
	 * or if they are present in the database or not.
	 *
	 * @param array $meta_key_to_props A mapping of meta keys => prop names.
	 * @param string $meta_type The internal WP meta type (post, user, etc).
	 *
	 * @return array                        A mapping of meta keys => prop names, filtered by ones that should be updated.
	 */
	protected function get_props_to_update( array $meta_key_to_props ): array {
		$props_to_update = [];
		$changed_props   = $this->get_changes();

		// Props should be updated if they are a part of the $changed array or don't exist yet.
		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			if ( array_key_exists( $prop, $changed_props ) || ! metadata_exists( $this->meta_type, $this->get_id(), $meta_key ) ) {
				$props_to_update[ $meta_key ] = $prop;
			}
		}

		return $props_to_update;
	}

	/**
	 * Update meta data in, or delete it from, the database.
	 *
	 * Avoids storing meta when it's either an empty string or empty array.
	 * Other empty values such as numeric 0 and null should still be stored.
	 * Data-stores can force meta to exist using `must_exist_meta_keys`.
	 *
	 * Note: WordPress `get_metadata` function returns an empty string when meta data does not exist.
	 *
	 * @param string $meta_key Meta key to update.
	 * @param mixed $meta_value Value to save.
	 *
	 * @return bool True if updated/deleted.
	 */
	protected function update_or_delete_post_meta( string $meta_key, $meta_value ): bool {
		if ( in_array( $meta_value, [ [], '' ], true ) && ! in_array( $meta_key, $this->must_exist_meta_keys, true ) ) {
			$updated = delete_metadata( $this->meta_type, $this->get_id(), $meta_key );
		} else {
			$updated = update_metadata( $this->meta_type, $this->get_id(), $meta_key, $meta_value );
		}

		return (bool) $updated;
	}

	/**
	 * Read DB Record.
	 *
	 * @param bool $refresh If true read fresh data from DB.
	 *                      Else try read from cached data.
	 *                      Default is false.
	 *
	 * @throws StoreEngineException
	 */
	public function read( bool $refresh = false ) {
		$this->set_defaults();

		if ( ! $this->get_id() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %s: Data object type. */
					__( 'ID is not set for %s.', 'storeengine' ),
					$this->object_type
				),
				'read-error-no-id',
				null,
				400
			);
		}

		// Get from cache if available.
		$data = wp_cache_get( $this->get_id(), $this->cache_group );

		if ( false === $data || true === $refresh ) {
			$this->clear_cache();
			$data = $this->read_data();
			wp_cache_set( $this->get_id(), $data, $this->cache_group );
		}

		$this->set_props( $data );

		$this->maybe_read_meta_data();
		$this->maybe_read_extra_data();

		$this->set_object_read( true );

		/**
		 * Fires when an object is read into memory.
		 *
		 * @param int $id The Object ID.
		 * @param self $this Object instance.
		 */
		do_action( "storeengine/$this->object_type/read", $this->get_id(), $this );
	}

	/**
	 * Read DB Record.
	 *
	 * @return array
	 * @throws StoreEngineException
	 */
	protected function read_data(): array {
		$columns = $this->readable_fields ? implode( ',', $this->readable_fields ) : '*';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT $columns FROM $this->table WHERE $this->primary_key = %d LIMIT 1;", $this->get_id() ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $data ) {
			if ( $this->wpdb->last_error ) {
				/* translators: %s: Error message. */
				throw new StoreEngineException( sprintf( __( 'Error reading data from database. Error: %s', 'storeengine' ), $this->wpdb->last_error ), 'db-read-error', [ 'id' => $this->get_id() ], 500 );
			}

			/* translators: %s: Data object type. */
			throw new StoreEngineException( sprintf( __( 'Data (%s) not found.', 'storeengine' ), $this->object_type ), 'error-no-record', [ 'id' => $this->get_id() ], 404 );
		}

		return $data;
	}

	/**
	 * Update DB Record.
	 *
	 * @throws StoreEngineException
	 */
	public function update() {
		if ( ! $this->get_id() ) {
			return;
		}

		$this->save_meta_data();
		$this->save_item_data();
		$this->update_object_meta();
		$this->save_extra_data();

		[ 'data' => $data, 'format' => $format ] = $this->prepare_for_db( 'update' );

		$this->wpdb->update( $this->table, $data, [ $this->primary_key => $this->get_id() ], $format, [ '%d' ] );

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
		}

		$this->apply_changes();
		$this->clear_cache();

		/**
		 * Fires immediately after an object updated into database.
		 *
		 * @param int $id The Object ID.
		 * @param self $this Object instance.
		 */
		do_action( "storeengine/$this->object_type/updated", $this->get_id(), $this );
	}

	protected function save_item_data() {
		foreach ( $this->get_props_to_update( $this->meta_key_to_props ) as $meta_key => $prop ) {
			update_metadata( $this->meta_type, $this->get_id(), $meta_key, $this->{"get_$prop"}( 'edit' ) );
		}
	}

	/**
	 * @param bool $force_delete
	 *
	 * @return bool
	 * @throws StoreEngineException
	 */
	public function delete( bool $force_delete = false ): bool {
		if ( ! $this->get_id() ) {
			return false;
		}

		if ( ! $force_delete && $this->is_trashable() ) {
			return $this->trash();
		}

		// Keep a reference before delete and set to zero.
		$id = $this->get_id();
		if ( $this->wpdb->delete( $this->table, [ $this->primary_key => $this->id ], [ '%d' ] ) ) {
			$this->delete_object_metas();
			$this->clear_cache();
			$this->set_id( 0 );

			if ( $this->wpdb->last_error ) {
				throw new StoreEngineException( $this->wpdb->last_error, 'db-error-delete-record' );
			}

			/**
			 * Fires immediately after an object gets deleted from database.
			 *
			 * @param int $id The Object ID.
			 * @param self $this Object instance.
			 * @param bool $force_delete The Object ID.
			 */
			do_action( "storeengine/$this->object_type/deleted", $id, $this, $force_delete );

			return true;
		} else {
			return false;
		}
	}

	public function trash(): bool {
		if ( $this->is_trashable() ) {
			$this->add_meta_data( '_trash_status', $this->get_status( 'edit' ) );
			$this->set_status( 'trash' );
			$this->add_meta_data( '_trash_time', time() );
			$this->save();

			return 'trash' === $this->get_status( 'edit' );
		}

		return false;
	}

	public function untrash(): bool {
		if ( $this->is_trashable() ) {
			$previous_status = $this->get_meta( '_trash_status' );
			$this->set_status( $previous_status ? $previous_status : 'draft' );
			$this->delete_meta_data( '_trash_status' );
			$this->delete_meta_data( '_trash_time' );
			$this->save();

			do_action( "storeengine/$this->object_type/untrashed", $this, $previous_status );

			return 'trash' !== $this->get_status( 'edit' );
		}

		return false;
	}

	public function is_trashable(): bool {
		return $this->allow_trash && is_callable( [ $this, 'get_status' ] ) && is_callable( [ $this, 'set_status' ] );
	}

	protected function get_object_meta_ids(): array {
		$db_info = $this->get_db_info();

		if ( ! $db_info ) {
			return [];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$object_meta_ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT {$db_info['meta_id_field']} as meta_id
			FROM {$db_info['table']}
			WHERE {$db_info['object_id_field']} = %d;",
				$this->get_id()
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'absint', $object_meta_ids );
	}

	protected function delete_object_metas() {
		foreach ( $this->get_object_meta_ids() as $mid ) {
			delete_metadata_by_mid( $this->meta_type, $mid );
		}
	}

	protected function maybe_read_meta_data() {
		if ( is_null( $this->meta_data ) ) {
			$this->read_meta_data();
		}
	}

	public function read_meta_data( $force_read = false ) {
		// Maybe not necessary to set here.
		$this->meta_data = [];

		if ( ! $this->get_id() ) {
			return;
		}

		$cache_loaded = false;
		$cached_meta  = [];

		// Prefix by group allows invalidation by group until https://core.trac.wordpress.org/ticket/4476 is implemented.
		if ( ! $force_read ) {
			if ( ! empty( $this->cache_group ) ) {
				$cached_meta  = wp_cache_get( $this->get_meta_cache_key(), $this->cache_group );
				$cache_loaded = is_array( $cached_meta );
			}
		}

		// We filter the raw meta data again when loading from cache, in case we cached in an earlier version where filter conditions were different.
		$raw_meta_data = $cache_loaded ? $this->filter_raw_meta_data( $cached_meta ) : $this->read_meta();

		if ( is_array( $raw_meta_data ) ) {
			$this->init_meta_data( $raw_meta_data );
			if ( ! $cache_loaded && ! empty( $this->cache_group ) ) {
				wp_cache_set( $this->get_meta_cache_key(), $raw_meta_data, $this->cache_group );
			}
		}
	}

	/**
	 * Helper method to compute meta cache key. Different from WP Meta cache key in that meta data cached using this key also contains meta_id column.
	 *
	 * @return string
	 */
	public function get_meta_cache_key() {
		if ( ! $this->get_id() ) {
			_doing_it_wrong( 'get_meta_cache_key', 'ID needs to be set before fetching a cache key.', '1.0.0' );

			return false;
		}

		return $this->generate_meta_cache_key();
	}

	/**
	 * Generate cache key from id and group.
	 *
	 * @return string Meta cache key.
	 */
	public function generate_meta_cache_key(): string {
		return Caching::get_cache_prefix( $this->cache_group ) . Caching::get_cache_prefix( 'object_' . $this->get_id() ) . 'object_meta_' . $this->get_id();
	}

	protected function maybe_read_extra_data() {
		if ( $this->read_extra_data_separately ) {
			$this->read_extra_data();
		}
	}

	protected function read_extra_data() {
		foreach ( $this->get_extra_data_keys() as $key ) {
			$function = 'set_' . $key;
			if ( is_callable( [ $this, $function ] ) ) {
				$this->{$function}( $this->get_metadata( $key ) );
			}
		}
	}

	/**
	 * Saves extra token data as meta.
	 *
	 * @param bool $force By default, only changed props are updated. When this param is true all props are updated.
	 *
	 * @return array List of updated props.
	 */
	protected function save_extra_data( bool $force = false ): array {
		if ( $this->extra_data_saved ) {
			return [];
		}

		$updated_props     = [];
		$extra_data_keys   = $this->get_extra_data_keys();
		$meta_key_to_props = ! empty( $extra_data_keys ) ? array_combine( $extra_data_keys, $extra_data_keys ) : [];
		$props_to_update   = $force ? $meta_key_to_props : $this->get_props_to_update( $meta_key_to_props );

		foreach ( $extra_data_keys as $key ) {
			if ( ! array_key_exists( $key, $props_to_update ) ) {
				continue;
			}

			$function = 'get_' . $key;

			if ( is_callable( [ $this, $function ] ) ) {
				$value = $this->{$function}( 'edit' );

				if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
					$value = $this->prepare_date_for_db( $value, $key );
				}

				if ( update_metadata( $this->meta_type, $this->get_id(), $key, $value ) ) {
					$updated_props[] = $key;
				}
			}
		}

		return $updated_props;
	}

	/**
	 * Helper function to initialize metadata entries from filtered raw meta data.
	 *
	 * @param array $filtered_meta_data Filtered metadata fetched from DB.
	 */
	public function init_meta_data( array $filtered_meta_data = [] ) {
		$this->meta_data = [];
		foreach ( $filtered_meta_data as $meta ) {
			$this->meta_data[] = new MetaData( [
				'id'    => (int) $meta->meta_id,
				'key'   => $meta->meta_key,
				'value' => maybe_unserialize( $meta->meta_value ),
			] );
		}
	}

	public function save_meta_data() {
		if ( is_null( $this->meta_data ) ) {
			return;
		}

		foreach ( $this->meta_data as $array_key => $meta ) {
			if ( ! is_object( $meta ) ) {
				continue;
			}
			if ( is_null( $meta->value ) ) {
				if ( ! empty( $meta->id ) ) {
					$this->delete_meta( $meta );
					/**
					 * Fires immediately after deleting metadata.
					 *
					 * @param int $meta_id ID of deleted metadata entry.
					 * @param int $object_id Object ID.
					 * @param string $meta_key Metadata key.
					 * @param mixed $meta_value Metadata value (will be empty for delete).
					 */
					do_action( "storeengine/deleted_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value );

					unset( $this->meta_data[ $array_key ] );
				}
			} elseif ( empty( $meta->id ) ) {
				$meta->id = $this->add_meta( $meta );
				/**
				 * Fires immediately after adding metadata.
				 *
				 * @param int $meta_id ID of added metadata entry.
				 * @param int $object_id Object ID.
				 * @param string $meta_key Metadata key.
				 * @param mixed $meta_value Metadata value.
				 */
				do_action( "storeengine/added_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value );

				$meta->apply_changes();
			} else {
				if ( $meta->get_changes() ) {
					$this->update_meta( $meta );
					/**
					 * Fires immediately after updating metadata.
					 *
					 * @param int $meta_id ID of updated metadata entry.
					 * @param int $object_id Object ID.
					 * @param string $meta_key Metadata key.
					 * @param mixed $meta_value Metadata value.
					 */
					do_action( "storeengine/updated_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value );

					$meta->apply_changes();
				}
			}
		}

		if ( ! empty( $this->cache_group ) ) {
			wp_cache_delete( self::get_meta_cache_key(), $this->cache_group );
		}
	}

	/**
	 * Deletes meta based on meta ID.
	 *
	 * @param stdClass|MetaData $meta (containing at least ->id).
	 *
	 * @return bool
	 */
	public function delete_meta( $meta ) {
		return delete_metadata_by_mid( $this->meta_type, $meta->id );
	}

	/**
	 * Add new piece of meta.
	 *
	 * @param stdClass|MetaData $meta (containing ->key and ->value).
	 *
	 * @return int|false meta ID
	 */
	public function add_meta( $meta ) {
		if ( ! is_string( $meta->key ) ) {
			return false;
		}

		$value = is_string( $meta->value ) ? wp_slash( $meta->value ) : $meta->value;
		$value = $this->prepare_date_for_db( $value, $meta->key );

		return add_metadata( $this->meta_type, $this->get_id(), wp_slash( $meta->key ), $value, false );
	}

	/**
	 * Update meta.
	 *
	 * @param stdClass|MetaData $meta (containing ->id, ->key and ->value).
	 *
	 * @return bool
	 */
	public function update_meta( $meta ): bool {
		if ( ! $meta->key && ! $meta->id ) {
			return false;
		}

		$value = is_string( $meta->value ) ? wp_slash( $meta->value ) : $meta->value;
		$value = $this->prepare_date_for_db( $value, $meta->key );

		return update_metadata_by_mid( $this->meta_type, $meta->id, $value, $meta->key );
	}


	protected function read_object_meta() {
		if ( ! $this->meta_type || ! $this->get_id() || empty( $this->meta_key_to_props ) ) {
			return;
		}

		$meta_data = $this->get_metadata( '', false );
		$set_props = [];
		foreach ( $this->meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = $meta_data[ $meta_key ][0] ?? null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value ); // get_post_meta only unserializes single values.
		}

		$this->set_props( $set_props );
	}

	protected function update_object_meta( $force = false ) {
		// Make sure to take extra data (like product url or text for external products) into account.
		$extra_data_keys = $this->get_extra_data_keys();
		$props_to_update = $force ? $this->meta_key_to_props : $this->get_props_to_update( $this->meta_key_to_props );

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $this->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;

			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $meta_key );
			}

			$updated = $this->update_or_delete_post_meta( $meta_key, $value );

			if ( $updated ) {
				$this->updated_props[] = $prop;
			}
		}

		// Update extra data associated with the product like button text or product URL for external products.
		if ( ! $this->extra_data_saved ) {
			foreach ( $extra_data_keys as $key ) {
				$meta_key = '_' . $key;
				$function = 'get_' . $key;
				if ( ! array_key_exists( $meta_key, $props_to_update ) ) {
					continue;
				}
				if ( is_callable( array( $this, $function ) ) ) {
					$value = $this->{$function}( 'edit' );
					$value = is_string( $value ) ? wp_slash( $value ) : $value;
					if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
						$value = $this->prepare_date_for_db( $value, $key );
					}
					$updated = $this->update_or_delete_post_meta( $meta_key, $value );

					if ( $updated ) {
						$this->updated_props[] = $key;
					}
				}
			}

			$this->extra_data_saved = true;
		}
	}

	/**
	 * Methods is protected and final to keep this as an internal API.
	 *
	 * @param string $context
	 *
	 * @return array
	 * @internal
	 */
	protected function prepare_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		// Should just get data from change when available and check context?
		$raw_data = $this->get_changes();

		if ( empty( $raw_data ) && 'create' === $context ) {
			$raw_data = $this->get_data(); // get defaults.
		}

		foreach ( $raw_data as $key => $value ) {
			if ( 'update' === $context && ( str_contains( 'date_created', $key ) || str_contains( 'created_at', $key ) ) ) {
				continue;
			}

			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $key );
			}

			$format[]     = $this->predict_format( $key, $value );
			$data[ $key ] = $value;
		}

		// Not using any filter for handling format.
		// If necessary update format for column in $wpdb::$field_types.

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	/**
	 * Methods is protected and final to keep this as an internal API.
	 *
	 * @param string $key
	 * @param $value
	 *
	 * @return string
	 * @internal
	 */
	final protected function predict_format( string $key, $value ): string {
		if ( ! empty( $this->data_format[ $key ] ) ) {
			return $this->data_format[ $key ];
		}

		if ( is_numeric( $value ) || is_bool( $value ) ) {
			return str_contains( (string) $value, '.' ) ? '%f' : '%d';
		} else {
			return '%s';
		}
	}

	public function prepare_date_for_db( $value, $key ) {
		if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
			if ( str_ends_with( $key, '_gmt' ) ) {
				$value = gmdate( 'Y-m-d H:i:s', $value->getTimestamp() );
			} else {
				$value = gmdate( 'Y-m-d H:i:s', $value->getOffsetTimestamp() );
			}
		}

		return $value;
	}

	/**
	 * Set all props to default values.
	 */
	public function set_defaults() {
		$this->data        = $this->default_data;
		$this->data_format = $this->default_data_format;
		$this->changes     = [];
		$this->set_object_read( false );
	}

	/**
	 * Set object read property.
	 *
	 * @param bool $read Should read?.
	 */
	public function set_object_read( bool $read = true ): bool {
		$previous          = $this->object_read;
		$this->object_read = $read;

		return $previous;
	}

	/**
	 * Get object read property.
	 *
	 * @return bool
	 */
	public function get_object_read(): bool {
		return $this->object_read;
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @param array|stdClass $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 * @param string $context In what context to run this.
	 *
	 * @return bool|WP_Error
	 */
	public function set_props( $props, string $context = 'set' ) {
		$errors = false;

		foreach ( $props as $prop => $value ) {
			if ( $prop === $this->primary_key ) {
				$this->set_id( $value );
				continue;
			}

			try {
				if ( 'created_via' !== $prop && $value && ( str_contains( $prop, 'date' ) || str_contains( $prop, 'created' ) || str_contains( $prop, 'modified' ) ) ) {
					if ( Formatting::is_datetime( $value ) ) {
						$this->set_date_prop( $prop, $value );
						continue;
					}
				}

				$setter = "set_$prop";

				if ( is_callable( [ $this, $setter ] ) ) {
					$this->{$setter}( $value );
				} else {
					// Allow setting props declared directly.
					$this->set_prop( $prop, $value );
				}
			} catch ( StoreEngineException $e ) {
				if ( ! $errors ) {
					$errors = new WP_Error();
				}

				if ( ! $e->get_data( 'property' ) ) {
					$e->add_data( 'property', $prop );
				}

				if ( ! $e->get_data( 'value' ) ) {
					$e->add_data( 'value', $value );
				}

				$errors->merge_from( $e->toWpError() );
			}
		}

		return $errors && $errors->has_errors() ? $errors : true;
	}

	public function get_props( array $props, string $context = 'view' ): array {
		$output = [];

		foreach ( $props as $prop ) {
			$getter = "get_$prop";

			if ( is_callable( [ $this, $getter ] ) ) {
				$value = $this->{$getter}( $context );
			} else {
				// Allow getting props declared directly.
				$value = $this->get_prop( $prop, $context );
			}

			if ( $value instanceof StoreengineDatetime ) {
				$value = $this->prepare_date_for_db( $value, $prop );
			}

			$output[ $prop ] = $value;
		}

		return $output;
	}

	/**
	 * Sets a date prop whilst handling formatting and datetime objects.
	 *
	 * @param string $prop Name of prop to set.
	 * @param string|int $value Value of the prop.
	 */
	protected function set_date_prop( string $prop, $value ) {
		try {
			if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
				$this->set_prop( $prop, null );

				return;
			}

			$this->set_prop( $prop, Formatting::string_to_datetime( $value ) );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * Sets a prop for a setter method.
	 *
	 * This stores changes in a special array so we can track what needs saving
	 * the DB later.
	 *
	 * @param string $prop Name of prop to set.
	 * @param mixed $value Value of the prop.
	 */
	protected function set_prop( string $prop, $value ) {
		if ( array_key_exists( $prop, $this->data ) ) {
			if ( true === $this->object_read ) {
				if ( $value !== $this->data[ $prop ] || array_key_exists( $prop, $this->changes ) ) {
					$this->changes[ $prop ] = $value;
				}
			} else {
				$this->data[ $prop ] = $value;
			}
		}
	}

	/**
	 * Get Object type. Overridden by child classes.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->object_type;
	}

	public function get_object_type(): string {
		return $this->object_type;
	}

	/**
	 * Type checking.
	 *
	 * @param string|array $type Type.
	 *
	 * @return boolean
	 */
	public function is_type( $type ): bool {
		return is_array( $type ) ? in_array( $this->get_type(), $type, true ) : $type === $this->get_type();
	}

	/**
	 * Return data.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Returns array of expected data keys for this object.
	 *
	 * @return array
	 */
	public function get_data_keys(): array {
		return array_keys( $this->data );
	}

	/**
	 * Returns all "extra" data keys for an object (for sub objects like product types).
	 *
	 * @return array
	 */
	public function get_extra_data_keys(): array {
		return array_keys( $this->extra_data );
	}

	/**
	 * Filter null meta values from array.
	 *
	 * @param mixed $meta Meta value to check.
	 *
	 * @return bool
	 */
	protected function filter_null_meta( $meta ): bool {
		return ! is_null( $meta->value );
	}

	/**
	 * Get All Meta-Data.
	 *
	 * @return MetaData[] of objects.
	 */
	public function get_meta_data(): array {
		$this->maybe_read_meta_data();

		return array_values( array_filter( $this->meta_data, [ $this, 'filter_null_meta' ] ) );
	}

	/**
	 * Return list of internal meta keys.
	 *
	 * @return array
	 */
	public function get_internal_meta_keys(): array {
		return $this->internal_meta_keys;
	}

	/**
	 * Check if the key is an internal one.
	 *
	 * @param ?string $key Key to check.
	 *
	 * @return bool   true if it's an internal key, false otherwise
	 */
	protected function is_internal_meta_key( ?string $key ): bool {
		$internal_meta_key = ! empty( $key ) && in_array( $key, $this->get_internal_meta_keys(), true );

		if ( ! $internal_meta_key ) {
			return false;
		}

		$has_setter_or_getter = is_callable( [ $this, 'set_' . ltrim( $key, '_' ) ] ) || is_callable( [ $this, 'get_' . ltrim( $key, '_' ) ] );

		if ( ! $has_setter_or_getter ) {
			return false;
		}


		/* translators: %s: $key Key to check */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'Generic add/update/get meta methods should not be used for internal meta data, including "%s". Use getters and setters.', 'storeengine' ), esc_html( $key ) ), '1.0.0' );

		return true;
	}

	/**
	 * Get Meta Data by Key.
	 *
	 * @param string $key Meta Key.
	 * @param bool $single return first found meta with key, or all with $key.
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return mixed
	 */
	public function get_meta( $key = '', $single = true, $context = 'view' ) {
		if ( $this->is_internal_meta_key( $key ) ) {
			$function = 'get_' . ltrim( $key, '_' );

			if ( is_callable( array( $this, $function ) ) ) {
				return $this->{$function}();
			}
		}

		$this->maybe_read_meta_data();
		$meta_data  = $this->get_meta_data();
		$array_keys = array_keys( wp_list_pluck( $meta_data, 'key' ), $key, true );
		$value      = $single ? '' : [];

		if ( ! empty( $array_keys ) ) {
			// We don't use the $this->meta_data property directly here because we don't want meta with a null value (i.e. meta which has been deleted via $this->delete_meta_data()).
			if ( $single ) {
				$value = $meta_data[ current( $array_keys ) ]->value;
			} else {
				$value = array_intersect_key( $meta_data, array_flip( $array_keys ) );
			}
		}

		if ( 'view' === $context ) {
			/**
			 * @ignore Ignore from Hook parser.
			 */
			$value = apply_filters( $this->get_hook_prefix( $key ), $value, $this );
		}

		return $value;
	}

	public function get_meta_values( $key = '', $context = 'view' ) {
		/** @var MetaData[] $data */
		$data = $this->get_meta( $key, false, 'read' );
		if ( empty( $data ) ) {
			return [];
		}

		$values = array_values( wp_list_pluck( $data, 'value' ) );

		if ( 'view' === $context ) {
			/**
			 * @ignore Ignore from Hook parser.
			 */
			$values = apply_filters( $this->get_hook_prefix( $key ), $values, $this );
		}

		return $values;
	}

	/**
	 * Use get_meta() method for reading metadata value with context.
	 * This is for internal usage, intended to be used with read_data()
	 *
	 * @param string $key
	 * @param bool $single
	 *
	 * @return string|array|false|mixed
	 * @see read_data
	 * @see get_meta
	 */
	protected function get_metadata( string $key = '', bool $single = true ) {
		return get_metadata( $this->meta_type, $this->get_id(), $key, $single );
	}

	/**
	 * See if meta data exists, since get_meta always returns a '' or array().
	 *
	 * @param string $key Meta Key.
	 * @param bool $strict
	 *
	 * @return boolean
	 */
	public function meta_exists( string $key = '', bool $strict = true ): bool {
		$this->maybe_read_meta_data();
		$array_keys = wp_list_pluck( $this->get_meta_data(), 'key' );

		return in_array( $key, $array_keys, $strict ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	/**
	 * Set all meta data from array.
	 *
	 * @param array $data Key/Value pairs.
	 */
	public function set_meta_data( $data ) {
		if ( ! empty( $data ) && is_array( $data ) ) {
			$this->maybe_read_meta_data();
			foreach ( $data as $meta ) {
				$meta = (array) $meta;
				if ( isset( $meta['key'], $meta['value'], $meta['id'] ) ) {
					$this->meta_data[] = new MetaData( [
						'id'    => $meta['id'],
						'key'   => $meta['key'],
						'value' => $meta['value'],
					] );
				}
			}
		}
	}

	/**
	 * Add meta data.
	 *
	 * @param string $key Meta key.
	 * @param string|array|mixed $value Meta value.
	 * @param bool $unique Should this be a unique key?.
	 */
	public function add_meta_data( string $key, $value, bool $unique = false ) {
		if ( ! $key ) {
			return;
		}
		if ( $this->is_internal_meta_key( $key ) ) {
			$function = 'set_' . ltrim( $key, '_' );

			if ( is_callable( array( $this, $function ) ) ) {
				$this->{$function}( $value );
			}
		}

		$this->maybe_read_meta_data();

		if ( $unique ) {
			$this->delete_meta_data( $key );
		}

		$this->meta_data[] = new MetaData( [
			'key'   => $key,
			'value' => $value,
		] );
	}

	/**
	 * Update meta data by key or ID, if provided.
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Meta value.
	 * @param int $meta_id Meta ID.
	 */
	public function update_meta_data( string $key, $value, int $meta_id = 0 ) {
		if ( $this->is_internal_meta_key( $key ) ) {
			$function = 'set_' . ltrim( $key, '_' );

			if ( is_callable( array( $this, $function ) ) ) {
				$this->{$function}( $value );

				return;
			}
		}

		$this->maybe_read_meta_data();

		$array_key = false;

		if ( $meta_id ) {
			$array_keys = array_keys( wp_list_pluck( $this->meta_data, 'id' ), $meta_id, true );
			$array_key  = $array_keys ? current( $array_keys ) : false;
		} else {
			// Find matches by key.
			$matches = [];
			foreach ( $this->meta_data as $meta_data_array_key => $meta ) {
				if ( $meta->key === $key ) {
					$matches[] = $meta_data_array_key;
				}
			}

			if ( ! empty( $matches ) ) {
				// Set matches to null so only one key gets the new value.
				foreach ( $matches as $meta_data_array_key ) {
					$this->meta_data[ $meta_data_array_key ]->value = null;
				}
				$array_key = current( $matches );
			}
		}

		if ( false !== $array_key ) {
			$meta        = $this->meta_data[ $array_key ];
			$meta->key   = $key;
			$meta->value = $value;
		}

		$this->add_meta_data( $key, $value, true );
	}

	/**
	 * Delete meta data.
	 *
	 * @param string $key Meta key.
	 */
	public function delete_meta_data( string $key ) {
		$this->maybe_read_meta_data();
		$array_keys = array_keys( wp_list_pluck( $this->meta_data, 'key' ), $key, true );

		if ( $array_keys ) {
			foreach ( $array_keys as $array_key ) {
				$this->meta_data[ $array_key ]->value = null;
			}
		}
	}

	/**
	 * Delete meta data with a matching value.
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Meta value. Entries will only be removed that match the value.
	 */
	public function delete_meta_data_value( $key, $value ) {
		$this->maybe_read_meta_data();
		$array_keys = array_keys( wp_list_pluck( $this->meta_data, 'key' ), $key, true );

		if ( $array_keys ) {
			foreach ( $array_keys as $array_key ) {
				if ( $value === $this->meta_data[ $array_key ]->value ) {
					$this->meta_data[ $array_key ]->value = null;
				}
			}
		}
	}

	/**
	 * Delete meta data.
	 *
	 * @param int $mid Meta ID.
	 */
	public function delete_meta_data_by_mid( $mid ) {
		$this->maybe_read_meta_data();
		$array_keys = array_keys( wp_list_pluck( $this->meta_data, 'id' ), (int) $mid, true );

		if ( $array_keys ) {
			foreach ( $array_keys as $array_key ) {
				$this->meta_data[ $array_key ]->value = null;
			}
		}
	}

	/**
	 * Return data changes only.
	 *
	 * @return array
	 */
	public function get_changes(): array {
		return $this->changes;
	}

	/**
	 * Merge changes with data and clear.
	 */
	public function apply_changes() {
		$this->data    = array_replace_recursive( $this->data, $this->changes ); // @codingStandardsIgnoreLine
		$this->changes = [];
	}

	/**
	 * Prefix for action and filter hooks on data.
	 *
	 * @param string $prop
	 *
	 * @return string
	 */
	protected function get_hook_prefix( string $prop ): string {
		return 'storeengine/' . $this->object_type . '/get/' . $prop;
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * Gets the value from either current pending changes, or the data itself.
	 * Context controls what happens to the value before it's returned.
	 *
	 * @param string $prop Name of prop to get.
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return mixed
	 */
	protected function get_prop( string $prop, string $context = 'view' ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = array_key_exists( $prop, $this->changes ) ? $this->changes[ $prop ] : $this->data[ $prop ];

			if ( 'view' === $context ) {
				/**
				 * @ignore Ignore from Hook parser.
				 */
				$value = apply_filters( $this->get_hook_prefix( $prop ), $value, $this );
			}
		}

		return $value;
	}

	/**
	 * Get datetime string or unix-timestamp
	 *
	 * @param string $prop
	 * @param string $format
	 * @param bool $gmt
	 * @param string $context
	 *
	 * @return false|int|string|null
	 */
	protected function get_formatted_date_prop( string $prop, string $format = 'mysql', bool $gmt = false, string $context = 'view' ) {
		/**
		 * @var ?StoreengineDatetime $date
		 */
		$date = $this->get_prop( $prop, $context );

		if ( ! $date ) {
			return $date;
		}

		if ( 'mysql' === $format ) {
			$format = 'Y-m-d H:i:s';
		}

		$timestamp = ! $gmt ? $date->getOffsetTimestamp() : $date->getTimestamp();

		if ( 'timestamp' === $format || 'U' === $format ) {
			return $timestamp;
		}

		return gmdate( $format, $timestamp );
	}

	/**
	 * Clear meta cache.
	 */
	public function clear_cache() {
		wp_cache_delete( $this->get_id(), $this->cache_group );
		if ( $this->meta_type ) {
			wp_cache_delete( $this->get_id(), $this->meta_type . '_meta' );
		}

		wp_cache_set_last_changed( $this->cache_group );
	}

	/**
	 * When invalid data is found, throw an exception unless reading from the DB.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int $http_status_code HTTP status code.
	 * @param array|null $data Extra error data.
	 * @param ?Throwable $previous Extra error data.
	 *
	 * @throws StoreEngineException Data Exception.
	 */
	protected function error( string $code, string $message, int $http_status_code = 400, ?array $data = null, ?Throwable $previous = null ) {
		throw new StoreEngineException( $message, $code, $data, $http_status_code, $previous );
	}
}

// End of file abstract-entity.php.
