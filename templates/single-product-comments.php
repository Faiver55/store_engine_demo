<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly
}

/*------------------
* Comment Form Modification Start
---------------------*/

if ( ! function_exists( 'storeengine__comments' ) ) {
	function storeengine__comments( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		?>
		<li <?php comment_class(); ?> id="comment-<?php comment_ID(); ?>">
			<div class="article d-flex gap-4">
				<?php if ( get_avatar( $comment ) ) { ?>
					<div class="author-pic">
						<?php echo get_avatar( $comment, 104, '', '', [ 'class' => 'rounded-circle' ] ); ?>
					</div>
				<?php } ?>
				<div class="details">
					<div class="author-meta">
						<div class="name">
							<h4><?php comment_author(); ?></h4>
						</div>
						<div class="date">
							<span><?php echo gmdate( get_option( 'date_format' ), $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</div>
						<div class="comment-content"><?php comment_text(); ?></div>
						<div class="reply">
							<?php comment_reply_link( array_merge( $args, [
	'reply_text' => esc_html__( 'Reply', 'storeengine' ),
	'depth'      => $depth,
	'max_depth'  => $args['max_depth'],
] ) ); ?>
						</div>
					</div>

					<?php if ( '0' === $comment->comment_approved ) : ?>
						<p><em><?php esc_html_e( 'Your comment is awaiting moderation.', 'storeengine' ); ?></em></p>
					<?php endif; ?>
				</div>
			</div>
		</li>
		<?php
	}
}

if ( ! function_exists( 'storeengine_comment_reform' ) ) {
	/**
	 * Comment Message Box
	 */
	function storeengine_comment_reform( $arg ) {
		$arg['title_reply'] = esc_html__( 'Post your Comment About This Product', 'storeengine' );

		return $arg;
	}

	add_filter( 'comment_form_defaults', 'storeengine_comment_reform' );
}

if ( ! function_exists( 'time_ago_comment' ) ) {
	function time_ago_comment( $comment_id = null ): string {
		if ( is_null( $comment_id ) ) {
			$comment_id = get_comment_ID(); // Get the current comment's ID if not provided
		}

		$comment_time = get_comment_date( 'U', $comment_id ); // Get the comment's timestamp
		$time_diff    = human_time_diff( $comment_time, current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		/* translators: %s: Time difference (in hour). */
		return sprintf( esc_html__( '%s Hours Ago', 'storeengine' ), $time_diff );
	}
}

if ( ! function_exists( 'storeengine_comments' ) ) :
	function storeengine_comments( $default_comment, $args, $depth ) {
		$GLOBALS['comment'] = $default_comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( 'pingback' === $default_comment->comment_type || 'trackback' === $default_comment->comment_type ) : ?>
			<li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
				<div class="comment-body">
					<?php esc_html_e( 'Pingback:', 'storeengine' ); ?><?php comment_author_link(); ?><?php edit_comment_link( esc_attr__( 'Edit', 'storeengine' ), '<span class="edit-link">', '</span>' ); ?>
				</div>
			</li>
		<?php else : ?>
			<ul class="storeengine-comment-area_wrap">
				<li id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
					<article id="div-comment-<?php comment_ID(); ?>" class="comment-body storeengine-comment-area_comment-list">
						<div class="storeengine-row storeengine-relative storeengine-gap-20 ">
							<div class="storeengine-comment-contain-img align-self-center">
								<?php echo get_avatar( get_comment( get_comment_ID() )->user_id, 50 ); // 50 is the size of the avatar ?>
							</div>
							<div class="storeengine-comment">
								<h3 class="comment-author-title storeengine-f18 storeengine-fw7 storeengine-wcl storeengine-ffh"><?php echo wp_kses_post( get_comment_author_link() ); ?></h3>
								<div class="d-flex gap-3">
									<p>
										<a class="storeengine-comment-time" href="<?php echo esc_url( get_comment_link( $default_comment->comment_ID ) ); ?>">
											<time datetime="<?php comment_time( 'c' ); ?>"><?php echo time_ago_comment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></time>
										</a>
									</p>
									<?php
									comment_reply_link( array_merge( $args, array(
										'add_below' => 'div-comment',
										'depth'     => $depth,
										'max_depth' => $args['max_depth'],
										'before'    => '<span class="storeengine_replay_text_link storeengine-mcl storeengine-f16 storeengine-fw7 storeengine-ffh">',
										'after'     => '</span>',
									) ) );
									?>
								</div>
							</div>
						</div>
						<div class="storeengine-comment-content"><?php comment_text(); ?></div>
					</article>
				</li>
			</ul>
		<?php
		endif;
	}
endif; // ends check for storeengine_comments()

/*------------------
* Comment Form Modification End
---------------------*/
?>
<div id="default-comments" class="storeengine-products-comment">

	<?php
	// You can start editing here -- including this comment!
	if ( have_comments() ) :
		?>
		<div class="title">
			<h3 class="comments-title">
				<span class="storeengine-default-comments-title">
					<?php
					$storeengine_comments_number = get_comments_number();
					printf(
					/* translators: %s: Number of comments. */
						esc_html( _n( '%s Comment About This Product', '%s Comments About This Product', $storeengine_comments_number, 'storeengine' ) ),
						esc_html( number_format_i18n( $storeengine_comments_number ) )
					);
					?>
				</span>
			</h3><!-- .comments-title -->
		</div>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list">
			<?php wp_list_comments( [
	'callback' => 'storeengine_comments',
	'style'    => 'ol',
] ); ?>
		</ol><!-- .comment-list -->

		<?php
		the_comments_navigation();

		// If comments are closed and there are comments, let's leave a little note, shall we?
		if ( ! comments_open() ) :
			?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'storeengine' ); ?></p>
		<?php
		endif;
	endif; // Check for have_comments().

	comment_form( [
		'comment_notes_before' => '',
		'label_submit'         => __( 'Submit', 'storeengine' ),
		'class_form'           => 'storeengine-clearfix storeengine-row',
	] );
	?>

</div><!-- #comments -->
