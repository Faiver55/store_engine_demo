<?php

namespace StoreEngine\frontend\template;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use StoreEngine\Utils\Helper;
use WP_Block_Template;
use WP_Error;
use WP_Query;

class Block {

	public static function init() {
		$self = new self();
		add_filter( 'page_template_hierarchy', array( $self, 'update_hierarchy' ) );
		add_filter( 'pre_get_block_file_template', array( $self, 'get_block_file_template' ), 10, 3 );
		add_filter( 'get_block_templates', array( $self, 'add_block_templates' ), 10, 3 );
	}

	public static function update_hierarchy( array $templates ): array {
		global $storeengine_settings;

		$cart_page = get_post_field( 'post_name', $storeengine_settings->cart_page );
		if ( in_array( "page-$cart_page.php", $templates, true ) ) {
			return array_merge( array( 'cart-storeengine.php' ), $templates );
		}

		$checkout_page = get_post_field( 'post_name', $storeengine_settings->checkout_page );
		if ( in_array( "page-$checkout_page.php", $templates, true ) ) {
			return array_merge( array( 'checkout-storeengine.php' ), $templates );
		}

		$dashboard_page = get_post_field( 'post_name', $storeengine_settings->dashboard_page );
		if ( in_array( "page-$dashboard_page.php", $templates, true ) ) {
			return array_merge( array( 'store-dashboard-storeengine.php' ), $templates );
		}

		$thankyou_page = get_post_field( 'post_name', $storeengine_settings->thankyou_page );
		if ( in_array( "page-$thankyou_page.php", $templates, true ) ) {
			return array_merge( array( 'thankyou-storeengine.php' ), $templates );
		}

		return $templates;
	}

	public function get_block_file_template( $template, $id, $template_type ) {
		$template_name_parts = explode( '//', $id );

		if ( count( $template_name_parts ) < 2 ) {
			return $template;
		}

		list( $template_id, $template_slug ) = $template_name_parts;

		if ( STOREENGINE_PLUGIN_SLUG !== $template_id ) {
			return $template;
		}

		// If we don't have a template let Gutenberg do its thing.
		if ( ! $this->block_template_is_available( $template_slug, $template_type ) ) {
			return $template;
		}

		$directory = STOREENGINE_BLOCK_TEMPLATES_DIR_PATH;

		$template_file_path = $directory . '/' . $template_slug . '.html';

		$template_object = $this->create_new_block_template_object( $template_file_path, $template_type, $template_slug );

		return $this->build_template_result_from_file( $template_object, $template_type );
	}

	public function block_template_is_available( $template_name, $template_type = 'wp_template' ): bool {
		if ( ! $template_name ) {
			return false;
		}
		$directory = STOREENGINE_BLOCK_TEMPLATES_DIR_PATH . $template_name . '.html';

		return is_readable( $directory ) || $this->get_block_templates( [ $template_name ], $template_type );
	}

	public function get_block_templates( $slugs = [], $template_type = 'wp_template' ): array {
		$templates_from_db     = $this->get_block_templates_from_db( $slugs, $template_type );
		$templates_from_plugin = $this->get_block_templates_from_plugin( $slugs, $templates_from_db, $template_type );

		return array_merge( $templates_from_db, $templates_from_plugin );
	}

	public function get_block_templates_from_db( $slugs = [], $template_type = 'wp_template' ): array {
		$check_query_args = [
			'post_type'      => $template_type,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'tax_query'      => [  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => array( STOREENGINE_PLUGIN_SLUG, get_stylesheet() ),
				],
			],
		];

		if ( is_array( $slugs ) && count( $slugs ) > 0 ) {
			$check_query_args['post_name__in'] = $slugs;
		}

		$check_query     = new WP_Query( $check_query_args );
		$saved_templates = $check_query->posts;

