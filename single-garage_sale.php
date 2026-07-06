<?php get_header(); ?>

<?php while(have_posts()): the_post();
  $date_start = get_post_meta(get_the_ID(),'sale_date_start',true);
  $date_end   = get_post_meta(get_the_ID(),'sale_date_end',true);
  $time_start = get_post_meta(get_the_ID(),'sale_time_start',true);
  $time_end   = get_post_meta(get_the_ID(),'sale_time_end',true);
  $address    = get_post_meta(get_the_ID(),'sale_address',true);
  $city       = get_post_meta(get_the_ID(),'sale_city',true);
  $state      = get_post_meta(get_the_ID(),'sale_state',true);
  $zip        = get_post_meta(get_the_ID(),'sale_zip',true);
  $items      = get_post_meta(get_the_ID(),'sale_items',true);
  $phone      = get_post_meta(get_the_ID(),'sale_phone',true);
  $contact    = get_post_meta(get_the_ID(),'sale_contact_name',true);
  $currency   = get_post_meta(get_the_ID(),'sale_currency',true) ?: 'USD';
  $condition  = get_post_meta(get_the_ID(),'listing_condition',true);
  $author     = get_the_author_meta('display_name');
  $member     = get_the_author_meta('user_registered');

  // Expired status
  $sale_status = get_post_meta(get_the_ID(),'listing_status',true);
  if(!$sale_status && $date_end && strtotime($date_end) < strtotime('today')) {
    $sale_status = 'expired';
  }

  $date_fmt     = $date_start ? date('D, M j, Y', strtotime($date_start)) : '';
  $date_end_fmt = $date_end   ? date('D, M j, Y', strtotime($date_end))   : '';
  $time_fmt     = ($time_start && $time_end) ? date('g:i A', strtotime($time_start)) . ' – ' . date('g:i A', strtotime($time_end)) : '';
  $location     = implode(', ', array_filter([$address, $city, $state, $zip]));
?>

