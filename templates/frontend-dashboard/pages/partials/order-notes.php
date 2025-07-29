<?php
/**
 * Order notes
 *
 * @var WP_Query[] $notes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4><?php esc_html_e( 'Order updates', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--order-notes">
			<ol class="storeengine-order-updates commentlist notes">
				<?php foreach ( $notes as $note ) : ?>
					<li class="storeengine-order-update comment note">
						<div class="storeengine-order-update-description description">
							<?php echo wpautop( wptexturize( $note->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="note-separator"></div>
						<div class="storeengine-order-update-date">
							<time datetime="<?php echo esc_attr( $note->date_created ); ?>" data-format="<?php echo esc_attr_x( 'dddd Do [of] MMMM YYYY, hh:mma', 'Moment.js supported date format for user-dashboard order notes', 'storeengine' ); ?>">
								<?php echo date_i18n( esc_html__( 'l jS \o\f F Y, h:ia', 'storeengine' ), strtotime( $note->date_created ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</time>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>
<?php
