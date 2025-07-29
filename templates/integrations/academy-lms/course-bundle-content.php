<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="academy-widget-enroll__head">
	<div class="academy-course-type">
		Paid
	</div>
</div>
<div class="academy-widget-enroll__content">
	<ul class="academy-widget-enroll__content-lists">
		<?php if ( $duration ) : ?>
			<li>
				<span class="label">
					<span class="academy-icon academy-icon--clock" aria-hidden="true"></span>
					<?php esc_html_e( 'Duration', 'storeengine' ); ?>
				</span>
				<span class="data"><?php echo esc_html( $duration ); ?></span>
			</li>
		<?php endif; ?>
		<?php if ( $total_lessons ) : ?>
			<li>
				<span class="label">
					<i class="academy-icon academy-icon--lesson" aria-hidden="true"></i>
				<?php esc_html_e( 'Lessons', 'storeengine' ); ?>
				</span>
				<span class="data"><?php echo esc_html( $total_lessons ); ?></span>
			</li>
		<?php endif; ?>
		<?php if ( $max_students ) : ?>
			<li>
				<span class="label">
					<span class="academy-icon academy-icon--group-profile" aria-hidden="true"></span>
					<?php esc_html_e( 'Available Seats', 'storeengine' ); ?>
				</span>
				<span class="data"><?php echo esc_html( $max_students - $total_enrolled ); ?></span>
			</li>
		<?php endif; ?>

		<?php if ( $last_update ) : ?>
			<li>
				<span class="label">
					<i class="academy-icon academy-icon--calender" aria-hidden="true"></i>
					<?php esc_html_e( 'Last Update', 'storeengine' ); ?>
				</span>
				<span class="data"><?php echo esc_html( $last_update ); ?></span>
			</li>
		<?php endif; ?>
	</ul>
</div>
