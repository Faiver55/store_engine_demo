<?php
/**
 * @var \StoreEngine\Classes\AbstractCollection $query
 * @var string $page_url
 * @var int $current_page
 * @var int $previous_page
 * @var int $max_pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="storeengine__order-pagination">
	<?php if ( $max_pages > 1 ) : ?>
		<ul class="pagination">
			<?php if ( $previous_page > 0 ) : ?>
				<li>
					<a href="<?php echo esc_attr( add_query_arg( 'paged', max( 1, $previous_page ), $page_url ) ); ?>" class="page-numbers prev" aria-label="<?php esc_html_e( 'Previous Page', 'storeengine' ); ?>">
						<i class="storeengine-icon storeengine-icon--arrow-left" aria-hidden="true"></i>
					</a>
				</li>
			<?php endif; ?>
			<?php for ( $i = 1; $i <= $max_pages; $i ++ ) : ?>
				<li>
					<?php if ( $i === $current_page ) { ?>
						<span class="page-numbers active" aria-current="page"><?php echo esc_html( $i ); ?></span>
					<?php } else { ?>
					<a href="<?php echo esc_attr( add_query_arg( 'paged', $i, $page_url ) ); ?>" class="page-numbers"><?php echo esc_html( $i ); ?></a>
					<?php } ?>
				</li>
			<?php endfor; ?>
			<?php if ( $max_pages > $current_page ) : ?>
				<li>
					<a href="<?php echo esc_attr( add_query_arg( 'paged', min( $max_pages, $current_page + 1 ), $page_url ) ); ?>" class="page-numbers next" aria-label="<?php esc_html_e( 'Next Page', 'storeengine' ); ?>">
						<i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i>
					</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>
</div>
