<?php
/**
 * Template Name: Elementor Full Width
 * Template Post Type: post, page
 *
 * Elementor দিয়ে full width page বানানোর জন্য এই template use করুন।
 * Header ও Footer থাকবে, কিন্তু content area পুরো wide হবে।
 */
get_header();
?>

<main id="main-content">
  <?php
  if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'single' ) ) {
      // Elementor Pro single template
  } else {
      while ( have_posts() ) :
          the_post();
          the_content();
      endwhile;
  }
  ?>
</main>

<?php get_footer(); ?>
