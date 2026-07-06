<?php
/*
Template Name: Sign In
*/
get_header();
if(is_user_logged_in()){ wp_redirect(home_url('/dashboard')); exit; }
$redirect = isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : home_url('/dashboard');
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
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Sign in to manage your listings</p>
    </div>

    <?php
    if(isset($_POST['signin_submit'])){
      $creds = ['user_login'=>sanitize_text_field($_POST['username']),'user_password'=>$_POST['password'],'remember'=>isset($_POST['remember'])];
      $user = wp_signon($creds, false);
      if(is_wp_error($user)): ?>
<div class="alert alert-error">❌ Invalid username or password. Please try again.</div>
      <?php else: wp_redirect($redirect); exit; endif;
    }
    ?>

    <form method="POST">
      <?php wp_nonce_field('dealboard_signin'); ?>
      <div class="form-group">
        <label>Email or Username</label>
        <input type="text" name="username" class="form-control" placeholder="your@email.com" required value="<?php echo esc_attr($_POST['username'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label style="display:flex;justify-content:space-between">
          Password
          <a href="<?php echo home_url('/forgot-password'); ?>" style="font-size:12px;color:#C8102E">Forgot password?</a>
        </label>
        <div style="position:relative">
  <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required style="padding-right:44px">
  <button type="button" onclick="togglePass('pwd','eye1')"
    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
    <svg id="eye1" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
  </button>
</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
        <input type="checkbox" name="remember" id="remember" style="accent-color:#10B981">
        <label for="remember" style="font-size:13px;color:#6B7280">Keep me signed in</label>
      </div>
      <input type="hidden" name="signin_submit" value="1">
      <button type="submit" class="btn-submit">Sign In</button>
    </form>

    <div class="auth-link">
      Don't have an account? <a href="<?php echo home_url('/sign-up'); ?>">Sign up free</a>
    </div>
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
