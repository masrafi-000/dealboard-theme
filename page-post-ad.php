<?php
/*
Template Name: Post Ad
*/
get_header();

// ===== EDIT MODE SETUP =====
$edit_id   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_post = null;
$is_edit   = false;

if ($edit_id && is_user_logged_in()) {
  $ep = get_post($edit_id);
  if ($ep && $ep->post_author == get_current_user_id() && $ep->post_type === 'listing') {
    $edit_post = $ep;
    $is_edit   = true;
  }
}

// Pre-load edit meta
$ev_title     = $is_edit ? $edit_post->post_title : '';
$ev_desc      = $is_edit ? $edit_post->post_content : '';
$ev_price     = $is_edit ? get_post_meta($edit_id,'listing_price',true) : '';
$ev_currency  = $is_edit ? get_post_meta($edit_id,'listing_currency',true) : 'USD';
$ev_city      = $is_edit ? get_post_meta($edit_id,'listing_city',true) : '';
$ev_state     = $is_edit ? get_post_meta($edit_id,'listing_state',true) : '';
$ev_country   = $is_edit ? get_post_meta($edit_id,'listing_country',true) : 'us';
$ev_phone     = $is_edit ? get_post_meta($edit_id,'listing_phone',true) : '';
$ev_email     = $is_edit ? get_post_meta($edit_id,'listing_email',true) : '';
$ev_condition = $is_edit ? get_post_meta($edit_id,'listing_condition',true) : '';
$ev_cats      = $is_edit ? wp_get_post_terms($edit_id,'listing_category') : [];
$ev_cat_id    = !empty($ev_cats) ? $ev_cats[0]->term_id : '';

// ===== HANDLE EDIT SUBMISSION =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['is_edit_submit']) && is_user_logged_in()) {
  $eid   = (int)($_POST['edit_post_id'] ?? 0);
  $epost = get_post($eid);
  if ($epost && $epost->post_author==get_current_user_id() && wp_verify_nonce($_POST['edit_nonce']??'','edit_listing_'.$eid)) {
    wp_update_post([
      'ID'           => $eid,
      'post_title'   => sanitize_text_field($_POST['title'] ?? ''),
      'post_content' => sanitize_textarea_field($_POST['description'] ?? ''),
    ]);
    $meta_fields = ['listing_price','listing_currency','listing_city','listing_state','listing_country','listing_phone','listing_email','listing_contact_name','listing_condition'];
    foreach($meta_fields as $mf) {
      if(isset($_POST[$mf])) update_post_meta($eid, $mf, sanitize_text_field($_POST[$mf]));
    }
    if(!empty($_POST['listing_category'])) {
      wp_set_post_terms($eid, [(int)$_POST['listing_category']], 'listing_category');
    }
    $photo_urls_raw = $_POST['photo_urls'] ?? '';
    if (!empty($photo_urls_raw)) {
      $existing_images = get_attached_media('image', $eid);
      foreach($existing_images as $att) wp_delete_attachment($att->ID, true);
      delete_post_thumbnail($eid);
      $photos    = array_filter(array_map('trim', explode("\n", $photo_urls_raw)));
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
          $aid = wp_insert_attachment(['post_mime_type'=>$ft['type'],'post_title'=>$filename,'post_content'=>'','post_status'=>'inherit','post_parent'=>$eid],$filepath,$eid);
          require_once ABSPATH.'wp-admin/includes/image.php';
          wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $filepath));
          if($first){ set_post_thumbnail($eid, $aid); $first = false; }
        }
      }
    }
    wp_redirect(home_url('/dashboard/?updated=1')); exit;
  }
}

