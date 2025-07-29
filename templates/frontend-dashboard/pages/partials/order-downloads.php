<?php
/**
 * Order downloads
 *
 * @var array $downloads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4><?php esc_html_e( 'Downloads', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__section--order-downloads">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--order-downloads">
				<thead>
				<tr>
					<th scope="row" class="download-product"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="row" class="download-remaining"><?php esc_html_e( 'Downloads remaining', 'storeengine' ); ?></th>
					<th scope="row" class="download-expires"><?php esc_html_e( 'Expires', 'storeengine' ); ?></th>
					<th scope="row" class="download-file"><?php esc_html_e( 'Download', 'storeengine' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $downloads as $download ) { ?>
					<tr class="storeengine-dashboard__table--download order_download">
						<th scope="col" class="download-product">
							<?php if ( $download['product_url'] ) { ?>
								<a href="<?php echo esc_url( $download['product_url'] ); ?>"><?php echo esc_html( $download['product_name'] ); ?></a>
							<?php } else { ?>
								<?php echo esc_html( $download['product_name'] ); ?>
							<?php } ?>
						</th>
						<td class="download-remaining">
							<?php echo is_numeric( $download['downloads_remaining'] ) ? esc_html( $download['downloads_remaining'] ) : esc_html__( '&infin;', 'woocommerce' ); ?>
						</td>
						<td class="download-expires">
							<?php if ( ! empty( $download['access_expires'] ) ) { ?>
								<time datetime="<?php echo esc_attr( $download['access_expires'] ); ?>">
									<?php esc_html( date_i18n( 'Y-m-d :i a', strtotime( $download['access_expires'] ) ) ); ?>
								</time>
							<?php } else { ?>
								<?php esc_html_e( 'Never', 'woocommerce' ); ?>
							<?php } ?>
						</td>
						<td class="download-file">
							<a href="<?php echo esc_url( $download['download_url'] ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--md button alt"><?php echo esc_html( $download['download_name'] ); ?></a>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php
