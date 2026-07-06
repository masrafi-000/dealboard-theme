<?php
/*
Template Name: Dashboard
*/
get_header();

if(!is_user_logged_in()){ wp_redirect(home_url('/sign-in')); exit; }

// Handle delete
if ( isset($_GET['delete']) && isset($_GET['_wpnonce']) ) {
  $del_id = (int)$_GET['delete'];
  if ( wp_verify_nonce($_GET['_wpnonce'], 'delete_listing_'.$del_id) ) {
    $del_post = get_post($del_id);
    if ( $del_post && $del_post->post_author == get_current_user_id() ) {
      wp_delete_post($del_id, true);
      wp_redirect(home_url('/dashboard/?deleted=1'));
      exit;
    }
  }
}

$user = wp_get_current_user();

// ── Auto-activate personal/free listings (7-day window) ──
$free_check = get_posts([
  'post_type'   => 'listing',
  'author'      => $user->ID,
  'post_status' => ['publish','pending','draft'],
  'numberposts' => -1,
  'fields'      => 'ids',
]);
foreach ( $free_check as $fid ) {
  $fplan    = get_post_meta($fid, 'listing_plan', true) ?: 'personal';
  $fstatus  = get_post_meta($fid, 'listing_status', true);
  $fexpires = get_post_meta($fid, 'listing_expires', true);
  if ( $fplan === 'personal' || $fplan === '' ) {
    if ( empty($fstatus) || $fstatus === '' ) {
      update_post_meta($fid, 'listing_status', 'active');
      update_post_meta($fid, 'listing_expires', date('Y-m-d', strtotime('+7 days')));
      wp_update_post(['ID' => $fid, 'post_status' => 'publish']);
    } elseif ( $fstatus === 'active' && empty($fexpires) ) {
      update_post_meta($fid, 'listing_expires', date('Y-m-d', strtotime('+7 days')));
    } elseif ( $fstatus === 'active' && $fexpires < date('Y-m-d') ) {
      update_post_meta($fid, 'listing_status', 'expired');
      wp_update_post(['ID' => $fid, 'post_status' => 'draft']);
    }
  }
}

// ── Pagination setup ──
$per_page   = 10;
$cur_page   = max(1, (int)($_GET['listing_page'] ?? 1));

// Lightweight query for stat counts (all listings, no pagination)
$all_ids = get_posts([
  'post_type'   => ['listing','garage_sale'],
  'author'      => $user->ID,
  'post_status' => ['publish','pending','private','draft'],
  'numberposts' => -1,
  'fields'      => 'ids',
]);
$active_count = 0; $pending_count = 0; $expired_count = 0; $total_count = count($all_ids);
foreach($all_ids as $_fid){
  $_s  = get_post_meta($_fid,'listing_status',true);
  $_ps = get_post_status($_fid);
  if($_s==='active') $active_count++;
  if($_ps==='pending' || $_s==='pending_payment') $pending_count++;
  if($_s==='expired') $expired_count++;
}

// Paginated main query
$my_listings = new WP_Query([
  'post_type'      => ['listing','garage_sale'],
  'author'         => $user->ID,
  'post_status'    => ['publish','pending','private','draft'],
  'posts_per_page' => $per_page,
  'paged'          => $cur_page,
]);
$total_pages = $my_listings->max_num_pages;

$avatar_url = get_avatar_url($user->ID, ['size'=>80]);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }

/* ── Base ─────────────────────────────────────────────────── */
.db-page {
  font-family: 'Inter', system-ui, sans-serif;
  background: #F1F5F9;
  min-height: 100vh;
  padding: 0 0 60px;
}

