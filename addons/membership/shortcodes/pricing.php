<?php

namespace StoreEngine\Addons\Membership\Shortcodes;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class Pricing {

	public function __construct() {
		add_shortcode( 'storeengine_membership_pricing', [ $this, 'render_pricing' ] );
	}

	public function render_pricing( $atts ): string {
		$atts         = shortcode_atts( [
			'no_pricing_text' => __( 'No pricing available!', 'storeengine' ),
			'orderby'         => 'ASC',
		], $atts, 'storeengine_membership_pricing' );
		$integrations = Helper::get_integration_repository_by_provider( 'storeengine/membership-addon', [ 'orderby' => $atts['orderby'] ] );

		ob_start();
		?>
		<div class="storeengine-membership-pricing storeengine-row storeengine-mt-5">
			<?php if ( empty( $integrations ) ) { ?>
				<p><?php echo esc_html( $atts['no_pricing_text'] ); ?></p>
			<?php } else {
				foreach ( $integrations as $integration ) {
					$features = get_post_meta( $integration->integration->get_integration_id(), '_storeengine_membership_features', true );
					Template::get_template( 'membership/pricing-card.php', [
						'integration' => $integration,
						'features'    => is_array( $features ) ? $features : [],
					] );
				}
			}
			?>
		</div>
		<?php

		// @TODO maybe just remove the content from buffer, as hook handling the cleanups anyway.
		/** @see Hooks::remove_empty_spaces() */

		$output = Helper::remove_line_break( ob_get_clean() );

		return Helper::remove_tag_space( $output );
	}

}
