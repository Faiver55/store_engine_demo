<?php
/**
 * Frontend helper functions.
 */

use StoreEngine\Classes\Attributes;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\ProductFactory;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;
use StoreEngine\Utils\Template;

/**
 * Handle redirects before content is output - hooked into template_redirect so is_page works.
 *
 * @return void
 * @see wc_template_redirect()
 */
function storeengine_template_redirect(): void {
	global $wp_query, $wp;

	if ( ! is_user_logged_in() && Helper::is_dashboard() ) {
		$auth_redirect_type = Helper::get_settings( 'auth_redirect_type', 'storeengine' );
		if ( 'storeengine' !== $auth_redirect_type ) {
			if ( 'default' === $auth_redirect_type ) {
				wp_safe_redirect( wp_login_url() );
				die();
			}

			if ( 'custom' === $auth_redirect_type && Helper::get_settings( 'auth_redirect_url' ) ) {
				wp_safe_redirect( Helper::get_settings( 'auth_redirect_url' ) );
				die();
			}
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	// When default permalinks are enabled, redirect shop page to post type archive url.
	if ( ! empty( $_GET['page_id'] ) && '' === get_option( 'permalink_structure' ) && Helper::get_settings( 'shop_page' ) === absint( $_GET['page_id'] ) && get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) ) {
		wp_safe_redirect( get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) );
		exit;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// When on the checkout with an empty cart, redirect to cart page.
	if (
		is_page( Helper::get_settings( 'checkout_page' ) ) &&
		Helper::get_settings( 'checkout_page' ) !== Helper::get_settings( 'cart_page' ) &&
		Helper::cart()->is_cart_empty() &&
		empty( $wp->query_vars['order-pay'] ) &&
		! isset( $wp->query_vars['order-received'] ) &&
		! isset( $wp->query_vars['order_pay'] ) &&
		! is_customize_preview() &&
		apply_filters( 'storeengine/checkout_redirect_empty_cart', true )
	) {
		wp_safe_redirect( Helper::get_page_permalink( 'cart_page' ) );
		exit;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	// Logout endpoint under My Account page. Logging out requires a valid nonce.
	if ( Helper::is_endpoint( 'customer-logout' ) ) {
		if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'customer-logout' ) ) {
			wp_logout();
			if ( ! empty( $_REQUEST['redirect_to'] ) ) {
				$redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
			} else {
				$redirect_to = storeengine_get_logout_redirect_url();
			}

			wp_safe_redirect( wp_validate_redirect( $redirect_to ) );
			exit;
		}
		wp_die(
		/* translators: %s: logout url */
			sprintf( wp_kses_post( __( 'Are you sure you want to log out? <a href="%s">Confirm and log out</a>', 'storeengine' ) ), esc_url( storeengine_logout_url() ) ),
			esc_html__( 'Are you sure you want to log out?', 'storeengine' ),
			[ 'back_link' => true ]
		);
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Trigger 404 if trying to access an endpoint on wrong page.
	if ( Helper::is_endpoint() && ! Helper::is_dashboard() && ! Helper::is_checkout() && apply_filters( 'storeengine/dashboard/endpoint_page_not_found', true ) ) {
		$wp_query->set_404();
		status_header( 404 );
		include get_query_template( '404' );
		exit;
	}

	// Redirect to the product page if we have a single product.
	if ( is_search() && is_post_type_archive( Helper::PRODUCT_POST_TYPE ) && apply_filters( 'storeengine/redirect/single_search_result', true ) && 1 === absint( $wp_query->found_posts ) ) {
		$product = storeengine_get_product( $wp_query->post->ID );
		if ( $product && $product->is_visible() ) {
			wp_safe_redirect( get_permalink( $product->get_id() ) );
			exit;
		}
	}
}

/**
 * Prevent any user who cannot 'edit_posts' (subscribers, customers etc.) from seeing the admin bar.
 *
 * @param bool $show_admin_bar If site should display admin bar.
 *
 * @return bool
 */
function storeengine_disable_admin_bar( bool $show_admin_bar ): bool {
	/**
	 * Controls whether the WooCommerce admin bar should be disabled.
	 *
	 * @param bool $enabled
	 */
	if ( apply_filters( 'storeengine/disable_admin_bar', true ) && ! ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_storeengine' ) ) ) {
		$show_admin_bar = false;
	}

	return $show_admin_bar;
}

/**
 * @throws StoreEngineException
 */
function storeengine_get_order( int $id ): Order {
	return new Order( $id );
}

function storeengine_cart(): Cart {
	return StoreEngine::init()->get_cart();
}

if ( ! function_exists( 'storeengine_get_header' ) ) {
	function storeengine_get_header( $header_name = 'product' ) {
		if ( Helper::is_fse_theme() ) {
			?>
			<!doctype html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<?php wp_head(); ?>
			</head>

			<body <?php body_class(); ?>>
			<?php wp_body_open(); ?>
			<div class="wp-site-blocks">
			<?php
			if ( apply_filters( 'storeengine/templates/is_allow_block_theme_header', true ) ) :
				?>
				<header class="wp-block-template-part site-header">
					<?php block_header_area(); ?>
				</header>
			<?php
			endif;
			?>
			<?php
		} else {
			get_header( $header_name );
		}
	}
}

if ( ! function_exists( 'storeengine_get_footer' ) ) {
	function storeengine_get_footer( $footer_name = 'product' ) {
		if ( Helper::is_fse_theme() ) {
			if ( apply_filters( 'storeengine/templates/is_allow_block_theme_footer', true ) ) :
				?>
				<footer class="wp-block-template-part site-footer">
					<?php block_footer_area(); ?>
				</footer>
			<?php endif; ?>
			</div>
			<?php wp_footer(); ?>
			</body>
			</html>
		<?php } else {
			get_footer( $footer_name );
		}
	}
}

if ( ! function_exists( 'storeengine_initialize_product_data' ) ) {
	function storeengine_initialize_product_data( $post ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		if ( empty( $post->post_type ) || $post->post_type !== 'storeengine_product' ) {
			return;
		}

		unset( $GLOBALS['product'] );

		$GLOBALS['product'] = storeengine_get_product( $post->ID );
	}
}

if ( ! function_exists( 'storeengine_single_product_header' ) ) {
	function storeengine_single_product_header() {
		Template::get_template(
			'single-product/header.php',
		);
	}
}

if ( ! function_exists( 'storeengine_single_product_add_to_cart' ) ) {
	function storeengine_single_product_add_to_cart() {
		Template::get_template( 'single-product/add-to-cart.php' );
	}
}

if ( ! function_exists( 'storeengine_single_product_footer' ) ) {
	function storeengine_single_product_footer() {
		Template::get_template(
			'single-product/footer.php',
		);
	}
}

if ( ! function_exists( 'storeengine_single_product_description' ) ) {
	function storeengine_single_product_description() {
		Template::get_template(
			'single-product/description.php',
		);
	}
}

if ( ! function_exists( 'storeengine_add_to_cart_form' ) ) {
	function storeengine_add_to_cart_form() {
		$cart_page_permalink = Helper::get_page_permalink( 'cart_page' );
		$has_view_cart       = true;
		Template::get_template(
			'single-product/add-to-cart.php',
			[
				'cart_page_permalink' => $cart_page_permalink,
				'has_view_cart'       => $has_view_cart,
			]
		);
	}
}

function storeengine_placeholder_image_src( $size = 'storeengine_thumbnail' ) {
	// @TODO allow admin to change placeholder image.
	// @TODO implement size args.

	return apply_filters( 'storeengine/placeholder/image_src', STOREENGINE_ASSETS_URI . 'images/thumbnail-placeholder.png', $size );
}

/**
 * Get the placeholder image.
 *
 * @param string $size
 * @param string|array $attr
 *
 * @return string
 * @see \WC_Install::create_options()
 *
 * @see add_image_size()
 */
function storeengine_placeholder_image( string $size = 'storeengine_thumbnail', $attr = '' ): string {
	// @TODO add thumbnail & single product image size
	// @TODO use wp_get_attachment_image

	$dimensions   = [
		'width'  => 600,
		'height' => 600,
	];
	$default_attr = [
		'class' => 'storeengine-placeholder wp-post-image',
		'alt'   => __( 'Placeholder', 'storeengine' ),
	];
	$attr         = wp_parse_args( $attr, $default_attr );
	$image        = storeengine_placeholder_image_src( $size );
	$hwstring     = image_hwstring( $dimensions['width'], $dimensions['height'] );
	$attributes   = [];

	foreach ( $attr as $name => $value ) {
		$attributes[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}

	$image_html = '<img src="' . esc_url( $image ) . '" ' . $hwstring . implode( ' ', $attributes ) . '/>'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

	return apply_filters( 'storeengine/placeholder/image', $image_html, $size, $dimensions );
}

function storeengine_has_product_thumbnail( ?int $product_id = null ): bool {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	return (bool) apply_filters( 'storeengine/has_post_thumbnail', has_post_thumbnail( $product_id ), $product_id );
}

function storeengine_has_product_gallery( ?int $product_id = null ): bool {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	$has_gallery = ! empty( get_post_meta( $product_id, '_storeengine_product_gallery_ids', true ) );

	return apply_filters( 'storeengine/has_product_gallery', $has_gallery, $product_id );
}

function storeengine_get_product_image( $size = 'storeengine_thumbnail', $product_id = null, $attr = '', $placeholder = true ) {
	$image        = '';
	$thumbnail_id = 0;

	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( $product_id ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $product_id );
	}

	if ( ! $thumbnail_id ) {
		// Fallback to first item on the gallery.
		$ids = get_post_meta( $product_id, '_storeengine_product_gallery_ids', true );
		if ( $ids && is_array( $ids ) ) {
			$ids          = array_unique( array_filter( $ids ) );
			$thumbnail_id = reset( $ids );
		}
	}

	if ( $thumbnail_id ) {
		$image = wp_get_attachment_image( $thumbnail_id, $size, false, $attr );
	}

	if ( ! $image && $placeholder ) {
		$image = storeengine_placeholder_image( $size, $attr );
	}

	return apply_filters( 'storeengine/product/image_html', $image, $product_id, $size, $attr, $placeholder );
}

function storeengine_product_image( $size = 'storeengine_thumbnail', $product_id = null, $attr = '', $placeholder = true ) {
	// Output from wp image html.
	echo wp_kses_post( storeengine_get_product_image( $size, $product_id, $attr, $placeholder ) );
}

function storeengine_product_gallery( $product_id = null, $size = 'storeengine_thumbnail', $attr = '' ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( $product_id ) {
		// @TODO allow admin to choose a featured image too..
		// @XXX backend allows admin to add same image multiple time.
		$ids = get_post_meta( get_the_ID(), '_storeengine_product_gallery_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}

		$ids = array_unique( array_filter( $ids ) );
		if ( ! Helper::is_product() && count( $ids ) > 1 ) {
			$ids = [ reset( $ids ) ];
			$ids = array_filter( $ids );
		}

		foreach ( $ids as $id ) {
			$image = wp_get_attachment_image_src( $id, $size );
			if ( ! $image ) {
				continue;
			}

			printf(
				'<span class="carousel-cell">%1$s</span>',
				wp_get_attachment_image( $id, $size, false, $attr )
			);
		}
	}
}

if ( ! function_exists( 'storeengine_price' ) ) {
	function storeengine_price() {
		Template::get_template(
			'single-product/price.php'
		);
	}
}

if ( ! function_exists( 'storeengine_single_view_cart' ) ) {
	function storeengine_single_view_cart() {
		$cart_item = storeengine_cart()->get_cart_items_by_product( get_the_ID() );

		if ( $cart_item ) {
			$total_quantity_in_cart = array_sum( array_column( $cart_item, 'quantity' ) );
			$num_prices_in_cart     = count( $cart_item );
			Template::get_template( 'notice/view-cart.php', [
				'total_quantity_in_cart' => $total_quantity_in_cart,
				'num_prices_in_cart'     => $num_prices_in_cart,
			] );
		}
	}
}

if ( ! function_exists( 'storeengine_product_loop_header' ) ) {
	function storeengine_product_loop_header() {
		Template::get_template(
			'loop/header.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_loop_content' ) ) {
	function storeengine_product_loop_content() {
		Template::get_template(
			'loop/content.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_loop_footer' ) ) {
	function storeengine_product_loop_footer() {
		Template::get_template(
			'loop/footer.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_loop_add_to_cart' ) ) {
	function storeengine_product_loop_add_to_cart() {
		Template::get_template( 'loop/add-to-cart.php' );
	}
}

if ( ! function_exists( 'storeengine_get_the_product_category' ) ) {
	function storeengine_get_the_product_category( $ID ) {
		return get_the_terms( $ID, Helper::PRODUCT_CATEGORY_TAXONOMY );
	}
}
if ( ! function_exists( 'storeengine_get_the_product_tag' ) ) {
	function storeengine_get_the_product_tag( $ID ) {
		return get_the_terms( $ID, Helper::PRODUCT_TAG_TAXONOMY );
	}
}
if ( ! function_exists( 'storeengine_single_categories' ) ) {
	function storeengine_single_categories() {
		Template::get_template(
			'single-product/categories.php',
		);
	}
}

if ( ! function_exists( 'storeengine_single_tag' ) ) {
	function storeengine_single_tag() {
		Template::get_template(
			'single-product/tag.php',
		);
	}
}

if ( ! function_exists( 'storeengine_global_products' ) ) {
	function storeengine_global_products() {
		Helper::get_template( 'global/products.php' );
	}
}

if ( ! function_exists( 'storeengine_no_products' ) ) {
	function storeengine_no_products() {
		Helper::get_template( 'archive/product-none.php' );
	}
}

if ( ! function_exists( 'storeengine_get_product' ) ) {
	function storeengine_get_product( $product_id ) {
		$product = ( new ProductFactory() )->get_product( $product_id );

		return $product->get_id() ? $product : false;
	}
}

if ( ! function_exists( 'storeengine_attributes_generator' ) ) {
	function storeengine_attributes_generator(): Attributes {
		return new Attributes();
	}
}

if ( ! function_exists( 'storeengine_get_checkout_url' ) ) {
	/**
	 * @return mixed|null
	 * @deprecated
	 */
	function storeengine_get_checkout_url() {
		return Helper::get_checkout_url();
	}
}

if ( ! function_exists( 'storeengine_get_account_menu_item' ) ) {
	function storeengine_get_account_menu_items() {
		$items = array(
			'index'             => __( 'Dashboard', 'storeengine' ),
			'orders'            => __( 'Orders', 'storeengine' ),
			'plans'             => __( 'Plans', 'storeengine' ),
			'edit-address'      => _n( 'Address', 'Addresses', ( 1 + (int) ShippingUtils::is_tax_enabled() ), 'storeengine' ),
			'affiliate-partner' => __( 'Affiliate', 'storeengine' ),
			'payment-methods'   => __( 'Payment methods', 'storeengine' ),
			'edit-account'      => __( 'Account details', 'storeengine' ),
			'customer-logout'   => __( 'Log out', 'storeengine' ),
		);

		return apply_filters( 'storeengine/account_menu_items', $items );
	}
}

if ( ! function_exists( 'storeengine_get_endpoint_url' ) ) {
	/**
	 * @param string $endpoint
	 * @param string|int|float $value
	 * @param string|false $permalink
	 *
	 * @return string
	 * @deprecated
	 * @use Helper::get_endpoint_url()
	 */
	function storeengine_get_endpoint_url( string $endpoint, $value = '', $permalink = '' ): string {
		return Helper::get_endpoint_url( $endpoint, $value, $permalink );
	}
}

function storeengine_get_logout_redirect_url(): string {
	return apply_filters( 'storeengine/logout_default_redirect_url', Helper::get_dashboard_url() );
}

function storeengine_logout_url( string $redirect = '' ): string {
	$redirect   = $redirect ? $redirect : storeengine_get_logout_redirect_url();
	$args       = [
		'redirect_to' => $redirect,
		'action'      => 'logout',
	];
	$logout_url = Helper::get_endpoint_url( 'customer-logout', '', Helper::get_dashboard_url() );
	$logout_url = wp_nonce_url( add_query_arg( $args, $logout_url ), 'customer-logout' );

	return apply_filters( 'storeengine/logout_url', $logout_url, $redirect );
}

/**
 * @param string $endpoint
 * @param string|int|float $value $value
 *
 * @return mixed|string|null
 */
function storeengine_get_dashboard_endpoint_url( string $endpoint, $value = '' ) {
	if ( 'index' === $endpoint || 'dashboard' === $endpoint || 'myaccount' === $endpoint ) {
		return Helper::get_dashboard_url();
	}

	if ( 'customer-logout' === $endpoint ) {
		return storeengine_logout_url();
	}

	return Helper::get_endpoint_url( $endpoint, $value, Helper::get_dashboard_url() );
}

function storeengine_show_notice( string $message, $args = [] ) {
	if ( is_string( $args ) ) {
		$args = [ 'type' => $args ];
	}

	$args = wp_parse_args( $args, [
		'type'        => 'info',
		'title'       => '',
		'icon'        => 'info',
		'alt'         => false,
		'dismissible' => false,
		'id'          => wp_unique_id( 'storeengine-notice-' ),
		'buttons'     => [],
	] );

	if ( 'danger' === $args['type'] ) {
		$args['type'] = 'error';
	}
	if ( 'alert' === $args['type'] ) {
		$args['type'] = 'warning';
	}

	$valid_notice_types = [ 'primary', 'secondary', 'info', 'success', 'error', 'warning' ];

	if ( ! in_array( $args['type'], $valid_notice_types, true ) ) {
		$args['type'] = 'info';
	}

	$args['message'] = $message;

	Template::get_template( 'notice/notice.php', $args );
}

if ( ! function_exists( 'storeengine_frontend_dashboard_content' ) ) {
	function storeengine_frontend_dashboard_content() {
		global $wp;
		if ( ! empty( $wp->query_vars ) ) {
			foreach ( $wp->query_vars as $key => $value ) {
				// Ignore pagename param.
				if ( 'storeengine_dashboard_page' !== $key ) {
					continue;
				}
				if ( has_action( 'storeengine/frontend/dashboard_' . $value . '_endpoint' ) ) {
					/**
					 * Hook for dynamic dashboard page contents.
					 *
					 * @param string $value page slug.
					 */
					do_action( 'storeengine/frontend/dashboard_' . $value . '_endpoint', get_query_var( 'storeengine_dashboard_sub_page' ) );

					return;
				}
			}
		}

		// No endpoint found? Default to dashboard.
		Template::get_template( 'frontend-dashboard/pages/dashboard.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_orders_endpoint_content' ) ) {
	function storeengine_frontend_dashboard_orders_endpoint_content( $order_id ) {
		if ( $order_id ) {
			$order            = Helper::get_order( absint( $order_id ) );
			$invalid_statuses = [ OrderStatus::DRAFT, OrderStatus::AUTO_DRAFT, OrderStatus::TRASH ];

			if ( ! $order || is_wp_error( $order ) || ! $order->get_id() || $order->has_status( $invalid_statuses ) || get_current_user_id() !== $order->get_customer_id() ) {
				Template::get_template( 'frontend-dashboard/pages/partials/invalid-order.php', [ 'order' => $order_id ] );
				return;
			}

			Template::get_template( 'frontend-dashboard/pages/view-order.php', [ 'order' => $order ] );
		} else {
			Template::get_template( 'frontend-dashboard/pages/orders.php' );
		}
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_plans_content' ) ) {
	function storeengine_frontend_dashboard_plans_content( $subscription_id ) {
		if ( $subscription_id ) {
			try {
				$subscription = \StoreEngine\Addons\Subscription\Classes\Subscription::get_subscription( absint( $subscription_id ) );
			} catch ( StoreEngineException $e ) {
				$subscription = false;
			}

			if ( ! $subscription || ! $subscription->get_id() || $subscription->has_status( [ OrderStatus::DRAFT, OrderStatus::AUTO_DRAFT, OrderStatus::TRASH ] ) || get_current_user_id() !== $subscription->get_customer_id() ) {
				Template::get_template( 'frontend-dashboard/pages/partials/invalid-plan.php', [ 'subscription' => $subscription_id ] );
				return;
			}

			Template::get_template( 'frontend-dashboard/pages/view-plan.php', [ 'subscription' => $subscription ] );
		} else {
			Template::get_template( 'frontend-dashboard/pages/plans.php' );
		}
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_downloads_content' ) ) {
	function storeengine_frontend_dashboard_downloads_content() {
		Template::get_template( 'frontend-dashboard/pages/downloads.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_payment_methods_content' ) ) {
	function storeengine_frontend_dashboard_payment_methods_content() {
		Template::get_template( 'frontend-dashboard/pages/payment-methods.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_add_payment_method_content' ) ) {
	function storeengine_frontend_dashboard_add_payment_method_content() {
		Template::get_template( 'frontend-dashboard/pages/form-add-payment-method.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_edit_address_content' ) ) {
	function storeengine_frontend_dashboard_edit_address_content( string $load_address = '' ) {
		if ( in_array( $load_address, [ 'billing', 'shipping' ], true ) ) {
			$load_address = sanitize_key( $load_address );
			$customer     = Helper::get_customer();
			$country      = 'billing' === $load_address ? $customer->get_billing_country() : $customer->get_shipping_country();

			if ( ! $country ) {
				$country = Countries::init()->get_base_country();
			}

			if ( 'billing' === $load_address ) {
				$allowed_countries = Countries::init()->get_allowed_countries();

				if ( ! array_key_exists( $country, $allowed_countries ) ) {
					$country = current( array_keys( $allowed_countries ) );
				}
			}

			if ( 'shipping' === $load_address ) {
				$allowed_countries = Countries::init()->get_shipping_countries();

				if ( ! array_key_exists( $country, $allowed_countries ) ) {
					$country = current( array_keys( $allowed_countries ) );
				}
			}

			$address = Countries::init()->get_address_fields( $country, $load_address . '_' );

			foreach ( $address as $key => $field ) {
				$method = 'get_' . $key;
				$value  = '';

				if ( method_exists( $customer, $method ) ) {
					$value = $customer->{'get_' . $key}();
				}

				if ( ! $value && ( 'billing_email' === $key || 'shipping_email' === $key ) ) {
					$value = $customer->get_email();
				}

				$address[ $key ]['value'] = apply_filters( 'storeengine/dashboard/edit_address/field_value', $value, $key, $load_address );
			}

			$address = apply_filters( 'storeengine/dashboard/edit_address/address_to_edit', $address, $load_address );

			Template::get_template( 'frontend-dashboard/pages/form-edit-address.php', [
				'load_address' => $load_address,
				'address'      => $address,
			] );
		} else {
			Template::get_template( 'frontend-dashboard/pages/edit-address.php', [
				'customer'  => Helper::get_customer(),
				'countries' => Countries::init()->get_countries(),
			] );
		}
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_payment_settings_content' ) ) {
	function storeengine_frontend_dashboard_payment_settings_content() {
		$user_id                    = get_current_user_id();
		$affiliate_settings         = \StoreEngine\Addons\Affiliate\Settings\Affiliate::get_settings_saved_data();
		$minimum_withdraw_amount    = isset( $affiliate_settings['minimum_withdraw_amount'] ) ? $affiliate_settings['minimum_withdraw_amount'] : 0;
		$is_enabled_paypal_withdraw = isset( $affiliate_settings['is_enabled_paypal_withdraw'] ) ? $affiliate_settings['is_enabled_paypal_withdraw'] : false;
		$is_enabled_echeck_withdraw = isset( $affiliate_settings['is_enabled_echeck_withdraw'] ) ? $affiliate_settings['is_enabled_echeck_withdraw'] : false;
		$is_enabled_bank_withdraw   = isset( $affiliate_settings['is_enabled_bank_withdraw'] ) ? $affiliate_settings['is_enabled_bank_withdraw'] : false;
		$selected_payment_method    = get_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', true ) ?? '';

		Template::get_template(
			'frontend-dashboard/pages/payments.php',
			[
				'minimum_withdraw_amount'    => $minimum_withdraw_amount,
				'is_enabled_paypal_withdraw' => $is_enabled_paypal_withdraw,
				'is_enabled_echeck_withdraw' => $is_enabled_echeck_withdraw,
				'is_enabled_bank_withdraw'   => $is_enabled_bank_withdraw,
				'withdraw_method_type'       => $selected_payment_method,
				'current_user_id'            => $user_id,
			]
		);
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_edit_account_content' ) ) {
	function storeengine_frontend_dashboard_edit_account_content() {
		Template::get_template( 'frontend-dashboard/pages/edit-account.php', [ 'customer' => Helper::get_customer() ] );
	}
}

// Archive
if ( ! function_exists( 'storeengine_archive_product_header' ) ) {
	function storeengine_archive_product_header() {
		Template::get_template( 'archive/header.php' );
	}
}

// Archive Header Filter
if ( ! function_exists( 'storeengine_archive_header_filter' ) ) {
	function storeengine_archive_header_filter() {
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$orderby = Helper::get_settings( 'product_archive_products_order', '' );
		}

		?>
		<div class="storeengine-products__filter">
			<form class="storeengine__header-ordering" method="get">
				<select name="orderby" class="storeengine__header-orderby" aria-label="<?php esc_attr_e( 'Sort Products', 'storeengine' ); ?>" onchange="this.form.submit()">
					<option value="" <?php selected( $orderby, '' ); ?>><?php esc_html_e( 'Default Sorting', 'storeengine' ); ?></option>
					<option value="menu_order" <?php selected( $orderby, 'menu_order' ); ?>><?php esc_html_e( 'Sort By Menu Order', 'storeengine' ); ?></option>
					<option value="title" <?php selected( $orderby, 'title' ); ?>><?php esc_html_e( 'Sort By Product Name', 'storeengine' ); ?></option>
					<option value="date" <?php selected( $orderby, 'date' ); ?>><?php esc_html_e( 'Sort By Publish Date', 'storeengine' ); ?></option>
					<option value="modified" <?php selected( $orderby, 'modified' ); ?>><?php esc_html_e( 'Sort By Modified Date', 'storeengine' ); ?></option>
					<option value="ID" <?php selected( $orderby, 'ID' ); ?>><?php esc_html_e( 'Sort By ID', 'storeengine' ); ?></option>
				</select>
				<input type="hidden" name="paged" value="1">
			</form>
		</div>
		<?php
	}
}

// Product archive pagination
if ( ! function_exists( 'storeengine_product_pagination' ) ) {
	function storeengine_product_pagination() {
		Template::get_template( 'archive/pagination.php' );
	}
}

if ( ! function_exists( 'storeengine_archive_product_sidebar' ) ) {
	function storeengine_archive_product_sidebar() {
		Template::get_template( 'archive/sidebar.php' );
	}
}

function storeengine_get_archive_filter_widgets() {
	$config = wp_parse_args( (array) Helper::get_settings( 'product_archive_filters' ), [
		'search'   => (object) [
			'status' => true,
			'order'  => 0,
		],
		'category' => (object) [
			'status' => true,
			'order'  => 1,
		],
		'tags'     => (object) [
			'status' => true,
			'order'  => 2,
		],
	] );

	$config = array_filter( $config, fn( $value ) => $value->status );
	$order  = array_column( $config, 'order' );

	array_multisort( $config, SORT_ASC, $order );

	return apply_filters( 'storeengine/archive/product_filter_widgets', $config );
}

if ( ! function_exists( 'storeengine_archive_header_filter_widget' ) ) {
	function storeengine_archive_header_filter_widget() {
		$filters = storeengine_get_archive_filter_widgets();
		foreach ( $filters as $widget => $value ) {
			$filter_function = 'storeengine_render_archive_product_filter_' . $widget . '_widget';
			if ( $value && function_exists( $filter_function ) ) {
				$classes = 'storeengine-archive-product-widget storeengine-archive-product-widget--' . $widget;
				if ( ! empty( $value->wrapper_class ) ) {
					$classes .= ' ' . $value->wrapper_class;
				}
				?>
				<div class=" <?php echo esc_attr( trim( $classes ) ); ?>">
					<?php do_action( 'storeengine/archive/sidebar/filter_widget_before', $widget ); ?>
					<?php call_user_func( $filter_function ); ?>
					<?php do_action( 'storeengine/archive/sidebar/filter_widget_after', $widget ); ?>
				</div>
				<?php
			}
		}
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_search_widget' ) ) {
	function storeengine_render_archive_product_filter_search_widget() {
		Template::get_template( 'archive/widgets/search.php', apply_filters( 'storeengine/archive/product_filter_by_search_args', [] ) );
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_category_widget' ) ) {
	function storeengine_render_archive_product_filter_category_widget() {
		$args = apply_filters( 'storeengine/archive/product_filter_by_category_args', [ 'categories' => Helper::get_all_product_category_lists() ] );
		Template::get_template( 'archive/widgets/category.php', $args );
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_tags_widget' ) ) {
	function storeengine_render_archive_product_filter_tags_widget() {
		$tags = get_terms( [
			'taxonomy'   => 'storeengine_product_tag',
			'hide_empty' => true,
		] );
		$args = apply_filters( 'storeengine/archive/product_filter_by_tags_args', [ 'tags' => $tags ] );

		Template::get_template( 'archive/widgets/tags.php', $args );
	}
}//end if

if ( ! function_exists( 'storeengine_checkout_payment_method' ) ) {
	function storeengine_checkout_payment_method() {
		$needs_payment = Helper::cart()->needs_payment();
		if ( $needs_payment ) {
			$available_gateways = Helper::get_payment_gateways()->get_available_payment_gateways();
			Helper::get_payment_gateways()->set_current_gateway( $available_gateways );
		}

		Template::get_template( 'checkout/payment.php', [ 'needs_payment' => $needs_payment ] );
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_user_info' ) ) {
	function storeengine_checkout_form_field_user_info() {
		Template::get_template( 'checkout/contact-info.php', [ 'current_user_email' => StoreEngine::init()->customer->get_email() ] );
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_shipping_address' ) ) {
	function storeengine_checkout_form_field_shipping_address() {
		if ( ! get_query_var( 'order_pay' ) ) {
			$order = Helper::get_recent_draft_order();
			if ( Helper::cart()->needs_shipping() ) {
				Template::get_template( 'checkout/shipping-address.php', [ 'order' => $order ] );
			}
		}
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_billing_address' ) ) {
	function storeengine_checkout_form_field_billing_address() {
		if ( ! get_query_var( 'order_pay' ) ) {
			$order = Helper::get_recent_draft_order();
			Template::get_template( 'checkout/billing-address.php', [
				'order'           => $order,
				'is_digital_cart' => ! Helper::cart()->needs_shipping(),
			] );
		}
	}
}

// Checkout Form
if ( ! function_exists( 'storeengine_checkout_total' ) ) {
	function storeengine_checkout_total() {
		Template::get_template( 'checkout/checkout-total.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_menu' ) ) {
	function storeengine_frontend_dashboard_menu() {
		$menu_items = Helper::get_frontend_dashboard_menu_items();

		uasort( $menu_items, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

		Helper::get_template( 'frontend-dashboard/menu.php', [ 'menu_items' => $menu_items ] );
	}
}

if ( ! function_exists( 'storeengine_get_the_canvas_container_class' ) ) {
	function storeengine_get_the_canvas_container_class() {
		global $post;

		echo esc_attr( apply_filters( 'storeengine/templates/canvas_container_class', 'storeengine-container', $post->ID ) );
	}
}

/**
 * Render frontend top-bar.
 *
 * @return void
 */
function storeengine_frontend_dashboard_content_topbar() {
	$path       = (string) get_query_var( 'storeengine_dashboard_page' );
	$path       = $path ? $path : 'index';
	$sub_path   = (string) get_query_var( 'storeengine_dashboard_sub_page' );
	$page_title = StoreEngine\Utils\Helper::get_frontend_dashboard_page_title( $path, $sub_path );

	Template::get_template( 'frontend-dashboard/topbar.php', [
		'page_title' => $page_title,
		'path'       => $path,
		'sub_path'   => $sub_path,
	] );
}

function storeengine_frontend_dashboard_breadcrumbs_order_title( string $path, string $sub_path ) {
	if ( 'orders' === $path && $sub_path ) {
		echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
		/* translators: %s. Order ID. */
		printf( esc_html__( 'Order #%s', 'storeengine' ), esc_html( $sub_path ) );
	}
}
function storeengine_frontend_dashboard_breadcrumbs_plan_title( string $path, string $sub_path ) {
	if ( 'plans' === $path && $sub_path ) {
		echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
		/* translators: %s. Plan ID. */
		printf( esc_html__( 'Plan #%s', 'storeengine' ), esc_html( $sub_path ) );
	}
}

/**
 * Resolves page template file for plugin.
 *
 * Shop page (product-archive) will not go through `page_template` filter. As the plugin marks it as a archive page
 * and instead WordPress will load it through `archive_template` filter.
 *
 * @param string $template
 *
 * @return string
 */
function storeengine_redirect_canvas_page_template( $template ) {
	if ( Helper::is_fse_theme() ) {
		return $template;
	}

	$post          = get_post();
	$page_template = get_post_meta( $post->ID, '_wp_page_template', true );
	if ( 'storeengine-canvas.php' === basename( $page_template ) ) {
		$template = STOREENGINE_TEMPLATE_PATH . 'storeengine-canvas.php';
	}

	return $template;
}

// Dashboard Order Pagination
if ( ! function_exists( 'storeengine_dashboard_order_pagination' ) ) {
	function storeengine_dashboard_order_pagination( $query ) {
		$current_page = max( 1, get_query_var( 'paged' ) );
		Template::get_template( 'frontend-dashboard/partials/query-pagination.php', [
			'query'         => $query,
			'page_url'      => storeengine_get_dashboard_endpoint_url( 'orders' ),
			'current_page'  => $current_page,
			'previous_page' => $current_page - 1,
			'max_pages'     => $query->get_max_num_pages(),
		] );
	}
}

// Dashboard Order Pagination
if ( ! function_exists( 'storeengine_dashboard_subscription_pagination' ) ) {
	function storeengine_dashboard_subscription_pagination( $query ) {
		$current_page = max( 1, get_query_var( 'paged' ) );
		Template::get_template( 'frontend-dashboard/partials/query-pagination.php', [
			'query'         => $query,
			'page_url'      => storeengine_get_dashboard_endpoint_url( 'plans' ),
			'current_page'  => $current_page,
			'previous_page' => $current_page - 1,
			'max_pages'     => $query->get_max_num_pages(),
		] );
	}
}

if ( ! function_exists( 'storeengine_review_lists' ) ) {
	function storeengine_review_lists( $comment ) {
		Template::get_template(
			'single-product/review.php',
			array( 'comment' => $comment )
		);
	}
}

if ( ! function_exists( 'storeengine_single_product_feedback' ) ) {
	function storeengine_single_product_feedback() {
		if ( ! (bool) Helper::get_settings( 'enable_product_reviews', true ) ) {
			return;
		}
		$rating = \StoreEngine\Models\Product::get_product_rating( get_the_ID() );
		Template::get_template( 'single-product/feedback.php', array( 'rating' => $rating ) );
	}
}

if ( ! function_exists( 'storeengine_single_product_review_and_comments' ) ) {
	function storeengine_single_product_review_and_comments() {
		$enable_product_reviews  = (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_product_reviews', true );
		$enable_product_comments = (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_product_comments', false );

		if ( ! $enable_product_comments && ! $enable_product_reviews ) {
			return;
		}

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	}
}

if ( ! function_exists( 'storeengine_review_display_gravatar' ) ) {
	/**
	 * Display the review authors gravatar
	 *
	 * @param stdClass|WP_Comment $comment WP_Comment.
	 *
	 * @return void
	 */
	function storeengine_review_display_gravatar( $comment ) {
		echo get_avatar( $comment->comment_author_email, apply_filters( 'storeengine/review_gravatar_size', '80' ), '' );
	}
}

if ( ! function_exists( 'storeengine_review_display_rating' ) ) {
	/**
	 * Display the reviewers star rating
	 *
	 * @return void
	 */
	function storeengine_review_display_rating() {
		if ( post_type_supports( 'storeengine_product', 'comments' ) ) {
			$reviews_status = (bool) Helper::get_settings( 'enable_product_reviews', true );
			if ( $reviews_status ) {
				Template::get_template( 'single-product/review-rating.php' );
			}
		}
	}
}

if ( ! function_exists( 'storeengine_review_display_meta' ) ) {
	/**
	 * Display the review authors meta (name, verified owner, review date)
	 *
	 * @return void
	 */
	function storeengine_review_display_meta() {
		Template::get_template( 'single-product/review-meta.php' );
	}
}


if ( ! function_exists( 'storeengine_review_display_comment_text' ) ) {

	/**
	 * Display the review content.
	 */
	function storeengine_review_display_comment_text() {
		echo '<div class="storeengine-review-description">';
		comment_text();
		// @TODO make dynamic single-product/review-thumbnail.php
		echo '</div>';
	}
}

if ( ! function_exists( 'storeengine_get_rating_html' ) ) {
	/**
	 * Get HTML for ratings.
	 *
	 * @param float $rating Rating being shown.
	 * @param int $count Total number of ratings.
	 *
	 * @return string
	 */
	function storeengine_get_rating_html( $rating, $count = 0 ) {
		$html = '';
		if ( 0 < $rating ) {
			$html = Helper::single_star_rating_generator( $rating );
		}

		return apply_filters( 'storeengine/course_get_rating_html', $html, $rating, $count );
	}
}

if ( ! function_exists( 'storeengine_get_rating_html' ) ) {
	function storeengine_single_product_count_review() {
		?>
		<div class="storeengine-single__rating storeengine-d-flex">
			<div>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
			</div>
			<div><a href="#">(<span>1</span> customer review)</a></div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'storeengine_review_lists' ) ) {
	function storeengine_review_lists( $comment ) {
		Helper::get_template( 'single-product/review.php', [ 'comment' => $comment ] );
	}
}

// Dashboard Downloads Pagination
if ( ! function_exists( 'storeengine_dashboard_downloads_pagination' ) ) {
	function storeengine_dashboard_downloads_pagination( $downloadable_permissions ) {
		Template::get_template(
			'frontend-dashboard/partials/downloads-pagination.php',
			array( 'downloadable_permissions' => $downloadable_permissions )
		);
	}
}

if ( ! function_exists( 'storeengine_get_cart_item_data' ) ) {
	function storeengine_get_cart_item_data( $cart_item ) {
		$item_data = [];
		foreach ( $cart_item->variation ?? [] as $taxonomy => $value ) {
			$taxonomy = get_taxonomy( $taxonomy );
			if ( $taxonomy instanceof WP_Taxonomy ) {
				$term = get_term_by( 'slug', $value, $taxonomy->name );
				if ( $term instanceof WP_Term ) {
					$value = $term->name;
				}
			}
			$item_data[] = [
				'label' => $taxonomy instanceof WP_Taxonomy ? $taxonomy->label : $taxonomy,
				'value' => $value,
			];
		}

		return apply_filters( 'storeengine/get_cart_item_data', $item_data, $cart_item );
	}
}

if ( ! function_exists( 'storeengine_display_item_meta' ) ) {
	/**
	 * Display item meta data.
	 *
	 * @param OrderItemProduct $item Order Item.
	 * @param array $args Arguments.
	 *
	 * @return string|void
	 * @since  0.0.6-beta
	 */
	function storeengine_display_item_meta( OrderItemProduct $item, array $args = [] ) {
		$strings = [];
		$html    = '';
		$args    = wp_parse_args(
			$args,
			[
				'before'       => '<ul class="storeengine-order-item-meta"><li>',
				'after'        => '</li></ul>',
				'separator'    => '</li><li>',
				'echo'         => true,
				'autop'        => true,
				'label_before' => '<strong class="storeengine-order-item-meta-label">',
				'label_after'  => ':</strong> ',
			]
		);

		foreach ( $item->get_all_formatted_metadata() as $metadata ) {
			$value     = wp_kses_post( make_clickable( trim( $metadata['display_value'] ) ) );
			$strings[] = $args['label_before'] . wp_kses_post( $metadata['display_key'] ) . $args['label_after'] . $value;
		}

		if ( $strings ) {
			$html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}

		$html = apply_filters( 'storeengine/display_item_meta', $html, $item, $args );

		if ( $args['echo'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		} else {
			return $html;
		}
	}
}

if ( ! function_exists( 'storeengine_single_product_display_price' ) ) {
	/**
	 * @param Price $price_item
	 *
	 * @return void
	 * @deprecated
	 */
	function storeengine_single_product_display_price( Price $price_item ) {
		if ( empty( $price_item->get_price() ) ) {
			return;
		}
		?>
		<span class="has-storeengine-single__sell-amount storeengine-product__price-amount storeengine-mr-1">
			<?php
			if ( 'subscription' === $price_item->get_type() ) {
				echo wp_kses_post( $price_item->get_formatted_payment_duration() );
				if ( $price_item->is_trial() ) {
					echo '<br>';
					echo wp_kses_post( 'Starting in ' . $price_item->get_trial_days() . ' days' );
				}

				if ( $price_item->is_setup_fee() ) {
					echo '<br>';
					echo wp_kses_post( Formatting::price( $price_item->get_setup_fee_price() ) . ' ' . $price_item->get_setup_fee_name() );
				}
			} else {
				echo wp_kses_post( Formatting::price( $price_item->get_price() ) );
			}
			?>
		</span>
		<?php
	}
}

// Single Like and dislike
if ( ! function_exists( 'storeengine_single_like_dislike' ) ) {
	function storeengine_single_like_dislike() {
		Template::get_template( 'single-product/like.php' );
	}
}

// Single
if ( ! function_exists( 'storeengine_single_filter' ) ) {
	function storeengine_single_filter() {
		Template::get_template( 'single-product/filter.php' );
	}
}

/**
 * Get account formatted address.
 *
 * @param string $address_type Type of address; 'billing' or 'shipping'.
 * @param int $customer_id Customer ID. Defaults to 0.
 *
 * @return string
 */
function storeengine_get_dashboard_formatted_address( string $address_type = 'billing', int $customer_id = 0 ): string {
	$getter  = "get_{$address_type}";
	$address = [];

	if ( 0 === $customer_id ) {
		$customer_id = get_current_user_id();
	}

	$customer = new \StoreEngine\Classes\Customer( $customer_id );

	if ( is_callable( array( $customer, $getter ) ) ) {
		$address = $customer->$getter();
		unset( $address['email'], $address['tel'] );
	}

	return Countries::init()->get_formatted_address( apply_filters( 'storeengine/frontend/dashboard_formatted_address', $address, $customer->get_id(), $address_type ) );
}

if ( ! function_exists( 'storeengine_form_field' ) ) {

	/**
	 * Outputs a checkout/address form field.
	 *
	 * @param string $key Key.
	 * @param array|string $args Arguments.
	 * @param ?string $value (default: null).
	 *
	 * @return string|void
	 */
	function storeengine_form_field( string $key, $args, ?string $value = null ) {
		$defaults = [
			'type'              => 'text',
			'label'             => '',
			'description'       => '',
			'placeholder'       => '',
			'maxlength'         => false,
			'minlength'         => false,
			'required'          => false,
			'autocomplete'      => false,
			'id'                => $key,
			'class'             => [],
			'label_class'       => [],
			'input_class'       => [],
			'return'            => false,
			'options'           => [],
			'custom_attributes' => [],
			'validate'          => [],
			'default'           => '',
			'autofocus'         => '',
			'priority'          => '',
			'unchecked_value'   => null,
			'checked_value'     => '1',
		];

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'storeengine/frontend/form_field_args', $args, $key, $value );

		if ( is_string( $args['class'] ) ) {
			$args['class'] = array( $args['class'] );
		}

		if ( is_string( $args['label_class'] ) ) {
			$args['label_class'] = array( $args['label_class'] );
		}

		if ( is_null( $value ) ) {
			$value = $args['default'];
		}

		// Custom attribute handling.
		$custom_attributes         = [];
		$args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

		if ( $args['required'] ) {
			// hidden inputs are the only kind of inputs that don't need an `aria-required` attribute.
			// checkboxes apply the `custom_attributes` to the label - we need to apply the attribute on the input itself, instead.
			if ( ! in_array( $args['type'], [ 'hidden', 'checkbox' ], true ) ) {
				$args['custom_attributes']['aria-required'] = 'true';
				$args['label_class'][]                      = 'required_field';
			}

			$args['class'][]    = 'validate-required';
			$required_indicator = '&nbsp;<abbr class="storeengine-required" aria-hidden="true">*</abbr>';
		} else {
			$required_indicator = '&nbsp;<span class="storeengine-optional screen-reader-text">(' . esc_html__( 'optional', 'storeengine' ) . ')</span>';
		}

		if ( $args['maxlength'] ) {
			$args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
		}

		if ( $args['minlength'] ) {
			$args['custom_attributes']['minlength'] = absint( $args['minlength'] );
		}

		if ( ! empty( $args['autocomplete'] ) ) {
			$args['custom_attributes']['autocomplete'] = $args['autocomplete'];
		}

		if ( true === $args['autofocus'] ) {
			$args['custom_attributes']['autofocus'] = 'autofocus';
		}

		if ( $args['description'] ) {
			$args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
		}

		if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
			foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field           = '';
		$label_id        = $args['id'];
		$sort            = $args['priority'] ? $args['priority'] : '';
		$field_container = '<p class="storeengine-form-field %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';

		switch ( $args['type'] ) {
			case 'country':
				$countries = 'shipping_country' === $key ? Countries::init()->get_shipping_countries() : Countries::init()->get_allowed_countries();

				if ( 1 === count( $countries ) ) {
					$field .= '<strong>' . current( array_values( $countries ) ) . '</strong>';

					$field .= '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . current( array_keys( $countries ) ) . '" ' . implode( ' ', $custom_attributes ) . ' class="country_to_state" readonly="readonly" />';
				} else {
					$data_label = ! empty( $args['label'] ) ? 'data-label="' . esc_attr( $args['label'] ) . '"' : '';

					$field = '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control country_to_state country_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ? $args['placeholder'] : esc_attr__( 'Select a country / region&hellip;', 'storeengine' ) ) . '" ' . $data_label . '><option value="">' . esc_html__( 'Select a country / region&hellip;', 'storeengine' ) . '</option>';

					foreach ( $countries as $ckey => $cvalue ) {
						$field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
					}

					$field .= $required_indicator . '</select>';

					$field .= '<noscript><button type="submit" name="storeengine_checkout_update_totals" value="' . esc_attr__( 'Update country / region', 'storeengine' ) . '">' . esc_html__( 'Update country / region', 'storeengine' ) . '</button></noscript>';
				}

				break;
			case 'state':
				/* Get country this state field is representing */
				$for_country = isset( $args['country'] ) ? $args['country'] : WC()->checkout->get_value( 'billing_state' === $key ? 'billing_country' : 'shipping_country' );
				$states      = Countries::init()->get_states( $for_country );

				if ( is_array( $states ) && empty( $states ) ) {
					$field_container = '<p class="storeengine-form-field %1$s" id="%2$s" style="display: none">%3$s</p>';

					$field .= '<input type="hidden" class="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '" readonly="readonly" data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';
				} elseif ( ! is_null( $for_country ) && is_array( $states ) ) {
					$data_label = ! empty( $args['label'] ) ? 'data-label="' . esc_attr( $args['label'] ) . '"' : '';

					$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control state_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ? $args['placeholder'] : esc_html__( 'Select an option&hellip;', 'storeengine' ) ) . '"  data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . $data_label . '>
						<option value="">' . esc_html__( 'Select an option&hellip;', 'storeengine' ) . '</option>';

					foreach ( $states as $ckey => $cvalue ) {
						$field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
					}

					$field .= '</select>';
				} else {
					$field .= '<input type="text" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';
				}

				break;
			case 'textarea':
				$field .= '<textarea name="' . esc_attr( $key ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . ( empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '' ) . ( empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '' ) . implode( ' ', $custom_attributes ) . '>' . esc_textarea( $value ) . '</textarea>';

				break;
			case 'checkbox':
				$field = '<label class="checkbox ' . esc_attr( implode( ' ', $args['label_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . '>';

				// Output a hidden field so a value is POSTed if the box is not checked.
				if ( ! is_null( $args['unchecked_value'] ) ) {
					$field .= sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $key ), esc_attr( $args['unchecked_value'] ) );
				}

				$field .= sprintf(
					'<input type="checkbox" name="%1$s" id="%2$s" value="%3$s" class="%4$s" %5$s%6$s /> %7$s',
					esc_attr( $key ),
					esc_attr( $args['id'] ),
					esc_attr( $args['checked_value'] ),
					esc_attr( 'input-checkbox ' . implode( ' ', $args['input_class'] ) ),
					checked( $value, $args['checked_value'], false ),
					$args['required'] ? ' aria-required="true"' : '',
					wp_kses_post( $args['label'] )
				);

				$field .= $required_indicator . '</label>';

				break;
			case 'text':
			case 'password':
			case 'datetime':
			case 'datetime-local':
			case 'date':
			case 'month':
			case 'time':
			case 'week':
			case 'number':
			case 'email':
			case 'url':
			case 'tel':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

				break;
			case 'hidden':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="hidden storeengine-hidden ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

				break;
			case 'select':
				$options = '';

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						if ( '' === $option_key ) {
							// A blank option is the proper way to set a placeholder. If one is supplied we make sure the placeholder key is set for selectWoo.
							if ( empty( $args['placeholder'] ) ) {
								$args['placeholder'] = $option_text ? $option_text : __( 'Choose an option', 'storeengine' );
							}
							$custom_attributes[] = 'data-allow_clear="true"';
						}
						$options .= '<option value="' . esc_attr( $option_key ) . '" ' . selected( $value, $option_key, false ) . '>' . esc_html( $option_text ) . '</option>';
					}

					$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '">
							' . $options . '
						</select>';
				}

				break;
			case 'radio':
				$label_id .= '_' . current( array_keys( $args['options'] ) );

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						$field .= '<input type="radio" class="store-form-control-radio ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" ' . implode( ' ', $custom_attributes ) . ' id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
						$field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) . '">' . esc_html( $option_text ) . $required_indicator . '</label>';
					}
				}

				break;
		}

		if ( ! empty( $field ) ) {
			$field_html = '';

			if ( $args['label'] && 'checkbox' !== $args['type'] ) {
				$field_html .= '<label for="' . esc_attr( $label_id ) . '" class="store-form-control-checkbox ' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . wp_kses_post( $args['label'] ) . $required_indicator . '</label>';
			}

			$field_html .= '<span class="storeengine-input-wrapper">' . $field;

			if ( $args['description'] ) {
				$field_html .= '<span class="storeengine-description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
			}

			$field_html .= '</span>';

			$container_class = esc_attr( implode( ' ', $args['class'] ) );
			$container_id    = esc_attr( $args['id'] ) . '_field';
			$field           = sprintf( $field_container, $container_class, $container_id, $field_html );
		}

		/**
		 * Filter by type.
		 */
		$field = apply_filters( 'storeengine/frontend/form_field_' . $args['type'], $field, $key, $args, $value );

		/**
		 * General filter on form fields.
		 *
		 * @since 3.4.0
		 */
		$field = apply_filters( 'storeengine/frontend/form_field', $field, $key, $args, $value );

		if ( $args['return'] ) {
			return $field;
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $field;
		}
	}
}

if ( ! function_exists( 'storeengine_render_upsell_items' ) ) {
	function storeengine_render_upsell_items() : void {
		$id  = is_int( $temp_id = get_the_ID() ) ? $temp_id : null;
		$ids = ! is_null( $id ) ? get_post_meta( $id, '_storeengine_upsell_ids', true ) : null;
		$ids = is_array( $ids ) ? $ids : null;

		if ( empty( $ids ) ) {
			return;
		}

		$args = array(
			'post_type'      => Helper::PRODUCT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__in'       => $ids,
		);

		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 1,
		] );

		$grid_class = Helper::get_responsive_column( array(
			'desktop' => (int) $products_per_row->desktop ?? 3,
			'tablet'  => 2,
			'mobile'  => 1,
		) );

		wp_reset_query();

		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
		query_posts( apply_filters( 'storeengine/products/cross_sell/args', $args ) );
		Template::get_template( 'single-product/upsell.php', array( 'grid_class' => $grid_class ) );
		wp_reset_query();
	}
}


if ( ! function_exists( 'storeengine_render_crosssell_items' ) ) {
	function storeengine_render_crosssell_items(): void {
		// Get product IDs from cart
		$cart_items  = storeengine_cart()->get_cart_items();
		$product_ids = array_unique(
			array_values(
				array_map(
					fn( $cart_item ) => (int) $cart_item->product_id,
					$cart_items
				)
			)
		);

		// Collect all cross-sell IDs
		$cross_sell_ids = [];
		foreach ( $product_ids as $pid ) {
			$cr_ids = get_post_meta( $pid, '_storeengine_crosssell_ids', true );
			if ( is_array( $cr_ids ) ) {
				$cross_sell_ids = array_merge( $cross_sell_ids, $cr_ids );
			}
		}
		$cross_sell_ids = array_unique( $cross_sell_ids );

		if ( empty( $cross_sell_ids ) ) {
			return;
		}

		$args = array(
			'post_type'      => Helper::PRODUCT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__in'       => $cross_sell_ids,
		);

		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 1,
		] );

		$grid_class = Helper::get_responsive_column( array(
			'desktop' => (int) $products_per_row->desktop ?? 3,
			'tablet'  => 2,
			'mobile'  => 1,
		) );

		wp_reset_query();

		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
		query_posts( apply_filters( 'storeengine/products/cross_sell/args', $args ) );
		Template::get_template( 'cart/cross-sell.php', array( 'grid_class' => $grid_class ) );
		wp_reset_query();
	}
}

/**
 * Get account orders actions.
 *
 * @param  Order $order Order instance or ID.
 * @return array
 */
function storeengine_get_account_orders_actions( Order $order ): array {
	$actions = [
		'view'   => [
			'url'        => $order->get_view_order_url(),
			'icon'       => 'eye',
			//'name'       => __( 'View', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'View order %s', 'storeengine' ), $order->get_order_number() ),
		],
		'pay'    => [
			'url'        => $order->get_checkout_payment_url(),
			'name'       => __( 'Pay now', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Pay for order %s', 'storeengine' ), $order->get_order_number() ),
		],
		'cancel' => [
			'url'        => $order->get_cancel_order_url( Helper::get_dashboard_url() ),
			'name'       => __( 'Cancel Order', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Cancel order %s', 'storeengine' ), $order->get_order_number() ),
			'data'       => [
				'confirm-action' => __( 'Are you sure you want to cancel your order?', 'storeengine' ),
			],
		],
	];

	if ( ! $order->needs_payment() ) {
		unset( $actions['pay'] );
	}

	// @TODO add support for order pay.
	unset( $actions['pay'] );

	/**
	 * Filters the valid order statuses for cancel action.
	 *
	 * @param array    $statuses_for_cancel Array of valid order statuses for cancel action.
	 * @param Order $order                Order instance.
	 */
	$statuses_for_cancel = apply_filters( 'storeengine/order/valid_statuses_for_cancel', [ OrderStatus::PAYMENT_PENDING, OrderStatus::PAYMENT_FAILED ], $order );

	if ( ! in_array( $order->get_status(), $statuses_for_cancel, true ) ) {
		unset( $actions['cancel'] );
	}

	return apply_filters( 'storeengine/dashboard/order/actions', $actions, $order );
}

function storeengine_render_dashboard_action_buttons( array $actions, string $for = 'order' ): void {
	Template::get_template(
		'frontend-dashboard/partials/action-buttons.php',
		[
			'action'     => array_slice( $actions, 0, 1, true ), // Get the first key-value pair
			'actions'    => array_slice( $actions, 1, null, true ), // Get the remaining key-value pairs
			'action_for' => $for,
		]
	);
}

if ( ! function_exists( 'storeengine_render_icon' ) ) {
	function storeengine_render_icon( string $icon ): void {
		?><span class="storeengine-icon storeengine-flex storeengine-icon--<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><?php
	}
}

if ( ! function_exists( 'storeengine_print_dropdown_button' ) ) {
	function storeengine_print_dropdown_button( string $key, array $action ): void {
		if ( ! empty( $action['is-divider'] ) ) {
			?>
			<div class="storeengine-dropdown--divider"></div>
			<?php
			return;
		}

		$aria_label = $action['aria-label'] ?? $action['name'];
		$classes    = [ 'storeengine-dropdown--item storeengine-btn storeengine-btn--link' ];

		if ( ! empty( $action['classes'] ) && is_string( $action['classes'] ) ) {
			$classes[] = sanitize_html_class( $action['classes'] );
		}

		$classes[] = 'storeengine-btn--' . sanitize_html_class( $key );
		$classes[] = ! empty( $action_for ) ? $action_for . '-' . sanitize_html_class( $key ) : '';
		$classes   = implode( ' ', array_filter( $classes ) );

		// Prepare escaped attribute string.
		$attributes  = 'class="' . esc_attr( $classes ) . '"';
		$attributes .= ' href="' . esc_url( $action['url'] ) . '"';
		$attributes .= ' aria-label="' . esc_attr( $aria_label ) . '"';
		if ( ! empty( $action['target'] ) && '_blank' === $action['target'] ) {
			$attributes .= ' target="_blank"';
		}

		if ( ! empty( $action['data'] ) && is_array( $action['data'] ) ) {
			foreach ( $action['data'] as $key => $value ) {
				$attributes .= ' data-' . sanitize_title( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		?>
		<a <?php echo $attributes; ?>>
			<?php
			if ( ! empty( $action['icon'] ) ) {
				storeengine_render_icon( $action['icon'] );
			}

			if ( ! empty( $action['name'] ) ) {
				echo esc_html( $action['name'] );
			}
			?>
		</a>
		<?php
	}
}
