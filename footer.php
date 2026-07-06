<footer id="site-footer">
  <div class="container">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="footer-brand">
        <?php
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
        ?>
        <a href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-block;margin-bottom:14px">
          <?php if($logo_url): ?>
          <img src="<?php echo esc_url($logo_url); ?>"
            alt="<?php bloginfo('name'); ?>"
            style="height:70px;width:auto;object-fit:contain">
          <?php else: ?>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;background:#C8102E;border-radius:8px;display:flex;align-items:center;justify-content:center">
              <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:white"><path d="M7 7h10l2 6H5L7 7zm5 9a2 2 0 100 4 2 2 0 000-4zm-5 0a2 2 0 100 4 2 2 0 000-4z"/></svg>
            </div>
            <div>
              <div style="font-size:16px;font-weight:800;color:white;line-height:1.1"><?php bloginfo('name'); ?></div>
              <div style="font-size:10px;color:#C9A84C;font-weight:600;letter-spacing:.5px"><?php bloginfo('description'); ?></div>
            </div>
          </div>
          <?php endif; ?>
        </a>
        <p class="footer-desc"><?php bloginfo('description'); ?></p>

        <!-- Social & Support -->
        <div style="margin-top:20px">
          <div style="font-size:11px;font-weight:700;color:#C9A84C;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
            Follow &amp; Support
          </div>
          <div class="footer-social" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-start">
			  
			 <!-- Facebook -->
			<a href="https://www.facebook.com/share/1BkC73S7BS" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Follow us on Facebook"
			  style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;transition:background 0.2s;border:1px solid rgba(255,255,255,0.1)"
			  onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
			  <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
				<path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
			  </svg>
			</a>

            <!-- TikTok -->
            <a href="https://www.tiktok.com/@american.alley.usa?_r=1&_t=ZS-970vi2V96Ky" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="Follow us on TikTok"
              style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;transition:background 0.2s;border:1px solid rgba(255,255,255,0.1)"
              onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.17 8.17 0 004.78 1.52V6.75a4.85 4.85 0 01-1.01-.06z"/>
              </svg>
            </a>

            <!-- Snapchat -->
            <a href="https://www.snapchat.com/@dua_book" target="_blank" rel="noopener noreferrer" aria-label="Snapchat" title="Follow us on Snapchat"
              style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;transition:background 0.2s;border:1px solid rgba(255,255,255,0.1)"
              onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                <path d="M12.166 2c-4.337 0-6.542 3.308-6.542 6.228 0 .308.025.613.074.912l-.766.139c-.159.03-.276.17-.276.333 0 .196.159.354.354.354l.788.001c-.077.42-.207.891-.459 1.274-.672 1.008-2.268 1.26-2.772 1.428-.504.168-.588.504-.42.84.168.336.588.504 1.176.588.084.336.168.672.42.84-.084.084-.168.168-.168.336 0 .42.336.84 1.008 1.176.84.42 2.1.672 3.528 1.008.252.504.756 1.008 1.68 1.008.924 0 1.428-.504 1.68-1.008 1.428-.336 2.688-.588 3.528-1.008.672-.336 1.008-.756 1.008-1.176 0-.168-.084-.252-.168-.336.252-.168.336-.504.42-.84.588-.084 1.008-.252 1.176-.588.168-.336.084-.672-.42-.84-.504-.168-2.1-.42-2.772-1.428-.252-.383-.382-.854-.459-1.274l.788-.001c.195 0 .354-.158.354-.354 0-.163-.117-.303-.276-.333l-.766-.139c.049-.299.074-.604.074-.912C18.708 5.308 16.503 2 12.166 2z"/>
              </svg>
            </a>

            <!-- Instagram -->
            <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Follow us on Instagram"
              style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;transition:background 0.2s;border:1px solid rgba(255,255,255,0.1)"
              onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                <circle cx="12" cy="12" r="4"/>
                <circle cx="17.5" cy="6.5" r="1" fill="white" stroke="none"/>
              </svg>
            </a>

            <!-- X (Twitter) -->
            <a href="#" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="Follow us on X"
              style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;transition:background 0.2s;border:1px solid rgba(255,255,255,0.1)"
              onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
            </a>

          </div>

          <!-- Support email text -->
          <div style="margin-top:10px">
            <a href="mailto:american.alley.support@gmail.com?subject=Support Request — American Alley"
              style="font-size:11px;color:rgba(255,255,255,0.5);text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:color .15s"
              onmouseover="this.style.color='#C9A84C'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
              <span style="font-size:14px">✉️</span>
              <span>american.alley.support@gmail.com</span>
            </a>
          </div>
        </div>

      </div>

      <!-- Marketplace -->
      <div class="footer-col">
        <h4>Marketplace</h4>
        <ul>
          <li><a href="<?php echo esc_url(home_url('/listings')); ?>">Browse All Ads</a></li>
          <li><a href="<?php echo esc_url(home_url('/garage-sales')); ?>">Garage Sales</a></li>
          <li><a href="<?php echo esc_url(home_url('/listings/?listing_category=free-stuff')); ?>">Free Stuff</a></li>
          <li><a href="<?php echo esc_url(home_url('/post-ad')); ?>">Post a Free Ad</a></li>
        </ul>
      </div>

      <!-- Sell -->
      <div class="footer-col">
        <h4>Sell</h4>
        <ul>
          <li><a href="<?php echo esc_url(home_url('/post-ad')); ?>">Post a Classified Ad</a></li>
          <li><a href="<?php echo esc_url(home_url('/list-garage-sale')); ?>">List a Garage Sale</a></li>
          <li><a href="<?php echo esc_url(home_url('/exchange')); ?>">Exchange</a></li>
          <li><a href="<?php echo esc_url(home_url('/dashboard')); ?>">My Listings</a></li>
        </ul>
      </div>

      <!-- Categories -->
      <div class="footer-col">
        <h4>Categories</h4>
        <ul>
          <?php
          $footer_cats = get_terms([
            'taxonomy'   => 'listing_category',
            'hide_empty' => false,
            'parent'     => 0,
            'number'     => 6,
          ]);
          if (!empty($footer_cats) && !is_wp_error($footer_cats)):
            foreach ($footer_cats as $footer_cat):
              $cat_link = get_term_link($footer_cat);
              if (!is_wp_error($cat_link)):
          ?>
          <li><a href="<?php echo esc_url($cat_link); ?>"><?php echo esc_html($footer_cat->name); ?></a></li>
          <?php endif; endforeach; else: ?>
          <li><a href="<?php echo esc_url(home_url('/listings')); ?>">All Categories</a></li>
          <?php endif; ?>
        </ul>
      </div>

    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
      <p>Buy local. Sell smart. Find deals.</p>
    </div>

  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>