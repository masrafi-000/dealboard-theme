<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="site-header">
  <div class="container">
    <nav class="navbar">

      <!-- Logo -->
      <a href="<?php echo home_url('/'); ?>" class="site-logo" style="gap:10px">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id):
          $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?>"
          style="height:52px;width:auto;object-fit:contain;max-width:180px">
        <?php else: ?>
        <div class="logo-icon" style="background:var(--color-primary);flex-shrink:0">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;fill:white">
            <path d="M7 7h10l2 6H5L7 7zm5 9a2 2 0 100 4 2 2 0 000-4zm-5 0a2 2 0 100 4 2 2 0 000-4z"/>
          </svg>
        </div>
        <div>
          <div style="font-size:16px;font-weight:800;color:var(--color-navy);line-height:1.1"><?php bloginfo('name'); ?></div>
          <div style="font-size:10px;color:var(--color-gold);font-weight:600;letter-spacing:.5px"><?php bloginfo('description'); ?></div>
        </div>
        <?php endif; ?>
      </a>

      <!-- Nav Links -->
      <div class="nav-links">
        <a href="<?php echo home_url('/listings'); ?>" class="<?php echo is_post_type_archive('listing') ? 'active' : ''; ?>">
          Browse Ads
        </a>
        <a href="<?php echo home_url('/garage-sales'); ?>" class="<?php echo is_post_type_archive('garage_sale') ? 'active' : ''; ?>">
          Garage Sales
        </a>
        <a href="<?php echo home_url('/exchange'); ?>" class="<?php echo is_page('exchange') ? 'active' : ''; ?>">
          🔄 Exchange
        </a>
      </div>

      <!-- Right Side -->
      <div class="nav-right">

        <!-- Currency Switcher -->
        <div class="currency-switcher" id="currency-switcher">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
          </svg>
          <span id="current-currency">US USD</span>
          <svg width="10" height="10" fill="currentColor" viewBox="0 0 16 16"><path d="M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/></svg>
        </div>

        <!-- Currency Dropdown -->
        <div id="currency-dropdown" style="display:none;position:absolute;top:60px;background:white;border:1px solid #E5E7EB;border-radius:12px;padding:8px;width:260px;box-shadow:0 10px 25px rgba(0,0,0,0.12);z-index:9999">
          <div style="padding:8px;margin-bottom:4px">
            <input type="text" placeholder="Search currency..." id="currency-search" style="width:100%;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none">
          </div>
          <div style="max-height:280px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#D1D5DB #F3F4F6;">
          <?php
          $currencies = [
            ['code'=>'USD','name'=>'US Dollar',          'symbol'=>'$',    'flag'=>'🇺🇸','key'=>'us'],
            ['code'=>'EUR','name'=>'Euro',                'symbol'=>'€',    'flag'=>'🇪🇺','key'=>'eu'],
            ['code'=>'GBP','name'=>'British Pound',       'symbol'=>'£',    'flag'=>'🇬🇧','key'=>'gb'],
            ['code'=>'CAD','name'=>'Canadian Dollar',     'symbol'=>'C$',   'flag'=>'🇨🇦','key'=>'ca'],
            ['code'=>'AUD','name'=>'Australian Dollar',   'symbol'=>'A$',   'flag'=>'🇦🇺','key'=>'au'],
            ['code'=>'JPY','name'=>'Japanese Yen',        'symbol'=>'¥',    'flag'=>'🇯🇵','key'=>'jp'],
            ['code'=>'INR','name'=>'Indian Rupee',        'symbol'=>'₹',    'flag'=>'🇮🇳','key'=>'in'],
            ['code'=>'BRL','name'=>'Brazilian Real',      'symbol'=>'R$',   'flag'=>'🇧🇷','key'=>'br'],
            ['code'=>'MXN','name'=>'Mexican Peso',        'symbol'=>'$',    'flag'=>'🇲🇽','key'=>'mx'],
            ['code'=>'CHF','name'=>'Swiss Franc',         'symbol'=>'CHF',  'flag'=>'🇨🇭','key'=>'ch'],
            ['code'=>'SEK','name'=>'Swedish Krona',       'symbol'=>'kr',   'flag'=>'🇸🇪','key'=>'se'],
            ['code'=>'NOK','name'=>'Norwegian Krone',     'symbol'=>'kr',   'flag'=>'🇳🇴','key'=>'no'],
            ['code'=>'DKK','name'=>'Danish Krone',        'symbol'=>'kr',   'flag'=>'🇩🇰','key'=>'dk'],
            ['code'=>'NZD','name'=>'New Zealand Dollar',  'symbol'=>'NZ$',  'flag'=>'🇳🇿','key'=>'nz'],
            ['code'=>'SGD','name'=>'Singapore Dollar',    'symbol'=>'S$',   'flag'=>'🇸🇬','key'=>'sg'],
            ['code'=>'KRW','name'=>'South Korean Won',    'symbol'=>'₩',    'flag'=>'🇰🇷','key'=>'kr'],
            ['code'=>'AED','name'=>'UAE Dirham',          'symbol'=>'د.إ',  'flag'=>'🇦🇪','key'=>'ae'],
            ['code'=>'SAR','name'=>'Saudi Riyal',         'symbol'=>'ر.س', 'flag'=>'🇸🇦','key'=>'sa'],
            /* ===== NEW: Bahrain & Oman ===== */
            ['code'=>'BHD','name'=>'Bahraini Dinar',      'symbol'=>'.د.ب','flag'=>'🇧🇭','key'=>'bh'],
            ['code'=>'OMR','name'=>'Omani Rial',          'symbol'=>'ر.ع.','flag'=>'🇴🇲','key'=>'om'],
            /* ============================== */
            ['code'=>'ZAR','name'=>'South African Rand',  'symbol'=>'R',    'flag'=>'🇿🇦','key'=>'za'],
            ['code'=>'TRY','name'=>'Turkish Lira',        'symbol'=>'₺',    'flag'=>'🇹🇷','key'=>'tr'],
            ['code'=>'PLN','name'=>'Polish Zloty',        'symbol'=>'zł',   'flag'=>'🇵🇱','key'=>'pl'],
            ['code'=>'CZK','name'=>'Czech Koruna',        'symbol'=>'Kč',   'flag'=>'🇨🇿','key'=>'cz'],
            ['code'=>'HUF','name'=>'Hungarian Forint',    'symbol'=>'Ft',   'flag'=>'🇭🇺','key'=>'hu'],
            ['code'=>'ILS','name'=>'Israeli Shekel',      'symbol'=>'₪',    'flag'=>'🇮🇱','key'=>'il'],
            ['code'=>'PHP','name'=>'Philippine Peso',     'symbol'=>'₱',    'flag'=>'🇵🇭','key'=>'ph'],
            ['code'=>'IDR','name'=>'Indonesian Rupiah',   'symbol'=>'Rp',   'flag'=>'🇮🇩','key'=>'id'],
            ['code'=>'MYR','name'=>'Malaysian Ringgit',   'symbol'=>'RM',   'flag'=>'🇲🇾','key'=>'my'],
            ['code'=>'PKR','name'=>'Pakistani Rupee',     'symbol'=>'Rs',   'flag'=>'🇵🇰','key'=>'pk'],
            ['code'=>'EGP','name'=>'Egyptian Pound',      'symbol'=>'E£',   'flag'=>'🇪🇬','key'=>'eg'],
            ['code'=>'NGN','name'=>'Nigerian Naira',      'symbol'=>'₦',    'flag'=>'🇳🇬','key'=>'ng'],
            ['code'=>'KES','name'=>'Kenyan Shilling',     'symbol'=>'KSh',  'flag'=>'🇰🇪','key'=>'ke'],
            ['code'=>'GHS','name'=>'Ghanaian Cedi',       'symbol'=>'₵',    'flag'=>'🇬🇭','key'=>'gh'],
          ];
          foreach($currencies as $cur):
          ?>
          <div class="currency-option" data-code="<?php echo esc_attr($cur['code']); ?>" data-key="<?php echo esc_attr($cur['key']); ?>"
            style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;cursor:pointer;transition:background 0.15s"
            onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='transparent'">
            <span style="font-size:18px;flex-shrink:0"><?php echo $cur['flag']; ?></span>
            <strong style="font-size:13px;color:#111827;flex:1"><?php echo esc_html($cur['code']); ?></strong>
            <span style="font-size:12px;color:#6B7280;font-weight:500"><?php echo esc_html($cur['symbol']); ?></span>
          </div>
          <?php endforeach; ?>
          </div>
        </div>

        <!-- #2 FIX: "Post a Garage Sale" button (was "Post Sale") -->
        <a href="<?php echo esc_url(home_url('/garage-sales/list')); ?>" class="btn-post-sale">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Post a Garage Sale
        </a>

        <!-- #2 FIX: "Sell" button (was "Post Ad") -->
        <a href="<?php echo esc_url(home_url('/post-ad')); ?>" class="btn-post-ad">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Sell
        </a>

        <?php if ( is_user_logged_in() ): ?>
          <a href="<?php echo esc_url(home_url('/dashboard')); ?>" class="btn-signin">Dashboard</a>
          <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn-signup">Sign Out</a>
        <?php else: ?>
          <a href="<?php echo esc_url(home_url('/sign-in')); ?>" class="btn-signin">Sign In</a>
          <a href="<?php echo esc_url(home_url('/sign-up')); ?>" class="btn-signup">Sign Up</a>
        <?php endif; ?>

        <!-- Mobile Toggle -->
        <div class="mobile-menu-toggle" id="mobile-toggle">
          <span></span><span></span><span></span>
        </div>
      </div>
    </nav>
  </div>
