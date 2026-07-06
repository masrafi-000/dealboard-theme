<?php
/**
 * Single Post Template
 */
get_header(); ?>

<div class="container" style="padding: 40px 20px; max-width: 800px;">
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <h1 style="font-size:28px;font-weight:800;color:#111;margin-bottom:12px;"><?php the_title(); ?></h1>
      <div style="font-size:13px;color:#6B7280;margin-bottom:28px;">
        <?php echo get_the_date(); ?> · <?php the_author(); ?>
      </div>
      <?php if ( has_post_thumbnail() ) : ?>
        <div style="margin-bottom:28px;border-radius:12px;overflow:hidden;">
          <?php the_post_thumbnail('large', ['style' => 'width:100%;']); ?>
        </div>
      <?php endif; ?>
      <div style="font-size:15px;line-height:1.8;color:#374151;">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
