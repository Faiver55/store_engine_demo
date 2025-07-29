<?php

namespace StoreEngine\Classes;

use StoreEngine\Ajax\Checkout;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;
use StoreEngine\Utils\TaxUtil;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class Customer - Represents a customer.
 */
class Customer {

	protected int $id = 0;

	protected array $data     = array(
		'user_login'      => '',
		'user_email'      => '',
		'user_pass'       => null,
		'user_url'        => '',
		'user_nicename'   => '',
		'display_name'    => '',
		'user_registered' => null,
	);
	protected array $new_data = array();

	protected array $core_meta_data     = array(
		'nickname'    => '',
		'first_name'  => '',
		'last_name'   => '',
		'description' => '',
	);
	protected array $new_core_meta_data = array();

	protected array $internal_meta_data = array(
		'total_orders'       => 0,
		'total_spent'        => 0,
		'subscribe_to_email' => false,
	);

	protected array $new_internal_meta_data = array();

	protected array $billing_address = array(
		'first_name' => '',
		'last_name'  => '',
		'address_1'  => '',
		'address_2'  => '',
		'state'      => '',
		'city'       => '',
		'postcode'   => '',
		'country'    => '',
		'email'      => '',
		'phone'      => '',
		'company'    => '',
	);

	protected array $shipping_address = array(
		'first_name' => '',
		'last_name'  => '',
		'address_1'  => '',
		'address_2'  => '',
		'state'      => '',
		'city'       => '',
		'postcode'   => '',
		'country'    => '',
		'email'      => '',
		'phone'      => '',
		'company'    => '',
	);

	protected array $draft_address = [];

	/**
	 * Stores if user is VAT exempt for this session.
	 *
	 * @var string
	 */
	protected $is_vat_exempt = false;

	protected bool $calculated_shipping = false;

	protected bool $in_session = false;

	/**
	 * Initialize the customer object.
	 *
	 * @param int $user_id
	 * @param bool $in_session
	 */
	public function __construct( int $user_id = 0, bool $in_session = false ) {
		$this->id         = $user_id;
		$this->in_session = $in_session;
		$this->get();
	}