</header>

<!-- Mobile Menu Overlay -->
<div id="mobile-menu" style="display:none;position:fixed;top:64px;left:0;right:0;bottom:0;background:white;z-index:999;padding:24px;overflow-y:auto">
  <div style="display:flex;flex-direction:column;gap:4px">
    <a href="<?php echo esc_url(home_url('/listings')); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#374151;border-radius:8px;border-bottom:1px solid #F3F4F6">Browse Ads</a>
    <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#374151;border-radius:8px;border-bottom:1px solid #F3F4F6">Garage Sales</a>
    <a href="<?php echo esc_url(home_url('/exchange')); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#374151;border-radius:8px;border-bottom:1px solid #F3F4F6">🔄 Exchange</a>
    <!-- #2 FIX: Mobile menu — "Sell" + "Post a Garage Sale" -->
    <a href="<?php echo esc_url(home_url('/post-ad')); ?>" style="padding:14px 16px;font-size:16px;font-weight:700;color:#10B981;border-radius:8px;border-bottom:1px solid #F3F4F6">+ Sell</a>
    <a href="<?php echo esc_url(home_url('/garage-sales/list')); ?>" style="padding:14px 16px;font-size:16px;font-weight:700;color:#10B981;border-radius:8px;border-bottom:1px solid #F3F4F6">📍 Post a Garage Sale</a>
    <?php if(!is_user_logged_in()): ?>
    <a href="<?php echo esc_url(home_url('/sign-in')); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#374151;border-radius:8px;border-bottom:1px solid #F3F4F6">Sign In</a>
    <a href="<?php echo esc_url(home_url('/sign-up')); ?>" style="padding:14px 16px;font-size:16px;font-weight:700;color:#10B981;border-radius:8px">Sign Up</a>
    <?php else: ?>
    <a href="<?php echo esc_url(home_url('/dashboard')); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#374151;border-radius:8px;border-bottom:1px solid #F3F4F6">Dashboard</a>
    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" style="padding:14px 16px;font-size:16px;font-weight:500;color:#EF4444;border-radius:8px">Sign Out</a>
    <?php endif; ?>
  </div>
</div>