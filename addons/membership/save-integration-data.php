<?php

namespace StoreEngine\Addons\Membership;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SaveIntegrationData {

	public $product_id;

	public $post_type;

	public $product_integrated_plan_id = 99;

	public static function init() {
		$self            = new self();
		$self->post_type = Helper::PRODUCT_POST_TYPE;
		$self->register_product_meta();
		add_action( 'save_post_' . $self->post_type, array( $self, 'saving_product_data' ), 10, 3 );
	}

	public function update_product_meta( $product_id, $meta_key, $meta_value ) {
		update_post_meta( $product_id, $meta_key, $meta_value );
	}

	public function register_product_meta() {
		$product_meta = [ '_storeengine_product_integrated_plan_id' => 'integer' ];

		foreach ( $product_meta as $meta_key => $meta_value_type ) {
			register_meta(
				'post',
				$meta_key,
				array(
					'object_subtype' => $this->post_type,
					'type'           => $meta_value_type,
					'single'         => true,
					'show_in_rest'   => true,
				)
			);
		}
	}

	public function get_integrated_plan_id_by_product_id( int $product_id, $meta_key ) {
		return get_post_meta( $product_id, $meta_key, true );
	}

	public function saving_product_data( $post_id, $post, $update ) {
		// Check if this is an auto-save routine.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if this is a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->product_id = $post_id;

		// Update product meta
		$this->update_product_meta( $this->product_id, '_storeengine_product_integrated_plan_id', $this->product_integrated_plan_id );
	}
}
