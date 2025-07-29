<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

\StoreEngine\Utils\Helper::get_template_part( 'email/template', 'header' );
?>
	<div class="container">
	<div class="content">
		<div class="wrapper">
			<h5 class="main-heading"><?php echo esc_html( $heading ); ?></h5>
			<div class="entry-content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
			<div class="footer">
				<?php echo wp_kses_post( $footer ); ?>
			</div>
		</div>
	</div>
<?php
\StoreEngine\Utils\Helper::get_template_part( 'email/template', 'footer' );

