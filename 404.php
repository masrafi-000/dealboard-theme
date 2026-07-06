<?php
/**
 * 404 Template
 */
get_header(); ?>

<div class="container" style="padding:80px 20px;text-align:center;min-height:60vh;">
  <div style="font-size:80px;margin-bottom:16px;">🔍</div>
  <h1 style="font-size:32px;font-weight:800;color:#111;margin-bottom:8px;">Page Not Found</h1>
  <p style="color:#6B7280;margin-bottom:28px;">The page you're looking for doesn't exist or has been moved.</p>
  <a href="<?php echo home_url('/'); ?>" class="btn-post-ad" style="display:inline-flex;border-radius:8px;padding:12px 28px;">Go to Homepage</a>
</div>

<?php get_footer(); ?>
