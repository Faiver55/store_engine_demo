<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

use StoreEngine\Classes\Attribute;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class AttributeMigration {
	protected ?int $wc_attribute_id = null;

	protected string $taxonomy;

	protected ?Attribute $attribute = null;

	protected array $attribute_order_mapping = [
		'menu_order' => 'custom-ordering',
		'id'         => 'term_id',
		'name'       => 'name',
		'name_num'   => 'numeric-name',
	];

	public function __construct( int $wc_attribute_id ) {
		$this->wc_attribute_id = $wc_attribute_id;
	}

	public function migrate(): ?int {
		$this->set_attribute();
		if ( ! $this->attribute ) {
			return null;
		}

		$this->import_terms();

		return $this->attribute->get_id();
	}

	protected function set_attribute() {
		$wc_attribute   = wc_get_attribute( $this->wc_attribute_id );
		$this->taxonomy = str_replace( 'pa_', '', $wc_attribute->slug );

		if ( strlen( $this->taxonomy ) > 25 ) {
			$this->taxonomy = rtrim( trim( substr( $this->taxonomy, 0, 25 ) ), '-_' );
			if ( taxonomy_exists( $this->taxonomy ) ) {
				$this->taxonomy = rtrim( trim( substr( $this->taxonomy, 0, 23 ) ), '-_' ) . '-' . 2;
				$i              = 3;
				while ( taxonomy_exists( $this->taxonomy ) ) {
					$this->taxonomy = rtrim( trim( substr( $this->taxonomy, 0, 23 ) ), '-_' ) . '-' . $i;
					$i++;
				}
			}
		}

		global $wpdb;
		$attribute_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT st.attribute_id
				FROM {$wpdb->prefix}woocommerce_attribute_taxonomies wt
				INNER JOIN {$wpdb->prefix}storeengine_attribute_taxonomies st ON wt.attribute_name = st.attribute_name
				WHERE st.attribute_name = %s",
				$this->taxonomy
			)
		);

		if ( $attribute_id ) {
			$this->attribute = Helper::get_product_attribute( $attribute_id ) ?? null;

			return;
		}

		$label           = Formatting::slug_to_words( $wc_attribute->name );
		$label           = Formatting::entity_decode_utf8( $label );
		$this->attribute = new Attribute();
		$this->attribute->set_name( $this->taxonomy );
		$this->attribute->set_label( $label );
		$this->attribute->set_type( $wc_attribute->type );
		$this->attribute->set_orderby( $this->attribute_order_mapping[ $wc_attribute->order_by ] );
		$this->attribute->set_public( $wc_attribute->has_archives );
		$this->attribute->save();
	}

	protected function import_terms() {
		$wc_terms      = get_terms( [
			'taxonomy'   => 'pa_' . $this->taxonomy,
			'hide_empty' => false, // Including all terms?! Maybe it should exclude unused attribute terms.
			'fields'     => 'names',
		] );
		$se_terms      = $this->attribute->get_terms( [ 'fields' => 'names' ] );
		$missing_terms = $wc_terms;

		foreach ( $wc_terms as $index => $wc_term ) {
			if ( in_array( $wc_term, $se_terms, true ) ) {
				unset( $missing_terms[ $index ] );
			}
		}

		foreach ( $missing_terms as $term_name ) {
			$term_name = Formatting::slug_to_words( $term_name );
			$term_name = Formatting::entity_decode_utf8( $term_name );
			wp_insert_term( $term_name, Helper::get_attribute_taxonomy_name( $this->taxonomy ), [ 'slug' => sanitize_title( $term_name ) ] );
		}
	}
}
