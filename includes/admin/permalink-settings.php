<?php

namespace StoreEngine\Admin;

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class PermalinkSettings {

	/**
	 * Permalink settings.
	 *
	 * @var array
	 */
	private array $permalinks = array();

	public static function init() {
		$self = new self();
		$self->settings_init();
		$self->settings_save();
	}

	/**
	 * Init our settings.
	 */
	public function settings_init() {
		add_settings_section( 'storeengine-permalink', __( 'StoreEngine Product permalinks', 'storeengine' ), [ $this, 'settings' ], 'permalink' );

		add_settings_field(
			'storeengine_product_category_slug',
			__( 'Product category base', 'storeengine' ),
			[ $this, 'product_category_slug_input' ],
			'permalink',
			'optional'
		);
		add_settings_field(
			'storeengine_product_tag_slug',
			__( 'Product tag base', 'storeengine' ),
			[ $this, 'product_tag_slug_input' ],
			'permalink',
			'optional'
		);
		$this->permalinks = Helper::get_permalink_structure();
	}

	public function product_category_slug_input() {
		?>
		<input name="storeengine_product_category_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['category_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'product-category', 'slug', 'storeengine' ); ?>" />
		<?php
	}

	public function product_tag_slug_input() {
		?>
		<input name="storeengine_product_tag_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['tag_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'product-tag', 'slug', 'storeengine' ); ?>" />
		<?php
	}

	/**
	 * Show the settings.
	 */
	public function settings() {
		/* translators: %s: Home URL */
		echo wp_kses_post( wpautop( sprintf( __( 'If you like, you may enter custom structures for your product URLs here. For example, using <code>shop</code> would make your product links like <code>%sshop/sample-product/</code>. This setting affects product URLs only, not things such as product categories.', 'storeengine' ), esc_url( home_url( '/' ) ) ) ) );

		$shop_page_id = (int) Helper::get_settings( 'shop_page' );
		$base_slug    = urldecode( ( $shop_page_id > 0 && get_post( $shop_page_id ) ) ? get_page_uri( $shop_page_id ) : _x( 'shop', 'default-slug', 'storeengine' ) );
		$product_base = _x( 'product', 'default-slug', 'storeengine' );

		$structures = array(
			0 => '',
			1 => '/' . trailingslashit( $base_slug ),
			2 => '/' . trailingslashit( $base_slug ) . trailingslashit( '%product_category%' ),
		);

		?>
		<table class="form-table storeengine-permalink-structure">
			<tbody>
			<tr>
				<th><label><input name="storeengine_product_permalink" type="radio" value="<?php echo esc_attr( $structures[0] ); ?>" class="storeenginetog" <?php checked( $structures[0], $this->permalinks['product_base'] ); ?> /> <?php esc_html_e( 'Default', 'storeengine' ); ?></label></th>
				<td><code class="default-example"><?php echo esc_html( home_url() ); ?>/?product=sample-product</code> <code class="non-default-example"><?php echo esc_html( home_url() ); ?>/<?php echo esc_html( $product_base ); ?>/sample-product/</code></td>
			</tr>
			<?php if ( $shop_page_id ) : ?>
				<tr>
					<th><label><input name="storeengine_product_permalink" type="radio" value="<?php echo esc_attr( $structures[1] ); ?>" class="storeenginetog" <?php checked( $structures[1], $this->permalinks['product_base'] ); ?> /> <?php esc_html_e( 'Products base', 'storeengine' ); ?></label></th>
					<td><code><?php echo esc_html( home_url() ); ?>/<?php echo esc_html( $base_slug ); ?>/sample-product/</code></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><label><input name="storeengine_product_permalink" id="storeengine_custom_selection" type="radio" value="custom" class="tog" <?php checked( in_array( $this->permalinks['product_base'], $structures, true ), false ); ?> />
						<?php esc_html_e( 'Custom base', 'storeengine' ); ?></label></th>
				<td>
					<input name="storeengine_product_permalink_structure" id="storeengine_product_permalink_structure" type="text" value="<?php echo esc_attr( $this->permalinks['product_base'] ? trailingslashit( $this->permalinks['product_base'] ) : '' ); ?>" class="regular-text code"> <span class="description"><?php esc_html_e( 'Enter a custom base to use. A base must be set or WordPress will use default instead.', 'storeengine' ); ?></span>
				</td>
			</tr>
			</tbody>
		</table>
		<?php wp_nonce_field( 'storeengine-permalinks', 'storeengine-permalinks-nonce' ); ?>
		<script type="text/javascript">
			jQuery( function() {
				jQuery('input.storeenginetog').on( 'change', function() {
					jQuery('#storeengine_product_permalink_structure').val( jQuery( this ).val() );
				});
				jQuery('.permalink-structure input').on( 'change', function() {
					jQuery('.storeengine-permalink-structure').find('code.non-default-example, code.default-example').hide();
					if ( jQuery(this).val() ) {
						jQuery('.storeengine-permalink-structure code.non-default-example').show();
						jQuery('.storeengine-permalink-structure input').prop('disabled', false);
					} else {
						jQuery('.storeengine-permalink-structure code.default-example').show();
						jQuery('.storeengine-permalink-structure input:eq(0)').trigger( 'click' );
						jQuery('.storeengine-permalink-structure input').attr('disabled', 'disabled');
					}
				});
				jQuery('.permalink-structure input:checked').trigger( 'change' );
				jQuery('#storeengine_permalink_structure').on( 'focus', function(){
					jQuery('#storeengine_custom_selection').trigger( 'click' );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Save the settings.
	 */
	public function settings_save() {
		if ( ! is_admin() ) {
			return;
		}

		// We need to save the options ourselves; settings api does not trigger save for the permalinks page.
		if ( isset( $_POST['permalink_structure'], $_POST['storeengine-permalinks-nonce'], $_POST['storeengine_product_category_slug'], $_POST['storeengine_product_tag_slug'] ) && wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['storeengine-permalinks-nonce'] )), 'storeengine-permalinks' ) ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$permalinks                  = (array) get_option( 'storeengine_permalinks', array() );
			$permalinks['category_base'] = Formatting::sanitize_permalink( wp_unslash( $_POST['storeengine_product_category_slug'] ) );
			$permalinks['tag_base']      = Formatting::sanitize_permalink( wp_unslash( $_POST['storeengine_product_tag_slug'] ) );
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Generate product base.
			$product_base = isset( $_POST['storeengine_product_permalink'] ) ? sanitize_text_field( wp_unslash( $_POST['storeengine_product_permalink'] ) ) : '';

			if ( 'custom' === $product_base ) {
				if ( isset( $_POST['storeengine_product_permalink_structure'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$product_base = preg_replace( '#/+#', '/', '/' . str_replace( '#', '', trim( wp_unslash( $_POST['storeengine_product_permalink_structure'] ) ) ) );
				} else {
					$product_base = '/';
				}

				// This is an invalid base structure and breaks pages.
				if ( '/%product_category%/' === trailingslashit( $product_base ) ) {
					$product_base = '/' . _x( 'product', 'slug', 'storeengine' ) . $product_base;
				}
			} elseif ( empty( $product_base ) ) {
				$product_base = _x( 'product', 'slug', 'storeengine' );
			}

			$permalinks['product_base'] = Formatting::sanitize_permalink( $product_base );

			// Shop base may require verbose page rules if nesting pages.
			$shop_page_id   = (int) Helper::get_settings( 'shop_page' );
			$shop_permalink = ( $shop_page_id > 0 && get_post( $shop_page_id ) ) ? get_page_uri( $shop_page_id ) : _x( 'shop', 'default-slug', 'storeengine' );

			if ( $shop_page_id && stristr( trim( $permalinks['product_base'], '/' ), $shop_permalink ) ) {
				$permalinks['use_verbose_page_rules'] = true;
			}

			update_option( 'storeengine_permalinks', $permalinks );
		}//end if
	}

}
