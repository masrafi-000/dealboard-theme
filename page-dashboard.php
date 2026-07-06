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
$my_listings = new WP_Query([
  'post_type'      => ['listing', 'garage_sale'],
  'author'         => $user->ID,
  'post_status' => ['publish','pending','private','draft'],
  'posts_per_page' => 50,
]);

$active_count = 0; $pending_count = 0;
while($my_listings->have_posts()){
  $my_listings->the_post();
  $s = get_post_meta(get_the_ID(),'listing_status',true);
  if($s==='active') $active_count++;
  if(get_post_status()==='pending') $pending_count++;
}
wp_reset_postdata();
?>

<style>
.dash-wrap { background:#F9FAFB; min-height:100vh; padding:32px 0 60px; }
.dash-inner { max-width:1100px; margin:0 auto; padding:0 20px; }
.dash-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px; }
.dash-title { font-size:24px;font-weight:800;color:#111; }
.dash-sub { font-size:14px;color:#6B7280;margin-top:2px; }
.dash-btn-new {
  display:inline-flex;align-items:center;gap:6px;
  padding:10px 20px;background:#C8102E;color:white;
  font-size:14px;font-weight:700;border-radius:8px;text-decoration:none;
  transition:background .15s;
}
.dash-btn-new:hover { background:#A50E26;color:white; }

/* Stats */
.dash-stats { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px; }
@media(max-width:600px){ .dash-stats { grid-template-columns:1fr; } }
.dash-stat { background:white;border:1px solid #E5E7EB;border-radius:12px;padding:20px;text-align:center; }
.dash-stat-icon { font-size:26px;margin-bottom:6px; }
.dash-stat-val { font-size:28px;font-weight:800; }
.dash-stat-lbl { font-size:12px;color:#6B7280;margin-top:3px; }

/* Table */
.dash-table-wrap { background:white;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden; }
.dash-table-head { padding:18px 24px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center; }
.dash-table-head h2 { font-size:16px;font-weight:700;color:#111;margin:0; }
table.dash-table { width:100%;border-collapse:collapse; }
table.dash-table th { padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;background:#F9FAFB; }
table.dash-table td { padding:13px 14px;border-top:1px solid #F3F4F6;font-size:13px;color:#374151; vertical-align:middle; }
table.dash-table tr:hover td { background:#FAFAFA; }

.dash-thumb { width:40px;height:40px;border-radius:6px;object-fit:cover; }
.dash-thumb-ph { width:40px;height:40px;border-radius:6px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;font-size:18px; }
.dash-listing-title { font-weight:600;color:#111;text-decoration:none; }
.dash-listing-title:hover { color:#C8102E; }

.dash-status { font-size:11px;font-weight:700;padding:3px 10px;border-radius:100px; }
.dash-actions { display:flex;gap:10px;align-items:center; }
.dash-act-edit { font-size:12px;font-weight:700;color:#C8102E;text-decoration:none; }
.dash-act-view { font-size:12px;font-weight:600;color:#6B7280;text-decoration:none; }
.dash-act-del { font-size:12px;font-weight:700;color:#EF4444;text-decoration:none;cursor:pointer; }
.dash-act-edit:hover { color:#A50E26; }
.dash-act-del:hover { color:#DC2626; }
.dash-sep { color:#E5E7EB; }
</style>

<div class="dash-wrap">
<div class="dash-inner">

  <div class="dash-header">
    <div>
      <div class="dash-title">My Dashboard</div>
      <div class="dash-sub">Welcome back, <?php echo esc_html($user->display_name); ?></div>
    </div>
    <a href="<?php echo esc_url(home_url('/post-ad')); ?>" class="dash-btn-new">+ Post New Ad</a>
  </div>
<?php if(isset($_GET['payment_success'])): ?>
<div style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-weight:600">
  ✅ Payment successful — your business listing is now live for 30 days. It will renew automatically for $2 unless you turn off auto-payment below.
</div>
<?php endif; ?>
<?php if(isset($_GET['payment_pending'])): ?>
<div style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;border-radius:8px;padding:16px;margin-bottom:20px">
  <strong>💳 Payment required</strong><br>
  Your business listing is saved but not live yet. Use the <strong>Pay $2 &amp; Activate</strong> button next to it below to complete checkout. It goes live the moment payment succeeds.
</div>
<?php endif; ?>
<?php if(isset($_GET['payment_error'])): ?>
<div style="background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-weight:600">
  ❌ <?php echo esc_html(wp_unslash($_GET['payment_error'])); ?>
</div>
<?php endif; ?>
<?php if(isset($_GET['dealboard_payment']) && $_GET['dealboard_payment']==='cancel'): ?>
<div style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;border-radius:8px;padding:14px 16px;margin-bottom:20px">
  Checkout was cancelled. Your listing is saved — you can pay anytime using the <strong>Pay $2 &amp; Activate</strong> button below.
</div>
<?php endif; ?>
<?php if(isset($_GET['autopay'])): ?>
<div style="background:#DBEAFE;color:#1D4ED8;border:1px solid #93C5FD;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-weight:600">
  <?php if($_GET['autopay']==='off'): ?>
  ⏸ Auto-payment turned off. Your ad stays visible until the current 30 days end, then it will stop showing.
  <?php else: ?>
  🔁 Auto-payment turned back on. Your ad will keep renewing for $2 every 30 days.
  <?php endif; ?>
</div>
<?php endif; ?>
  <?php if(isset($_GET['deleted'])): ?>
  <div style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-weight:600">
    ✅ Listing deleted successfully.
  </div>
  <?php endif; ?>

  <?php if(isset($_GET['updated'])): ?>
  <div style="background:#DBEAFE;color:#1D4ED8;border:1px solid #93C5FD;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-weight:600">
    ✅ Listing updated successfully.
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="dash-stats">
    <div class="dash-stat">
      <div class="dash-stat-icon">📋</div>
      <div class="dash-stat-val" style="color:#C8102E"><?php echo $my_listings->found_posts; ?></div>
      <div class="dash-stat-lbl">Total Listings</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-icon">✅</div>
      <div class="dash-stat-val" style="color:#059669"><?php echo $active_count; ?></div>
      <div class="dash-stat-lbl">Active</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-icon">⏳</div>
      <div class="dash-stat-val" style="color:#F97316"><?php echo $pending_count; ?></div>
      <div class="dash-stat-lbl">Pending</div>
    </div>
  </div>

  <!-- Table -->
  <div class="dash-table-wrap">
    <div class="dash-table-head">
      <h2>My Listings</h2>
    </div>
    <div style="overflow-x:auto">
      <table class="dash-table">
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
        if($my_listings->have_posts()):
          while($my_listings->have_posts()): $my_listings->the_post();
            $pid     = get_the_ID();
            $post_type_row = get_post_type($pid);
if ($post_type_row === 'garage_sale') {
  $price    = get_post_meta($pid,'sale_currency',true) ?: 'Cash';
  $cur      = '';
  $status   = 'active';
  $views    = get_post_meta($pid,'listing_views',true) ?: 0;
  $expires  = get_post_meta($pid,'sale_date_start',true);
  $cat_name = '🏡 Garage Sale';
} else {
  $price   = get_post_meta($pid,'listing_price',true);
  $cur     = get_post_meta($pid,'listing_currency',true) ?: 'USD';
  $status  = get_post_meta($pid,'listing_status',true) ?: 'active';
  $views   = get_post_meta($pid,'listing_views',true) ?: 0;
  $expires = get_post_meta($pid,'listing_expires',true);
  $cats    = wp_get_post_terms($pid,'listing_category');
  $cat_name= !empty($cats) && !is_wp_error($cats) ? $cats[0]->name : '—';
}
$plan = get_post_meta($pid,'listing_plan',true) ?: 'personal';
            $ps      = get_post_status();
            $status_show  = $ps==='pending' ? 'Pending' : ucfirst($status);
            $status_colors = ['active'=>['bg'=>'#D1FAE5','txt'=>'#065F46'],'sold'=>['bg'=>'#F3F4F6','txt'=>'#6B7280'],'pending'=>['bg'=>'#FEF3C7','txt'=>'#92400E'],'expired'=>['bg'=>'#FEE2E2','txt'=>'#DC2626']];
            $sc = $ps==='pending' ? ['bg'=>'#FEF3C7','txt'=>'#92400E'] : ($status_colors[$status] ?? ['bg'=>'#F3F4F6','txt'=>'#6B7280']);
            $del_url = wp_nonce_url(home_url('/dashboard/?delete='.$pid), 'delete_listing_'.$pid);
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if(has_post_thumbnail()): ?>
                <img src="<?php echo esc_url(get_the_post_thumbnail_url(null,'thumbnail')); ?>" class="dash-thumb">
                <?php else: ?>
                <div class="dash-thumb-ph">📦</div>
                <?php endif; ?>
                <a href="<?php the_permalink(); ?>" class="dash-listing-title"><?php the_title(); ?></a>
              </div>
            </td>
            <td style="font-weight:700;color:#C8102E">
  <?php
  if($post_type_row === 'garage_sale') {
    echo '🏡 ' . esc_html($price);
  } else {
    echo $price ? esc_html('$'.$price.' '.$cur) : '—';
  }
  ?>
</td>
            <td style="color:#6B7280"><?php echo esc_html($cat_name); ?></td>
            <td>
              <span class="dash-status" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['txt']; ?>">
                <?php echo esc_html($status_show); ?>
              </span>
            </td>
            <td style="color:#6B7280"><?php echo esc_html($views); ?></td>
            <td style="color:#6B7280">
  <?php
  if($post_type_row === 'garage_sale') {
    echo $expires ? esc_html(date('M j, Y',strtotime($expires))) : '—';
  } else {
    echo $expires ? esc_html(date('M j, Y',strtotime($expires))) : '—';
  }
  ?>
</td>
            <td>
              <div class="dash-actions">
                <?php
$post_type_here = get_post_type($pid);
$listing_type_here = get_post_meta($pid,'listing_type',true);
$listing_cats_here = wp_get_post_terms($pid,'listing_category');
$listing_cat_slug_here = !empty($listing_cats_here) && !is_wp_error($listing_cats_here) ? $listing_cats_here[0]->slug : '';

if ($post_type_here === 'garage_sale') {
  $edit_url = home_url('/list-garage-sale/?edit='.$pid);
} elseif ($listing_type_here === 'exchange' || $listing_cat_slug_here === 'exchange') {
  $edit_url = home_url('/exchange/?edit='.$pid);
} else {
  $edit_url = home_url('/post-ad/?edit='.$pid);
}
?>
<a href="<?php echo esc_url($edit_url); ?>" class="dash-act-edit">✏️ Edit</a>
                <span class="dash-sep">|</span>
                <a href="<?php the_permalink(); ?>" class="dash-act-view">👁 View</a>
                <span class="dash-sep">|</span>
                <a href="<?php echo esc_url($del_url); ?>" class="dash-act-del"
                   onclick="return confirm('Delete this listing? This cannot be undone.')">🗑 Delete</a>
              </div>
              <?php
              // ===== Business subscription controls =====
              if ($post_type_row === 'listing' && $plan === 'business') {
                $sub_id  = get_post_meta($pid,'listing_stripe_subscription',true);
                $autopay = get_post_meta($pid,'listing_autopay',true);
                $pay_url    = wp_nonce_url(home_url('/?dealboard_pay=1&listing='.$pid), 'db_pay_'.$pid);
                $cancel_url = wp_nonce_url(home_url('/?dealboard_sub=cancel&listing='.$pid), 'db_sub_'.$pid);
                $resume_url = wp_nonce_url(home_url('/?dealboard_sub=resume&listing='.$pid), 'db_sub_'.$pid);
                echo '<div style="margin-top:8px;font-size:12px">';
                if (empty($sub_id)) {
                  // Not paid yet (pending_payment) or expired without a sub.
                  echo '<a href="'.esc_url($pay_url).'" onclick="if(typeof window.dbOpenPaymentModal === \'function\'){ window.dbOpenPaymentModal('.$pid.'); return false; }" style="display:inline-block;background:#C8102E;color:#fff;font-weight:700;padding:5px 12px;border-radius:6px;text-decoration:none">💳 Pay $2 &amp; Activate</a>';
                } elseif ($status === 'expired') {
                  echo '<a href="'.esc_url($pay_url).'" onclick="if(typeof window.dbOpenPaymentModal === \'function\'){ window.dbOpenPaymentModal('.$pid.'); return false; }" style="display:inline-block;background:#C8102E;color:#fff;font-weight:700;padding:5px 12px;border-radius:6px;text-decoration:none">🔄 Reactivate — $2 / 30 days</a>';
                } elseif ($autopay === '1') {
                  echo '<span style="color:#065F46;font-weight:700">🔁 Auto-pay ON</span> '
                     . '<a href="'.esc_url($cancel_url).'" style="color:#DC2626;font-weight:600" onclick="return confirm(\'Turn off auto-payment? Your ad stays visible until the current 30 days end, then stops showing.\')">Turn off</a>';
                } else {
                  echo '<span style="color:#92400E;font-weight:700">⏸ Auto-pay OFF</span> '
                     . '<a href="'.esc_url($resume_url).'" style="color:#065F46;font-weight:600">Turn on</a>';
                }
                echo '</div>';
              }
              ?>
            </td>
          </tr>
        <?php endwhile; wp_reset_postdata();
        else: ?>
          <tr><td colspan="7" style="padding:48px;text-align:center;color:#9CA3AF">
            <div style="font-size:40px;margin-bottom:12px">📋</div>
            <p>No listings yet. <a href="<?php echo home_url('/post-ad'); ?>" style="color:#C8102E;font-weight:600">Post your first ad!</a></p>
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="text-align:center;padding:24px">
    <a href="<?php echo wp_logout_url(home_url()); ?>" style="font-size:13px;color:#9CA3AF">Sign Out</a>
  </div>

</div>
</div>

<?php get_footer(); ?>