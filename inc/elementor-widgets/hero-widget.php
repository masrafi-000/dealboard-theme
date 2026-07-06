<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DealBoard_Hero_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'dealboard_hero'; }
    public function get_title() { return '🏠 Hero Section'; }
    public function get_icon() { return 'eicon-banner'; }
    public function get_categories() { return ['dealboard']; }
    public function get_keywords() { return ['hero', 'banner', 'search', 'dealboard']; }

    protected function register_controls() {

        // Content Tab
        $this->start_controls_section( 'content_section', [
            'label' => 'Content',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'badge_text', [
            'label'   => 'Badge Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Local deals in your community',
        ]);

        $this->add_control( 'heading_line1', [
            'label'   => 'Heading Line 1',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Buy & Sell Locally,',
        ]);

        $this->add_control( 'heading_line2', [
            'label'   => 'Heading Line 2 (Green)',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Find Hidden Gems',
        ]);

        $this->add_control( 'subtitle', [
            'label'   => 'Subtitle',
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'Classified ads, garage sales, and great deals — all in one place.',
            'rows'    => 2,
        ]);

        $this->add_control( 'search_placeholder', [
            'label'   => 'Search Placeholder',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'What are you looking for?',
        ]);

        $this->add_control( 'link1_text', [
            'label'   => 'Link 1 Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => "Post an Ad — It's Free",
        ]);

        $this->add_control( 'link1_url', [
            'label'       => 'Link 1 URL',
            'type'        => \Elementor\Controls_Manager::URL,
            'placeholder' => '/post-ad',
            'default'     => ['url' => '/post-ad'],
        ]);

        $this->add_control( 'link2_text', [
            'label'   => 'Link 2 Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'List a Garage Sale',
        ]);

        $this->add_control( 'link2_url', [
            'label'       => 'Link 2 URL',
            'type'        => \Elementor\Controls_Manager::URL,
            'default'     => ['url' => '/garage-sales'],
        ]);

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section( 'style_section', [
            'label' => 'Style',
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'bg_color_start', [
            'label'   => 'Background Color (Start)',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#0D9488',
            'selectors' => ['{{WRAPPER}} .db-hero' => 'background: linear-gradient(135deg, {{VALUE}} 0%, var(--db-bg-end, #047857) 100%)'],
        ]);

        $this->add_control( 'heading_color', [
            'label'   => 'Heading Color',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .db-hero-title' => 'color: {{VALUE}}'],
        ]);

        $this->add_control( 'highlight_color', [
            'label'   => 'Highlight Color (Line 2)',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#A7F3D0',
            'selectors' => ['{{WRAPPER}} .db-hero-highlight' => 'color: {{VALUE}}'],
        ]);

        $this->add_control( 'padding', [
            'label'      => 'Section Padding',
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'default'    => ['top'=>'80','right'=>'24','bottom'=>'80','left'=>'24','unit'=>'px'],
            'selectors'  => ['{{WRAPPER}} .db-hero' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $link1 = $s['link1_url']['url'] ?: home_url('/post-ad');
        $link2 = $s['link2_url']['url'] ?: home_url('/garage-sales');
        ?>
<section class="db-hero hero-section">
  <div class="hero-badge">
    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    <?php echo esc_html($s['badge_text']); ?>
  </div>
  <h1 class="db-hero-title hero-title">
    <?php echo esc_html($s['heading_line1']); ?><br>
    <span class="db-hero-highlight highlight"><?php echo esc_html($s['heading_line2']); ?></span>
  </h1>
  <p class="hero-subtitle"><?php echo esc_html($s['subtitle']); ?></p>
  <form class="hero-search-box" action="<?php echo esc_url(home_url('/listings')); ?>" method="GET">
    <input type="text" name="s" placeholder="<?php echo esc_attr($s['search_placeholder']); ?>" autocomplete="off">
    <button type="submit" class="hero-search-btn">Search</button>
  </form>
  <div class="hero-links">
    <a href="<?php echo esc_url($link1); ?>" class="hero-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      <?php echo esc_html($s['link1_text']); ?>
    </a>
    <a href="<?php echo esc_url($link2); ?>" class="hero-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      <?php echo esc_html($s['link2_text']); ?>
    </a>
  </div>
</section>
        <?php
    }
}