	/**
	 * Get & Set up the customer data.
	 *
	 * @return Customer|false
	 */
	public function get() {
		if ( 0 === $this->get_id() && ! $this->in_session ) {
			return false;
		}

		$user = get_userdata( $this->get_id() );

		if ( ! $user && ! $this->in_session ) {
			return false;
		}

		if ( $user ) {
			$this->set_data( $user );
		}

		if ( $this->in_session && Helper::get_recent_draft_order( 0, null, false ) ) {
			$order = Helper::get_recent_draft_order( 0, null, false );
			$keys  = [
				'first_name',
				'last_name',
				'address_1',
				'address_2',
				'state',
				'city',
				'postcode',
				'country',
				'email',
				'phone',
				'company',
			];

			foreach ( $keys as $key ) {
				if ( method_exists( $order, 'get_billing_' . $key ) && $order->{'get_billing_' . $key}( 'edit' ) ) {
					$this->draft_address['billing'][ $key ] = $order->{'get_billing_' . $key}( 'edit' );
				}
				if ( method_exists( $this, 'set_shipping_' . $key ) && method_exists( $order, 'set_shipping_' . $key ) && $order->{'get_shipping_' . $key}( 'edit' ) ) {
					$this->draft_address['shipping'][ $key ] = $order->{'get_shipping_' . $key}( 'edit' );
				}
			}
		}

		return $this;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_email() {
		$value = $this->get_data( 'user_email' );
		if ( $this->in_session && ! $value ) {
			$order = Helper::get_recent_draft_order( 0, null, false );
			$value = $order->get_order_email( 'edit' );
		}

		return $value;
	}

	public function get_username() {
		return $this->get_data( 'user_login' );
	}

	public function get_user_registered() {
		return $this->get_data( 'user_registered' );
	}

	public function get_url() {
		return $this->get_data( 'user_url' );
	}

	public function get_nicename() {
		return $this->get_data( 'user_nicename' );
	}

	public function get_display_name() {
		return $this->get_data( 'display_name' );
	}

	public function get_total_orders() {
		return $this->get_internal_meta_data( 'total_orders' );
	}

	public function get_total_spent() {
		return $this->get_internal_meta_data( 'total_spent' );
	}

	public function has_subscribe_to_email() {
		return $this->get_internal_meta_data( 'subscribe_to_email' );
	}

	public function get_first_name() {
		return $this->get_core_meta_data( 'first_name' );
	}

	public function get_last_name() {
		return $this->get_core_meta_data( 'last_name' );
	}

	public function get_billing_address_1(): ?string {
		return $this->get_billing_address( 'address_1' );
	}

	public function get_billing_address_2(): ?string {
		return $this->get_billing_address( 'address_2' );
	}

	public function get_billing_city(): ?string {
		return $this->get_billing_address( 'city' );
	}

	public function get_billing_state(): ?string {
		return $this->get_billing_address( 'state' );
	}

	public function get_billing_postcode(): ?string {
		return $this->get_billing_address( 'postcode' );
	}

	/**
	 * @see Checkout::update_checkout()
	 */
	public function get_billing_postal_code(): ?string {
		return $this->get_billing_address( 'postcode' );
	}

	public function get_billing_country() {
		return $this->get_billing_address( 'country' );
	}

	public function get_billing_email(): ?string {
		return $this->get_billing_address( 'email' );
	}

	public function get_billing_phone(): ?string {
		return $this->get_billing_address( 'phone' );
	}

	public function get_billing_first_name(): ?string {
		return $this->get_billing_address( 'first_name' );
	}

	public function get_billing_last_name(): ?string {
		return $this->get_billing_address( 'last_name' );
	}

	public function get_billing_full_name(): string {
		$full_name = [ $this->get_billing_first_name(), $this->get_billing_last_name() ];
		$full_name = trim( implode( ' ', array_map( 'trim', $full_name ) ) );

		if ( ! $full_name ) {
			$full_name = $this->get_display_name();
		}

		return $full_name;
	}

	public function get_billing_company(): ?string {
		return $this->get_billing_address( 'company' );
	}

	public function get_shipping_address_1(): ?string {
		return $this->get_shipping_address( 'address_1' );
	}

	public function get_shipping_address_2(): ?string {
		return $this->get_shipping_address( 'address_2' );
	}

	public function get_shipping_city(): ?string {
		return $this->get_shipping_address( 'city' );
	}

	public function get_shipping_state(): ?string {
		return $this->get_shipping_address( 'state' );
	}

	public function get_shipping_postal_code(): ?string {
		return $this->get_shipping_address( 'postcode' );
	}

	public function get_shipping_postcode(): ?string {
		return $this->get_shipping_address( 'postcode' );
	}

	public function get_shipping_country(): ?string {
		return $this->get_shipping_address( 'country' );
	}

	public function get_shipping_email(): ?string {
		return $this->get_shipping_address( 'email' );
	}

	public function get_shipping_phone(): ?string {
		return $this->get_shipping_address( 'phone' );
	}

	public function get_shipping_first_name(): ?string {
		return $this->get_shipping_address( 'first_name' );
	}

	public function get_shipping_last_name(): ?string {
		return $this->get_shipping_address( 'last_name' );
	}

	public function get_shipping_company(): ?string {
		return $this->get_shipping_address( 'company' );
	}

	public function set_data( WP_User $user ) {
		$this->id                      = $user->ID;
		$this->data['user_login']      = $user->user_login;
		$this->data['user_email']      = $user->user_email;
		$this->data['user_url']        = $user->user_url;
		$this->data['user_nicename']   = $user->user_nicename;
		$this->data['display_name']    = $user->display_name;
		$this->data['user_registered'] = $user->user_registered;

		$this->core_meta_data['nickname']    = $user->nickname;
		$this->core_meta_data['first_name']  = $user->first_name;
		$this->core_meta_data['last_name']   = $user->last_name;
		$this->core_meta_data['description'] = $user->description;

		$prefix          = Helper::DB_PREFIX;
		$billing_address = json_decode( $user->{$prefix . 'billing_address'}, true );
		if ( is_array( $billing_address ) ) {
			$this->billing_address = $billing_address;
		}

		$shipping_address = json_decode( $user->{$prefix . 'shipping_address'}, true );
		if ( is_array( $shipping_address ) ) {
			$this->shipping_address = $shipping_address;
		}

		$this->internal_meta_data['total_orders']       = (int) $user->{$prefix . 'total_orders'};
		$this->internal_meta_data['total_spent']        = (float) $user->{$prefix . 'total_spent'};
		$this->internal_meta_data['subscribe_to_email'] = (bool) $user->{$prefix . 'subscribe_to_email'};
	}

	public function set_username( string $username ) {
		$this->new_data['user_login'] = $username;
	}

	public function set_email( string $email ) {
		$this->new_data['user_email'] = $email;
	}

	public function set_password( string $value ) {
		$this->new_data['user_pass'] = $value;
	}

	public function set_url( string $url ) {
		$this->new_data['user_url'] = $url;
	}

	public function get_name() {
		$customer_name = null;
		if ( ! empty( $this->get_first_name() ) ) {
			$customer_name = $this->get_first_name() . ' ' . $this->get_last_name();
		} elseif ( ! empty( $this->get_display_name() ) ) {
			$customer_name = $this->get_display_name();
		} else {
			$customer_name = $this->get_username();
		}

		return $customer_name;
	}

	public function set_display_name( string $display_name ) {
		$this->new_data['display_name'] = $display_name;
	}

	public function set_total_orders( int $value ) {
		$this->new_internal_meta_data['total_orders'] = $value;
	}

	public function set_total_spent( float $value ) {
		$this->new_internal_meta_data['total_spent'] = $value;
	}

	public function set_subscribe_to_email( bool $value ) {
		$this->new_internal_meta_data['subscribe_to_email'] = $value;
	}

	public function set_first_name( string $first_name ) {
		$this->new_core_meta_data['first_name'] = $first_name;
	}

	public function set_last_name( string $last_name ) {
		$this->new_core_meta_data['last_name'] = $last_name;
	}

	public function set_billing_address_1( ?string $address_1 ) {
		$this->billing_address['address_1'] = $address_1;
	}

	public function set_billing_address_2( ?string $address_2 ) {
		$this->billing_address['address_2'] = $address_2;
	}

	public function set_billing_city( ?string $city ) {
		$this->billing_address['city'] = $city;
	}

	public function set_billing_state( ?string $state ) {
		$this->billing_address['state'] = $state;
	}

	public function set_billing_postcode( ?string $postcode ) {
		$this->billing_address['postcode'] = $postcode;
	}

	public function set_billing_country( ?string $country ) {
		$this->billing_address['country'] = $country;
	}

	public function set_billing_email( ?string $email ) {
		$this->billing_address['email'] = $email;
	}

	public function set_billing_phone( ?string $phone ) {
		$this->billing_address['phone'] = $phone;
	}

	public function set_billing_first_name( ?string $first_name ) {
		$this->billing_address['first_name'] = $first_name;
	}

	public function set_billing_last_name( ?string $last_name ) {
		$this->billing_address['last_name'] = $last_name;
	}

	public function set_billing_company( ?string $company ) {
		$this->billing_address['company'] = $company;
	}

	public function set_shipping_address_1( ?string $address_1 ) {
		$this->shipping_address['address_1'] = $address_1;
	}

	public function set_shipping_address_2( ?string $address_2 ) {
		$this->shipping_address['address_2'] = $address_2;
	}

	public function set_shipping_city( ?string $city ) {
		$this->shipping_address['city'] = $city;
	}

	public function set_shipping_state( ?string $state ) {
		$this->shipping_address['state'] = $state;
	}

	public function set_shipping_postcode( ?string $postcode ) {
		$this->shipping_address['postcode'] = $postcode;
	}

	public function set_shipping_country( ?string $country ) {
		$this->shipping_address['country'] = $country;
	}

	public function set_shipping_email( ?string $email ) {
		$this->shipping_address['email'] = $email;
	}

	public function set_shipping_phone( ?string $phone ) {
		$this->shipping_address['phone'] = $phone;
	}

	public function set_shipping_first_name( ?string $first_name ) {
		$this->shipping_address['first_name'] = $first_name;
	}

	public function set_shipping_last_name( ?string $last_name ) {
		$this->shipping_address['last_name'] = $last_name;
	}

	public function set_shipping_company( ?string $company ) {
		$this->shipping_address['company'] = $company;
	}

	public function save() {
		if ( 0 === $this->id ) {
			return $this->create();
		}

		$this->save_data();
		$this->save_core_meta_data();
		$this->save_internal_meta_data();
		$this->save_address();
		$this->save_address( 'shipping' );

		return true;
	}

	protected function create() {
		$data         = $this->new_data;
		$data         = array_filter( $data, fn( $v, $k ) => ( $v !== $this->data[ $k ] ), ARRAY_FILTER_USE_BOTH );
		$data['role'] = 'storeengine_customer';

		if ( empty( $data['user_login'] ) && ! empty( $data['user_email'] ) ) {
			$data['user_login'] = $this->generate_username();
		}

		$user_id = wp_insert_user( $data );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$this->id = $user_id;
		$this->get();
		$this->save();

		update_user_meta( $user_id, 'storeengine_total_orders', 0 );
		update_user_meta( $user_id, 'storeengine_total_spent', 0 );

		return $this;
	}

	protected function generate_username() {
		$base_username = strstr( $this->get_email(), '@', true );
		$username      = $base_username;
		$counter       = 1;

		while ( username_exists( $username ) ) {
			$username = $base_username . $counter;
			$counter ++;
		}

		return $username;
	}

	protected function save_data() {
		$data = $this->new_data;
		$data = array_filter( $data, fn( $v, $k ) => ( $v !== $this->data[ $k ] ), ARRAY_FILTER_USE_BOTH );

		if ( 0 === count( array_keys( $data ) ) ) {
			return;
		}

		wp_update_user( array_merge( $data, [ 'ID' => $this->id ] ) );
	}

	protected function save_core_meta_data() {
		$data = $this->new_core_meta_data;
		$data = array_filter( $data, fn( $v, $k ) => ( $v !== $this->core_meta_data[ $k ] ), ARRAY_FILTER_USE_BOTH );

		if ( 0 === count( array_keys( $data ) ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			update_user_meta( $this->id, $key, $value );
		}
	}

	protected function save_internal_meta_data() {
		$data = $this->new_internal_meta_data;
		$data = array_filter( $data, fn( $v, $k ) => ( $v !== $this->internal_meta_data[ $k ] ), ARRAY_FILTER_USE_BOTH );

		if ( 0 === count( array_keys( $data ) ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			update_user_meta( $this->id, Helper::DB_PREFIX . $key, $value );
		}
	}

	protected function save_address( string $type = 'billing' ) {
		$data = $this->billing_address;

		if ( 'shipping' === $type ) {
			$data = $this->shipping_address;
		}

		if ( 0 === count( array_keys( $data ) ) ) {
			return;
		}


		update_user_meta( $this->id, Helper::DB_PREFIX . $type . '_address', wp_json_encode( $data ) );
	}

	protected function get_data( string $prop, string $context = 'view' ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = array_key_exists( $prop, $this->new_data ) ? $this->new_data[ $prop ] : $this->data[ $prop ];

			if ( 'view' === $context ) {
				/**
				 * @ignore Ignore from Hook parser.
				 */
				$value = apply_filters( 'storeengine/customer/get/' . $prop, $value, $this );
			}
		}

		return $value;
	}

	protected function get_core_meta_data( string $key ) {
		if ( array_key_exists( $key, $this->new_core_meta_data ) ) {
			return $this->new_core_meta_data[ $key ];
		}

		return $this->core_meta_data[ $key ];
	}

	protected function get_internal_meta_data( string $key ) {
		if ( array_key_exists( $key, $this->new_internal_meta_data ) ) {
			return $this->new_internal_meta_data[ $key ];
		}

		return $this->internal_meta_data[ $key ];
	}

	/**
	 * @param string $prop
	 * @param string $context
	 *
	 * @return string|null
	 */
	protected function get_billing_address( string $prop, string $context = 'view' ): ?string {
		$value = null;

		if ( array_key_exists( $prop, $this->billing_address ) ) {
			$value = $this->draft_address['billing'][ $prop ] ?? $this->billing_address[ $prop ];

			if ( 'view' === $context ) {
				/**
				 * Filter: 'storeengine/order_get_[billing|shipping]_[prop]'
				 *
				 * Allow developers to change the returned value for any order address property.
				 *
				 * @param string $value The address property value.
				 * @param Order $order The order object being read.
				 *
				 * @ignore Ignore from HookParser.
				 */
					$value = apply_filters( 'storeengine/customer/billing_' . $prop, $value, $this );
			}
		}

		return $value;
	}

	/**
	 * @param string $key
	 * @param string|null $default
	 *
	 * @return string|null
	 */
	protected function get_shipping_address( string $prop, string $context = 'view' ): ?string {
		$value = null;

		if ( array_key_exists( $prop, $this->shipping_address ) ) {
			$value = $this->draft_address['shipping'][ $prop ] ?? $this->shipping_address[ $prop ];

			if ( 'view' === $context ) {
				/**
				 * Filter: 'storeengine/order_get_[billing|shipping]_[prop]'
				 *
				 * Allow developers to change the returned value for any order address property.
				 *
				 * @param string $value The address property value.
				 * @param Order $order The order object being read.
				 *
				 * @ignore Ignore from HookParser.
				 */
				$value = apply_filters( 'storeengine/customer/billing_' . $prop, $value, $this );
			}
		}

		return $value;
	}

	/**
	 * Set if customer has tax exemption.
	 *
	 * @param bool|string $is_vat_exempt If is vat exempt.
	 */
	public function set_is_vat_exempt( $is_vat_exempt ) {
		$this->is_vat_exempt = Formatting::string_to_bool( $is_vat_exempt );
	}

	/**
	 * Get if customer is VAT exempt?
	 *
	 * @return bool
	 */
	public function get_is_vat_exempt() {
		return $this->is_vat_exempt;
	}

	/**
	 * Is customer VAT exempt?
	 *
	 * @return bool
	 */
	public function is_vat_exempt() {
		return $this->get_is_vat_exempt();
	}

	/**
	 * Calculated shipping?
	 *
	 * @param bool|string $calculated If shipping is calculated.
	 */
	public function set_calculated_shipping( $calculated = true ) {
		$this->calculated_shipping = Formatting::string_to_bool( $calculated );
	}

	/**
	 * Has customer calculated shipping?
	 *
	 * @return bool
	 */
	public function get_calculated_shipping(): bool {
		return $this->calculated_shipping;
	}

	/**
	 * Has calculated shipping?
	 *
	 * @return bool
	 */
	public function has_calculated_shipping(): bool {
		return $this->get_calculated_shipping();
	}

	public function get_shipping(): array {
		return $this->shipping_address;
	}

	public function get_billing(): array {
		return $this->billing_address;
	}

	/**
	 * Indicates if the customer has a non-empty shipping address.
	 *
	 * Note that this does not indicate if the customer's shipping address
	 * is complete, only that one or more fields are populated.
	 *
	 * @return bool
	 */
	public function has_shipping_address(): bool {
		return $this->get_shipping_address_1() || $this->get_shipping_address_2();
	}

	/**
	 * Checks whether the address is "full" in the sense that it contains all required fields to calculate shipping rates.
	 *
	 * @return bool Whether the customer has a full shipping address (address_1, city, state, postcode, country).
	 * Only required fields are checked.
	 */
	public function has_full_shipping_address(): bool {
		// These are the important fields required to get the shipping rates. Note that while we're respecting the filters
		// for the shipping calculator below (city, postcode, state), we're not respecting the filter for the country field.
		// The country field is always required as a bare minimum for shipping.
		$shipping_address = [
			'country' => $this->get_shipping_country(),
		];

		/**
		 * Filter to not require shipping city for shipping calculation, even if it is required at checkout.
		 * This can be used to allow shipping calculations to be done without a city.
		 *
		 * @param bool $show_city Whether to use the city field. Default true.
		 */
		if ( apply_filters( 'storeengine/shipping/calculator_enable_city', true ) ) {
			$shipping_address['city'] = $this->get_shipping_city();
		}

		/**
		 * Filter to not require shipping state for shipping calculation, even if it is required at checkout.
		 * This can be used to allow shipping calculations to be done without a state.
		 *
		 * @param bool $show_state Whether to use the state field. Default true.
		 */
		if ( apply_filters( 'storeengine/shipping/calculator_enable_state', true ) ) {
			$shipping_address['state'] = $this->get_shipping_state();
		}

		/**
		 * Filter to not require shipping postcode for shipping calculation, even if it is required at checkout.
		 * This can be used to allow shipping calculations to be done without a postcode.
		 *
		 * @since 8.4.0
		 *
		 * @param bool $show_postcode Whether to use the postcode field. Default true.
		 */
		if ( apply_filters( 'storeengine/shipping/calculator_enable_postcode', true ) ) {
			$shipping_address['postcode'] = $this->get_shipping_postcode();
		}

		$address_fields = Countries::init()->get_country_locale();
		$locale_key     = ! empty( $shipping_address['country'] ) && array_key_exists( $shipping_address['country'], $address_fields ) ? $shipping_address['country'] : 'default';
		$default_locale = $address_fields['default'];
		$country_locale = $address_fields[ $locale_key ] ?? array();

		/**
		 * Checks all shipping address fields against the country's locale settings.
		 *
		 * If there's a `required` setting for the field in the country-specific locale, that setting is used, otherwise
		 * the default locale's setting is used. If the default locale doesn't have a setting either, the field is
		 * considered optional and therefore valid, even if empty.
		 */
		foreach ( $shipping_address as $key => $value ) {
			// Skip further checks if the field has a value. From this point on $value is empty.
			if ( ! empty( $value ) ) {
				continue;
			}

			$locale_to_check = isset( $country_locale[ $key ]['required'] ) ? $country_locale : $default_locale;

			// If the locale requires the field return false.
			if ( isset( $locale_to_check[ $key ]['required'] ) && true === Formatting::string_to_bool( $locale_to_check[ $key ]['required'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Indicates if the customer has a non-empty shipping address.
	 *
	 * Note that this does not indicate if the customer's shipping address
	 * is complete, only that one or more fields are populated.
	 *
	 * @return bool
	 */
	public function has_billing_address(): bool {
		return $this->get_billing_address_1() || $this->get_billing_address_2();
	}

	/**
	 * Get taxable address.
	 *
	 * @return array
	 */
	public function get_taxable_address(): array {
		$tax_based_on = TaxUtil::tax_based_on();
		// Check shipping method at this point to see if we need special handling.
		// @TODO can use this after implementing separate session data.
		/*if ( true === apply_filters( 'storeengine/apply_base_tax_for_local_pickup', true ) && count( array_intersect( ShippingUtils::get_chosen_shipping_method_ids(), apply_filters( 'storeengine/local_pickup_methods', [ 'local_pickup' ] ) ) ) > 0 ) {
			$tax_based_on = 'base';
		}*/

		if ( 'base' === $tax_based_on ) {
			$country  = Helper::get_settings( 'store_country' );
			$state    = Helper::get_settings( 'store_state' );
			$postcode = Helper::get_settings( 'store_postcode' );
			$city     = Helper::get_settings( 'store_city' );
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = $this->get_billing_country();
			$state    = $this->get_billing_state();
			$postcode = $this->get_billing_postcode();
			$city     = $this->get_billing_city();
		} else {
			$country  = $this->get_shipping_country();
			$state    = $this->get_shipping_state();
			$postcode = $this->get_shipping_postcode();
			$city     = $this->get_shipping_city();
		}

		/**
		 * Filters the taxable address for a given customer.
		 *
		 * @param array $taxable_address An array of country, state, postcode, and city for the customer's taxable address.
		 * @param object $customer The customer object for which the taxable address is being requested.
		 *
		 * @return array The filtered taxable address for the customer.
		 */
		return apply_filters( 'storeengine/customer_taxable_address', [ $country, $state, $postcode, $city ], $this );
	}

	/**
	 * Is customer outside base country (for tax purposes)?
	 *
	 * @return bool
	 */
	public function is_customer_outside_base(): bool {
		list( $country, $state ) = $this->get_taxable_address();
		if ( $country ) {
			if ( Helper::get_settings( 'store_country' ) !== $country ) {
				return true;
			}
			if ( Helper::get_settings( 'store_state' ) && Helper::get_settings( 'store_state' ) !== $state ) {
				return true;
			}
		}

		return false;
	}
}
