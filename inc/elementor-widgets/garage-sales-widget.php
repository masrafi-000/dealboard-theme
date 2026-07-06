<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =====================
// GARAGE SALES WIDGET
// =====================
class DealBoard_GarageSales_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'dealboard_garage_sales'; }
    public function get_title() { return '📍 Garage Sales'; }
    public function get_icon() { return 'eicon-map-pin'; }
    public function get_categories() { return ['dealboard']; }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => 'Settings',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'title', [
            'label'   => 'Section Title',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Upcoming Garage Sales',
        ]);

        $this->add_control( 'subtitle', [
            'label'   => 'Subtitle',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Find sales happening in your area this weekend',
        ]);

        $this->add_control( 'count', [
            'label'   => 'Number of Sales',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 3,
            'min'     => 1,
            'max'     => 9,
        ]);

        $this->add_control( 'bg_color', [
            'label'   => 'Background Color',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#FFFBEB',
            'selectors' => ['{{WRAPPER}} .db-garage-section' => 'background-color: {{VALUE}}'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $sales = dealboard_get_garage_sales((int)($s['count'] ?? 3));
        ?>
<section class="db-garage-section section-alt">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <span style="color:#F97316">📍</span> <?php echo esc_html($s['title']); ?>
        <small style="font-size:13px;font-weight:400;color:#6B7280;margin-left:4px"><?php echo esc_html($s['subtitle']); ?></small>
      </h2>
      <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" class="section-link" style="color:#F97316">See all →</a>
    </div>
    <div class="garage-grid">
      <?php if ($sales->have_posts()):
        while ($sales->have_posts()): $sales->the_post();
          $date  = get_post_meta(get_the_ID(),'sale_date_start',true);
          $start = get_post_meta(get_the_ID(),'sale_time_start',true);
          $end   = get_post_meta(get_the_ID(),'sale_time_end',true);
          $addr  = get_post_meta(get_the_ID(),'sale_address',true);
          $city  = get_post_meta(get_the_ID(),'sale_city',true);
          $items = get_post_meta(get_the_ID(),'sale_items',true);
          $date_fmt = $date ? date('D, M j, Y',strtotime($date)) : '';
      ?>
      <a href="<?php the_permalink(); ?>" class="garage-card">
        <div class="garage-image">
          <?php if (has_post_thumbnail()): the_post_thumbnail('medium');
          else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#FEF3C7;font-size:50px">🏡</div><?php endif; ?>
          <?php if ($date_fmt): ?><div class="garage-date-badge"><?php echo esc_html($date_fmt); ?></div><?php endif; ?>
        </div>
        <div class="garage-body">
          <div class="garage-title"><?php the_title(); ?></div>
          <?php if ($items): ?><div class="garage-subtitle"><?php echo esc_html($items); ?></div><?php endif; ?>
          <div class="garage-info">
            <?php if ($addr||$city): ?><span>📍 <?php echo esc_html(trim($addr.', '.$city,', ')); ?></span><?php endif; ?>
            <?php if ($date_fmt): ?><span>📅 <?php echo esc_html($date_fmt); ?></span><?php endif; ?>
            <?php if ($start&&$end): ?><span>⏰ <?php echo esc_html($start.' – '.$end); ?></span><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endwhile; wp_reset_postdata();
      else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <p>No upcoming garage sales. <a href="<?php echo esc_url(home_url('/garage-sales')); ?>" style="color:#F97316">List yours!</a></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
        <?php
    }
}


// =====================
// CTA BANNER WIDGET
// =====================
class DealBoard_CTA_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'dealboard_cta'; }
    public function get_title() { return '🚀 CTA Banner'; }
    public function get_icon() { return 'eicon-call-to-action'; }
    public function get_categories() { return ['dealboard']; }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => 'Content',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'title', [
            'label'   => 'Title',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Ready to sell something?',
        ]);

        $this->add_control( 'subtitle', [
            'label'   => 'Subtitle',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Post your ad for free and reach thousands of local buyers.',
        ]);

        $this->add_control( 'btn1_text', [
            'label'   => 'Button 1 Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Post a Classified Ad',
        ]);

        $this->add_control( 'btn1_url', [
            'label'   => 'Button 1 URL',
            'type'    => \Elementor\Controls_Manager::URL,
            'default' => ['url' => '/post-ad'],
        ]);

        $this->add_control( 'btn2_text', [
            'label'   => 'Button 2 Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'List a Garage Sale',
        ]);

        $this->add_control( 'btn2_url', [
            'label'   => 'Button 2 URL',
            'type'    => \Elementor\Controls_Manager::URL,
            'default' => ['url' => '/garage-sales'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section( 'style', [
            'label' => 'Style',
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'bg_start', [
            'label'   => 'Gradient Start',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#0D9488',
            'selectors' => ['{{WRAPPER}} .cta-banner' => 'background: linear-gradient(135deg, {{VALUE}} 0%, #059669 100%)'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $url1 = $s['btn1_url']['url'] ?: home_url('/post-ad');
        $url2 = $s['btn2_url']['url'] ?: home_url('/garage-sales');
        ?>
<div class="cta-section">
  <div class="container">
    <div class="cta-banner">
      <h2 class="cta-title"><?php echo esc_html($s['title']); ?></h2>
      <p class="cta-subtitle"><?php echo esc_html($s['subtitle']); ?></p>
      <div class="cta-buttons">
        <a href="<?php echo esc_url($url1); ?>" class="btn-cta-primary"><?php echo esc_html($s['btn1_text']); ?></a>
        <a href="<?php echo esc_url($url2); ?>" class="btn-cta-secondary"><?php echo esc_html($s['btn2_text']); ?></a>
      </div>
    </div>
  </div>
</div>
        <?php
    }
}
