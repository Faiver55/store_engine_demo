<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_endpoint = get_query_var( 'storeengine_dashboard_page' );

/**
 * @var array $menu_items
 */

$current_endpoint     = get_query_var( 'storeengine_dashboard_page' );
$current_sub_endpoint = get_query_var( 'storeengine_dashboard_sub_page' );
foreach ( $menu_items as $endpoint => $item ) :
	if ( ! $item['public'] ) {
		continue;
	}

	$is_current = $current_endpoint === $endpoint || '' === $current_endpoint && 'index' === $endpoint;
	$classnames = 'storeengine-dashboard-menu__item-' . $endpoint;
	if ( $is_current ) {
		$classnames .= ' storeengine-dashboard-menu__item-current';
	}

	?>
	<li class="<?php echo esc_attr( $classnames ); ?>">
		<a href="<?php echo esc_url( isset( $item['permalink'] ) ? $item['permalink'] : storeengine_get_dashboard_endpoint_url( $endpoint ) ); ?>">
			<i class="<?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></i>
			<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( $item['label'] ); ?></span>
		</a>
		<?php if ( ! empty( $item['children'] ) ) {
			$children = array_filter( $item['children'], fn( $c )=> $c['public'] ?? false );
			if ( empty( $children ) ) {
				continue;
			}

			uasort( $children, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
			?>
		<ul class="storeengine-dashboard-menu--children storeengine-frontend-dashboard__menu--children">
			<?php foreach ( $children as $sub_endpoint => $child ) {
				$sub_classnames = 'storeengine-dashboard-menu__sub-item-' . $endpoint . '--' . $sub_endpoint;
				if ( $is_current && $current_sub_endpoint === $sub_endpoint ) {
					$sub_classnames .= ' storeengine-dashboard-menu__sub-item-current';
					$sub_classnames .= ' storeengine-dashboard-menu__item-current';
				}
				?>
				<li class="<?php echo esc_attr( $sub_classnames ); ?>">
					<a href="<?php echo esc_url( isset( $item['permalink'] ) ? $item['permalink'] : storeengine_get_dashboard_endpoint_url( $endpoint, $sub_endpoint ) ); ?>">
						<?php if ( ! empty( $child['icon'] ) ) { ?>
						<i class="<?php echo esc_attr( $child['icon'] ); ?>" aria-hidden="true"></i>
						<?php } ?>
						<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( $child['label'] ); ?></span>
					</a>
				</li>
			<?php } ?>
		</ul>
		<?php } ?>
	</li>
<?php endforeach; ?>
