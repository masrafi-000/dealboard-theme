<?php
/**
 * Elementor Full Width Template
 * Header & Footer আছে, কিন্তু content area full width
 * Elementor দিয়ে section width নিজে control করা যায়
 */
get_header(); ?>

<main id="main-content" style="min-height:60vh">
  <?php
  // Check if Elementor Pro header location is set
  if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'single' ) ) {
      // Elementor Pro single template active
  } else {
      // Fallback: show page content
      while ( have_posts() ) :
          the_post();
          ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
              <?php the_content(); ?>
          </article>
          <?php
      endwhile;
  }
  ?>
</main>

<?php get_footer(); ?>
