<?php get_header(); ?>

<?php
$queried    = get_queried_object();
$is_cat     = ($queried instanceof WP_Term);
$page_title = $is_cat ? $queried->name : 'All Listings';
$found      = $wp_query->found_posts ?? 0;

// Active category info
$active_cat_id    = 0;
$active_parent_id = 0;
$is_subcategory   = false;

if ($is_cat) {
    $active_cat_id = $queried->term_id;
    if ($queried->parent > 0) {
        $is_subcategory   = true;
        $active_parent_id = $queried->parent;
    } else {
        $active_parent_id = $queried->term_id;
    }
}

// All parent categories
$all_cats = get_terms([
    'taxonomy'   => 'listing_category',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

// Subcategories of active parent
$subcats = [];
if ($active_parent_id) {
    $subcats = get_terms([
        'taxonomy'   => 'listing_category',
        'hide_empty' => false,
        'parent'     => $active_parent_id,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
}
?>

<style>
.browse-page { padding: 32px 0 60px; }
.browse-title { font-size: 22px; font-weight: 700; color: #111827; }
.listings-count { font-size: 14px; color: #6B7280; margin-top: 4px; }
.browse-top { margin-bottom: 20px; }

.browse-search-row {
    display: flex; gap: 10px; margin-bottom: 20px; align-items: center;
}
.browse-search-input {
    flex: 1; padding: 10px 16px 10px 40px;
    border: 1.5px solid #E5E7EB; border-radius: 8px;
    font-size: 14px; outline: none; font-family: inherit;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%239CA3AF' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center;
    transition: border-color 0.15s;
}
.browse-search-input:focus { border-color: #10B981; }
.filter-btn {
    display: flex; align-items: center; gap: 6px; padding: 10px 16px;
    border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 13px;
    font-weight: 500; color: #374151; background: white; cursor: pointer;
    white-space: nowrap; text-decoration: none; transition: all 0.15s; font-family: inherit;
}
.filter-btn:hover { border-color: #10B981; color: #10B981; }

/* ===== PARENT TABS ===== */
.cat-tabs-wrapper { margin-bottom: 0; }
.cat-tabs-outer { display: flex; align-items: flex-start; gap: 8px; }
.cat-tab-fixed { flex-shrink: 0; }
.cat-tabs-scroll {
    display: flex; align-items: center; gap: 8px;
    overflow-x: auto; scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin; scrollbar-color: #D1D5DB #F3F4F6;
    padding-bottom: 10px; flex: 1; min-width: 0;
}
.cat-tabs-scroll::-webkit-scrollbar { display: block; height: 3px; }
.cat-tabs-scroll::-webkit-scrollbar-track { background: #F3F4F6; border-radius: 9999px; }
.cat-tabs-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 9999px; }
.cat-tabs-scroll::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }

.cat-tab {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
    border: 1.5px solid #E5E7EB; border-radius: 9999px; font-size: 13px;
    font-weight: 500; color: #374151; white-space: nowrap; cursor: pointer;
    background: white; text-decoration: none; transition: all 0.15s; flex-shrink: 0;
}
.cat-tab:hover { border-color: #10B981; color: #10B981; }
.cat-tab.active { background: #10B981; border-color: #10B981; color: white; }

/* ===== SUBCATEGORY ROW ===== */
.subcat-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 14px 0 16px;
    border-bottom: 1px solid #F3F4F6;
    margin-bottom: 20px;
    animation: fadeDown 0.2s ease;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* "All in [Category]" */
.subcat-all {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border: 1.5px solid #10B981;
    border-radius: 9999px; font-size: 12px; font-weight: 600;
    color: #10B981; background: white; text-decoration: none;
    white-space: nowrap; transition: all 0.15s; flex-shrink: 0;
}
.subcat-all:hover, .subcat-all.active { background: #10B981; color: white; }

/* Subcategory pills */
.subcat-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border: 1.5px solid #E5E7EB;
    border-radius: 9999px; font-size: 12px; font-weight: 500;
    color: #374151; background: white; text-decoration: none;
    white-space: nowrap; transition: all 0.15s; flex-shrink: 0;
}
.subcat-pill:hover { border-color: #10B981; color: #10B981; }
.subcat-pill.active {
    background: #ECFDF5; border-color: #10B981;
    color: #065F46; font-weight: 600;
}
</style>

<div class="container">
  <div class="browse-page">

    <div class="browse-top">
      <h1 class="browse-title"><?php echo esc_html($page_title); ?></h1>
      <p class="listings-count"><?php echo esc_html($found); ?> listing<?php echo $found !== 1 ? 's' : ''; ?> found</p>
    </div>

    <form method="GET" class="browse-search-row">
      <input type="text" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
        placeholder="Search listings by title..." class="browse-search-input">
      <button type="submit" class="filter-btn">Search</button>
      <a href="#" class="filter-btn">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
        </svg>
        Filters
      </a>
    </form>

    <!-- ===== PARENT CATEGORY TABS ===== -->
    <div class="cat-tabs-wrapper">
      <div class="cat-tabs-outer">
        <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>"
           class="cat-tab cat-tab-fixed <?php echo !$is_cat ? 'active' : ''; ?>">
          All Categories
        </a>
        <div class="cat-tabs-scroll" id="cat-tabs-scroll">
          <?php if (!is_wp_error($all_cats) && !empty($all_cats)):
            foreach ($all_cats as $cat):
              $cat_link  = get_term_link($cat);
              if (is_wp_error($cat_link)) continue;
              $is_active = $is_cat && (
                $queried->term_id === $cat->term_id ||
                ($is_subcategory && $active_parent_id === $cat->term_id)
              );
              $emoji = dealboard_category_icon($cat->slug);
          ?>
          <a href="<?php echo esc_url($cat_link); ?>"
             class="cat-tab <?php echo $is_active ? 'active' : ''; ?>">
            <?php echo $emoji; ?> <?php echo esc_html($cat->name); ?>
          </a>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ===== SUBCATEGORY ROW ===== -->
    <?php if ($is_cat && !empty($subcats) && !is_wp_error($subcats)): ?>
    <div class="subcat-row">
      <?php
      // Parent term link
      $parent_term = $is_subcategory ? get_term($active_parent_id, 'listing_category') : $queried;
      $parent_link = !is_wp_error($parent_term) ? get_term_link($parent_term) : '#';
      ?>
      <a href="<?php echo esc_url($parent_link); ?>"
         class="subcat-all <?php echo !$is_subcategory ? 'active' : ''; ?>">
        All <?php echo esc_html($parent_term->name); ?>
      </a>
      <?php foreach ($subcats as $sub):
        $sub_link   = get_term_link($sub);
        if (is_wp_error($sub_link)) continue;
        $sub_active = ($is_subcategory && $active_cat_id === $sub->term_id);
      ?>
      <a href="<?php echo esc_url($sub_link); ?>"
         class="subcat-pill <?php echo $sub_active ? 'active' : ''; ?>">
        <?php echo esc_html($sub->name); ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="margin-bottom:20px"></div>
    <?php endif; ?>

    <!-- ===== LISTINGS GRID ===== -->
    <div class="listings-grid">
      <?php if (have_posts()):
        while (have_posts()): the_post();
          $price    = get_post_meta(get_the_ID(),'listing_price',true);
          $city     = get_post_meta(get_the_ID(),'listing_city',true);
          $currency = get_post_meta(get_the_ID(),'listing_currency',true) ?: 'USD';
          $views    = get_post_meta(get_the_ID(),'listing_views',true) ?: 0;
          $lc       = wp_get_post_terms(get_the_ID(),'listing_category');
          $cat_name = (!empty($lc) && !is_wp_error($lc)) ? $lc[0]->name : '';
          $author   = get_the_author_meta('display_name');
          $time     = human_time_diff(get_the_time('U'), current_time('timestamp')).' ago';
      ?>
      <a href="<?php the_permalink(); ?>" class="listing-card">
        <div class="listing-image">
          <?php if (has_post_thumbnail()): the_post_thumbnail('medium',['loading'=>'lazy']); else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font-size:40px">📦</div>
          <?php endif; ?>
          <?php if ($price): ?><div class="listing-price"><?php echo esc_html('$'.$price); ?></div><?php endif; ?>
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
      <?php endwhile;
      else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div style="font-size:48px;margin-bottom:16px">🔍</div>
        <h3>No listings found</h3>
        <p>Try a different category or <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" style="color:#10B981">browse all listings</a></p>
      </div>
      <?php endif; ?>
    </div>

    <?php the_posts_pagination(['prev_text'=>'← Previous','next_text'=>'Next →','mid_size'=>2]); ?>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var scroll = document.getElementById('cat-tabs-scroll');
  if (!scroll) return;
  var active = scroll.querySelector('.cat-tab.active');
  if (active) active.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
});
</script>

<?php get_footer(); ?>
