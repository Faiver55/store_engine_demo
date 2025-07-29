<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;
use StoreEngine\Utils\Helper;

class TemplateLoader {

	public function template_loader( $template ) {
		if ( is_embed() ) {
			return $template;
		}

		$default_file = $this->get_template_loader_default_file();

		if ( $default_file ) {
			/**
			 * Filter hook to choose which files to find before storeengine does it's own logic.
			 *
			 * @var array
			 */
			$search_files = $this->get_template_loader_files( $default_file );
			$template     = locate_template( $search_files );

			if ( ! $template ) {
				if ( false !== strpos( $default_file, Helper::PRODUCT_CATEGORY_TAXONOMY ) || false !== strpos( $default_file, Helper::PRODUCT_TAG_TAXONOMY ) ) {
					$cs_template = str_replace( '_', '-', $default_file );
					$template    = Template::plugin_path() . 'templates/' . $cs_template;
				} else {
					$template = Template::plugin_path() . 'templates/' . $default_file;
				}
			}
		}

		return $template;
	}

	/**
	 * Get the default filename for a template.
	 *
	 * @return string
	 */
	private function get_template_loader_default_file(): string {
		$default_file = '';
		if ( is_singular( Helper::PRODUCT_POST_TYPE ) ) {
			$default_file = 'single-product.php';
		} elseif ( is_tax( get_object_taxonomies( Helper::PRODUCT_POST_TYPE ) ) ) {
			if ( is_tax( Helper::PRODUCT_CATEGORY_TAXONOMY ) ) {
				$default_file = 'taxonomy-product-category.php';
			} elseif ( is_tax( Helper::PRODUCT_TAG_TAXONOMY ) ) {
				$default_file = 'taxonomy-product-tag.php';
			} else {
				$default_file = 'archive-product.php';
			}
		} elseif ( is_post_type_archive( Helper::PRODUCT_POST_TYPE ) ) {
			$default_file = 'archive-product.php';
		}

		return $default_file;
	}

	private function get_template_loader_files( $default_file ) {
		$templates   = apply_filters( 'storeengine\frontend\template\loader_files', array(), $default_file );
		$templates[] = 'storeengine.php';

		if ( is_page_template() ) {
			$page_template = get_page_template_slug();

			if ( $page_template ) {
				$validated_file = validate_file( $page_template );
				if ( 0 === $validated_file ) {
					$templates[] = $page_template;
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// @TODO implement error logger.
						// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
						error_log( "StoreEngine: Unable to validate template path: \"$page_template\". Error Code: $validated_file." );
						// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
				}
			}
		}

		if ( is_singular( Helper::PRODUCT_POST_TYPE ) ) {
			$object       = get_queried_object();
			$name_decoded = urldecode( $object->post_name );
			if ( $name_decoded !== $object->post_name ) {
				$templates[] = "single-product-{$name_decoded}.php";
			}
			$templates[] = "single-product-{$object->post_name}.php";
		}

		if ( is_tax( get_object_taxonomies( Helper::PRODUCT_CATEGORY_TAXONOMY ) ) ) {
			$object = get_queried_object();
			if ( is_tax( Helper::PRODUCT_CATEGORY_TAXONOMY ) ) {
				$templates[] = 'taxonomy-product-category-' . $object->slug . '.php';
				$templates[] = Template::template_path() . 'taxonomy-product-category-' . $object->slug . '.php';
				$templates[] = 'taxonomy-product-category.php';
				$templates[] = Template::template_path() . 'taxonomy-product-category.php';
			} elseif ( is_tax( Helper::PRODUCT_TAG_TAXONOMY ) ) {
				$templates[] = 'taxonomy-product-tag-' . $object->slug . '.php';
				$templates[] = Template::template_path() . 'taxonomy-product-tag-' . $object->slug . '.php';
				$templates[] = 'taxonomy-product-tag.php';
				$templates[] = Template::template_path() . 'taxonomy-product-tag.php';
			}
			$cs_default  = str_replace( '_', '-', $default_file );
			$templates[] = $cs_default;
		}

		$templates[] = $default_file;

		if ( isset( $cs_default ) ) {
			$templates[] = Template::template_path() . $cs_default;
		}

		$templates[] = Template::template_path() . $default_file;

		return array_unique( $templates );
	}

	/**
	 * @deprecated use self::frontend_dashboard_template_redirect
	 */
	public function frontend_dashboard_template( $template ) {
		global $wp_query;
		if ( get_queried_object_id() === (int) Helper::get_settings( 'dashboard_page' ) || ! empty( $wp_query->query['storeengine_dashboard_page'] ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}

			return Template::plugin_path() . 'templates/shortcode/frontend-dashboard.php';
		}

		return $template;
	}
}
