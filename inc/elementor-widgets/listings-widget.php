<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DealBoard_Listings_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'dealboard_listings'; }
    public function get_title() { return '🏷️ Recent Listings'; }
    public function get_icon() { return 'eicon-posts-grid'; }
    public function get_categories() { return ['dealboard']; }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => 'Settings',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'title', [
            'label'   => 'Section Title',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Recent Listings',
        ]);

        $this->add_control( 'count', [
            'label'   => 'Number of Listings',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min'     => 2,
            'max'     => 20,
        ]);

        $this->add_control( 'columns', [
            'label'   => 'Columns',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '4',
            'options' => ['2'=>'2','3'=>'3','4'=>'4'],
        ]);

        $this->add_control( 'see_all_text', [
            'label'   => 'See All Link Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'See all →',
        ]);

        $this->add_control( 'bg_color', [
            'label'   => 'Background Color',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .db-listings-section' => 'background-color: {{VALUE}}'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $cols = (int)($s['columns'] ?? 4);
        $q    = dealboard_get_recent_listings((int)($s['count'] ?? 8));
        ?>
<section class="db-listings-section section" style="background:white;border-top:1px solid #F3F4F6">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><span>🏷️</span> <?php echo esc_html($s['title']); ?></h2>
      <a href="<?php echo esc_url(home_url('/listings')); ?>" class="section-link"><?php echo esc_html($s['see_all_text']); ?></a>
    </div>
    <div class="listings-grid" style="grid-template-columns:repeat(<?php echo $cols; ?>,1fr)">
      <?php if ($q->have_posts()):
        while ($q->have_posts()): $q->the_post();
          $price    = get_post_meta(get_the_ID(),'listing_price',true);
          $city     = get_post_meta(get_the_ID(),'listing_city',true);
          $currency = get_post_meta(get_the_ID(),'listing_currency',true) ?: 'USD';
          $views    = get_post_meta(get_the_ID(),'listing_views',true) ?: 0;
          $lc       = wp_get_post_terms(get_the_ID(),'listing_category');
          $cat_name = !empty($lc)&&!is_wp_error($lc) ? $lc[0]->name : '';
          $author   = get_the_author_meta('display_name');
          $time     = human_time_diff(get_the_time('U'),current_time('timestamp')).' ago';
      ?>
      <a href="<?php the_permalink(); ?>" class="listing-card">
        <div class="listing-image">
          <?php if (has_post_thumbnail()): the_post_thumbnail('medium',['loading'=>'lazy']);
          else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font-size:40px">📦</div><?php endif; ?>
          <?php if ($price): ?><div class="listing-price"><?php echo esc_html('$'.$price); ?></div><?php endif; ?>
          <?php if ($cat_name): ?><div class="listing-category-badge"><?php echo esc_html($cat_name); ?></div><?php endif; ?>
          <div class="listing-seller"><?php echo esc_html(strtoupper(substr($author,0,2))); ?></div>
        </div>
        <div class="listing-body">
          <div class="listing-title"><?php the_title(); ?></div>
          <?php if ($currency!=='USD'): ?><div class="listing-currency-note">Listed in <?php echo esc_html($currency); ?> · converted</div><?php endif; ?>
          <div class="listing-meta">
            <?php if ($city): ?><span>📍 <?php echo esc_html($city); ?></span><?php endif; ?>
            <span>👁 <?php echo esc_html($views); ?></span>
            <span>🕐 <?php echo esc_html($time); ?></span>
          </div>
        </div>
      </a>
      <?php endwhile; wp_reset_postdata();
      else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <p>No listings yet. <a href="<?php echo esc_url(home_url('/post-ad')); ?>" style="color:#10B981">Post the first one!</a></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
        <?php
    }
}