/* ── Hero header ──────────────────────────────────────────── */
.db-hero {
  background: linear-gradient(135deg, #C8102E 0%, #7B000F 100%);
  padding: 36px 20px 80px;
  position: relative;
  overflow: hidden;
}
.db-hero::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 280px; height: 280px;
  background: rgba(255,255,255,.06);
  border-radius: 50%;
}
.db-hero::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -40px;
  width: 220px; height: 220px;
  background: rgba(255,255,255,.04);
  border-radius: 50%;
}
.db-hero-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  position: relative;
  z-index: 1;
}
.db-hero-user {
  display: flex;
  align-items: center;
  gap: 16px;
}
.db-hero-avatar {
  width: 56px; height: 56px;
  border-radius: 50%;
  border: 3px solid rgba(255,255,255,.35);
  object-fit: cover;
  flex-shrink: 0;
}
.db-hero-avatar-ph {
  width: 56px; height: 56px;
  border-radius: 50%;
  border: 3px solid rgba(255,255,255,.35);
  background: rgba(255,255,255,.2);
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; flex-shrink: 0;
}
.db-hero-name {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
}
.db-hero-email {
  font-size: 13px;
  color: rgba(255,255,255,.7);
  margin-top: 2px;
}
.db-btn-new {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 11px 22px;
  background: #fff;
  color: #C8102E;
  font-size: 14px;
  font-weight: 700;
  border-radius: 10px;
  text-decoration: none;
  transition: all .2s;
  white-space: nowrap;
  box-shadow: 0 4px 14px rgba(0,0,0,.15);
}
.db-btn-new:hover { background: #FFF5F5; color: #A50E26; transform: translateY(-1px); }

/* ── Content area ─────────────────────────────────────────── */
.db-body {
  max-width: 1100px;
  margin: -52px auto 0;
  padding: 0 16px;
  position: relative;
  z-index: 2;
}

/* ── Alerts ───────────────────────────────────────────────── */
.db-alert {
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 16px;
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  animation: dbSlideIn .3s ease;
}
@keyframes dbSlideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.db-alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
.db-alert-warn    { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
.db-alert-error   { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }
.db-alert-info    { background: #DBEAFE; color: #1D4ED8; border: 1px solid #93C5FD; }

/* ── Stats ────────────────────────────────────────────────── */
.db-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
@media(max-width: 640px) { .db-stats { grid-template-columns: repeat(2, 1fr); } }
.db-stat {
  background: #fff;
  border-radius: 14px;
  padding: 20px 16px;
  text-align: center;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  transition: transform .2s, box-shadow .2s;
}
.db-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.db-stat-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
  margin: 0 auto 10px;
}
.db-stat-val { font-size: 30px; font-weight: 800; line-height: 1; }
.db-stat-lbl { font-size: 11px; font-weight: 600; color: #9CA3AF; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }

/* ── Card (listings) ──────────────────────────────────────── */
.db-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
  overflow: hidden;
  margin-bottom: 20px;
}
.db-card-head {
  padding: 18px 22px;
  border-bottom: 1px solid #F3F4F6;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.db-card-head h2 { font-size: 16px; font-weight: 700; color: #111; margin: 0; }
.db-count-badge {
  background: #F3F4F6;
  color: #6B7280;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 100px;
}

/* ── Desktop table ────────────────────────────────────────── */
.db-table-wrap { overflow-x: auto; }
table.db-table { width: 100%; border-collapse: collapse; }
table.db-table th {
  padding: 10px 14px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  color: #9CA3AF;
  text-transform: uppercase;
  letter-spacing: .6px;
  background: #FAFAFA;
  border-bottom: 1px solid #F3F4F6;
}
table.db-table td {
  padding: 14px 14px;
  border-bottom: 1px solid #F9FAFB;
  font-size: 13px;
  color: #374151;
  vertical-align: middle;
}
table.db-table tr:last-child td { border-bottom: none; }
table.db-table tbody tr { transition: background .15s; }
table.db-table tbody tr:hover td { background: #FAFAFA; }

.db-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.db-thumb-ph {
  width: 44px; height: 44px;
  border-radius: 8px;
  background: linear-gradient(135deg,#FEE2E2,#FECACA);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.db-listing-title {
  font-weight: 600; color: #111; text-decoration: none;
  display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical;
  overflow: hidden; max-width: 180px;
  transition: color .15s;
}
.db-listing-title:hover { color: #C8102E; }

.db-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 700;
  padding: 3px 10px; border-radius: 100px;
  white-space: nowrap;
}
.db-plan-chip {
  display: inline-block;
  font-size: 10px; font-weight: 600;
  padding: 2px 7px; border-radius: 6px;
  margin-top: 4px;
}

/* Action buttons in table */
.db-acts { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.db-act {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 600;
  padding: 5px 10px; border-radius: 7px;
  text-decoration: none; transition: all .15s; white-space: nowrap;
}
.db-act-edit  { background: #FFF5F5; color: #C8102E; }
.db-act-view  { background: #F9FAFB; color: #374151; }
.db-act-del   { background: #FFF5F5; color: #EF4444; cursor: pointer; border: none; font-family: inherit; }
.db-act-edit:hover  { background: #C8102E; color: #fff; }
.db-act-view:hover  { background: #E5E7EB; color: #111; }
.db-act-del:hover   { background: #EF4444; color: #fff; }

/* Pay/subscription controls */
.db-sub-wrap { margin-top: 8px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.db-pay-btn {
  display: inline-flex; align-items: center; gap: 5px;
  background: linear-gradient(135deg,#C8102E,#9B000E);
  color: #fff; font-weight: 700; font-size: 11px;
  padding: 6px 12px; border-radius: 7px;
  text-decoration: none; transition: all .2s;
  box-shadow: 0 2px 8px rgba(200,16,46,.3);
}
.db-pay-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(200,16,46,.4); color: #fff; }
.db-autopay-on  { color: #059669; font-weight: 700; font-size: 12px; }
.db-autopay-off { color: #D97706; font-weight: 700; font-size: 12px; }
.db-sub-link-off { font-size: 12px; color: #DC2626; font-weight: 600; text-decoration: none; }
.db-sub-link-on  { font-size: 12px; color: #059669; font-weight: 600; text-decoration: none; }
.db-sub-link-off:hover { text-decoration: underline; }
.db-sub-link-on:hover  { text-decoration: underline; }

/* ── Mobile listing cards ─────────────────────────────────── */
.db-mobile-cards { display: none; }
.db-m-card {
  padding: 16px;
  border-bottom: 1px solid #F3F4F6;
}
.db-m-card:last-child { border-bottom: none; }
.db-m-row1 { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.db-m-info { flex: 1; min-width: 0; }
.db-m-title {
  font-size: 14px; font-weight: 700; color: #111;
  text-decoration: none; display: block; margin-bottom: 4px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.db-m-title:hover { color: #C8102E; }
.db-m-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 6px; }
.db-m-price { font-size: 13px; font-weight: 700; color: #C8102E; }
.db-m-cat   { font-size: 11px; color: #9CA3AF; }
.db-m-row2  { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.db-m-expires { font-size: 11px; color: #9CA3AF; }
.db-m-acts  { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Responsive breakpoints ───────────────────────────────── */
@media (max-width: 768px) {
  .db-hero { padding: 28px 16px 70px; }
  .db-hero-name { font-size: 18px; }
  .db-body { padding: 0 12px; margin-top: -46px; }
  .db-table-wrap table { display: none; }
  .db-mobile-cards { display: block; }
}
@media (max-width: 480px) {
  .db-hero-avatar, .db-hero-avatar-ph { width: 44px; height: 44px; }
  .db-btn-new { padding: 10px 16px; font-size: 13px; }
  .db-stat-val { font-size: 24px; }
}

/* ── Empty state ──────────────────────────────────────────── */
.db-empty {
  padding: 56px 24px;
  text-align: center;
  color: #9CA3AF;
}
.db-empty-icon { font-size: 48px; margin-bottom: 12px; }
.db-empty p { font-size: 15px; margin: 0; }
.db-empty a { color: #C8102E; font-weight: 700; text-decoration: none; }

/* ── Pagination ───────────────────────────────────────────── */
.db-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 16px 22px;
  border-top: 1px solid #F3F4F6;
  background: #FAFAFA;
}
.db-pag-info {
  font-size: 13px;
  color: #9CA3AF;
  font-weight: 500;
}
.db-pag-info strong { color: #374151; }
.db-pag-pages {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}
.db-pag-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: all .15s;
  color: #374151;
  background: #fff;
  border: 1px solid #E5E7EB;
}
.db-pag-btn:hover {
  background: #FEF2F2;
  border-color: #C8102E;
  color: #C8102E;
}
.db-pag-btn.active {
  background: #C8102E;
  border-color: #C8102E;
  color: #fff;
  pointer-events: none;
}
.db-pag-btn.disabled {
  opacity: .4;
  pointer-events: none;
  cursor: default;
}
.db-pag-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  color: #9CA3AF;
  font-size: 13px;
}
@media(max-width:480px){
  .db-pagination { flex-direction: column; align-items: flex-start; padding: 14px 16px; }
  .db-pag-btn { min-width: 32px; height: 32px; font-size: 12px; }
}
</style>

<div class="db-page">

  <!-- ── Hero ──────────────────────────────────────────────── -->
  <div class="db-hero">
    <div class="db-hero-inner">
      <div class="db-hero-user">
        <?php if($avatar_url): ?>
        <img src="<?php echo esc_url($avatar_url); ?>" class="db-hero-avatar" alt="">
        <?php else: ?>
        <div class="db-hero-avatar-ph">👤</div>
        <?php endif; ?>
        <div>
          <div class="db-hero-name"><?php echo esc_html($user->display_name); ?></div>
          <div class="db-hero-email"><?php echo esc_html($user->user_email); ?></div>
        </div>
      </div>
      <a href="<?php echo esc_url(home_url('/post-ad')); ?>" class="db-btn-new">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Post New Ad
      </a>
    </div>
  </div>

  <!-- ── Body ──────────────────────────────────────────────── -->
  <div class="db-body">

    <!-- Alerts -->
    <?php if(isset($_GET['payment_success'])): ?>
    <div class="db-alert db-alert-success">
      <span style="font-size:18px">✅</span>
      <div><strong>Payment successful!</strong> Your business listing is now live for 30 days. It will renew automatically for $2 unless you turn off auto-payment below.</div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['payment_pending'])): ?>
    <div class="db-alert db-alert-warn">
      <span style="font-size:18px">⏳</span>
      <div><strong>Payment processing.</strong> Your listing will activate automatically within a few minutes. Refresh this page to check.</div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['payment_error'])): ?>
    <div class="db-alert db-alert-error">
      <span style="font-size:18px">❌</span>
      <div><?php echo esc_html(wp_unslash($_GET['payment_error'])); ?></div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['dealboard_payment']) && $_GET['dealboard_payment']==='cancel'): ?>
    <div class="db-alert db-alert-warn">
      <span style="font-size:18px">↩️</span>
      <div>Checkout was cancelled. Your listing is saved — click <strong>Post Business Listing</strong> below to complete payment anytime.</div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['autopay'])): ?>
    <div class="db-alert db-alert-info">
      <span style="font-size:18px"><?php echo $_GET['autopay']==='off' ? '⏸' : '🔁'; ?></span>
      <div>
        <?php if($_GET['autopay']==='off'): ?>
        Auto-payment turned off. Your ad stays visible until the current period ends, then it will stop showing.
        <?php else: ?>
        Auto-payment turned back on. Your ad will keep renewing for $2 every 30 days.
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?>
    <div class="db-alert db-alert-success"><span style="font-size:18px">🗑</span> <div>Listing deleted successfully.</div></div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
    <div class="db-alert db-alert-info"><span style="font-size:18px">✏️</span> <div>Listing updated successfully.</div></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="db-stats">
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#FEF2F2">📋</div>
        <div class="db-stat-val" style="color:#C8102E"><?php echo $my_listings->found_posts; ?></div>
        <div class="db-stat-lbl">Total</div>
      </div>
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#D1FAE5">✅</div>
        <div class="db-stat-val" style="color:#059669"><?php echo $active_count; ?></div>
        <div class="db-stat-lbl">Active</div>
      </div>
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#FEF3C7">⏳</div>
        <div class="db-stat-val" style="color:#D97706"><?php echo $pending_count; ?></div>
        <div class="db-stat-lbl">Pending</div>
      </div>
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#FEE2E2">⛔</div>
        <div class="db-stat-val" style="color:#DC2626"><?php echo $expired_count; ?></div>
        <div class="db-stat-lbl">Expired</div>
      </div>
    </div>

    <!-- Listings card -->
    <div class="db-card">
      <div class="db-card-head">
        <h2>My Listings</h2>
        <span class="db-count-badge"><?php echo $my_listings->found_posts; ?></span>
      </div>

      <?php
      $my_listings->rewind_posts();
      if($my_listings->have_posts()):
      ?>

      <!-- ── Desktop table ──────────────────────────────────── -->
      <div class="db-table-wrap">
        <table class="db-table">
          <thead>
            <tr>
              <th>Listing</th>
              <th>Price</th>
              <th>Category</th>
              <th>Status</th>
              <th>Views</th>
              <th>Expires</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $my_listings->rewind_posts();
          while($my_listings->have_posts()): $my_listings->the_post();
            $pid           = get_the_ID();
            $post_type_row = get_post_type($pid);
            $ps            = get_post_status($pid);
            $plan          = get_post_meta($pid,'listing_plan',true) ?: 'personal';

            if ($post_type_row === 'garage_sale') {
              $price = get_post_meta($pid,'sale_currency',true) ?: 'Cash';
              $cur = ''; $status = 'active';
              $views   = get_post_meta($pid,'listing_views',true) ?: 0;
              $expires = get_post_meta($pid,'sale_date_start',true);
              $cat_name = '🏡 Garage Sale';
            } else {
              $price   = get_post_meta($pid,'listing_price',true);
              $cur     = get_post_meta($pid,'listing_currency',true) ?: 'USD';
              $status  = get_post_meta($pid,'listing_status',true) ?: 'active';
              $views   = get_post_meta($pid,'listing_views',true) ?: 0;
              $expires = get_post_meta($pid,'listing_expires',true);
              $cats    = wp_get_post_terms($pid,'listing_category');
              $cat_name= !empty($cats)&&!is_wp_error($cats) ? $cats[0]->name : '—';
            }

            // Status badge
            if ($ps==='pending') {
              $slabel='Pending Review'; $sbg='#FEF3C7'; $stxt='#92400E';
            } elseif ($status==='pending_payment') {
              $slabel='Awaiting Payment'; $sbg='#FEE2E2'; $stxt='#DC2626';
            } elseif ($status==='active') {
              $slabel='Active'; $sbg='#D1FAE5'; $stxt='#059669';
            } elseif ($status==='expired') {
              $slabel='Expired'; $sbg='#FEE2E2'; $stxt='#DC2626';
            } elseif ($status==='sold') {
              $slabel='Sold'; $sbg='#F3F4F6'; $stxt='#6B7280';
            } else {
              $slabel=ucfirst($status ?: 'Draft'); $sbg='#F3F4F6'; $stxt='#6B7280';
            }

            $plan_label = $plan==='business' ? ['🏢 Business','#FEF2F2','#C8102E'] : ['👤 Free','#F0FDF4','#059669'];
            $del_url = wp_nonce_url(home_url('/dashboard/?delete='.$pid),'delete_listing_'.$pid);

            $listing_type_here    = get_post_meta($pid,'listing_type',true);
            $listing_cats_here    = wp_get_post_terms($pid,'listing_category');
            $listing_cat_slug_here = !empty($listing_cats_here)&&!is_wp_error($listing_cats_here) ? $listing_cats_here[0]->slug : '';
            if ($post_type_row==='garage_sale') { $edit_url=home_url('/list-garage-sale/?edit='.$pid); }
            elseif ($listing_type_here==='exchange'||$listing_cat_slug_here==='exchange') { $edit_url=home_url('/exchange/?edit='.$pid); }
            else { $edit_url=home_url('/post-ad/?edit='.$pid); }
          ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <?php if(has_post_thumbnail()): ?>
                  <img src="<?php echo esc_url(get_the_post_thumbnail_url(null,'thumbnail')); ?>" class="db-thumb">
                  <?php else: ?><div class="db-thumb-ph">📦</div><?php endif; ?>
                  <a href="<?php the_permalink(); ?>" class="db-listing-title"><?php the_title(); ?></a>
                </div>
              </td>
              <td style="font-weight:700;color:#C8102E;white-space:nowrap">
                <?php
                if($post_type_row==='garage_sale') echo '🏡 '.esc_html($price);
                else echo $price ? esc_html('$'.$price.' '.$cur) : '—';
                ?>
              </td>
              <td style="color:#6B7280;white-space:nowrap"><?php echo esc_html($cat_name); ?></td>
              <td>
                <span class="db-badge" style="background:<?php echo $sbg; ?>;color:<?php echo $stxt; ?>">
                  <?php echo esc_html($slabel); ?>
                </span>
                <?php if($post_type_row==='listing'): ?>
                <div><span class="db-plan-chip" style="background:<?php echo $plan_label[1]; ?>;color:<?php echo $plan_label[2]; ?>"><?php echo esc_html($plan_label[0]); ?></span></div>
                <?php endif; ?>
              </td>
              <td style="color:#6B7280;text-align:center"><?php echo esc_html($views); ?></td>
              <td style="color:#9CA3AF;white-space:nowrap;font-size:12px">
                <?php echo $expires ? esc_html(date('M j, Y',strtotime($expires))) : '—'; ?>
              </td>
              <td>
                <div class="db-acts">
                  <a href="<?php echo esc_url($edit_url); ?>" class="db-act db-act-edit">✏️ Edit</a>
                  <a href="<?php the_permalink(); ?>" class="db-act db-act-view">👁 View</a>
                  <a href="<?php echo esc_url($del_url); ?>" class="db-act db-act-del"
                     onclick="return confirm('Delete this listing? This cannot be undone.')">🗑</a>
                </div>
                <?php if($post_type_row==='listing' && $plan==='business'):
                  $sub_id     = get_post_meta($pid,'listing_stripe_subscription',true);
                  $autopay    = get_post_meta($pid,'listing_autopay',true);
                  $pay_url    = wp_nonce_url(home_url('/?dealboard_pay=1&listing='.$pid),'db_pay_'.$pid);
                  $cancel_url = wp_nonce_url(home_url('/?dealboard_sub=cancel&listing='.$pid),'db_sub_'.$pid);
                  $resume_url = wp_nonce_url(home_url('/?dealboard_sub=resume&listing='.$pid),'db_sub_'.$pid);
                ?>
                <div class="db-sub-wrap">
                  <?php if($status==='active' && $sub_id): ?>
                    <?php if($autopay==='1'): ?>
                      <span class="db-autopay-on">🔁 Auto-pay ON</span>
                      <a href="<?php echo esc_url($cancel_url); ?>" class="db-sub-link-off"
                         onclick="return confirm('Turn off auto-payment? Your ad stays visible until the current period ends, then stops showing.')">Turn off</a>
                    <?php else: ?>
                      <span class="db-autopay-off">⏸ Auto-pay OFF</span>
                      <a href="<?php echo esc_url($resume_url); ?>" class="db-sub-link-on">Turn on</a>
                    <?php endif; ?>
                  <?php elseif($status==='active' && !$sub_id): ?>
                    <span style="font-size:11px;color:#D97706;font-weight:600">⚠️ No auto-renewal<?php echo $expires ? ' (exp. '.esc_html(date('M j',strtotime($expires))).')' : ''; ?></span>
                    <a href="<?php echo esc_url($pay_url); ?>" class="db-pay-btn">$2/month renewal</a>
                  <?php else: ?>
                    <?php $bl = ($status==='expired') ? '🔄 Reactivate — $2/month' : '💳 Post Business Listing — $2/month'; ?>
                    <a href="<?php echo esc_url($pay_url); ?>" class="db-pay-btn"><?php echo $bl; ?></a>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- ── Mobile cards ────────────────────────────────────── -->
      <div class="db-mobile-cards">
        <?php
        $my_listings->rewind_posts();
        while($my_listings->have_posts()): $my_listings->the_post();
          $pid           = get_the_ID();
          $post_type_row = get_post_type($pid);
          $ps            = get_post_status($pid);
          $plan          = get_post_meta($pid,'listing_plan',true) ?: 'personal';

          if ($post_type_row === 'garage_sale') {
            $price = get_post_meta($pid,'sale_currency',true) ?: 'Cash';
            $cur = ''; $status = 'active';
            $views   = get_post_meta($pid,'listing_views',true) ?: 0;
            $expires = get_post_meta($pid,'sale_date_start',true);
            $cat_name = '🏡 Garage Sale';
          } else {
            $price   = get_post_meta($pid,'listing_price',true);
            $cur     = get_post_meta($pid,'listing_currency',true) ?: 'USD';
            $status  = get_post_meta($pid,'listing_status',true) ?: 'active';
            $views   = get_post_meta($pid,'listing_views',true) ?: 0;
            $expires = get_post_meta($pid,'listing_expires',true);
            $cats    = wp_get_post_terms($pid,'listing_category');
            $cat_name= !empty($cats)&&!is_wp_error($cats) ? $cats[0]->name : '—';
          }
          if ($ps==='pending') { $slabel='Pending Review'; $sbg='#FEF3C7'; $stxt='#92400E'; }
          elseif ($status==='pending_payment') { $slabel='Awaiting Payment'; $sbg='#FEE2E2'; $stxt='#DC2626'; }
          elseif ($status==='active') { $slabel='Active'; $sbg='#D1FAE5'; $stxt='#059669'; }
          elseif ($status==='expired') { $slabel='Expired'; $sbg='#FEE2E2'; $stxt='#DC2626'; }
          elseif ($status==='sold') { $slabel='Sold'; $sbg='#F3F4F6'; $stxt='#6B7280'; }
          else { $slabel=ucfirst($status?:'Draft'); $sbg='#F3F4F6'; $stxt='#6B7280'; }

          $del_url = wp_nonce_url(home_url('/dashboard/?delete='.$pid),'delete_listing_'.$pid);
          $listing_type_here    = get_post_meta($pid,'listing_type',true);
          $listing_cats_here    = wp_get_post_terms($pid,'listing_category');
          $listing_cat_slug_here = !empty($listing_cats_here)&&!is_wp_error($listing_cats_here) ? $listing_cats_here[0]->slug : '';
          if ($post_type_row==='garage_sale') $edit_url=home_url('/list-garage-sale/?edit='.$pid);
          elseif ($listing_type_here==='exchange'||$listing_cat_slug_here==='exchange') $edit_url=home_url('/exchange/?edit='.$pid);
          else $edit_url=home_url('/post-ad/?edit='.$pid);
        ?>
        <div class="db-m-card">
          <div class="db-m-row1">
            <?php if(has_post_thumbnail()): ?>
            <img src="<?php echo esc_url(get_the_post_thumbnail_url(null,'thumbnail')); ?>" class="db-thumb">
            <?php else: ?><div class="db-thumb-ph">📦</div><?php endif; ?>
            <div class="db-m-info">
              <a href="<?php the_permalink(); ?>" class="db-m-title"><?php the_title(); ?></a>
              <div class="db-m-meta">
                <span class="db-m-price"><?php
                  if($post_type_row==='garage_sale') echo '🏡 '.esc_html($price);
                  else echo $price ? esc_html('$'.$price.' '.$cur) : '—';
                ?></span>
                <span class="db-m-cat"><?php echo esc_html($cat_name); ?></span>
              </div>
              <span class="db-badge" style="background:<?php echo $sbg;?>;color:<?php echo $stxt;?>"><?php echo esc_html($slabel);?></span>
            </div>
          </div>
          <div class="db-m-row2">
            <span class="db-m-expires">
              <?php if($expires) echo '📅 '.esc_html(date('M j, Y',strtotime($expires))); else echo '—'; ?>
              &nbsp;👁 <?php echo esc_html($views); ?>
            </span>
            <div class="db-m-acts">
              <a href="<?php echo esc_url($edit_url); ?>" class="db-act db-act-edit">✏️ Edit</a>
              <a href="<?php the_permalink(); ?>" class="db-act db-act-view">👁</a>
              <a href="<?php echo esc_url($del_url); ?>" class="db-act db-act-del"
                 onclick="return confirm('Delete this listing? This cannot be undone.')">🗑</a>
            </div>
          </div>
          <?php if($post_type_row==='listing' && $plan==='business'):
            $sub_id     = get_post_meta($pid,'listing_stripe_subscription',true);
            $autopay    = get_post_meta($pid,'listing_autopay',true);
            $pay_url    = wp_nonce_url(home_url('/?dealboard_pay=1&listing='.$pid),'db_pay_'.$pid);
            $cancel_url = wp_nonce_url(home_url('/?dealboard_sub=cancel&listing='.$pid),'db_sub_'.$pid);
            $resume_url = wp_nonce_url(home_url('/?dealboard_sub=resume&listing='.$pid),'db_sub_'.$pid);
          ?>
          <div class="db-sub-wrap" style="margin-top:10px">
            <?php if($status==='active' && $sub_id): ?>
              <?php if($autopay==='1'): ?>
                <span class="db-autopay-on">🔁 Auto-pay ON</span>
                <a href="<?php echo esc_url($cancel_url); ?>" class="db-sub-link-off"
                   onclick="return confirm('Turn off auto-payment? Your ad stays visible until the current period ends, then stops showing.')">Turn off</a>
              <?php else: ?>
                <span class="db-autopay-off">⏸ Auto-pay OFF</span>
                <a href="<?php echo esc_url($resume_url); ?>" class="db-sub-link-on">Turn on</a>
              <?php endif; ?>
            <?php elseif($status==='active' && !$sub_id): ?>
              <span style="font-size:11px;color:#D97706;font-weight:600">⚠️ No auto-renewal</span>
              <a href="<?php echo esc_url($pay_url); ?>" class="db-pay-btn">Set up $2/month</a>
            <?php else: ?>
              <?php $bl=($status==='expired')?'🔄 Reactivate — $2/month':'💳 Post Business Listing — $2/month'; ?>
              <a href="<?php echo esc_url($pay_url); ?>" class="db-pay-btn"><?php echo $bl; ?></a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>

      <?php else: ?>
      <div class="db-empty">
        <div class="db-empty-icon">📋</div>
        <p>No listings yet. <a href="<?php echo home_url('/post-ad'); ?>">Post your first ad!</a></p>
      </div>
      <?php endif; ?>

      <?php if($total_pages > 1): ?>
      <!-- ── Pagination bar ───────────────────────────────────────── -->
      <?php
        $from    = (($cur_page - 1) * $per_page) + 1;
        $to      = min($cur_page * $per_page, $total_count);
        $base_url = remove_query_arg('listing_page');

        // Build page URL helper
        function db_pag_url($page, $base){
          return esc_url( add_query_arg('listing_page', $page, $base) );
        }

        // Determine which page numbers to show
        $pages_to_show = [];
        $pages_to_show[] = 1;
        for($i = max(2, $cur_page-2); $i <= min($total_pages-1, $cur_page+2); $i++){
          $pages_to_show[] = $i;
        }
        if($total_pages > 1) $pages_to_show[] = $total_pages;
        $pages_to_show = array_unique($pages_to_show);
        sort($pages_to_show);
      ?>
      <div class="db-pagination">
        <div class="db-pag-info">
          Showing <strong><?php echo $from; ?>–<?php echo $to; ?></strong> of <strong><?php echo $total_count; ?></strong> listings
        </div>
        <div class="db-pag-pages">
          <!-- Prev -->
          <a href="<?php echo db_pag_url(max(1,$cur_page-1),$base_url); ?>"
             class="db-pag-btn<?php echo $cur_page<=1 ? ' disabled' : ''; ?>">
            ‹
          </a>

          <?php
          $prev_p = null;
          foreach($pages_to_show as $p):
            if($prev_p !== null && $p - $prev_p > 1):
              echo '<span class="db-pag-ellipsis">…</span>';
            endif;
          ?>
          <a href="<?php echo db_pag_url($p,$base_url); ?>"
             class="db-pag-btn<?php echo $p===$cur_page ? ' active' : ''; ?>">
            <?php echo $p; ?>
          </a>
          <?php $prev_p=$p; endforeach; ?>

          <!-- Next -->
          <a href="<?php echo db_pag_url(min($total_pages,$cur_page+1),$base_url); ?>"
             class="db-pag-btn<?php echo $cur_page>=$total_pages ? ' disabled' : ''; ?>">
            ›
          </a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.db-card -->

  </div><!-- /.db-body -->
</div><!-- /.db-page -->

<?php get_footer(); ?>