// ===== HANDLE NEW LISTING SUBMISSION =====
$error_msg   = '';
$pending_pay = false; // will be set to listing ID when business plan needs Stripe

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dealboard_post_nonce']) && !isset($_POST['is_edit_submit'])) {
  if (!wp_verify_nonce($_POST['dealboard_post_nonce'], 'dealboard_post_listing')) {
    $error_msg = 'Security check failed.';
  } elseif (!is_user_logged_in()) {
    $error_msg = 'You must be logged in to post.';
  } else {
    $title = sanitize_text_field($_POST['title'] ?? '');
    if (empty($title)) {
      $error_msg = 'Title is required.';
    } else {
      $plan        = sanitize_text_field($_POST['post_plan'] ?? 'personal');
      $expiry_days = ($plan === 'business') ? 30 : 14;
      $post_status = ($plan === 'business') ? 'pending' : 'publish';
      $post_id = wp_insert_post([
        'post_type'    => 'listing',
        'post_title'   => $title,
        'post_content' => sanitize_textarea_field($_POST['description'] ?? ''),
        'post_status'  => $post_status,
        'post_author'  => get_current_user_id(),
      ]);
      if (!is_wp_error($post_id)) {
        $meta_fields = ['listing_price','listing_currency','listing_city','listing_state','listing_country','listing_phone','listing_email','listing_contact_name','listing_price_type','listing_condition','listing_lat','listing_lng','listing_map_address'];
        foreach($meta_fields as $mf) {
          if(isset($_POST[$mf])) update_post_meta($post_id, $mf, sanitize_text_field($_POST[$mf]));
        }
        update_post_meta($post_id, 'listing_plan', $plan);
        update_post_meta($post_id, 'listing_expires', date('Y-m-d', strtotime("+{$expiry_days} days")));
        if($plan === 'business') {
          update_post_meta($post_id, 'listing_status', 'pending_payment');
          update_post_meta($post_id, 'listing_featured', '1');
          update_post_meta($post_id, 'listing_business', '1');
        } else {
          update_post_meta($post_id, 'listing_status', 'active');
        }
        if(!empty($_POST['listing_category'])) {
          wp_set_post_terms($post_id, [(int)$_POST['listing_category']], 'listing_category');
        }
        $max_photos     = ($plan==='business') ? 20 : 7;
        $photo_urls_raw = $_POST['photo_urls'] ?? '';
        if(!empty($photo_urls_raw)) {
          $photos = array_filter(array_map('trim', explode("\n", $photo_urls_raw)));
          $photos = array_slice($photos, 0, $max_photos);
          update_post_meta($post_id, 'listing_photo_urls', implode("\n", $photos));
          $first = true;
          foreach($photos as $photo_data) {
            if(strpos($photo_data,'data:image')===0) {
              preg_match('/data:image\/(\w+);base64,/', $photo_data, $m);
              $ext      = $m[1]??'jpg';
              $img_data = base64_decode(preg_replace('/^data:image\/\w+;base64,/','',$photo_data));
              $upload   = wp_upload_dir();
              $filename = 'listing-'.$post_id.'-'.uniqid().'.'.$ext;
              $filepath = $upload['path'].'/'.$filename;
              file_put_contents($filepath, $img_data);
              $ft  = wp_check_filetype($filename);
              $aid = wp_insert_attachment(['post_mime_type'=>$ft['type'],'post_title'=>$filename,'post_content'=>'','post_status'=>'inherit'],$filepath,$post_id);
              require_once ABSPATH.'wp-admin/includes/image.php';
              wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid,$filepath));
              if($first){set_post_thumbnail($post_id,$aid);$first=false;}
            } elseif(filter_var($photo_data,FILTER_VALIDATE_URL)&&$first) {
              require_once ABSPATH.'wp-admin/includes/media.php';
              require_once ABSPATH.'wp-admin/includes/file.php';
              require_once ABSPATH.'wp-admin/includes/image.php';
              $aid = media_sideload_image($photo_data,$post_id,null,'id');
              if(!is_wp_error($aid)){set_post_thumbnail($post_id,$aid);$first=false;}
            }
          }
        }
        $video_data = $_POST['video_url']??'';
        if(!empty($video_data) && strpos($video_data,'data:video')===0) {
          preg_match('/data:video\/(\w+);base64,/',$video_data,$vm);
          $vext   = $vm[1]??'mp4';
          $v_data = base64_decode(preg_replace('/^data:video\/\w+;base64,/','',$video_data));
          $vup    = wp_upload_dir();
          $vf     = 'listing-video-'.$post_id.'-'.uniqid().'.'.$vext;
          $vp     = $vup['path'].'/'.$vf;
          file_put_contents($vp,$v_data);
          $vft  = wp_check_filetype($vf);
          $vaid = wp_insert_attachment(['post_mime_type'=>$vft['type'],'post_title'=>$vf,'post_content'=>'','post_status'=>'inherit'],$vp,$post_id);
          if(!is_wp_error($vaid)) update_post_meta($post_id,'listing_video_url',wp_get_attachment_url($vaid));
        }
        if($plan === 'business') {
          // Listing saved. Use AJAX + JS to create the Stripe checkout session
          // and redirect the browser — avoids nonce/redirect-chain issues.
          $pending_pay = $post_id;
        } else {
          wp_redirect(get_permalink($post_id));
          exit;
        }
      } else { $error_msg = 'Failed to create listing.'; }
    }
  }
}

