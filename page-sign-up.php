<?php
/*
Template Name: Sign Up
*/
get_header();
if(is_user_logged_in()){ wp_redirect(home_url('/dashboard')); exit; }

$error = ''; $notice = '';
$step  = 'register';            // register | verify
$token = '';
$pending_email = '';

/* ---------------------------------------------------------
   Helper: check if an SMTP plugin / mailer is configured.
   wp_mail() on a vanilla XAMPP install uses PHP mail() which
   usually silently fails. We detect this and warn the user.
--------------------------------------------------------- */
function dealboard_smtp_likely_configured() {
    // Popular SMTP plugins that hook into phpmailer_init
    $smtp_plugins = [
        'wp-mail-smtp/wp_mail_smtp.php',
        'post-smtp/postman-smtp.php',
        'easy-wp-smtp/easy-wp-smtp.php',
        'smtp-mailer/smtp-mailer.php',
        'fluent-smtp/fluent-smtp.php',
        'sendgrid-email-delivery-simplified/wpsendgrid.php',
    ];
    foreach ( $smtp_plugins as $plugin ) {
        if ( is_plugin_active( $plugin ) ) return true;
    }
    // Custom from address is set (admin took some mail action)
    $opt = get_option( 'dealboard_mail_from' );
    if ( $opt && is_email( $opt ) ) return true;
    return false;
}

