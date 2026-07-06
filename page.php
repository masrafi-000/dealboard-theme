<?php
/**
 * Default Page Template
 */
get_header(); ?>

<div class="container" style="padding: 40px 20px; max-width: 900px;">
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <h1 style="font-size:28px;font-weight:800;color:#111;margin-bottom:24px;"><?php the_title(); ?></h1>
      <div style="font-size:15px;line-height:1.8;color:#374151;">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
