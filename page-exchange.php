<?php
/*
Template Name: Exchange
*/
get_header();

// ===== EDIT MODE =====
$edit_id   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_post = null;
$is_edit   = false;

if ($edit_id && is_user_logged_in()) {
  $ep = get_post($edit_id);
  if ($ep && $ep->post_author == get_current_user_id()) {
    $edit_post = $ep;
    $is_edit   = true;
  }
}

$ev_have      = $is_edit ? get_post_meta($edit_id,'i_have_title',true) : '';
$ev_want      = $is_edit ? get_post_meta($edit_id,'i_want_title',true) : '';
$ev_have_desc = $is_edit ? '' : '';
$ev_want_desc = $is_edit ? '' : '';
$ev_city      = $is_edit ? get_post_meta($edit_id,'listing_city',true) : '';
$ev_phone     = $is_edit ? get_post_meta($edit_id,'listing_phone',true) : '';
$ev_email     = $is_edit ? get_post_meta($edit_id,'listing_email',true) : '';

// Parse description for i_have_desc and i_want_desc
if ($is_edit && $edit_post) {
  $content = $edit_post->post_content;
  if (preg_match('/I HAVE:.*?\n\n(.*?)\n\nI WANT:/s', $content, $m)) {
    $ev_have_desc = trim($m[1]);
  }
  if (preg_match('/I WANT:.*?\n\n(.*?)$/s', $content, $m)) {
    $ev_want_desc = trim($m[1]);
  }
}

// ===== HANDLE EDIT SUBMISSION =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['is_edit_submit']) && is_user_logged_in()) {
  $eid   = (int)($_POST['edit_post_id'] ?? 0);
  $epost = get_post($eid);
  if ($epost && $epost->post_author==get_current_user_id() && wp_verify_nonce($_POST['edit_nonce']??'','edit_exchange_'.$eid)) {
    $have = sanitize_text_field($_POST['i_have_title'] ?? '');
    $want = sanitize_text_field($_POST['i_want_title'] ?? '');
    $content = "I HAVE: " . $have . "\n\n";
    $content .= sanitize_textarea_field($_POST['i_have_desc'] ?? '') . "\n\n";
    $content .= "I WANT: " . $want . "\n\n";
    $content .= sanitize_textarea_field($_POST['i_want_desc'] ?? '');
    wp_update_post([
      'ID'           => $eid,
      'post_title'   => 'Exchange: '.$have.' for '.$want,
      'post_content' => $content,
    ]);
    update_post_meta($eid,'i_have_title', $have);
    update_post_meta($eid,'i_want_title', $want);
    update_post_meta($eid,'listing_city', sanitize_text_field($_POST['city']??''));
    update_post_meta($eid,'listing_phone', sanitize_text_field($_POST['phone']??''));
    update_post_meta($eid,'listing_email', sanitize_email($_POST['email']??''));
    $photo_urls_raw = $_POST['photo_urls'] ?? '';
    if (!empty($photo_urls_raw)) {
      // Delete ALL existing attached images first
      $existing_images = get_attached_media('image', $eid);
      foreach($existing_images as $att) {
        wp_delete_attachment($att->ID, true);
      }
      delete_post_thumbnail($eid);

      // Save all new photos
      $photos = array_filter(array_map('trim', explode("\n", $photo_urls_raw)));
      $plan_edit = get_post_meta($eid, 'listing_plan', true) ?: 'personal';
      $max_edit  = ($plan_edit === 'business') ? 20 : 7;
      $photos    = array_slice($photos, 0, $max_edit);
      $first     = true;
      foreach($photos as $photo_data) {
        $photo_data = trim($photo_data);
        if(empty($photo_data)) continue;
        if(strpos($photo_data,'data:image')===0) {
          preg_match('/data:image\/(\w+);base64,/',$photo_data,$m);
          $ext      = $m[1]??'jpg';
          $img_data = base64_decode(preg_replace('/^data:image\/\w+;base64,/','',$photo_data));
          $upload   = wp_upload_dir();
          $filename = 'listing-'.$eid.'-'.uniqid().'.'.$ext;
          $filepath = $upload['path'].'/'.$filename;
          file_put_contents($filepath, $img_data);
          $ft  = wp_check_filetype($filename);
          $aid = wp_insert_attachment([
            'post_mime_type' => $ft['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $eid,
          ], $filepath, $eid);
          require_once ABSPATH.'wp-admin/includes/image.php';
          wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $filepath));
          if($first){ set_post_thumbnail($eid, $aid); $first = false; }
        }
      }
    }
    wp_redirect(home_url('/dashboard/?updated=1')); exit;
  }
}

