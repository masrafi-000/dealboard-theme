<?php get_header(); ?>

<?php
// Elementor check
if ( function_exists('elementor_theme_do_location') && elementor_theme_do_location('single') ) {
    get_footer(); return;
}

$front_id = (int) get_option('page_on_front');
$has_elementor = false;
if ( $front_id && class_exists('\Elementor\Plugin') ) {
    $el_mode = get_post_meta( $front_id, '_elementor_edit_mode', true );
    $el_data = get_post_meta( $front_id, '_elementor_data', true );
    if ( $el_mode === 'builder' && ! empty($el_data) ) {
        $has_elementor = true;
    }
}

if ( $has_elementor ) {
    $post = get_post($front_id);
    setup_postdata($post);
    the_content();
    wp_reset_postdata();
} else {
?>

<!-- ===== HERO ===== -->
<section class="hero-section">
  <div class="hero-badge">
    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    Local deals in your community
  </div>
  <h1 class="hero-title">Buy &amp; Sell Locally,<br><span class="highlight" style="color:#C9A84C">Find Hidden Gems</span></h1>
  <p class="hero-subtitle">Classified ads, garage sales, and great deals — all in one place.</p>
  <form class="hero-search-box" action="<?php echo esc_url(home_url('/listings')); ?>" method="GET">
    <input type="text" name="s" placeholder="What are you looking for?" autocomplete="off">
    <button type="submit" class="hero-search-btn">Search</button>
  </form>
  <div class="hero-links">
    <a href="<?php echo esc_url(home_url('/post-ad')); ?>" class="hero-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      Post an Ad — It's Free
    </a>
    <a href="<?php echo esc_url(home_url('/garage-sales/list')); ?>" class="hero-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      List a Garage Sale
    </a>
  </div>
</section>

<!-- ===== CATEGORIES ===== -->
<?php
$cats = get_terms(['taxonomy'=>'listing_category','hide_empty'=>false,'parent'=>0]);
$defaults = [
  ['name'=>'Vehicles','emoji'=>'🚗'],['name'=>'Electronics','emoji'=>'💻'],
  ['name'=>'Furniture','emoji'=>'🛋️'],['name'=>'Clothing','emoji'=>'👕'],
  ['name'=>'Tools & Equipment','emoji'=>'🔧'],['name'=>'Sports & Outdoors','emoji'=>'⚽'],
  ['name'=>'Home & Garden','emoji'=>'🏡'],['name'=>'Toys & Kids','emoji'=>'🧸'],
];
?>
<style>
.home-cat-row {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 12px;
}
.home-cat-row .category-card:nth-child(n+9) { display: none; }
@media (max-width: 1024px) {
  .home-cat-row { grid-template-columns: repeat(4, 1fr); }
  .home-cat-row .category-card:nth-child(n+9) { display: flex; }
}
@media (max-width: 600px) { .home-cat-row { grid-template-columns: repeat(3, 1fr); } }
</style>
<section class="section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Browse by Category</h2>
      <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" class="section-link">View all →</a>
    </div>
    <div class="home-cat-row">
      <?php if (!is_wp_error($cats) && !empty($cats)):
        foreach ($cats as $cat):
          $link = get_term_link($cat);
          if (is_wp_error($link)) $link = home_url('/listings');
      ?>
      <a href="<?php echo esc_url($link); ?>" class="category-card">
        <div class="category-icon"><span style="font-size:22px"><?php echo dealboard_category_icon($cat->slug); ?></span></div>
        <span class="category-name"><?php echo esc_html($cat->name); ?></span>
      </a>
      <?php endforeach; else: foreach($defaults as $d): ?>
      <a href="<?php echo esc_url(home_url('/listings')); ?>" class="category-card">
        <div class="category-icon"><span style="font-size:22px"><?php echo $d['emoji']; ?></span></div>
        <span class="category-name"><?php echo esc_html($d['name']); ?></span>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<!-- ===== RECENT LISTINGS ===== -->
<section class="section" style="background:white;border-top:1px solid #F3F4F6">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><span>🏷️</span> Recent Listings</h2>
      <a href="<?php echo esc_url(home_url('/listings')); ?>" class="section-link">See all →</a>
    </div>
    <div class="listings-grid">
      <?php
      $lq = new WP_Query(['post_type'=>'listing','post_status'=>'publish','posts_per_page'=>8,'orderby'=>'date','order'=>'DESC']);
      if ($lq->have_posts()):
        while ($lq->have_posts()): $lq->the_post();
          $price          = get_post_meta(get_the_ID(),'listing_price',true);
          $city           = get_post_meta(get_the_ID(),'listing_city',true);
          $currency       = get_post_meta(get_the_ID(),'listing_currency',true) ?: 'USD';
          $views          = get_post_meta(get_the_ID(),'listing_views',true) ?: 0;
          $listing_status = get_post_meta(get_the_ID(),'listing_status',true);
          $lc             = wp_get_post_terms(get_the_ID(),'listing_category');
          $cat_name       = (!empty($lc) && !is_wp_error($lc)) ? $lc[0]->name : '';
          $author         = get_the_author_meta('display_name');
          $time           = human_time_diff(get_the_time('U'), current_time('timestamp')).' ago';
      ?>
      <a href="<?php the_permalink(); ?>" class="listing-card">
        <div class="listing-image" style="position:relative">
          <?php if (has_post_thumbnail()): the_post_thumbnail('medium',['loading'=>'lazy']); else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font-size:40px">📦</div>
          <?php endif; ?>

          <?php if($listing_status === 'expired'): ?>
          <div style="position:absolute;bottom:8px;left:8px;background:#DC2626;color:white;font-size:10px;font-weight:800;padding:4px 10px;border-radius:5px;z-index:2;letter-spacing:.8px;text-transform:uppercase;box-shadow:0 2px 6px rgba(0,0,0,.2)">
            ⛔ Expired
          </div>
          <?php endif; ?>

          <?php if ($price): ?><div class="listing-price" data-currency="<?php echo esc_attr($currency); ?>"><?php echo esc_html('$'.$price); ?></div><?php endif; ?>
          <?php if ($cat_name): ?><div class="listing-category-badge"><?php echo esc_html($cat_name); ?></div><?php endif; ?>
          <div class="listing-seller"><?php echo esc_html(strtoupper(substr($author,0,2))); ?></div>
        </div>
        <div class="listing-body">
          <div class="listing-title"><?php the_title(); ?></div>
          <?php if ($currency !== 'USD'): ?>
          <div class="listing-currency-note">Listed in <?php echo esc_html($currency); ?> · converted</div>
          <?php endif; ?>
          <div class="listing-meta">
            <?php if ($city): ?><span>📍 <?php echo esc_html($city); ?></span><?php endif; ?>
            <span>👁 <?php echo esc_html($views); ?></span>
            <span>🕐 <?php echo esc_html($time); ?></span>
          </div>
        </div>
      </a>
      <?php endwhile; wp_reset_postdata(); else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <p>No listings yet. <a href="<?php echo esc_url(home_url('/post-ad')); ?>" style="color:#10B981">Be the first to post!</a></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== GARAGE SALES ===== -->
<section class="section-alt">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <span style="color:#F97316">📍</span> Upcoming Garage Sales
        <small style="font-size:13px;font-weight:400;color:#6B7280;margin-left:4px">Find sales happening in your area this weekend</small>
      </h2>
      <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" class="section-link" style="color:#F97316">See all →</a>
    </div>
    <div class="garage-grid">
      <?php
      $sq = new WP_Query(['post_type'=>'garage_sale','post_status'=>'publish','posts_per_page'=>3,'orderby'=>'date','order'=>'DESC']);
      if ($sq->have_posts()):
        while ($sq->have_posts()): $sq->the_post();
          $date        = get_post_meta(get_the_ID(),'sale_date_start',true);
          $date_end_gs = get_post_meta(get_the_ID(),'sale_date_end',true);
          $start       = get_post_meta(get_the_ID(),'sale_time_start',true);
          $end         = get_post_meta(get_the_ID(),'sale_time_end',true);
          $addr        = get_post_meta(get_the_ID(),'sale_address',true);
          $city        = get_post_meta(get_the_ID(),'sale_city',true);
          $items       = get_post_meta(get_the_ID(),'sale_items',true);
          $gs_status   = get_post_meta(get_the_ID(),'listing_status',true);
          $dfmt        = $date ? date('D, M j, Y', strtotime($date)) : '';
          // Auto-expire by end date
          if(!$gs_status && $date_end_gs && strtotime($date_end_gs) < strtotime('today')) {
            $gs_status = 'expired';
          }
      ?>
      <a href="<?php the_permalink(); ?>" class="garage-card" style="position:relative">
        <div class="garage-image">
          <?php if (has_post_thumbnail()): the_post_thumbnail('medium', ['style'=>'width:100%;height:300px;object-fit:cover;object-position:center;display:block']); else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#FEF3C7;font-size:50px">🏡</div>
          <?php endif; ?>
          <?php if ($dfmt): ?><div class="garage-date-badge"><?php echo esc_html($dfmt); ?></div><?php endif; ?>

          <?php if($gs_status === 'expired'): ?>
          <div style="position:absolute;bottom:8px;left:8px;background:#DC2626;color:white;font-size:10px;font-weight:800;padding:4px 10px;border-radius:5px;z-index:2;letter-spacing:.8px;text-transform:uppercase;box-shadow:0 2px 6px rgba(0,0,0,.2)">
            ⛔ Expired
          </div>
          <?php endif; ?>
        </div>
        <div class="garage-body">
          <div class="garage-title"><?php the_title(); ?></div>
          <?php if ($items): ?><div class="garage-subtitle"><?php echo esc_html($items); ?></div><?php endif; ?>
          <div class="garage-info">
            <?php if ($addr||$city): ?><span>📍 <?php echo esc_html(trim($addr.', '.$city, ', ')); ?></span><?php endif; ?>
            <?php if ($dfmt): ?><span>📅 <?php echo esc_html($dfmt); ?></span><?php endif; ?>
            <?php if ($start&&$end): ?><span>⏰ <?php echo esc_html($start.' – '.$end); ?></span><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endwhile; wp_reset_postdata(); else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <p>No upcoming garage sales. <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" style="color:#F97316">List yours!</a></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== CTA ===== -->
<div class="cta-section">
  <div class="container">
    <div class="cta-banner">
      <h2 class="cta-title">Ready to sell something?</h2>
      <p class="cta-subtitle">Post your ad for free and reach thousands of local buyers.</p>
      <div class="cta-buttons">
        <a href="<?php echo esc_url(home_url('/post-ad')); ?>" class="btn-cta-primary">Post a Classified Ad</a>
        <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" class="btn-cta-secondary">List a Garage Sale</a>
      </div>
    </div>
  </div>
</div>

<?php } // end if/else elementor ?>

<?php get_footer(); ?>