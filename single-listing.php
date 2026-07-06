<?php get_header(); ?>

<?php while(have_posts()): the_post();
  $price     = get_post_meta(get_the_ID(),'listing_price',true);
  $currency  = get_post_meta(get_the_ID(),'listing_currency',true) ?: 'USD';
  $city      = get_post_meta(get_the_ID(),'listing_city',true);
  $address   = get_post_meta(get_the_ID(),'listing_address',true);
  $condition = get_post_meta(get_the_ID(),'listing_condition',true);
  $phone     = get_post_meta(get_the_ID(),'listing_phone',true);
  $email     = get_post_meta(get_the_ID(),'listing_email',true);
  $views     = get_post_meta(get_the_ID(),'listing_views',true) ?: 0;
  $status    = get_post_meta(get_the_ID(),'listing_status',true) ?: 'active';
// Also check post_status for expired
if(get_post_status() === 'private' && $status !== 'expired') {
  $status = 'expired';
}
  $type      = get_post_meta(get_the_ID(),'listing_type',true);
  $i_have    = get_post_meta(get_the_ID(),'i_have_title',true);
  $i_want    = get_post_meta(get_the_ID(),'i_want_title',true);
  $photo_urls  = get_post_meta(get_the_ID(),'listing_photo_urls',true);
  $video_url   = get_post_meta(get_the_ID(),'listing_video_url',true);
  $lat         = get_post_meta(get_the_ID(),'listing_lat',true);
  $lng         = get_post_meta(get_the_ID(),'listing_lng',true);
  $cats      = wp_get_post_terms(get_the_ID(),'listing_category');
  $cat       = (!empty($cats) && !is_wp_error($cats)) ? $cats[0] : null;
  $author_id = get_the_author_meta('ID');
  $author    = get_the_author_meta('display_name');
  $member    = get_the_author_meta('user_registered');
  $time      = human_time_diff(get_the_time('U'),current_time('timestamp')).' ago';
  $is_exchange = ($type === 'exchange' || $price === 'Exchange');

  // All photos — use attached media only, exclude featured image duplicate
  $thumbnail_id = get_post_thumbnail_id(get_the_ID());
  $all_photos   = [];
  $attached     = get_attached_media('image', get_the_ID());

  foreach($attached as $att) {
    // Skip featured image — it will be added first separately
    if($att->ID == $thumbnail_id) continue;
    $url = wp_get_attachment_url($att->ID);
    if($url && !in_array($url, $all_photos)) $all_photos[] = $url;
  }

  // Add featured image at the beginning
  if($thumbnail_id) {
    $thumb_url = wp_get_attachment_url($thumbnail_id);
    if($thumb_url) array_unshift($all_photos, $thumb_url);
  }

  // Also check meta photo_urls for external URLs
  if($photo_urls) {
    foreach(array_filter(explode("\n",$photo_urls)) as $url) {
      $url = trim($url);
      if($url && !in_array($url,$all_photos) && strpos($url,'data:image')===false && filter_var($url, FILTER_VALIDATE_URL)) {
        $all_photos[] = $url;
      }
    }
  }
?>

