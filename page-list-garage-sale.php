<?php
/*
Template Name: List Garage Sale
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

$ev_title    = $is_edit ? $edit_post->post_title : '';
$ev_desc     = $is_edit ? $edit_post->post_content : '';
$ev_items    = $is_edit ? get_post_meta($edit_id,'sale_items',true) : '';
$ev_date     = $is_edit ? get_post_meta($edit_id,'sale_date_start',true) : date('Y-m-d');
$ev_date_end = $is_edit ? get_post_meta($edit_id,'sale_date_end',true) : date('Y-m-d');
$ev_time_s   = $is_edit ? get_post_meta($edit_id,'sale_time_start',true) : '08:00';
$ev_time_e   = $is_edit ? get_post_meta($edit_id,'sale_time_end',true) : '17:00';
$ev_address  = $is_edit ? get_post_meta($edit_id,'sale_address',true) : '';
$ev_city     = $is_edit ? get_post_meta($edit_id,'sale_city',true) : '';
$ev_state    = $is_edit ? get_post_meta($edit_id,'sale_state',true) : '';
$ev_zip      = $is_edit ? get_post_meta($edit_id,'sale_zip',true) : '';
$ev_country  = $is_edit ? get_post_meta($edit_id,'sale_country',true) : 'us';
$ev_currency = $is_edit ? get_post_meta($edit_id,'sale_currency',true) : 'USD';
$ev_phone    = $is_edit ? get_post_meta($edit_id,'sale_phone',true) : '';
$ev_contact  = $is_edit ? get_post_meta($edit_id,'sale_contact_name',true) : '';

// ===== HANDLE EDIT SUBMISSION =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['is_edit_submit']) && is_user_logged_in()) {
  $eid   = (int)($_POST['edit_post_id'] ?? 0);
  $epost = get_post($eid);
  if ($epost && $epost->post_author==get_current_user_id() && wp_verify_nonce($_POST['edit_nonce']??'','edit_garage_'.$eid)) {
    wp_update_post([
      'ID'           => $eid,
      'post_title'   => sanitize_text_field($_POST['sale_title']??''),
      'post_content' => sanitize_textarea_field($_POST['additional_details']??''),
    ]);
    $meta_fields = ['sale_items','sale_date_start','sale_date_end','sale_time_start','sale_time_end','sale_address','sale_city','sale_state','sale_zip','sale_country','sale_currency','sale_phone','sale_contact_name'];
    foreach($meta_fields as $mf) {
      if(isset($_POST[$mf])) update_post_meta($eid, $mf, sanitize_text_field($_POST[$mf]));
    }
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

// ===== HANDLE NEW SUBMISSION =====
$error_msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gs_nonce']) && !isset($_POST['is_edit_submit'])) {
  if (!wp_verify_nonce($_POST['gs_nonce'],'dealboard_garage_sale')) {
    $error_msg = 'Security check failed.';
  } elseif (!is_user_logged_in()) {
    $error_msg = 'You must be logged in.';
  } else {
    $title = sanitize_text_field($_POST['sale_title']??'');
    if (empty($title)) { $error_msg = 'Title is required.'; }
    else {
      $post_id = wp_insert_post([
        'post_type'    => 'garage_sale',
        'post_title'   => $title,
        'post_content' => sanitize_textarea_field($_POST['additional_details']??''),
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
      ]);
      if (!is_wp_error($post_id)) {
        $meta_fields = ['sale_items','sale_date_start','sale_date_end','sale_time_start','sale_time_end','sale_address','sale_city','sale_state','sale_zip','sale_country','sale_currency','sale_phone','sale_contact_name'];
        foreach($meta_fields as $mf) {
          if(isset($_POST[$mf])) update_post_meta($post_id, $mf, sanitize_text_field($_POST[$mf]));
        }

        // Save photo
        $photo_urls_raw = $_POST['photo_urls']??'';
        if (!empty($photo_urls_raw)) {
          $photos = array_filter(array_map('trim', explode("\n", $photo_urls_raw)));
          $first = true;
          foreach($photos as $photo_data) {
            if(strpos($photo_data,'data:image')===0) {
              preg_match('/data:image\/(\w+);base64,/',$photo_data,$m);
              $img_data=base64_decode(preg_replace('/^data:image\/\w+;base64,/','',$photo_data));
              $upload=wp_upload_dir();
              $filename='garage-'.$post_id.'-'.uniqid().'.'.($m[1]??'jpg');
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
        wp_redirect(get_permalink($post_id)); exit;
      } else { $error_msg='Failed to create listing.'; }
    }
  }
}

$countries = [
  'us'=>['name'=>'United States','currency'=>'USD'],
  'gb'=>['name'=>'United Kingdom','currency'=>'GBP'],
  'ca'=>['name'=>'Canada','currency'=>'CAD'],
  'au'=>['name'=>'Australia','currency'=>'AUD'],
  'bd'=>['name'=>'Bangladesh','currency'=>'BDT'],
  'ae'=>['name'=>'UAE','currency'=>'AED'],
  'om'=>['name'=>'Oman','currency'=>'OMR'],
  'bh'=>['name'=>'Bahrain','currency'=>'BHD'],
  'kw'=>['name'=>'Kuwait','currency'=>'KWD'],
  'qa'=>['name'=>'Qatar','currency'=>'QAR'],
  'sa'=>['name'=>'Saudi Arabia','currency'=>'SAR'],
  'in'=>['name'=>'India','currency'=>'INR'],
  'pk'=>['name'=>'Pakistan','currency'=>'PKR'],
  'ng'=>['name'=>'Nigeria','currency'=>'NGN'],
];
$currencies = ['USD','GBP','CAD','AUD','EUR','BDT','AED','OMR','BHD','KWD','QAR','SAR','INR','PKR','NGN'];
?>

<style>
.gs-form-wrap{background:#F9FAFB;min-height:100vh;padding:0 0 60px}
.gs-form-inner{max-width:700px;margin:0 auto;padding:0 20px}
.gs-back{display:inline-flex;align-items:center;gap:6px;padding:24px 0 20px;font-size:14px;color:#6B7280;text-decoration:none}
.gs-back:hover{color:#111}
.gs-h1{font-size:26px;font-weight:800;color:#111827;margin-bottom:4px}
.gs-sub{font-size:14px;color:#6B7280;margin-bottom:24px}
.gs-card{background:white;border:1px solid #E5E7EB;border-radius:12px;padding:28px;margin-bottom:16px}
.gs-section-title{font-size:15px;font-weight:700;color:#111827;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #F3F4F6}
.gs-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px}
.gs-label .req{color:#C8102E}
.gs-input{width:100%;padding:10px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:14px;color:#111;outline:none;background:white;transition:border-color .15s;font-family:inherit}
.gs-input:focus{border-color:#C8102E;box-shadow:0 0 0 3px rgba(200,16,46,.1)}
.gs-input::placeholder{color:#9CA3AF}
.gs-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;cursor:pointer}
.gs-textarea{min-height:100px;resize:vertical;line-height:1.6}
.gs-group{margin-bottom:18px}
.gs-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.gs-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.gs-btn-submit{width:100%;padding:15px;background:#C8102E;color:white;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:8px;font-family:inherit}
.gs-btn-submit:hover{background:#A50E26}
.gs-alert-error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;font-size:14px;margin-bottom:16px}
.gs-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid #E5E7EB;cursor:pointer}
.gs-thumb:hover{border-color:#EF4444}
@media(max-width:600px){.gs-grid-2,.gs-grid-3{grid-template-columns:1fr}}
</style>

<div class="gs-form-wrap">
<div class="gs-form-inner">

  <a href="<?php echo esc_url($is_edit ? home_url('/dashboard') : home_url('/garage-sales')); ?>" class="gs-back">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M15 18l-6-6 6-6"/></svg>
    <?php echo $is_edit ? 'Back to Dashboard' : 'Back'; ?>
  </a>

  <h1 class="gs-h1"><?php echo $is_edit ? '✏️ Edit Garage Sale' : '🏡 List a Garage Sale'; ?></h1>
  <p class="gs-sub"><?php echo $is_edit ? 'Update your garage sale details.' : 'Attract local shoppers to your upcoming garage sale.'; ?></p>

  <?php if($error_msg): ?><div class="gs-alert-error">❌ <?php echo esc_html($error_msg); ?></div><?php endif; ?>

  <?php if(!is_user_logged_in()): ?>
  <div class="gs-card" style="text-align:center;padding:48px">
    <div style="font-size:48px;margin-bottom:12px">🔒</div>
    <h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Sign in to List a Sale</h2>
    <a href="<?php echo esc_url(home_url('/sign-in')); ?>"
       style="display:inline-block;padding:12px 28px;background:#C8102E;color:white;font-weight:700;border-radius:8px">Sign In</a>
  </div>
  <?php else: ?>

  <form method="POST">
    <?php if($is_edit): ?>
    <input type="hidden" name="is_edit_submit" value="1">
    <input type="hidden" name="edit_post_id" value="<?php echo $edit_id; ?>">
    <?php echo wp_nonce_field('edit_garage_'.$edit_id,'edit_nonce',true,false); ?>
    <?php else: ?>
    <?php wp_nonce_field('dealboard_garage_sale','gs_nonce'); ?>
    <?php endif; ?>

    <!-- SALE DETAILS -->
    <div class="gs-card">
      <div class="gs-section-title">Sale Details</div>
      <div class="gs-group">
        <label class="gs-label">Sale Title <span class="req">*</span></label>
        <input type="text" name="sale_title" class="gs-input"
          placeholder="e.g., Big Spring Garage Sale, Moving Sale, Estate Sale"
          value="<?php echo esc_attr($ev_title); ?>" required>
      </div>
      <div class="gs-group">
        <label class="gs-label">What's for Sale?</label>
        <input type="text" name="sale_items" class="gs-input"
          placeholder="e.g., Furniture, tools, clothing, books, kitchen items..."
          value="<?php echo esc_attr($ev_items); ?>">
      </div>
      <div class="gs-group" style="margin-bottom:0">
        <label class="gs-label">Additional Details</label>
        <textarea name="additional_details" class="gs-input gs-textarea"
          placeholder="Any extra info about the sale, parking, payment methods accepted..."><?php echo esc_textarea($ev_desc); ?></textarea>
      </div>
    </div>

    <!-- DATE & TIME -->
    <div class="gs-card">
      <div class="gs-section-title">Date &amp; Time</div>
      <div class="gs-grid-2">
        <div class="gs-group">
          <label class="gs-label">Start Date <span class="req">*</span></label>
          <input type="date" name="sale_date_start" class="gs-input"
            value="<?php echo esc_attr($ev_date); ?>" required>
        </div>
        <div class="gs-group">
          <label class="gs-label">End Date <span class="req">*</span></label>
          <input type="date" name="sale_date_end" class="gs-input"
            value="<?php echo esc_attr($ev_date_end); ?>" required>
        </div>
      </div>
      <div class="gs-grid-2">
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">Start Time</label>
          <input type="time" name="sale_time_start" class="gs-input" value="<?php echo esc_attr($ev_time_s); ?>">
        </div>
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">End Time</label>
          <input type="time" name="sale_time_end" class="gs-input" value="<?php echo esc_attr($ev_time_e); ?>">
        </div>
      </div>
    </div>

    <!-- LOCATION -->
    <div class="gs-card">
      <div class="gs-section-title">Location <span class="req">*</span></div>
      <div class="gs-grid-2" style="margin-bottom:14px">
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">Country</label>
          <select name="sale_country" class="gs-input gs-select">
            <?php foreach($countries as $code=>$info): ?>
            <option value="<?php echo $code; ?>" <?php selected($ev_country,$code); ?>>
              <?php echo strtoupper($code).' '.$info['name']; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">Currency</label>
          <select name="sale_currency" class="gs-input gs-select">
            <?php foreach($currencies as $cur): ?>
            <option value="<?php echo $cur; ?>" <?php selected($ev_currency,$cur); ?>><?php echo $cur; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="gs-group">
        <label class="gs-label">Street Address</label>
        <input type="text" name="sale_address" class="gs-input"
          placeholder="123 Main Street" value="<?php echo esc_attr($ev_address); ?>">
      </div>
      <div class="gs-grid-3">
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">City</label>
          <input type="text" name="sale_city" class="gs-input" placeholder="City" value="<?php echo esc_attr($ev_city); ?>">
        </div>
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">State</label>
          <input type="text" name="sale_state" class="gs-input" placeholder="State" value="<?php echo esc_attr($ev_state); ?>">
        </div>
        <div class="gs-group" style="margin-bottom:0">
          <label class="gs-label">ZIP</label>
          <input type="text" name="sale_zip" class="gs-input" placeholder="ZIP" value="<?php echo esc_attr($ev_zip); ?>">
        </div>
      </div>
    </div>

    <!-- PHOTOS (new only) -->
    <?php
// Show existing photos in edit mode
if($is_edit):
  $existing_imgs = get_attached_media('image', $edit_id);
  if(!empty($existing_imgs)):
?>
<div class="gs-card">
  <div class="gs-section-title">Current Photos</div>
  <p style="font-size:13px;color:#6B7280;margin-bottom:12px">Upload new photos below to replace existing ones.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach($existing_imgs as $img): ?>
    <img src="<?php echo esc_url(wp_get_attachment_url($img->ID)); ?>"
      style="width:80px;height:80px;border-radius:8px;object-fit:cover;border:2px solid #E5E7EB">
    <?php endforeach; ?>
  </div>
</div>
<?php endif; endif; ?>
    <div class="gs-card">
      <div class="gs-section-title">Photos</div>
      <p style="font-size:13px;color:#6B7280;margin-bottom:14px">Add photos of your sale items. Up to 5 photos.</p>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <input type="text" id="gs-photo-url" class="gs-input" placeholder="https://...">
        <button type="button" onclick="gsAddPhoto()"
          style="padding:10px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap">+ Add</button>
      </div>
      <div>
        <input type="file" id="gs-upload" multiple accept="image/*" style="display:none" onchange="gsHandleUpload(this)">
        <button type="button" onclick="document.getElementById('gs-upload').click()"
          style="display:flex;align-items:center;gap:6px;padding:9px 16px;border:1.5px dashed #D1D5DB;border-radius:8px;background:#F9FAFB;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit">
          📷 Upload Photos
        </button>
      </div>
      <div id="gs-photo-strip" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"></div>
      <input type="hidden" name="photo_urls" id="gs-photo-hidden">
    </div>

    <!-- CONTACT -->
    <div class="gs-card">
      <div class="gs-section-title">Contact Information</div>
      <div class="gs-group">
        <label class="gs-label">Your Name</label>
        <input type="text" name="sale_contact_name" class="gs-input"
          placeholder="Your name"
          value="<?php echo esc_attr($ev_contact ?: wp_get_current_user()->display_name); ?>">
      </div>
      <div class="gs-group" style="margin-bottom:0">
        <label class="gs-label">Phone</label>
        <input type="tel" name="sale_phone" class="gs-input"
          placeholder="+1 555 000 0000" value="<?php echo esc_attr($ev_phone); ?>">
      </div>
    </div>

    <button type="submit" class="gs-btn-submit">
      <?php echo $is_edit ? '💾 Save Changes' : '🏡 List My Garage Sale'; ?>
    </button>
    <?php if(!$is_edit): ?>
    <p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:10px">
      Your listing will be visible to local shoppers immediately.
    </p>
    <?php endif; ?>
  </form>

  <?php endif; ?>
</div>
</div>

<script>
var gsPhotos = [];
function gsAddPhoto() {
  var url = document.getElementById('gs-photo-url').value.trim();
  if (!url || gsPhotos.length >= 5) return;
  gsPhotos.push(url); gsRender();
  document.getElementById('gs-photo-url').value = '';
}
function gsHandleUpload(input) {
  Array.from(input.files).slice(0, 5 - gsPhotos.length).forEach(function(f) {
    var r = new FileReader();
    r.onload = function(e) { gsPhotos.push(e.target.result); gsRender(); };
    r.readAsDataURL(f);
  });
}
function gsRender() {
  var strip = document.getElementById('gs-photo-strip');
  if (!strip) return;
  strip.innerHTML = '';
  gsPhotos.forEach(function(url, i) {
    var img = document.createElement('img');
    img.src = url; img.className = 'gs-thumb'; img.title = 'Click to remove';
    img.onclick = function() { gsPhotos.splice(i,1); gsRender(); };
    strip.appendChild(img);
  });
  document.getElementById('gs-photo-hidden').value = gsPhotos.join('\n');
}
</script>

<?php get_footer(); ?>