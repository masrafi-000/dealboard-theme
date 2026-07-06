<?php
/*
Template Name: Reset Password
*/

// Must run before get_header() / any output
$error_msg   = '';
$success_msg = '';

$key   = sanitize_text_field( $_GET['key']   ?? $_POST['rp_key']   ?? '' );
$login = sanitize_text_field( rawurldecode( $_GET['login'] ?? $_POST['rp_login'] ?? '' ) );

// ── Handle form submission ───────────────────────────────────────────────────
if ( isset( $_POST['rp_nonce'] ) && wp_verify_nonce( $_POST['rp_nonce'], 'reset_password' ) ) {
    $new_pass  = $_POST['pass1'] ?? '';
    $new_pass2 = $_POST['pass2'] ?? '';
    $rp_key    = sanitize_text_field( $_POST['rp_key']   ?? '' );
    $rp_login  = sanitize_text_field( $_POST['rp_login'] ?? '' );

    if ( empty( $new_pass ) || empty( $new_pass2 ) ) {
        $error_msg = 'Please enter and confirm your new password.';
    } elseif ( $new_pass !== $new_pass2 ) {
        $error_msg = 'Passwords do not match. Please try again.';
    } elseif ( strlen( $new_pass ) < 8 ) {
        $error_msg = 'Password must be at least 8 characters.';
    } else {
        // Verify the key is still valid
        $user = check_password_reset_key( $rp_key, $rp_login );
        if ( is_wp_error( $user ) ) {
            $error_msg = 'This reset link has expired or is invalid. Please request a new one.';
        } else {
            reset_password( $user, $new_pass );
            $success_msg = 'done';
        }
    }

    // Keep key/login for re-rendering the form on error
    if ( $error_msg ) {
        $key   = $rp_key;
        $login = $rp_login;
    }
}

// ── Validate the key on GET (link from email) ────────────────────────────────
$key_valid = false;
if ( $key && $login && ! $success_msg ) {
    $check = check_password_reset_key( $key, $login );
    if ( ! is_wp_error( $check ) ) {
        $key_valid = true;
    } else {
        $error_msg = 'This reset link has expired or is invalid. Please <a href="'
                   . esc_url( home_url( '/forgot-password/' ) )
                   . '">request a new one</a>.';
    }
}

get_header();

$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_url       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
?>

<style>
.auth-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #F9FAFB;
  padding: 40px 20px;
}
.auth-card {
  background: white;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  padding: 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
}
.auth-title { font-size: 22px; font-weight: 800; color: #111; margin-bottom: 6px; }
.auth-sub   { font-size: 14px; color: #6B7280; margin-bottom: 0; }
.form-group { margin-bottom: 18px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
.form-control {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid #E5E7EB; border-radius: 8px;
  font-size: 14px; outline: none; font-family: inherit;
  transition: border-color .15s;
}
.form-control:focus { border-color: #C8102E; box-shadow: 0 0 0 3px rgba(200,16,46,.1); }
.form-control::placeholder { color: #9CA3AF; }
.btn-submit {
  width: 100%; padding: 13px;
  background: #C8102E; color: white;
  font-size: 15px; font-weight: 700;
  border: none; border-radius: 8px;
  cursor: pointer; font-family: inherit;
  transition: background .15s;
}
.btn-submit:hover { background: #A50E26; }
.auth-link { text-align:center; font-size:13px; color:#6B7280; margin-top:20px; }
.auth-link a { color:#C8102E; font-weight:600; text-decoration:none; }
.alert-success {
  background: #D1FAE5; color: #065F46;
  border: 1px solid #6EE7B7; border-radius: 8px;
  padding: 14px 16px; font-size: 14px; font-weight: 600;
  margin-bottom: 20px; text-align: center;
}
.alert-error {
  background: #FEE2E2; color: #DC2626;
  border: 1px solid #FECACA; border-radius: 8px;
  padding: 14px 16px; font-size: 14px;
  margin-bottom: 20px;
}
.pass-strength { font-size: 12px; margin-top: 6px; font-weight: 600; }
</style>

<div class="auth-page">
  <div class="auth-card">

    <!-- Logo -->
    <div style="text-align:center;margin-bottom:28px">
      <a href="<?php echo home_url(); ?>" style="display:inline-block;margin-bottom:14px">
        <?php if ( $logo_url ): ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo('name'); ?>"
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
      <h1 class="auth-title">Set New Password</h1>
      <p class="auth-sub">Choose a strong password for your account.</p>
    </div>

    <?php if ( $success_msg === 'done' ): ?>
      <!-- ── Success state ─────────────────────────────────── -->
      <div class="alert-success">
        ✅ Password updated successfully!
      </div>
      <p style="text-align:center;font-size:14px;color:#6B7280;margin-bottom:20px">
        You can now sign in with your new password.
      </p>
      <a href="<?php echo esc_url( home_url('/sign-in/') ); ?>" class="btn-submit" style="display:block;text-align:center;text-decoration:none">
        Go to Sign In →
      </a>

    <?php elseif ( $error_msg && ! $key_valid ): ?>
      <!-- ── Invalid / expired link ────────────────────────── -->
      <div class="alert-error">❌ <?php echo $error_msg; // contains safe HTML/escaped link ?></div>
      <div class="auth-link">
        <a href="<?php echo esc_url( home_url('/forgot-password/') ); ?>">← Request a new reset link</a>
      </div>

    <?php else: ?>
      <!-- ── Password reset form ───────────────────────────── -->
      <?php if ( $error_msg ): ?>
      <div class="alert-error">❌ <?php echo esc_html( $error_msg ); ?></div>
      <?php endif; ?>

      <form method="POST" id="rp-form">
        <?php wp_nonce_field( 'reset_password', 'rp_nonce' ); ?>
        <input type="hidden" name="rp_key"   value="<?php echo esc_attr( $key ); ?>">
        <input type="hidden" name="rp_login" value="<?php echo esc_attr( $login ); ?>">

        <div class="form-group">
          <label for="pass1">New Password</label>
          <input type="password" id="pass1" name="pass1" class="form-control"
                 placeholder="At least 8 characters" required autocomplete="new-password">
          <div class="pass-strength" id="pass-strength-msg"></div>
        </div>

        <div class="form-group">
          <label for="pass2">Confirm Password</label>
          <input type="password" id="pass2" name="pass2" class="form-control"
                 placeholder="Repeat your password" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn-submit">Update Password</button>
      </form>

      <div class="auth-link">
        Remember it? <a href="<?php echo esc_url( home_url('/sign-in/') ); ?>">Sign In</a>
      </div>

    <?php endif; ?>

  </div>
</div>

<script>
(function(){
  var p1 = document.getElementById('pass1');
  var msg = document.getElementById('pass-strength-msg');
  if(!p1 || !msg) return;
  p1.addEventListener('input', function(){
    var v = p1.value, len = v.length;
    if(len === 0){ msg.textContent=''; return; }
    var score = 0;
    if(len >= 8)  score++;
    if(len >= 12) score++;
    if(/[A-Z]/.test(v)) score++;
    if(/[0-9]/.test(v)) score++;
    if(/[^A-Za-z0-9]/.test(v)) score++;
    var labels = ['','Weak','Fair','Good','Strong','Very Strong'];
    var colors = ['','#DC2626','#D97706','#0284C7','#059669','#065F46'];
    msg.textContent  = labels[score] || '';
    msg.style.color  = colors[score] || '';
  });
})();
</script>

<?php get_footer(); ?>
