<?php
/**
 * Catch-all template
 *
 * @package vamtam/jolie
 */

VamtamFramework::set( 'page_title', esc_html__( 'Blog', 'jolie' ) );

get_header();
?>
<div class="page-wrapper">

	<article <?php post_class( VamtamTemplates::get_layout() ) ?>>
		<div class="page-content clearfix">
			<?php get_template_part( 'loop' ); ?>
		</div>
	</article>

	<?php get_template_part( 'sidebar' ) ?>
</div>
<?php get_footer(); ?>