$categories = get_terms(['taxonomy'=>'listing_category','hide_empty'=>false,'parent'=>0]);
$countries = [
  'us'=>['name'=>'United States','currency'=>'USD','phone'=>'+1'],
  'gb'=>['name'=>'United Kingdom','currency'=>'GBP','phone'=>'+44'],
  'ca'=>['name'=>'Canada','currency'=>'CAD','phone'=>'+1'],
  'au'=>['name'=>'Australia','currency'=>'AUD','phone'=>'+61'],
  'eu'=>['name'=>'European Union','currency'=>'EUR','phone'=>'+'],
  'bd'=>['name'=>'Bangladesh','currency'=>'BDT','phone'=>'+880'],
  'ng'=>['name'=>'Nigeria','currency'=>'NGN','phone'=>'+234'],
  'gh'=>['name'=>'Ghana','currency'=>'GHS','phone'=>'+233'],
  'ke'=>['name'=>'Kenya','currency'=>'KES','phone'=>'+254'],
  'za'=>['name'=>'South Africa','currency'=>'ZAR','phone'=>'+27'],
  'in'=>['name'=>'India','currency'=>'INR','phone'=>'+91'],
  'pk'=>['name'=>'Pakistan','currency'=>'PKR','phone'=>'+92'],
  'ae'=>['name'=>'UAE','currency'=>'AED','phone'=>'+971'],
  'om'=>['name'=>'Oman','currency'=>'OMR','phone'=>'+968'],
  'bh'=>['name'=>'Bahrain','currency'=>'BHD','phone'=>'+973'],
  'kw'=>['name'=>'Kuwait','currency'=>'KWD','phone'=>'+965'],
  'qa'=>['name'=>'Qatar','currency'=>'QAR','phone'=>'+974'],
  'sa'=>['name'=>'Saudi Arabia','currency'=>'SAR','phone'=>'+966'],
  'eg'=>['name'=>'Egypt','currency'=>'EGP','phone'=>'+20'],
  'ma'=>['name'=>'Morocco','currency'=>'MAD','phone'=>'+212'],
];
$currencies = [
  'USD'=>'🇺🇸 USD — US Dollar','GBP'=>'🇬🇧 GBP — British Pound',
  'CAD'=>'🇨🇦 CAD — Canadian Dollar','AUD'=>'🇦🇺 AUD — Australian Dollar',
  'EUR'=>'🇪🇺 EUR — Euro','BDT'=>'🇧🇩 BDT — Bangladeshi Taka',
  'NGN'=>'🇳🇬 NGN — Nigerian Naira','GHS'=>'🇬🇭 GHS — Ghanaian Cedi',
  'KES'=>'🇰🇪 KES — Kenyan Shilling','ZAR'=>'🇿🇦 ZAR — South African Rand',
  'INR'=>'🇮🇳 INR — Indian Rupee','PKR'=>'🇵🇰 PKR — Pakistani Rupee',
  'AED'=>'🇦🇪 AED — UAE Dirham','OMR'=>'🇴🇲 OMR — Omani Rial',
  'BHD'=>'🇧🇭 BHD — Bahraini Dinar','KWD'=>'🇰🇼 KWD — Kuwaiti Dinar',
  'QAR'=>'🇶🇦 QAR — Qatari Riyal','SAR'=>'🇸🇦 SAR — Saudi Riyal',
  'EGP'=>'🇪🇬 EGP — Egyptian Pound','MAD'=>'🇲🇦 MAD — Moroccan Dirham',
];
$payment_methods = [
  ['id'=>'cash','label'=>'Cash','icon'=>'💵'],
  ['id'=>'paypal','label'=>'PayPal','icon'=>'🅿️'],
  ['id'=>'venmo','label'=>'Venmo','icon'=>'💙'],
  ['id'=>'zelle','label'=>'Zelle','icon'=>'💜'],
  ['id'=>'bank_transfer','label'=>'Bank Transfer','icon'=>'🏦'],
  ['id'=>'credit_card','label'=>'Credit Card','icon'=>'💳'],
  ['id'=>'wire_transfer','label'=>'Wire Transfer','icon'=>'🔁'],
  ['id'=>'crypto','label'=>'Cryptocurrency','icon'=>'₿'],
  ['id'=>'revolut','label'=>'Revolut','icon'=>'🔵'],
  ['id'=>'wise','label'=>'Wise','icon'=>'🟢'],
  ['id'=>'apple_pay','label'=>'Apple Pay','icon'=>'🍎'],
  ['id'=>'google_pay','label'=>'Google Pay','icon'=>'🟡'],
  ['id'=>'benefit_pay','label'=>'BenefitPay','icon'=>'🔶'],
];
$conditions = [
  'new'  => '✨ New',
  'used' => '🔧 Used',
];
?>