<style>
.sl-wrap { background:#F9FAFB; padding:24px 0 60px; }
.sl-breadcrumb {
  font-size:13px;color:#6B7280;margin-bottom:20px;
  display:flex;align-items:center;gap:6px;flex-wrap:wrap;
}
.sl-breadcrumb a { color:#6B7280; text-decoration:none; }
.sl-breadcrumb a:hover { color:#C8102E; }
.sl-layout {
  display:grid;grid-template-columns:1fr 340px;gap:24px;
  align-items:start;
}
@media(max-width:900px){ .sl-layout { grid-template-columns:1fr; } }
.sl-gallery { border-radius:14px;overflow:hidden;background:#F3F4F6;margin-bottom:16px;position:relative; }
.sl-gallery-main {
  width:100%;height:400px;object-fit:contain;
  background:#F3F4F6;display:block;cursor:zoom-in;
}
.sl-gallery-placeholder {
  height:300px;display:flex;align-items:center;
  justify-content:center;font-size:64px;background:#F3F4F6;
}
.sl-thumbs { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px; }
.sl-thumb {
  width:68px;height:68px;border-radius:8px;object-fit:cover;
  border:2.5px solid transparent;cursor:pointer;transition:border-color .15s;
}
.sl-thumb.active,.sl-thumb:hover { border-color:#C8102E; }
.sl-detail {
  background:white;border:1px solid #E5E7EB;border-radius:14px;padding:24px;margin-bottom:16px;
}
.sl-cat-badge {
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 12px;background:#FEE2E2;color:#C8102E;
  font-size:12px;font-weight:700;border-radius:9999px;margin-bottom:12px;
}
.sl-title { font-size:24px;font-weight:800;color:#111;margin-bottom:12px;line-height:1.2; }
.sl-price { font-size:30px;font-weight:800;color:#C8102E;margin-bottom:16px; }
.sl-price-note { font-size:14px;font-weight:400;color:#6B7280; }
.sl-exchange-grid { display:grid;grid-template-columns:1fr 40px 1fr;gap:12px;align-items:center;margin-bottom:16px; }
.sl-ex-box { background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:10px;padding:14px; }
.sl-ex-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px; }
.sl-ex-label.have { color:#C8102E; }
.sl-ex-label.want { color:#C9A84C; }
.sl-ex-value { font-size:14px;font-weight:700;color:#111; }
.sl-ex-arrow { display:flex;align-items:center;justify-content:center;font-size:20px; }
.sl-meta-grid {
  display:grid;grid-template-columns:repeat(2,1fr);gap:12px;
  background:#F9FAFB;border-radius:10px;padding:16px;margin-bottom:16px;
}
.sl-meta-item { display:flex;align-items:flex-start;gap:10px; }
.sl-meta-item .icon { font-size:18px;flex-shrink:0; }
.sl-meta-label { font-size:10px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px; }
.sl-meta-value { font-size:13px;font-weight:600;color:#111; }
.sl-desc { border-top:1px solid #F3F4F6;padding-top:20px; }
.sl-desc h3 { font-size:16px;font-weight:700;color:#111;margin-bottom:10px; }
.sl-sidebar { position:sticky;top:90px; }
.sl-contact-card { background:white;border:1px solid #E5E7EB;border-radius:14px;padding:22px; }
.sl-contact-price { font-size:28px;font-weight:800;color:#C8102E;margin-bottom:16px; }
.sl-btn-call {
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:13px;background:#C8102E;color:white;
  font-size:15px;font-weight:700;border-radius:10px;
  text-decoration:none;margin-bottom:10px;transition:background .15s;
}
.sl-btn-call:hover { background:#A50E26;color:white; }
.sl-btn-secondary {
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:11px;border:1.5px solid #E5E7EB;background:white;
  color:#374151;font-size:14px;font-weight:600;border-radius:10px;
  text-decoration:none;margin-bottom:8px;cursor:pointer;transition:all .15s;font-family:inherit;
}
.sl-btn-secondary:hover { border-color:#C8102E;color:#C8102E; }
.sl-safety { background:#FFFBEB;border-radius:10px;padding:14px;margin-top:14px; }
.sl-safety h4 { font-size:13px;font-weight:700;color:#92400E;margin-bottom:8px; }
.sl-safety ul { padding-left:16px;list-style:disc;font-size:12px;color:#92400E;line-height:1.8; }
.sl-seller {
  border-top:1px solid #F3F4F6;margin-top:16px;padding-top:16px;
  display:flex;align-items:center;gap:12px;
}
.sl-avatar {
  width:44px;height:44px;border-radius:50%;background:#FEE2E2;
  display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:700;color:#C8102E;flex-shrink:0;
}
.sl-seller-name { font-size:14px;font-weight:700;color:#111; }
.sl-seller-since { font-size:12px;color:#6B7280; }
.sl-expired-stamp {
  position:absolute;top:12px;left:12px;
  background:#DC2626;color:white;
  font-size:12px;font-weight:800;letter-spacing:1px;
  padding:5px 12px;border-radius:6px;z-index:10;
  text-transform:uppercase;box-shadow:0 2px 8px rgba(0,0,0,.2);
}
</style>

<div class="sl-wrap">
<div class="container">

  <!-- Breadcrumb -->
  <div class="sl-breadcrumb">
    <a href="<?php echo home_url('/'); ?>">Home</a> <span>›</span>
    <a href="<?php echo home_url('/listings'); ?>">Browse Ads</a>
    <?php if($cat): ?>
    <span>›</span>
    <a href="<?php echo esc_url(get_term_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a>
    <?php endif; ?>
    <span>›</span>
    <span style="color:#111"><?php the_title(); ?></span>
  </div>

  <!-- Expired Notice Banner -->
  <?php if($status === 'expired'): ?>
  <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:24px">⛔</span>
    <div>
      <div style="font-size:15px;font-weight:700;color:#DC2626">This listing has expired</div>
      <div style="font-size:13px;color:#EF4444;margin-top:2px">This ad is no longer active and may not be available.</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="sl-layout">

    <!-- LEFT COLUMN -->
    <div>

      <!-- Gallery -->
      <?php if(!empty($all_photos)): ?>
      <div class="sl-gallery">
        <?php if($status === 'expired'): ?>
        <div class="sl-expired-stamp">⛔ Expired</div>
        <?php endif; ?>
        <img src="<?php echo esc_url($all_photos[0]); ?>" class="sl-gallery-main" id="sl-main-img"
          alt="<?php the_title_attribute(); ?>"
          onclick="this.style.objectFit = this.style.objectFit==='cover' ? 'contain':'cover'">
      </div>
      <?php if(count($all_photos) > 1): ?>
      <div class="sl-thumbs">
        <?php foreach($all_photos as $i => $photo): ?>
        <img src="<?php echo esc_url($photo); ?>" class="sl-thumb <?php echo $i===0?'active':''; ?>"
          onclick="document.getElementById('sl-main-img').src=this.src;document.querySelectorAll('.sl-thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active')">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if($video_url): ?>
      <div style="margin-bottom:16px">
        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">🎬 Video</div>
        <video src="<?php echo esc_url($video_url); ?>" controls
          style="width:100%;max-height:300px;border-radius:10px;background:#111;display:block">
          Your browser does not support video playback.
        </video>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="sl-gallery">
        <?php if($status === 'expired'): ?>
        <div class="sl-expired-stamp">⛔ Expired</div>
        <?php endif; ?>
        <div class="sl-gallery-placeholder">📦</div>
      </div>
      <?php endif; ?>

      <!-- Detail Card -->
      <div class="sl-detail">
        <?php if($cat): ?>
        <div class="sl-cat-badge">
          <?php echo dealboard_category_icon($cat->slug); ?> <?php echo esc_html($cat->name); ?>
        </div>
        <?php endif; ?>

        <h1 class="sl-title"><?php the_title(); ?></h1>

        <?php if($is_exchange && $i_have && $i_want): ?>
        <div class="sl-exchange-grid">
          <div class="sl-ex-box">
            <div class="sl-ex-label have">📦 I Have</div>
            <div class="sl-ex-value"><?php echo esc_html($i_have); ?></div>
          </div>
          <div class="sl-ex-arrow">⇄</div>
          <div class="sl-ex-box">
            <div class="sl-ex-label want">🎯 I Want</div>
            <div class="sl-ex-value"><?php echo esc_html($i_want); ?></div>
          </div>
        </div>
        <?php elseif($price): ?>
        <div class="sl-price" data-currency="<?php echo esc_attr($currency); ?>">
          <?php echo $price === 'Exchange' ? '🔄 Exchange' : esc_html('$'.$price); ?>
          <?php if($currency !== 'USD'): ?>
          <span class="sl-price-note">(<?php echo esc_html($currency); ?>)</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="sl-meta-grid">
          <?php if($city): ?>
          <div class="sl-meta-item">
            <span class="icon">📍</span>
            <div><div class="sl-meta-label">Location</div><div class="sl-meta-value"><?php echo esc_html($city); ?></div></div>
          </div>
          <?php endif; ?>
          <?php if($condition): ?>
          <div class="sl-meta-item">
            <span class="icon">✅</span>
            <div><div class="sl-meta-label">Condition</div><div class="sl-meta-value"><?php echo esc_html(ucwords(str_replace('_',' ',$condition))); ?></div></div>
          </div>
          <?php endif; ?>
          <div class="sl-meta-item">
            <span class="icon">🕐</span>
            <div><div class="sl-meta-label">Posted</div><div class="sl-meta-value"><?php echo esc_html($time); ?></div></div>
          </div>
          <div class="sl-meta-item">
            <span class="icon">👁</span>
            <div><div class="sl-meta-label">Views</div><div class="sl-meta-value"><?php echo esc_html($views); ?></div></div>
          </div>
        </div>

        <?php $content = get_the_content(); if($content): ?>
        <div class="sl-desc">
          <h3>Description</h3>
          <div style="font-size:14px;color:#374151;line-height:1.8"><?php the_content(); ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Map Section — inside left column, below detail card -->
      <?php if($lat && $lng): ?>
      <div style="background:white;border:1px solid #E5E7EB;border-radius:14px;padding:24px;margin-bottom:16px">
        <h3 style="font-size:16px;font-weight:700;color:#111;margin-bottom:4px">📍 General Location</h3>
        <p style="font-size:12px;color:#9CA3AF;margin-bottom:12px">Approximate area shown for privacy. Exact location shared after contact.</p>
        <div id="sl-map" style="height:220px;border-radius:10px;border:1.5px solid #E5E7EB;overflow:hidden"></div>
      </div>
      <?php endif; ?>

    </div>
    <!-- END LEFT COLUMN -->

    <!-- RIGHT Sidebar -->
    <div class="sl-sidebar">
      <div class="sl-contact-card">

        <?php if($status === 'expired'): ?>
        <div style="background:#FEE2E2;color:#DC2626;border-radius:8px;padding:12px;text-align:center;font-weight:700;margin-bottom:16px;font-size:14px">
          ⛔ This listing has expired
        </div>
        <?php else: ?>

        <?php if($price && $price !== 'Exchange'): ?>
        <div class="sl-contact-price" data-currency="<?php echo esc_attr($currency); ?>">
          $<?php echo esc_html($price); ?>
          <?php if($currency !== 'USD'): ?>
          <span style="font-size:14px;font-weight:400;color:#6B7280">(<?php echo esc_html($currency); ?>)</span>
          <?php endif; ?>
        </div>
        <?php elseif($is_exchange): ?>
        <div class="sl-contact-price" style="font-size:20px">🔄 Exchange Offer</div>
        <?php endif; ?>

        <?php if($status === 'sold'): ?>
        <div style="background:#FEE2E2;color:#DC2626;border-radius:8px;padding:12px;text-align:center;font-weight:700;margin-bottom:12px">
          ❌ This item has been sold
        </div>
        <?php else: ?>
          <?php if($phone): ?>
          <?php
          $listing_country = get_post_meta(get_the_ID(),'listing_country',true);
          $country_codes = ['us'=>'+1','gb'=>'+44','bd'=>'+880','ae'=>'+971','sa'=>'+966','in'=>'+91','pk'=>'+92'];
          $code = $country_codes[$listing_country] ?? '';
          $full_phone = $code && strpos($phone,$code)===false ? $code.$phone : $phone;
          ?>
          <a href="tel:<?php echo esc_attr($full_phone); ?>" class="sl-btn-call">
            📞 Call Seller
          </a>
          <?php endif; ?>
          <?php if($email): ?>
          <a href="mailto:<?php echo esc_attr($email); ?>?subject=<?php echo rawurlencode('Re: '.get_the_title()); ?>"
             class="sl-btn-secondary">
            ✉️ Send Email
          </a>
          <?php endif; ?>
          <button class="sl-btn-secondary"
            onclick="navigator.share ? navigator.share({title:'<?php the_title_attribute(); ?>',url:window.location.href}) : prompt('Copy this link:',window.location.href)">
            🔗 Share This Ad
          </button>
        <?php endif; ?>

        <?php endif; ?>

        <div class="sl-safety">
          <h4>💡 Safety Tips</h4>
          <ul>
            <li>Meet in a public place</li>
            <li>Don't pay in advance</li>
            <li>Inspect before buying</li>
          </ul>
        </div>

        <div class="sl-seller">
          <div class="sl-avatar"><?php echo esc_html(strtoupper(substr($author,0,1))); ?></div>
          <div>
            <div class="sl-seller-name"><?php echo esc_html($author); ?></div>
            <div class="sl-seller-since">Member since <?php echo date('M Y', strtotime($member)); ?></div>
          </div>
        </div>

      </div>
    </div>
    <!-- END RIGHT SIDEBAR -->

  </div>
</div>
</div>

<?php if($lat && $lng): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var lat = <?php echo floatval($lat); ?>;
  var lng = <?php echo floatval($lng); ?>;
  var offset  = 0.005;
  var fuzzLat = lat + (Math.random() - 0.5) * offset;
  var fuzzLng = lng + (Math.random() - 0.5) * offset;
  var map = L.map('sl-map', {
    center: [fuzzLat, fuzzLng],
    zoom: 13,
    zoomControl: true,
    scrollWheelZoom: false,
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 16
  }).addTo(map);
  L.circle([fuzzLat, fuzzLng], {
    color: '#C8102E',
    fillColor: '#C8102E',
    fillOpacity: 0.15,
    radius: 600,
    weight: 2,
  }).addTo(map);
});
</script>
<?php endif; ?>

<?php endwhile; ?>
<?php get_footer(); ?>