<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre page load redirects.
 */
add_action( 'template_redirect', 'storeengine_template_redirect' );

/**
 * Admin bar visibility.
 */
add_filter( 'show_admin_bar', 'storeengine_disable_admin_bar', 10, 1 ); // phpcs:ignore WordPress.VIP.AdminBarRemoval.RemovalDetected

/**
 * Page Template
 */
add_filter( 'page_template', 'storeengine_redirect_canvas_page_template' );

/**
 * Archive Course Page
 */
add_action( 'storeengine/templates/archive_product_content', 'storeengine_global_products');
add_action( 'storeengine/templates/no_product_found', 'storeengine_no_products');

// Product Single
add_action( 'storeengine/templates/single_product_content', 'storeengine_single_view_cart' );
add_action( 'storeengine/templates/single_product_content', 'storeengine_single_product_header' );
add_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_single_product_add_to_cart' );
add_action( 'storeengine/templates/single_product_content', 'storeengine_single_product_description' );
add_action( 'storeengine/templates/single_product_content', 'storeengine_single_product_review_and_comments' );
add_action( 'storeengine/templates/single_product_feedback', 'storeengine_single_product_feedback' );
add_action( 'storeengine/templates/single_view_cart_button', 'storeengine_single_view_cart_button' );
add_action( 'storeengine/templates/single_filter', 'storeengine_single_filter' );

// Sidebar Widget
add_action( 'storeengine/templates/single_product_sidebar_widgets', 'storeengine_price' );
add_action( 'storeengine/templates/single_product_sidebar_widgets', 'storeengine_add_to_cart_form' );

add_action( 'storeengine/templates/single_product_sidebar_widgets', 'storeengine_single_categories' );
add_action( 'storeengine/templates/single_product_sidebar_widgets', 'storeengine_single_tag' );

// Archive Header
add_action( 'storeengine/templates/archive_product_header', 'storeengine_archive_product_header' );

// Archive Footer
add_action( 'storeengine/templates/after_product_loop', 'storeengine_product_pagination' );

// Archive sidebar
add_action( 'storeengine/templates/archive_product_sidebar_content', 'storeengine_archive_header_filter_widget' );
add_action( 'storeengine/templates/archive_product_sidebar', 'storeengine_archive_product_sidebar' );

// Archive Filter
add_action( 'storeengine/templates/archive_header_filter', 'storeengine_archive_header_filter' );

/**
 * Product Loop
 */
add_action( 'the_post', 'storeengine_initialize_product_data', 15 );
add_action( 'storeengine/templates/product_loop_header', 'storeengine_product_loop_header' );
add_action( 'storeengine/templates/product_loop_content', 'storeengine_product_loop_content', 11 );
add_action( 'storeengine/templates/product_loop_footer', 'storeengine_product_loop_footer', 11 );
add_action( 'storeengine/templates/product_loop_footer_content', 'storeengine_product_loop_add_to_cart' );

/**
 * Checkout page
 */
add_action( 'storeengine_checkout_payment', 'storeengine_checkout_payment_method' );
add_action( 'storeengine/templates/storeengine_checkout_total', 'storeengine_checkout_total' );
// fields
add_action('storeengine/templates/checkout_form_fields', 'storeengine_checkout_form_field_user_info');
add_action('storeengine/templates/checkout_form_fields', 'storeengine_checkout_form_field_shipping_address');
add_action('storeengine/templates/checkout_form_fields', 'storeengine_checkout_form_field_billing_address');
add_action('storeengine/templates/checkout_form_fields', 'storeengine_checkout_payment_method');

/**
 * Frontend Dashboard
 */
add_action( 'storeengine/frontend/dashboard_content', 'storeengine_frontend_dashboard_content_topbar' );

add_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs', 'storeengine_frontend_dashboard_breadcrumbs_order_title', 10, 2 );
add_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs', 'storeengine_frontend_dashboard_breadcrumbs_plan_title', 10, 2 );

add_action( 'storeengine/frontend/dashboard_content', 'storeengine_frontend_dashboard_content' );
add_action( 'storeengine/frontend/dashboard_menu', 'storeengine_frontend_dashboard_menu' );

add_action( 'storeengine/frontend/dashboard_orders_endpoint', 'storeengine_frontend_dashboard_orders_endpoint_content' );
add_action( 'storeengine/frontend/dashboard_plans_endpoint', 'storeengine_frontend_dashboard_plans_content' );

add_action( 'storeengine/frontend/dashboard_downloads_endpoint', 'storeengine_frontend_dashboard_downloads_content' );
add_action( 'storeengine/frontend/dashboard_edit-address_endpoint', 'storeengine_frontend_dashboard_edit_address_content' );

add_action( 'storeengine/frontend/dashboard_payment-methods_endpoint', 'storeengine_frontend_dashboard_payment_methods_content' );
add_action( 'storeengine/frontend/dashboard_add-payment-method_endpoint', 'storeengine_frontend_dashboard_add_payment_method_content' );

add_action( 'storeengine/frontend/dashboard_payment-settings_endpoint', 'storeengine_frontend_dashboard_payment_settings_content' );
add_action( 'storeengine/frontend/dashboard_edit-account_endpoint', 'storeengine_frontend_dashboard_edit_account_content' );


add_action( 'storeengine/templates/dashboard_order_pagination', 'storeengine_dashboard_order_pagination' );
add_action( 'storeengine/templates/dashboard_subscription_pagination', 'storeengine_dashboard_subscription_pagination' );

/**
 * Review
 */
add_action( 'storeengine/templates/review_thumbnail', 'storeengine_review_display_gravatar' );
add_action( 'storeengine/templates/review_display_rating', 'storeengine_review_display_rating' );
add_action( 'storeengine/templates/review_meta', 'storeengine_review_display_meta' );
add_action( 'storeengine/templates/review_comment_text', 'storeengine_review_display_comment_text' );

add_action( 'storeengine/templates/dashboard_downloads_pagination', 'storeengine_dashboard_downloads_pagination' );

/**
 * Upsell & crosssell
 */
add_action( 'storeengine/templates/after_main_content', 'storeengine_render_upsell_items' );
add_action( 'storeengine/templates/after_cart_list_table', 'storeengine_render_crosssell_items' );

/**
 * Like and Dislike
 */
// @TODO implement like-dislike.
//       hook: storeengine/templates/like_dislike, cb: storeengine_single_like_dislike
//       maybe use hook: storeengine/templates/review_after_comment_text