<style>
.post-ad-wrap{background:#F9FAFB;min-height:100vh;padding:0 0 60px}
.post-ad-inner{max-width:700px;margin:0 auto;padding:0 20px}
.post-ad-back{display:inline-flex;align-items:center;gap:6px;padding:24px 0 20px;font-size:14px;color:#6B7280;text-decoration:none}
.post-ad-back:hover{color:#111}
.post-ad-h1{font-size:26px;font-weight:800;color:#111827;margin-bottom:4px}
.post-ad-sub{font-size:14px;color:#6B7280;margin-bottom:24px}
.pad-card{background:white;border:1px solid #E5E7EB;border-radius:12px;padding:28px;margin-bottom:16px}
.pad-section-title{font-size:15px;font-weight:700;color:#111827;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #F3F4F6}
.pad-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px}
.pad-input{width:100%;padding:10px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:14px;color:#111;outline:none;background:white;transition:border-color .15s;font-family:inherit}
.pad-input:focus{border-color:#C8102E;box-shadow:0 0 0 3px rgba(200,16,46,.1)}
.pad-input::placeholder{color:#9CA3AF}
.pad-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;cursor:pointer}
.pad-textarea{min-height:110px;resize:vertical;line-height:1.6}
.pad-group{margin-bottom:18px}
.price-type-group{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.price-type-btn{padding:8px 18px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;color:#374151;background:white;cursor:pointer;transition:all .15s;font-family:inherit}
.price-type-btn.active{background:#C8102E;border-color:#C8102E;color:white}
.price-currency-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.price-input-wrap{position:relative}
.price-symbol{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;color:#374151;font-weight:600;white-space:nowrap;pointer-events:none;z-index:1}
.price-input-wrap .pad-input{padding-left:52px}
.payment-methods-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
.payment-method-btn{display:flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;font-weight:600;color:#374151;background:white;cursor:pointer;transition:all .15s;user-select:none}
.payment-method-btn.selected{border-color:#C8102E;background:#FEE2E2;color:#C8102E}
.condition-group{display:flex;gap:8px;flex-wrap:wrap}
.condition-label{display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid #E5E7EB;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;transition:all .15s;background:white;color:#374151}
.condition-label:hover{border-color:#C8102E}
.condition-label input{display:none}
.condition-label.checked{border-color:#C8102E;background:#FEE2E2;color:#C8102E;font-weight:600}
.location-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.photo-url-row{display:flex;gap:8px;margin-bottom:12px}
.photo-url-row .pad-input{flex:1}
.btn-add-photo{padding:10px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;white-space:nowrap;font-family:inherit}
.photo-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid #E5E7EB;cursor:pointer}
.photo-thumb:hover{border-color:#EF4444}
.upload-btn{display:flex;align-items:center;gap:6px;padding:9px 16px;border:1.5px dashed #D1D5DB;border-radius:8px;background:#F9FAFB;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit;transition:all .15s}
.upload-btn:hover{border-color:#C8102E;color:#C8102E}
.contact-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.phone-input-wrap{display:flex}
.phone-code{padding:10px 12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-right:none;border-radius:8px 0 0 8px;font-size:13px;color:#374151;font-weight:600;white-space:nowrap}
.phone-input-wrap .pad-input{border-radius:0 8px 8px 0}
.btn-post-listing{width:100%;padding:15px;background:#C8102E;color:white;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:8px;font-family:inherit}
.btn-post-listing:hover{background:#A50E26}
.alert-error-box{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;font-size:14px;margin-bottom:16px}
.plan-selector{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.plan-option{position:relative;cursor:pointer}
.plan-option input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.plan-card{border:2px solid #E5E7EB;border-radius:12px;padding:18px 14px;text-align:center;transition:all .15s;background:white;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;box-sizing:border-box}
.plan-option input:checked+.plan-card{border-color:#C8102E;background:#FEE2E2}
.plan-icon{font-size:26px}
.plan-name{font-size:14px;font-weight:700;color:#111}
.plan-price{font-size:13px;font-weight:700;color:#C8102E}
.plan-note{font-size:11px;color:#6B7280;line-height:1.5;background:#F9FAFB;border-radius:6px;padding:8px 10px;width:100%;text-align:left;margin-top:4px}
.plan-option input:checked+.plan-card .plan-note{background:#FEE2E2}
.plan-check{width:20px;height:20px;border:2px solid #E5E7EB;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all .15s;margin-top:4px}
.plan-option input:checked+.plan-card .plan-check{background:#C8102E;border-color:#C8102E}
.plan-option input:checked+.plan-card .plan-check::after{content:'✓';color:white;font-size:11px;font-weight:700}
#business-payment{display:none;background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:16px;margin-bottom:16px}
@media(max-width:600px){.price-currency-row,.location-grid,.contact-row,.plan-selector{grid-template-columns:1fr}}
	
/* Desktop: show desktop, hide mobile */
#upload-btns-desktop { display: flex !important; flex-wrap: wrap; }
#upload-btns-mobile  { display: none !important; }

/* Mobile: show mobile, hide desktop */
@media(max-width:768px) {
  #upload-btns-desktop { display: none !important; }
  #upload-btns-mobile  { display: flex !important; flex-wrap: wrap; }
}

@media(max-width:768px) {
  .cat-tabs-wrapper { display: none !important; }
  .subcat-row { display: none !important; }
}
</style>

<div class="post-ad-wrap">
<div class="post-ad-inner">

  <a href="<?php echo esc_url($is_edit ? home_url('/dashboard') : home_url('/')); ?>" class="post-ad-back">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M15 18l-6-6 6-6"/></svg>
    <?php echo $is_edit ? 'Back to Dashboard' : 'Back'; ?>
  </a>

  <h1 class="post-ad-h1"><?php echo $is_edit ? '✏️ Edit Listing' : 'Post a Classified Ad'; ?></h1>
  <p class="post-ad-sub"><?php echo $is_edit ? 'Update your listing details below.' : 'List your item for free — visible to buyers worldwide.'; ?></p>

  <?php if($error_msg): ?><div class="alert-error-box">❌ <?php echo esc_html($error_msg); ?></div><?php endif; ?>

  <?php if(!is_user_logged_in()): ?>
  <div class="pad-card" style="text-align:center;padding:48px">
    <div style="font-size:48px;margin-bottom:12px">🔒</div>
    <h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Sign in to Post an Ad</h2>
    <a href="<?php echo esc_url(home_url('/sign-in?redirect='.urlencode(home_url('/post-ad')))); ?>"
       style="display:inline-block;padding:12px 28px;background:#C8102E;color:white;font-weight:700;border-radius:8px">Sign In</a>
  </div>
  <?php else: ?>

  <form method="POST" id="post-ad-form">
    <?php wp_nonce_field('dealboard_post_listing','dealboard_post_nonce'); ?>

    <?php if($is_edit): ?>
    <input type="hidden" name="is_edit_submit" value="1">
    <input type="hidden" name="edit_post_id" value="<?php echo $edit_id; ?>">
    <?php echo wp_nonce_field('edit_listing_'.$edit_id,'edit_nonce',true,false); ?>
    <?php else: ?>
    <div style="margin-bottom:16px">
      <h2 style="font-size:16px;font-weight:700;color:#111;margin-bottom:4px">Choose Your Plan</h2>
      <p style="font-size:13px;color:#6B7280;margin-bottom:14px">Select how you want to post your ad</p>
      <div class="plan-selector">
        <label class="plan-option">
          <input type="radio" name="post_plan" value="personal" id="plan-personal" checked onchange="togglePlan(this)">
          <div class="plan-card">
            <div class="plan-icon">👤</div>
            <div class="plan-name">Personal</div>
            <div class="plan-price">Free</div>
            <div class="plan-note">✅ Active 14 days<br>✅ Up to 7 photos<br>✅ 1 video (10 sec)<br>⚠️ Auto-deleted after 14 days</div>
            <div class="plan-check"></div>
          </div>
        </label>
        <label class="plan-option">
          <input type="radio" name="post_plan" value="business" id="plan-business" onchange="togglePlan(this)">
          <div class="plan-card">
            <div class="plan-icon">🏢</div>
            <div class="plan-name">Business</div>
            <div class="plan-price">$2 / month</div>
            <div class="plan-note">✅ Active 1 month<br>✅ Up to 20 photos<br>✅ Featured placement<br>✅ Business badge</div>
            <div class="plan-check"></div>
          </div>
        </label>
      </div>
      <div id="business-payment">
        <strong style="color:#92400E">💳 Business Plan — $2 every 30 days</strong>
        <p style="font-size:13px;color:#92400E;margin:8px 0 0">
          After you post, you'll be taken to our secure <strong>Stripe</strong> checkout to pay <strong>$2</strong>.
          Your ad stays live for 30 days and renews automatically for $2 each cycle.
          You can turn off auto-payment anytime from your dashboard — your ad then stays
          visible until the 30 days end, after which it stops showing.
        </p>
      </div>
    </div>
    <?php endif; ?>

    <!-- ITEM DETAILS -->
    <div class="pad-card">
      <div class="pad-section-title">Item Details</div>
      <div class="pad-group">
        <label class="pad-label">Title *</label>
        <input type="text" name="title" class="pad-input"
          placeholder="e.g., iPhone 13, Honda Civic, Dining Table"
          value="<?php echo esc_attr($ev_title); ?>" required>
      </div>
      <div class="pad-group">
        <label class="pad-label">Category</label>
        <select name="listing_category" class="pad-input pad-select">
          <option value="">Select a category...</option>
          <?php if(!is_wp_error($categories) && $categories): foreach($categories as $cat): ?>
          <option value="<?php echo $cat->term_id; ?>" <?php selected($ev_cat_id, $cat->term_id); ?>>
            <?php echo esc_html($cat->name); ?>
          </option>
          <?php endforeach; endif; ?>
        </select>
      </div>
      <div class="pad-group">
        <label class="pad-label">Condition</label>
        <div class="condition-group">
          <?php foreach($conditions as $val => $label): ?>
          <label class="condition-label <?php echo $ev_condition===$val ? 'checked' : ''; ?>" onclick="selectCondition(this)">
            <input type="radio" name="listing_condition" value="<?php echo $val; ?>" <?php checked($ev_condition,$val); ?>>
            <?php echo $label; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="pad-group">
        <label class="pad-label">Description</label>
        <textarea name="description" class="pad-input pad-textarea"
          placeholder="Describe the item, why you're selling..."><?php echo esc_textarea($ev_desc); ?></textarea>
      </div>
    </div>

    <!-- PRICING -->
    <div class="pad-card">
      <div class="pad-section-title">Pricing &amp; Currency</div>
      <label class="pad-label">Price Type</label>
      <div class="price-type-group">
        <?php foreach(['fixed'=>'Fixed Price','best_offer'=>'Best Offer','negotiable'=>'Negotiable','free'=>'Free'] as $v=>$l): ?>
        <button type="button" class="price-type-btn <?php echo $v==='fixed'?'active':''; ?>"
          data-val="<?php echo $v; ?>" onclick="setPriceType(this)"><?php echo $l; ?></button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="listing_price_type" id="price_type_input" value="fixed">
      <div class="price-currency-row" id="price-row">
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">Price</label>
          <div class="price-input-wrap">
            <span class="price-symbol" id="currency-symbol">$</span>
            <input type="number" name="listing_price" class="pad-input"
              placeholder="0.00" min="0" step="0.01"
              value="<?php echo esc_attr($ev_price); ?>">
          </div>
        </div>
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">Currency</label>
          <select name="listing_currency" id="currency-select" class="pad-input pad-select" onchange="updateCurrencySymbol()">
            <?php foreach($currencies as $code=>$label): ?>
            <option value="<?php echo $code; ?>" <?php selected($ev_currency,$code); ?>>
              <?php echo esc_html($label); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="pad-group" style="margin-top:18px">
        <label class="pad-label">Accepted Payment Methods</label>
        <div class="payment-methods-grid">
          <?php foreach($payment_methods as $pm): ?>
          <div class="payment-method-btn" onclick="togglePayment(this,'<?php echo $pm['id']; ?>')">
            <span><?php echo $pm['icon']; ?></span><span><?php echo esc_html($pm['label']); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="listing_payment_methods" id="payment_methods_input" value="">
      </div>
    </div>

    <!-- LOCATION -->
    <div class="pad-card">
      <div class="pad-section-title">Location</div>
      <div class="pad-group">
        <label class="pad-label">Country</label>
        <select name="listing_country" id="country-select" class="pad-input pad-select" onchange="updateCountry()">
          <?php foreach($countries as $code=>$info): ?>
          <option value="<?php echo $code; ?>" data-currency="<?php echo $info['currency']; ?>" data-phone="<?php echo $info['phone']; ?>"
            <?php selected($ev_country,$code); ?>><?php echo strtoupper($code).' '.$info['name']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="location-grid">
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">City *</label>
          <input type="text" name="listing_city" class="pad-input"
            placeholder="e.g., London" value="<?php echo esc_attr($ev_city); ?>" required>
        </div>
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">State / Province</label>
          <input type="text" name="listing_state" class="pad-input"
            placeholder="e.g., TX" value="<?php echo esc_attr($ev_state); ?>">
        </div>
      </div>
		<div class="pad-group" style="margin-top:14px">
  <label class="pad-label">Pin Location on Map <span style="font-size:11px;color:#9CA3AF">(optional)</span></label>
  <div style="position:relative;margin-bottom:8px">
    <input type="text" id="map-search-input" class="pad-input"
      placeholder="Search address..." style="padding-right:80px">
    <button type="button" onclick="searchMapAddress()"
      style="position:absolute;right:6px;top:50%;transform:translateY(-50%);padding:5px 12px;background:#C8102E;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">
      Search
    </button>
  </div>
  <div id="listing-map" style="height:220px;border-radius:10px;border:1.5px solid #E5E7EB;overflow:hidden"></div>
  <input type="hidden" name="listing_lat" id="listing_lat" value="">
  <input type="hidden" name="listing_lng" id="listing_lng" value="">
  <input type="hidden" name="listing_map_address" id="listing_map_address" value="">
  <div id="map-selected-addr" style="font-size:12px;color:#059669;margin-top:6px;display:none">
    📍 <span id="map-addr-text"></span>
    <a href="#" onclick="clearMapPin();return false" style="color:#EF4444;margin-left:8px;font-size:11px">✕ Clear</a>
  </div>
</div>
      <div style="font-size:12px;color:#6B7280;margin-top:6px" id="country-note">US United States — Default currency: USD</div>
    </div>

    <!-- PHOTOS & VIDEO -->
    <div class="pad-card">
      <div class="pad-section-title">Photos &amp; Video</div>
      <div style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px">
        <span>📸 <strong id="photo-limit-text">Up to 7 photos</strong></span> &nbsp;
        <span>🎬 <strong>1 video (max 10 sec)</strong></span> &nbsp;
        <span>⏱ <strong id="expire-limit-text">Active 14 days</strong></span>
      </div>

      <?php if($is_edit):
        $existing_imgs = get_attached_media('image', $edit_id);
        if(!empty($existing_imgs)): ?>
      <div style="margin-bottom:14px">
        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Current Photos:</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach($existing_imgs as $img): ?>
          <img src="<?php echo esc_url(wp_get_attachment_url($img->ID)); ?>"
            style="width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid #E5E7EB">
          <?php endforeach; ?>
        </div>
        <p style="font-size:12px;color:#9CA3AF;margin-top:6px">Upload new photos below to replace existing ones.</p>
      </div>
      <?php endif; endif; ?>

      <div class="photo-url-row">
        <input type="text" id="photo-url-input" class="pad-input" placeholder="https://... (paste URL)">
        <button type="button" class="btn-add-photo" onclick="addPhotoUrl()">+ Add</button>
      </div>

<!-- Hidden file inputs -->
<input type="file" id="photo-upload" multiple accept="image/*" style="display:none" onchange="handlePhotoUpload(this)">
<input type="file" id="photo-camera" multiple accept="image/*" capture="environment" style="display:none" onchange="handlePhotoUpload(this)">
<input type="file" id="video-upload" accept="video/mp4,video/quicktime,video/webm" style="display:none" onchange="handleVideoUpload(this)">
<input type="file" id="video-camera" accept="video/mp4,video/quicktime,video/webm" capture="environment" style="display:none" onchange="handleVideoUpload(this)">

<!-- Desktop buttons -->
<div id="upload-btns-desktop" style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap">
  <button type="button" class="upload-btn" onclick="document.getElementById('photo-upload').click()">📷 Upload Photos</button>
  <button type="button" class="upload-btn" onclick="document.getElementById('video-upload').click()">🎬 Upload Video <small style="color:#9CA3AF">(max 10s)</small></button>
</div>

<!-- Mobile buttons -->
<div id="upload-btns-mobile" style="display:none;gap:10px;margin-bottom:12px;flex-wrap:wrap">
  <button type="button" class="upload-btn" onclick="document.getElementById('photo-upload').click()">🖼️ Gallery</button>
  <button type="button" class="upload-btn" onclick="document.getElementById('photo-camera').click()">📷 Camera</button>
  <button type="button" class="upload-btn" onclick="document.getElementById('video-upload').click()">🎬 Video Gallery</button>
  <button type="button" class="upload-btn" onclick="document.getElementById('video-camera').click()">🎥 Record Video</button>
</div>

      <div id="photo-counter" style="font-size:12px;color:#9CA3AF;margin-bottom:8px">0 / 7 photos</div>
      <div id="photo-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px"></div>
      <input type="hidden" name="photo_urls" id="photo_urls_hidden" value="">

      <div id="video-preview-wrap" style="display:none;margin-top:8px">
        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">🎬 Video Preview:</div>
        <video id="video-preview" controls style="max-width:220px;border-radius:8px;border:2px solid #C8102E"></video>
        <div id="video-status" style="font-size:11px;margin-top:4px;color:#10B981"></div>
        <button type="button" onclick="removeVideo()" style="font-size:11px;color:#EF4444;background:none;border:none;cursor:pointer;padding:0;margin-top:4px">✕ Remove</button>
      </div>
      <input type="hidden" name="video_url" id="video_url_hidden" value="">
    </div>

    <!-- CONTACT -->
    <div class="pad-card">
      <div class="pad-section-title">Contact Information</div>
      <div class="pad-group">
        <label class="pad-label">Your Name</label>
        <input type="text" name="listing_contact_name" class="pad-input"
          placeholder="First name or full name"
          value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
      </div>
      <div class="contact-row">
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">Phone</label>
          <div class="phone-input-wrap">
            <span class="phone-code" id="phone-code">+1</span>
            <input type="tel" name="listing_phone" class="pad-input"
              placeholder="Phone number" value="<?php echo esc_attr($ev_phone); ?>">
          </div>
        </div>
        <div class="pad-group" style="margin-bottom:0">
          <label class="pad-label">Email</label>
          <input type="email" name="listing_email" class="pad-input"
            placeholder="you@example.com"
            value="<?php echo esc_attr($ev_email ?: wp_get_current_user()->user_email); ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="btn-post-listing" id="submit-btn">
      <?php echo $is_edit ? '💾 Save Changes' : 'Post Listing for Free'; ?>
    </button>
    <?php if(!$is_edit): ?>
    <p id="submit-note" style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:10px">
      Free: Active 14 days · Up to 7 photos · 1 video (10 sec) · Auto-deleted unless reposted
    </p>
    <?php endif; ?>
  </form>

  <?php
  // ===== STRIPE CHECKOUT MODAL (business ad) =====
  if ($pending_pay && !$error_msg):
  ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.dbOpenPaymentModal === 'function') {
      window.dbOpenPaymentModal(<?php echo (int)$pending_pay; ?>);
    } else {
      window.location.href = '<?php echo esc_url(home_url("/dashboard/?open_payment_modal=" . $pending_pay)); ?>';
    }
  });
  </script>
  <?php endif; ?>

  <?php endif; ?>
</div>
</div>

<script>
var maxPhotoLimit = 7;
var photoUrls = [];
var selectedPayments = [];

function selectCondition(label) {
  document.querySelectorAll('.condition-label').forEach(function(l) { l.classList.remove('checked'); });
  label.classList.add('checked');
  label.querySelector('input').checked = true;
}

function setPriceType(btn) {
  document.querySelectorAll('.price-type-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('price_type_input').value = btn.dataset.val;
  document.getElementById('price-row').style.display = btn.dataset.val==='free'?'none':'grid';
}

var currencySymbols = {
  'USD':'$','GBP':'£','CAD':'C$','AUD':'A$','EUR':'€','BDT':'৳',
  'NGN':'₦','GHS':'₵','KES':'KSh','ZAR':'R','INR':'₹','PKR':'Rs',
  'AED':'د.إ','OMR':'ر.ع','BHD':'BD','KWD':'KD','QAR':'QR',
  'SAR':'SR','EGP':'E£','MAD':'MAD'
};

function updateCurrencySymbol() {
  var code  = document.getElementById('currency-select').value;
  var sym   = currencySymbols[code] || '$';
  var symEl = document.getElementById('currency-symbol');
  var input = document.querySelector('.price-input-wrap .pad-input');
  symEl.textContent = sym;
  if(input) {
    setTimeout(function() { input.style.paddingLeft = (symEl.offsetWidth + 18) + 'px'; }, 10);
  }
}

function updateCountry() {
  var opt = document.getElementById('country-select').selectedOptions[0];
  document.getElementById('phone-code').textContent = opt.dataset.phone;
  document.getElementById('country-note').textContent = opt.textContent.trim()+' — Default currency: '+opt.dataset.currency;
  var cs = document.getElementById('currency-select');
  for(var i=0;i<cs.options.length;i++){
    if(cs.options[i].value===opt.dataset.currency){cs.selectedIndex=i;break;}
  }
  updateCurrencySymbol();
}

function togglePayment(el,id) {
  el.classList.toggle('selected');
  if(el.classList.contains('selected')){if(!selectedPayments.includes(id))selectedPayments.push(id);}
  else{selectedPayments=selectedPayments.filter(p=>p!==id);}
  document.getElementById('payment_methods_input').value=selectedPayments.join(',');
}

function addPhotoUrl() {
  var url = document.getElementById('photo-url-input').value.trim();
  if(!url||photoUrls.length>=maxPhotoLimit) return;
  photoUrls.push(url);
  renderPhotoPreview();
  document.getElementById('photo-url-input').value='';
}

function handlePhotoUpload(input) {
  var remaining = maxPhotoLimit - photoUrls.length;
  if(remaining<=0){alert('Max '+maxPhotoLimit+' photos for this plan.');return;}
  Array.from(input.files).slice(0,remaining).forEach(file=>{
    var r=new FileReader();
    r.onload=function(e){
      if(photoUrls.length<maxPhotoLimit){photoUrls.push(e.target.result);renderPhotoPreview();}
    };
    r.readAsDataURL(file);
  });
}

function renderPhotoPreview() {
  var strip=document.getElementById('photo-preview');
  strip.innerHTML='';
  var counter=document.getElementById('photo-counter');
  if(counter) counter.textContent=photoUrls.length+' / '+maxPhotoLimit+' photos';
  photoUrls.slice(0,maxPhotoLimit).forEach(function(url,i){
    var img=document.createElement('img');
    img.src=url;img.className='photo-thumb';img.title='Click to remove';
    img.onclick=function(){photoUrls.splice(i,1);renderPhotoPreview();updatePhotoHidden();};
    strip.appendChild(img);
  });
  updatePhotoHidden();
}

function updatePhotoHidden() {
  var h=document.getElementById('photo_urls_hidden');
  if(h) h.value=photoUrls.join('\n');
}

function handleVideoUpload(input) {
  var file=input.files[0]; if(!file) return;
  var v=document.createElement('video'); v.preload='metadata';
  v.onloadedmetadata=function(){
    window.URL.revokeObjectURL(v.src);
    if(v.duration>10){alert('❌ Video must be 10 seconds or less! ('+Math.round(v.duration)+'s)');input.value='';return;}
    var r=new FileReader();
    r.onload=function(e){
      document.getElementById('video-preview').src=e.target.result;
      document.getElementById('video-preview-wrap').style.display='block';
      document.getElementById('video-status').textContent='✅ '+Math.round(v.duration*10)/10+'s — OK';
      document.getElementById('video_url_hidden').value=e.target.result;
    };
    r.readAsDataURL(file);
  };
  v.src=URL.createObjectURL(file);
}

function removeVideo() {
  document.getElementById('video-upload').value='';
  document.getElementById('video_url_hidden').value='';
  document.getElementById('video-preview-wrap').style.display='none';
  document.getElementById('video-preview').src='';
}

function togglePlan(radio) {
  var bp=document.getElementById('business-payment');
  var et=document.getElementById('expire-limit-text');
  var pt=document.getElementById('photo-limit-text');
  var counter=document.getElementById('photo-counter');
  var btn=document.getElementById('submit-btn');
  var note=document.getElementById('submit-note');
  if(radio.value==='business'){
    bp.style.display='block'; maxPhotoLimit=20;
    if(et) et.textContent='Active 1 month';
    if(pt) pt.textContent='Up to 20 photos';
    if(counter) counter.textContent=photoUrls.length+' / 20 photos';
    if(btn) btn.textContent='Post Business Listing — $2/month';
    if(note) note.textContent='Business: Active 1 month · Up to 20 photos · $2/month';
  } else {
    bp.style.display='none'; maxPhotoLimit=7;
    if(et) et.textContent='Active 14 days';
    if(pt) pt.textContent='Up to 7 photos';
    if(counter) counter.textContent=photoUrls.length+' / 7 photos';
    if(btn) btn.textContent='Post Listing for Free';
    if(note) note.textContent='Free: Active 14 days · Up to 7 photos · 1 video (10 sec) · Auto-deleted unless reposted';
  }
}

var urlInput = document.getElementById('photo-url-input');
if(urlInput) urlInput.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();addPhotoUrl();}});

document.addEventListener('DOMContentLoaded', function() {
  updateCurrencySymbol();
});
</script>

<!-- Leaflet CSS & JS — free, no API key -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
var listingMap = null;
var listingMarker = null;

document.addEventListener('DOMContentLoaded', function() {
  // Init map
  listingMap = L.map('listing-map', {zoomControl: true}).setView([37.09, -95.71], 4);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(listingMap);

  // Click to pin
  listingMap.on('click', function(e) {
    setPin(e.latlng.lat, e.latlng.lng);
    reverseGeocode(e.latlng.lat, e.latlng.lng);
  });
});

function searchMapAddress() {
  var q = document.getElementById('map-search-input').value.trim();
  if(!q) return;
  fetch('https://nominatim.openstreetmap.org/search?format=json&q='+encodeURIComponent(q)+'&limit=1')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if(data && data.length > 0) {
        var lat = parseFloat(data[0].lat);
        var lng = parseFloat(data[0].lon);
        var addr = data[0].display_name;
        listingMap.setView([lat, lng], 15);
        setPin(lat, lng);
        setMapValues(lat, lng, addr);
      } else {
        alert('Address not found. Try a different search.');
      }
    })
    .catch(function(){ alert('Search failed. Please try again.'); });
}

function reverseGeocode(lat, lng) {
  fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng)
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if(data && data.display_name) {
        setMapValues(lat, lng, data.display_name);
      } else {
        setMapValues(lat, lng, lat.toFixed(5)+', '+lng.toFixed(5));
      }
    });
}

function setPin(lat, lng) {
  if(listingMarker) listingMap.removeLayer(listingMarker);
  listingMarker = L.marker([lat, lng], {draggable: true}).addTo(listingMap);
  listingMarker.on('dragend', function(e) {
    var pos = e.target.getLatLng();
    reverseGeocode(pos.lat, pos.lng);
  });
}

function setMapValues(lat, lng, addr) {
  document.getElementById('listing_lat').value = lat;
  document.getElementById('listing_lng').value = lng;
  document.getElementById('listing_map_address').value = addr;
  document.getElementById('map-addr-text').textContent = addr;
  document.getElementById('map-selected-addr').style.display = 'block';
}

function clearMapPin() {
  if(listingMarker) { listingMap.removeLayer(listingMarker); listingMarker = null; }
  document.getElementById('listing_lat').value = '';
  document.getElementById('listing_lng').value = '';
  document.getElementById('listing_map_address').value = '';
  document.getElementById('map-selected-addr').style.display = 'none';
}

// Enter key on search
var msi = document.getElementById('map-search-input');
if(msi) msi.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();searchMapAddress();} });
</script>

<?php get_footer(); ?>