		return array_map(
			function ( $saved_template ) {
				return $this->build_template_result_from_post( $saved_template );
			},
			$saved_templates
		);
	}

	public static function build_template_result_from_post( $post ) {
		$terms = get_the_terms( $post, 'wp_theme' );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( ! $terms ) {
			return new WP_Error( 'template_missing_theme', __( 'No theme is defined for this template.', 'storeengine' ) );
		}

		$theme = $terms[0]->name;

		$template                 = new WP_Block_Template();
		$template->wp_id          = $post->ID;
		$template->id             = $theme . '//' . $post->post_name;
		$template->theme          = $theme;
		$template->content        = $post->post_content;
		$template->slug           = $post->post_name;
		$template->source         = 'custom';
		$template->type           = $post->post_type;
		$template->description    = $post->post_excerpt;
		$template->title          = $post->post_title;
		$template->status         = $post->post_status;
		$template->has_theme_file = true;
		$template->is_custom      = false;
		$template->post_types     = array(); // Don't appear in any Edit Post template selector dropdown.

		if ( 'wp_template_part' === $post->post_type ) {
			$type_terms = get_the_terms( $post, 'wp_template_part_area' );
			if ( ! is_wp_error( $type_terms ) && false !== $type_terms ) {
				$template->area = $type_terms[0]->name;
			}
		}

		if ( STOREENGINE_PLUGIN_SLUG === $theme ) {
			$template->origin = 'plugin';
		}

		return $template;
	}

	public function get_block_templates_from_plugin( $slugs, $already_found_templates, $template_type = 'wp_template' ): array {
		$template_files = $this->get_templates_files_from_plugin();
		$templates      = [];

		foreach ( $template_files as $template_file ) {
			$template_slug = $this->generate_template_slug_from_path( $template_file );

			// This template does not have a slug we're looking for. Skip it.
			if ( is_array( $slugs ) && count( $slugs ) > 0 && ! in_array( $template_slug, $slugs, true ) ) {
				continue;
			}

			// If the template is already in the list (i.e. it came from the
			// database) then we should not overwrite it with the one from the filesystem.
			if (
				count(
					array_filter(
						$already_found_templates,
						function ( $template ) use ( $template_slug ) {
							$template_obj = (object) $template; //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
							return $template_obj->slug === $template_slug;
						}
					)
				) > 0 ) {
				continue;
			}

			// At this point the template only exists in the Blocks filesystem and has not been saved in the DB,
			// or superseded by the theme.
			$templates[] = $this->create_new_block_template_object( $template_file, $template_type, $template_slug );
		}//end foreach
		return $templates;
	}

	public function get_templates_files_from_plugin(): array {
		return $this->get_template_paths( STOREENGINE_BLOCK_TEMPLATES_DIR_PATH );
	}

	public function get_template_paths( $base_directory ): array {
		$path_list = [];
		if ( file_exists( $base_directory ) ) {
			$nested_files      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_directory ) );
			$nested_html_files = new RegexIterator( $nested_files, '/^.+\.html$/i', RegexIterator::GET_MATCH );
			foreach ( $nested_html_files as $path => $file ) {
				$path_list[] = $path;
			}
		}
		return $path_list;
	}

	public function generate_template_slug_from_path( $path ): string {
		$template_extension = '.html';

		return basename( $path, $template_extension );
	}

	public function create_new_block_template_object( $template_file, $template_type, $template_slug, $template_is_from_theme = false ): object {
		$theme_slug = get_template();

		$new_template_item = [
			'slug'        => $template_slug,
			'id'          => $template_is_from_theme ? $theme_slug . '//' . $template_slug : STOREENGINE_PLUGIN_SLUG . '//' . $template_slug,
			'path'        => $template_file,
			'type'        => $template_type,
			'theme'       => $template_is_from_theme ? $theme_slug : STOREENGINE_PLUGIN_SLUG,
			'source'      => $template_is_from_theme ? 'theme' : 'plugin',
			'title'       => $this->convert_slug_to_title( $template_slug ),
			'description' => '',
			'post_types'  => array(), // Don't appear in any Edit Post template selector dropdown.
		];

		return (object) $new_template_item;
	}

	public function convert_slug_to_title( $template_slug ): ?string {
		switch ( $template_slug ) {
			case 'single-storeengine_product':
				return __( 'Single Product', 'storeengine' );
			case 'archive-storeengine_product':
				return __( 'Archive Products', 'storeengine' );
			default:
				// Replace all hyphens and underscores with spaces.
				return ucwords( preg_replace( '/[\-_]/', ' ', $template_slug ) );
		}
	}

	public function build_template_result_from_file( $template_file, $template_type ): WP_Block_Template {
		$template_file = (object) $template_file;

		// If the theme has an archive-products.html template but does not have product taxonomy templates
		// then we will load in the archive-product.html template from the theme to use for product taxonomies on the frontend.
		$template_is_from_theme = 'theme' === $template_file->source;
		$theme_slug             = get_template();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file content.
		$template_content  = Helper::remove_line_break( file_get_contents( $template_file->path ) );
		$template          = new WP_Block_Template();
		$template->id      = $template_is_from_theme ? $theme_slug . '//' . $template_file->slug : STOREENGINE_PLUGIN_SLUG . '//' . $template_file->slug;
		$template->theme   = $template_is_from_theme ? $theme_slug : STOREENGINE_PLUGIN_SLUG;
		$template->content = $template_content;
		$template->content = self::inject_theme_attribute_in_content( $template_content );
		// Plugin was agreed as a valid source value despite existing inline docs at the time of creating: https://github.com/WordPress/gutenberg/issues/36597#issuecomment-976232909.
		$template->source         = $template_file->source ? $template_file->source : 'plugin';
		$template->slug           = $template_file->slug;
		$template->type           = $template_type;
		$template->title          = ! empty( $template_file->title ) ? $template_file->title : $this->get_block_template_title( $template_file->slug );
		$template->description    = ! empty( $template_file->description ) ? $template_file->description : $this->get_block_template_description( $template_file->slug );
		$template->status         = 'publish';
		$template->has_theme_file = true;
		$template->origin         = $template_file->source;
		$template->is_custom      = false; // Templates loaded from the filesystem aren't custom, ones that have been edited and loaded from the DB are.
		$template->post_types     = array(); // Don't appear in any Edit Post template selector dropdown.
		$template->area           = 'uncategorized';

		return $template;
	}

	public function inject_theme_attribute_in_content( $template_content ) {
		$has_updated_content = false;
		$new_content         = '';
		$template_blocks     = parse_blocks( $template_content );

		$blocks = $this->flatten_blocks( $template_blocks );
		foreach ( $blocks as &$block ) {
			if (
				'core/template-part' === $block['blockName'] &&
				! isset( $block['attrs']['theme'] )
			) {
				$block['attrs']['theme'] = wp_get_theme()->get_stylesheet();
				$has_updated_content     = true;
			}
		}

		if ( $has_updated_content ) {
			foreach ( $template_blocks as &$block ) {
				$new_content .= serialize_block( $block );
			}

			return $new_content;
		}

		return $template_content;
	}

	public function flatten_blocks( &$blocks ): array {
		$all_blocks = [];
		$queue      = [];
		foreach ( $blocks as &$block ) {
			$queue[] = &$block;
		}
		$queue_count = count( $queue );

		while ( $queue_count > 0 ) {
			$block = &$queue[0];
			array_shift( $queue );
			$all_blocks[] = &$block;

			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as &$inner_block ) {
					$queue[] = &$inner_block;
				}
			}

			$queue_count = count( $queue );
		}

		return $all_blocks;
	}

	public function get_block_template_title( $template_slug ) {
		$plugin_template_types = $this->get_plugin_block_template_types();
		if ( isset( $plugin_template_types[ $template_slug ] ) ) {
			return $plugin_template_types[ $template_slug ]['title'];
		} else {
			// Human friendly title converted from the slug.
			return ucwords( preg_replace( '/[\-_]/', ' ', $template_slug ) );
		}
	}

	public function get_plugin_block_template_types(): array {
		return [
			'single-storeengine_product'         => array(
				'title'       => _x( 'Single Product', 'Template name', 'storeengine' ),
				'description' => __( 'Template used to display the single docs.', 'storeengine' ),
			),
			'archive-single-storeengine_product' => array(
				'title'       => _x( 'Archive Products', 'Template name', 'storeengine' ),
				'description' => __( 'Template used to display the single docs.', 'storeengine' ),
			),
		];
	}

	public function get_block_template_description( $template_slug ) {
		$plugin_template_types = $this->get_plugin_block_template_types();
		if ( isset( $plugin_template_types[ $template_slug ] ) ) {
			return $plugin_template_types[ $template_slug ]['description'];
		}
		return '';
	}

	public function add_block_templates( $query_result, $query, $template_type ) {
		$post_type = $query['post_type'] ?? '';
		$slugs     = $query['slug__in'] ?? [];

		$template_files = $this->get_block_templates( $slugs, $template_type );

		// @todo: Add apply_filters to _gutenberg_get_template_files() in Gutenberg to prevent duplication of logic.
		foreach ( $template_files as $template_file ) {
			if ( $post_type && isset( $template_file->post_types ) && ! in_array( $post_type, $template_file->post_types, true )
			) {
				continue;
			}

			// It would be custom if the template was modified in the editor, so if it's not custom we can load it from
			// the filesystem.
			if ( 'custom' !== $template_file->source ) {
				$template = $this->build_template_result_from_file( $template_file, $template_type );
			} else {
				$template_file->title       = $this->get_block_template_title( $template_file->slug );
				$template_file->description = $this->get_block_template_description( $template_file->slug );
				$query_result[]             = $template_file;
				continue;
			}

			$is_not_custom   = false === array_search(
				wp_get_theme()->get_stylesheet() . '//' . $template_file->slug,
				array_column( $query_result, 'id' ),
				true
			);
			$fits_slug_query =
				! isset( $query['slug__in'] ) || in_array( $template_file->slug, $query['slug__in'], true );
			$fits_area_query =
				! isset( $query['area'] ) || $template_file->area === $query['area'];
			$should_include  = $is_not_custom && $fits_slug_query && $fits_area_query;
			if ( $should_include ) {
				$query_result[] = $template;
			}
		}//end foreach

		return $query_result;
	}
}