/* ---------------------------------------------------------
   STEP 1 — registration details → send OTP (no account yet)
--------------------------------------------------------- */
if(isset($_POST['signup_submit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '','dealboard_signup')){
  $username = sanitize_user($_POST['username'] ?? '');
  $email    = sanitize_email($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $pass2    = $_POST['password2'] ?? '';

  if(empty($username)||empty($email)||empty($pass))      $error = 'All fields are required.';
  elseif(!is_email($email))                              $error = 'Please enter a valid email address.';
  elseif(strlen($pass) < 8)                              $error = 'Password must be at least 8 characters.';
  elseif($pass !== $pass2)                               $error = 'Passwords do not match.';
  elseif(username_exists($username))                     $error = 'Username already taken.';
  elseif(email_exists($email))                           $error = 'Email already registered.';
  else {
    $otp   = dealboard_generate_otp();
    $token = dealboard_store_pending_signup([
      'username' => $username,
      'email'    => $email,
      'password' => $pass,
      'otp'      => $otp,
    ]);

    // ── Try to send the OTP email ──────────────────────────
    $sent = dealboard_send_otp_email($email, $username, $otp);

    if ( $sent ) {
      // Mail delivered (or queued by SMTP plugin)
      $pending_email = $email;
      $step   = 'verify';
      $notice = 'We emailed a 6-digit verification code to ' . $email . '.';
    } else {
      // wp_mail() returned false → mail not sent
      dealboard_delete_pending_signup($token); // clean up the transient
      $token = '';
      if ( is_admin() || ( defined('WP_DEBUG') && WP_DEBUG ) ) {
        // Dev environment: show OTP on screen so testing is possible
        $pending_email = $email;
        $token = dealboard_store_pending_signup([
          'username' => $username,
          'email'    => $email,
          'password' => $pass,
          'otp'      => $otp,
        ]);
        $step   = 'verify';
        $notice = '[DEV MODE] Email not sent. Your OTP is: ' . $otp;
      } else {
        $error = 'We could not send a verification email to ' . esc_html($email)
               . '. Please check the address and try again, or contact support if the problem persists.';
      }
    }
  }
}

/* ---------------------------------------------------------
   Resend code
--------------------------------------------------------- */
if(isset($_POST['otp_resend']) && wp_verify_nonce($_POST['_wpnonce'] ?? '','dealboard_otp')){
  $token   = sanitize_text_field($_POST['otp_token'] ?? '');
  $pending = dealboard_get_pending_signup($token);
  if($pending){
    $otp = dealboard_generate_otp();
    $pending['otp']   = $otp;
    $pending['tries'] = 0;
    dealboard_update_pending_signup($token, $pending);

    // ── Try to send the OTP email ────────────────────────
    $sent = dealboard_send_otp_email($pending['email'], $pending['username'], $otp);

    if ( $sent ) {
      $pending_email = $pending['email'];
      $step   = 'verify';
      $notice = 'A new verification code has been sent to ' . $pending['email'] . '.';
    } else {
      $pending_email = $pending['email'];
      $step = 'verify';
      if ( defined('WP_DEBUG') && WP_DEBUG ) {
        $notice = '[DEV MODE] Email not sent. Your new OTP is: ' . $otp;
      } else {
        $error = 'Could not resend the verification email. Please check your email address or contact support.';
      }
    }
  } else {
    $error = 'Your session expired. Please sign up again.';
    $step  = 'register';
  }
}

/* ---------------------------------------------------------
   STEP 2 — verify OTP → create account + log in
--------------------------------------------------------- */
if(isset($_POST['otp_submit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '','dealboard_otp')){
  $token   = sanitize_text_field($_POST['otp_token'] ?? '');
  $code    = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
  $pending = dealboard_get_pending_signup($token);

  if(!$pending){
    $error = 'Your verification session expired. Please sign up again.';
    $step  = 'register';
  } else {
    $pending_email = $pending['email'];
    $step = 'verify';
    if($pending['tries'] >= DEALBOARD_OTP_MAX_TRIES){
      dealboard_delete_pending_signup($token);
      $error = 'Too many incorrect attempts. Please sign up again.';
      $step  = 'register';
    } elseif($code === '' || $code !== $pending['otp']){
      $pending['tries']++;
      dealboard_update_pending_signup($token, $pending);
      $left  = max(0, DEALBOARD_OTP_MAX_TRIES - $pending['tries']);
      $error = 'Incorrect code. ' . $left . ' attempt' . ($left===1?'':'s') . ' left.';
    } else {
      // Success — create the account now.
      $user_id = wp_create_user($pending['username'], $pending['password'], $pending['email']);
      dealboard_delete_pending_signup($token);
      if(is_wp_error($user_id)){
        $error = $user_id->get_error_message();
        $step  = 'register';
      } else {
        update_user_meta($user_id, 'email_verified', '1');
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_redirect(home_url('/dashboard')); exit;
      }
    }
  }
}
?>
<div class="auth-page">
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:28px">
      <a href="<?php echo home_url(); ?>" style="display:inline-block;margin-bottom:12px">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if($custom_logo_id):
          $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?>"
          style="height:64px;width:auto;object-fit:contain">
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:8px">
          <div style="width:32px;height:32px;background:#C8102E;border-radius:8px;display:flex;align-items:center;justify-content:center">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:white"><path d="M7 7h10l2 6H5L7 7zm5 9a2 2 0 100 4 2 2 0 000-4zm-5 0a2 2 0 100 4 2 2 0 000-4z"/></svg>
          </div>
          <span style="font-size:18px;font-weight:800;color:#111"><?php bloginfo('name'); ?></span>
        </div>
        <?php endif; ?>
      </a>
      <?php if($step==='verify'): ?>
      <h1 class="auth-title">Verify your email</h1>
      <p class="auth-sub">Enter the 6-digit code we sent you</p>
      <?php else: ?>
      <h1 class="auth-title">Create free account</h1>
      <p class="auth-sub">Start buying &amp; selling in your community</p>
      <?php endif; ?>
    </div>

    <?php if($error): ?>
    <div class="alert alert-error">❌ <?php echo esc_html($error); ?></div>
    <?php endif; ?>
    <?php if($notice && !$error): ?>
    <div class="alert" style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;border-radius:8px;padding:12px 16px;font-size:14px;margin-bottom:16px">
      ✅ <?php echo esc_html($notice); ?>
    </div>
    <?php endif; ?>

    <?php if($step==='verify'): ?>
    <!-- ===== STEP 2: OTP VERIFICATION ===== -->
    <form method="POST" autocomplete="one-time-code">
      <?php wp_nonce_field('dealboard_otp'); ?>
      <input type="hidden" name="otp_token" value="<?php echo esc_attr($token); ?>">
      <div class="form-group">
        <label>Verification Code</label>
        <input type="text" name="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
          class="form-control" placeholder="••••••" required autofocus
          style="letter-spacing:10px;text-align:center;font-size:22px;font-weight:700"
          oninput="this.value=this.value.replace(/[^0-9]/g,'')">
      </div>
      <input type="hidden" name="otp_submit" value="1">
      <button type="submit" class="btn-submit">Verify &amp; Create Account</button>
    </form>

    <form method="POST" style="margin-top:14px;text-align:center">
      <?php wp_nonce_field('dealboard_otp'); ?>
      <input type="hidden" name="otp_token" value="<?php echo esc_attr($token); ?>">
      <input type="hidden" name="otp_resend" value="1">
      <button type="submit"
        style="background:none;border:none;color:#C8102E;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit">
        Didn't get it? Resend code
      </button>
    </form>

    <div class="auth-link">
      <a href="<?php echo home_url('/sign-up'); ?>">← Start over</a>
    </div>

    <?php else: ?>
    <!-- ===== STEP 1: REGISTRATION ===== -->
    <form method="POST">
      <?php wp_nonce_field('dealboard_signup'); ?>
      <div class="form-group">
        <label>Full Name / Username</label>
        <input type="text" name="username" class="form-control" placeholder="johndoe" required value="<?php echo esc_attr($_POST['username']??''); ?>">
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="your@email.com" required value="<?php echo esc_attr($_POST['email']??''); ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative">
  <input type="password" name="password" id="pwd1" class="form-control" placeholder="Create a strong password" required minlength="8" style="padding-right:44px">
  <button type="button" onclick="togglePass('pwd1','eye1')"
    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
    <svg id="eye1" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
  </button>
</div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div style="position:relative">
  <input type="password" name="password2" id="pwd2" class="form-control" placeholder="Repeat your password" required style="padding-right:44px">
  <button type="button" onclick="togglePass('pwd2','eye2')"
    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
    <svg id="eye2" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
  </button>
</div>
      </div>
      <div style="font-size:12px;color:#6B7280;margin-bottom:20px">
        By signing up, you agree to our <a href="<?php echo home_url('/terms'); ?>" style="color:#10B981">Terms of Service</a> and <a href="<?php echo home_url('/privacy'); ?>" style="color:#10B981">Privacy Policy</a>.
      </div>
      <input type="hidden" name="signup_submit" value="1">
      <button type="submit" class="btn-submit">Create Free Account</button>
    </form>

    <div class="auth-link">
      Already have an account? <a href="<?php echo home_url('/sign-in'); ?>">Sign in</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePass(inputId, eyeId) {
  var input = document.getElementById(inputId);
  var eye = document.getElementById(eyeId);
  if (input.type === 'password') {
    input.type = 'text';
    eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    input.type = 'password';
    eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
</script>

<?php get_footer();
