<?php
/*
Template Name: Forgot Password
*/

// Handle form submission
if (isset($_POST['forgot_nonce']) && wp_verify_nonce($_POST['forgot_nonce'], 'forgot_password')) {
  $login = sanitize_text_field($_POST['user_login'] ?? '');
  if (!empty($login)) {
    $user = get_user_by('email', $login);
    if (!$user) $user = get_user_by('login', $login);
    if ($user) {
      $key = get_password_reset_key($user);
      if (!is_wp_error($key)) {
        $reset_url = add_query_arg([
          'key'   => $key,
          'login' => rawurlencode($user->user_login),
        ], home_url('/reset-password/'));
        $subject = 'Reset Your Password — ' . get_bloginfo('name');
        $message = "Hi {$user->display_name},\n\n";
        $message .= "You requested a password reset for your account.\n\n";
        $message .= "Click the link below to reset your password:\n\n";
        $message .= $reset_url . "\n\n";
        $message .= "This link expires in 24 hours.\n\n";
        $message .= "If you did not request this, please ignore this email.\n\n";
        $message .= "— " . get_bloginfo('name');
        wp_mail($user->user_email, $subject, $message);
      }
    }
  }
  // Always redirect with success (security - don't reveal if email exists)
  wp_redirect(home_url('/forgot-password?sent=1'));
  exit;
}

get_header();

$custom_logo_id = get_theme_mod('custom_logo');
$logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
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
.auth-sub { font-size: 14px; color: #6B7280; margin-bottom: 0; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
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
.auth-link { text-align: center; font-size: 13px; color: #6B7280; margin-top: 20px; }
.auth-link a { color: #C8102E; font-weight: 600; text-decoration: none; }
.alert-success {
  background: #D1FAE5; color: #065F46;
  border: 1px solid #6EE7B7; border-radius: 8px;
  padding: 14px 16px; font-size: 14px; font-weight: 600;
  margin-bottom: 20px; text-align: center;
}
.alert-info {
  background: #EFF6FF; color: #1D4ED8;
  border: 1px solid #BFDBFE; border-radius: 8px;
  padding: 12px 16px; font-size: 13px;
  margin-bottom: 20px; text-align: center;
}
</style>

<div class="auth-page">
  <div class="auth-card">

    <!-- Logo -->
    <div style="text-align:center;margin-bottom:28px">
      <a href="<?php echo home_url(); ?>" style="display:inline-block;margin-bottom:14px">
        <?php if($logo_url): ?>
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
      <h1 class="auth-title">Forgot Password?</h1>
      <p class="auth-sub">Enter your email and we'll send you a reset link.</p>
    </div>

    <?php if(isset($_GET['sent'])): ?>
    <div class="alert-success">
      ✅ Reset link sent! Check your email inbox.
    </div>
    <div class="alert-info">
      📧 Didn't receive it? Check your spam folder or try again.
    </div>
    <?php endif; ?>

    <form method="POST">
      <?php wp_nonce_field('forgot_password', 'forgot_nonce'); ?>
      <div class="form-group">
        <label>Email or Username</label>
        <input type="text" name="user_login" class="form-control"
          placeholder="your@email.com" required
          value="<?php echo esc_attr($_POST['user_login'] ?? ''); ?>">
      </div>
      <button type="submit" class="btn-submit">Send Reset Link</button>
    </form>

    <div class="auth-link">
      Remember your password? <a href="<?php echo home_url('/sign-in'); ?>">Sign In</a>
    </div>
    <div class="auth-link" style="margin-top:8px">
      Don't have an account? <a href="<?php echo home_url('/sign-up'); ?>">Sign Up Free</a>
    </div>

  </div>
</div>

<?php get_footer(); ?>