// ===== HANDLE NEW EXCHANGE SUBMISSION =====
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ex_nonce']) && !isset($_POST['is_edit_submit'])) {
  if (!wp_verify_nonce($_POST['ex_nonce'],'dealboard_exchange')) {
    $error = 'Security check failed.';
  } elseif (!is_user_logged_in()) {
    $error = 'You must be logged in to post an exchange.';
  } else {
    $have = sanitize_text_field($_POST['i_have_title'] ?? '');
    $want = sanitize_text_field($_POST['i_want_title'] ?? '');
    if (empty($have) || empty($want)) {
      $error = 'Please fill in both "I Have" and "I Want" fields.';
    } else {
      $content  = "I HAVE: " . $have . "\n\n";
      $content .= sanitize_textarea_field($_POST['i_have_desc'] ?? '') . "\n\n";
      $content .= "I WANT: " . $want . "\n\n";
      $content .= sanitize_textarea_field($_POST['i_want_desc'] ?? '');

      $post_id = wp_insert_post([
        'post_type'    => 'listing',
        'post_title'   => 'Exchange: ' . $have . ' for ' . $want,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
      ]);

      if (!is_wp_error($post_id)) {
        update_post_meta($post_id,'listing_price','Exchange');
        update_post_meta($post_id,'listing_city', sanitize_text_field($_POST['city']??''));
        update_post_meta($post_id,'listing_phone', sanitize_text_field($_POST['phone']??''));
        update_post_meta($post_id,'listing_email', sanitize_email($_POST['email']??''));
        update_post_meta($post_id,'listing_type','exchange');
        update_post_meta($post_id,'i_have_title', $have);
        update_post_meta($post_id,'i_want_title', $want);
        update_post_meta($post_id,'listing_status','active');
        update_post_meta($post_id,'listing_expires', date('Y-m-d',strtotime('+14 days')));

        // Save photos
        $photo_urls = $_POST['photo_urls'] ?? '';
        if (!empty($photo_urls)) {
          $photos = array_filter(array_map('trim', explode("\n", $photo_urls)));
          $first = true;
          foreach ($photos as $photo_data) {
            if (empty($photo_data)) continue;
            if (strpos($photo_data,'data:image')===0) {
              preg_match('/data:image\/(\w+);base64,/',$photo_data,$m);
              $ext=$m[1]??'jpg';
              $img_data=base64_decode(preg_replace('/^data:image\/\w+;base64,/','',$photo_data));
              $upload=wp_upload_dir();
              $filename='exchange-'.$post_id.'-'.uniqid().'.'.$ext;
              $filepath=$upload['path'].'/'.$filename;
              file_put_contents($filepath,$img_data);
              $ft=wp_check_filetype($filename);
              $aid=wp_insert_attachment(['post_mime_type'=>$ft['type'],'post_title'=>$filename,'post_content'=>'','post_status'=>'inherit'],$filepath,$post_id);
              require_once ABSPATH.'wp-admin/includes/image.php';
              wp_update_attachment_metadata($aid,wp_generate_attachment_metadata($aid,$filepath));
              if($first){set_post_thumbnail($post_id,$aid);$first=false;}
            }
          }
        }

        $ex_cat = get_term_by('slug','exchange','listing_category');
        if ($ex_cat) wp_set_post_terms($post_id,[$ex_cat->term_id],'listing_category');
        wp_redirect(get_permalink($post_id)); exit;
      } else {
        $error = 'Failed to post exchange.';
      }
    }
  }
}
?>

