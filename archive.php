<?php
/**
 * Archive page template
 *
 * @package vamtam/jolie
 */

VamtamFramework::set( 'page_title', get_the_archive_title() );

get_header(); ?>

<div class="page-wrapper">

	<?php VamtamTemplates::$in_page_wrapper = true; ?>

	<article>
		<?php if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'archive' ) ) : ?>
			<div class="page-content clearfix">
				<?php get_template_part( 'loop' ) ?>
			</div>
		<?php endif; ?>
	</article>

	<?php get_template_part( 'sidebar' ) ?>
</div>

<?php get_footer();


