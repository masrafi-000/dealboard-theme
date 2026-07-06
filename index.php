<?php
/**
 * DealBoard - Main Index Template
 * This is the fallback template for all pages.
 */
get_header(); ?>

<div class="container" style="padding: 40px 20px; min-height: 60vh;">
  <?php if ( have_posts() ) : ?>
    <div class="listings-grid">
      <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('listing-card'); ?>>
          <?php if ( has_post_thumbnail() ) : ?>
            <div class="listing-image">
              <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('medium'); ?>
              </a>
            </div>
          <?php endif; ?>
          <div class="listing-body">
            <h2 class="listing-title" style="font-size:16px;">
              <a href="<?php the_permalink(); ?>" style="color:#111;"><?php the_title(); ?></a>
            </h2>
            <div class="listing-meta">
              <span><?php the_date(); ?></span>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </div>

    <?php the_posts_pagination(['prev_text' => '← Previous', 'next_text' => 'Next →']); ?>

  <?php else : ?>
    <div class="empty-state">
      <div style="font-size:48px;margin-bottom:16px">📭</div>
      <h2>No content found</h2>
      <p>It looks like nothing was found. <a href="<?php echo home_url('/'); ?>" style="color:#10B981;">Go to Homepage</a></p>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