<style>
.ex-wrap{background:#F9FAFB;min-height:100vh;padding:0 0 60px}
.ex-inner{max-width:700px;margin:0 auto;padding:0 20px}
.ex-back{display:inline-flex;align-items:center;gap:6px;padding:24px 0 20px;font-size:14px;color:#6B7280;text-decoration:none}
.ex-back:hover{color:#111}
.ex-h1{font-size:26px;font-weight:800;color:#111827;margin-bottom:4px}
.ex-sub{font-size:14px;color:#6B7280;margin-bottom:24px}
.ex-card{background:white;border:1px solid #E5E7EB;border-radius:12px;padding:28px;margin-bottom:16px}
.ex-section-title{font-size:15px;font-weight:700;color:#111827;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #F3F4F6}
.ex-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px}
.ex-label .req{color:#C8102E}
.ex-input{width:100%;padding:10px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:14px;color:#111;outline:none;background:white;transition:border-color .15s;font-family:inherit}
.ex-input:focus{border-color:#C8102E;box-shadow:0 0 0 3px rgba(200,16,46,.1)}
.ex-input::placeholder{color:#9CA3AF}
.ex-textarea{min-height:100px;resize:vertical;line-height:1.6}
.ex-group{margin-bottom:18px}
.ex-box{background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:10px;padding:20px;margin-bottom:4px}
.ex-box:focus-within{border-color:#C8102E}
.ex-box-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.ex-box-label.have{color:#C8102E}
.ex-box-label.want{color:#C9A84C}
.ex-arrow-divider{display:flex;align-items:center;justify-content:center;gap:16px;margin:20px 0}
.ex-arrow-divider::before,.ex-arrow-divider::after{content:'';flex:1;height:1px;background:#E5E7EB}
.ex-arrow-icon{width:40px;height:40px;background:#C8102E;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(200,16,46,.3)}
.ex-photo-row{display:flex;gap:8px;margin-bottom:12px}
.ex-photo-row .ex-input{flex:1}
.ex-btn-add{padding:10px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit}
.ex-photo-strip{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.ex-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid #E5E7EB;cursor:pointer}
.ex-thumb:hover{border-color:#EF4444}
.ex-contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ex-btn-submit{width:100%;padding:15px;background:#C8102E;color:white;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:8px;font-family:inherit}
.ex-btn-submit:hover{background:#A50E26}
.ex-alert-error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;font-size:14px;margin-bottom:16px}
@media(max-width:600px){.ex-contact-grid{grid-template-columns:1fr}}
</style>

<div class="ex-wrap">
<div class="ex-inner">

  <a href="<?php echo esc_url($is_edit ? home_url('/dashboard') : home_url('/')); ?>" class="ex-back">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M15 18l-6-6 6-6"/></svg>
    <?php echo $is_edit ? 'Back to Dashboard' : 'Back'; ?>
  </a>

  <h1 class="ex-h1"><?php echo $is_edit ? '✏️ Edit Exchange' : '🔄 Exchange'; ?></h1>
  <p class="ex-sub"><?php echo $is_edit ? 'Update your exchange offer below.' : 'Trade what you have for what you want.'; ?></p>

  <?php if($error): ?><div class="ex-alert-error">❌ <?php echo esc_html($error); ?></div><?php endif; ?>

  <?php if(!is_user_logged_in()): ?>
  <div class="ex-card" style="text-align:center;padding:48px">
    <div style="font-size:48px;margin-bottom:12px">🔄</div>
    <h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Sign in to Post an Exchange</h2>
    <a href="<?php echo esc_url(home_url('/sign-in?redirect='.urlencode(home_url('/exchange')))); ?>"
       style="display:inline-block;padding:12px 28px;background:#C8102E;color:white;font-weight:700;border-radius:8px">Sign In</a>
  </div>
  <?php else: ?>

  <form method="POST" id="exchange-form">
    <?php if($is_edit): ?>
    <input type="hidden" name="is_edit_submit" value="1">
    <input type="hidden" name="edit_post_id" value="<?php echo $edit_id; ?>">
    <?php echo wp_nonce_field('edit_exchange_'.$edit_id,'edit_nonce',true,false); ?>
    <?php else: ?>
    <?php wp_nonce_field('dealboard_exchange','ex_nonce'); ?>
    <?php endif; ?>

    <!-- I HAVE / I WANT -->
    <div class="ex-card">
      <div class="ex-section-title"><?php echo $is_edit ? 'Update Exchange Offer' : 'What do you have?'; ?></div>

      <div class="ex-box">
        <div class="ex-box-label have"><span>📦</span> I HAVE</div>
        <div class="ex-group">
          <label class="ex-label">Item Title <span class="req">*</span></label>
          <input type="text" name="i_have_title" class="ex-input"
            placeholder="e.g. iPhone 13 Pro, Dining Table..."
            value="<?php echo esc_attr($ev_have); ?>" required>
        </div>
        <div class="ex-group" style="margin-bottom:0">
          <label class="ex-label">Description</label>
          <textarea name="i_have_desc" class="ex-input ex-textarea"
            placeholder="Condition, age, any defects..."><?php echo esc_textarea($ev_have_desc); ?></textarea>
        </div>
      </div>

      <div class="ex-arrow-divider">
        <div class="ex-arrow-icon">
          <svg width="20" height="20" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/>
          </svg>
        </div>
      </div>

      <div class="ex-box">
        <div class="ex-box-label want"><span>🎯</span> I WANT</div>
        <div class="ex-group">
          <label class="ex-label">What do you want in return? <span class="req">*</span></label>
          <input type="text" name="i_want_title" class="ex-input"
            placeholder="e.g. Laptop, Sofa, Camera..."
            value="<?php echo esc_attr($ev_want); ?>" required>
        </div>
        <div class="ex-group" style="margin-bottom:0">
          <label class="ex-label">Details</label>
          <textarea name="i_want_desc" class="ex-input ex-textarea"
            placeholder="Brand preference, condition expected..."><?php echo esc_textarea($ev_want_desc); ?></textarea>
        </div>
      </div>
    </div>

    <!-- PHOTOS (new only) -->
    <div class="ex-card">
      <div class="ex-section-title">Photos of Your Item</div>
      <p style="font-size:13px;color:#6B7280;margin-bottom:14px">Up to 7 photos.</p>
      <div class="ex-photo-row">
        <input type="text" id="ex-photo-url" class="ex-input" placeholder="https://...">
        <button type="button" class="ex-btn-add" onclick="exAddPhoto()">+ Add</button>
      </div>
      <div style="margin-bottom:12px">
        <input type="file" id="ex-upload" multiple accept="image/*" style="display:none" onchange="exHandleUpload(this)">
        <button type="button" onclick="document.getElementById('ex-upload').click()"
          style="display:flex;align-items:center;gap:6px;padding:9px 16px;border:1.5px dashed #D1D5DB;border-radius:8px;background:#F9FAFB;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit">
          📷 Upload Photos
        </button>
      </div>
      <div class="ex-photo-strip" id="ex-photo-strip"></div>
      <input type="hidden" name="photo_urls" id="ex-photo-hidden">
    </div>

    <!-- LOCATION & CONTACT -->
    <div class="ex-card">
      <div class="ex-section-title">Location &amp; Contact</div>
      <div class="ex-group">
        <label class="ex-label">City</label>
        <input type="text" name="city" class="ex-input" placeholder="e.g. Toronto, Dubai..."
          value="<?php echo esc_attr($ev_city); ?>">
      </div>
      <div class="ex-contact-grid">
        <div class="ex-group" style="margin-bottom:0">
          <label class="ex-label">Phone</label>
          <input type="tel" name="phone" class="ex-input" placeholder="Phone number"
            value="<?php echo esc_attr($ev_phone); ?>">
        </div>
        <div class="ex-group" style="margin-bottom:0">
          <label class="ex-label">Email</label>
          <input type="email" name="email" class="ex-input" placeholder="your@email.com"
            value="<?php echo esc_attr($ev_email ?: wp_get_current_user()->user_email); ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="ex-btn-submit">
      <?php echo $is_edit ? '💾 Save Changes' : '🔄 Post Exchange Offer'; ?>
    </button>
    <?php if(!$is_edit): ?>
    <p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:10px">
      Exchange posts are active for 14 days.
    </p>
    <?php endif; ?>
  </form>

  <?php endif; ?>
</div>
</div>

<script>
var exPhotos = [];
function exAddPhoto() {
  var url = document.getElementById('ex-photo-url').value.trim();
  if (!url || exPhotos.length >= 7) return;
  exPhotos.push(url); exRender();
  document.getElementById('ex-photo-url').value = '';
}
function exHandleUpload(input) {
  Array.from(input.files).slice(0, 7 - exPhotos.length).forEach(function(f) {
    var r = new FileReader();
    r.onload = function(e) { exPhotos.push(e.target.result); exRender(); };
    r.readAsDataURL(f);
  });
}
function exRender() {
  var strip = document.getElementById('ex-photo-strip');
  if (!strip) return;
  strip.innerHTML = '';
  exPhotos.forEach(function(url, i) {
    var img = document.createElement('img');
    img.src = url; img.className = 'ex-thumb'; img.title = 'Click to remove';
    img.onclick = function() { exPhotos.splice(i,1); exRender(); };
    strip.appendChild(img);
  });
  document.getElementById('ex-photo-hidden').value = exPhotos.join('\n');
}
var urlEl = document.getElementById('ex-photo-url');
if(urlEl) urlEl.addEventListener('keydown', function(e) {
  if (e.key==='Enter') { e.preventDefault(); exAddPhoto(); }
});
</script>

<?php get_footer(); ?>