<div class="container" style="padding:32px 0 60px">

  <!-- Breadcrumb -->
  <div style="font-size:13px;color:#6B7280;margin-bottom:20px;display:flex;align-items:center;gap:6px">
    <a href="<?php echo home_url('/'); ?>" style="color:#6B7280">Home</a>
    <span>›</span>
    <a href="<?php echo home_url('/garage-sales'); ?>" style="color:#6B7280">Garage Sales</a>
    <span>›</span>
    <span style="color:#111"><?php the_title(); ?></span>
  </div>

  <!-- Expired Notice Banner -->
  <?php if($sale_status === 'expired'): ?>
  <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:24px">⛔</span>
    <div>
      <div style="font-size:15px;font-weight:700;color:#DC2626">This garage sale has ended</div>
      <div style="font-size:13px;color:#EF4444;margin-top:2px">This sale is no longer active.</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="listing-single-layout">

    <!-- LEFT -->
    <div>

      <!-- Image -->
      <div style="border-radius:12px;overflow:hidden;margin-bottom:20px;background:#F3F4F6;position:relative;">
        <?php
        $thumbnail_id = get_post_thumbnail_id(get_the_ID());
        $all_imgs     = [];
        $attached     = get_attached_media('image', get_the_ID());
        foreach($attached as $att) {
          if($att->ID == $thumbnail_id) continue;
          $url = wp_get_attachment_url($att->ID);
          if($url && !in_array($url, $all_imgs)) $all_imgs[] = $url;
        }
        if($thumbnail_id) {
          $thumb_url = wp_get_attachment_url($thumbnail_id);
          if($thumb_url) array_unshift($all_imgs, $thumb_url);
        }
        if(!empty($all_imgs)): ?>
          <?php if($sale_status === 'expired'): ?>
          <div style="position:absolute;top:12px;left:12px;background:#DC2626;color:white;font-size:12px;font-weight:800;letter-spacing:1px;padding:5px 12px;border-radius:6px;z-index:10;text-transform:uppercase;box-shadow:0 2px 8px rgba(0,0,0,.2)">⛔ Expired</div>
          <?php endif; ?>
          <img src="<?php echo esc_url($all_imgs[0]); ?>" id="gs-main-img"
            style="width:100%;max-height:420px;object-fit:contain;background:#F3F4F6;display:block">
          <?php if(count($all_imgs) > 1): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;padding:8px">
            <?php foreach($all_imgs as $i => $img_url): ?>
            <img src="<?php echo esc_url($img_url); ?>"
              style="width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid <?php echo $i===0?'#C8102E':'#E5E7EB'; ?>;cursor:pointer"
              onclick="document.getElementById('gs-main-img').src=this.src">
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <?php if($sale_status === 'expired'): ?>
          <div style="position:absolute;top:12px;left:12px;background:#DC2626;color:white;font-size:12px;font-weight:800;letter-spacing:1px;padding:5px 12px;border-radius:6px;z-index:10;text-transform:uppercase;box-shadow:0 2px 8px rgba(0,0,0,.2)">⛔ Expired</div>
          <?php endif; ?>
          <div style="height:220px;display:flex;align-items:center;justify-content:center;background:#FEF3C7;font-size:80px">🏡</div>
        <?php endif; ?>
      </div>

      <!-- Details -->
      <div class="listing-detail-body">
        <h1 class="listing-detail-title"><?php the_title(); ?></h1>
        <?php if($items): ?>
        <p style="font-size:15px;color:#6B7280;margin-bottom:20px"><?php echo esc_html($items); ?></p>
        <?php endif; ?>

        <!-- Info Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#F9FAFB;border-radius:10px;padding:20px;margin-bottom:24px">

          <?php if($date_fmt): ?>
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:20px">📅</span>
            <div>
              <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Date</div>
              <div style="font-size:14px;font-weight:600;color:#111">
                <?php echo esc_html($date_fmt); ?>
                <?php if($date_end_fmt && $date_end_fmt !== $date_fmt): ?> – <?php echo esc_html($date_end_fmt); ?><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if($time_fmt): ?>
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:20px">⏰</span>
            <div>
              <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Time</div>
              <div style="font-size:14px;font-weight:600;color:#111"><?php echo esc_html($time_fmt); ?></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if($location): ?>
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:20px">📍</span>
            <div>
              <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Location</div>
              <div style="font-size:14px;font-weight:600;color:#111"><?php echo esc_html($location); ?></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if($currency): ?>
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:20px">💱</span>
            <div>
              <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Currency</div>
              <div style="font-size:14px;font-weight:600;color:#111"><?php echo esc_html($currency); ?></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if($condition): ?>
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:20px">✅</span>
            <div>
              <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Condition</div>
              <div style="font-size:14px;font-weight:600;color:#111"><?php echo esc_html(ucfirst($condition)); ?></div>
            </div>
          </div>
          <?php endif; ?>

        </div>

        <!-- Description -->
        <?php $desc = get_the_content(); if($desc): ?>
        <div class="listing-description">
          <h3 style="font-size:16px;font-weight:700;color:#111;margin-bottom:14px">About This Sale</h3>
          <?php the_content(); ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- RIGHT Sidebar -->
    <div>
      <div class="contact-card">

        <?php if($sale_status === 'expired'): ?>
        <div style="background:#FEE2E2;color:#DC2626;border-radius:8px;padding:12px;text-align:center;font-weight:700;margin-bottom:16px;font-size:14px">
          ⛔ This sale has ended
        </div>
        <?php else: ?>

        <!-- Date badge -->
        <?php if($date_fmt): ?>
        <div style="background:#FEF3C7;border-radius:10px;padding:14px 16px;margin-bottom:16px;text-align:center">
          <div style="font-size:12px;color:#92400E;font-weight:600;margin-bottom:2px">📅 Sale Date</div>
          <div style="font-size:15px;font-weight:800;color:#111"><?php echo esc_html($date_fmt); ?></div>
          <?php if($time_fmt): ?>
          <div style="font-size:13px;color:#6B7280;margin-top:2px">⏰ <?php echo esc_html($time_fmt); ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($phone): ?>
        <a href="tel:<?php echo esc_attr($phone); ?>" class="btn-contact">📞 Call for Info</a>
        <?php endif; ?>

        <button type="button" class="btn-contact-secondary" style="margin-bottom:10px"
          onclick="navigator.share ? navigator.share({title:'<?php the_title_attribute(); ?>',url:window.location.href}) : (prompt('Copy this link:',window.location.href))">
          🔗 Share This Sale
        </button>

        <?php if($location): ?>
        <a href="https://maps.google.com/?q=<?php echo urlencode($location); ?>" target="_blank"
           class="btn-contact-secondary">🗺️ View on Map</a>
        <?php endif; ?>

        <?php endif; // end expired check ?>

        <!-- Tips -->
        <div style="background:#FFFBEB;border-radius:8px;padding:14px;margin-top:16px;font-size:12px;color:#92400E">
          <strong>💡 Tips</strong>
          <ul style="margin-top:8px;padding-left:16px;list-style:disc;line-height:1.8">
            <li>Arrive early for best finds</li>
            <li>Bring cash (small bills)</li>
            <li>Bring bags/boxes</li>
            <li>Prices may be negotiable</li>
          </ul>
        </div>

        <!-- Seller -->
        <div class="seller-info">
          <div style="display:flex;align-items:center;gap:12px">
            <div class="seller-avatar"><?php echo esc_html(strtoupper(substr($author,0,1))); ?></div>
            <div>
              <div style="font-size:14px;font-weight:700;color:#111"><?php echo esc_html($contact ?: $author); ?></div>
              <div style="font-size:12px;color:#6B7280">Member since <?php echo date('M Y', strtotime($member)); ?></div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>