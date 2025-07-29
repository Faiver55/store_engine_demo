<?php

if ( ! defined('ABSPATH') ) {
	exit(); // Exit if accessed directly
}
?>
<div class="storeengine-like-dislike-wrapper">
	<h2> <?php echo esc_html__('Was this review helpful?', 'storeengine'); ?> </h2>
	<div class="storeengine-like-dislike storeengine-d-flex ">
		<div class="storeengine--like storeengine-d-flex">
			<i class="storeengine-icon storeengine-icon--like"></i>
			<div class="storeengine--like-count">
				<span>12</span>
			</div>
		</div>
		<div class="storeengine--dislike storeengine-d-flex">
			<i class="storeengine-icon storeengine-icon--dislike"></i>
			<span>10</span>
		</div>
	</div>
</div>
