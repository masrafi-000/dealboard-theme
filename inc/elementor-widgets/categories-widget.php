<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DealBoard_Categories_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'dealboard_categories'; }
    public function get_title() { return '📂 Browse Categories'; }
    public function get_icon() { return 'eicon-tags'; }
    public function get_categories() { return ['dealboard']; }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => 'Content',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'title', [
            'label'   => 'Section Title',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Browse by Category',
        ]);

        $this->add_control( 'view_all_text', [
            'label'   => 'View All Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'View all →',
        ]);

        $this->add_control( 'columns', [
            'label'   => 'Columns',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '8',
            'options' => ['4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8'],
        ]);

        $this->add_control( 'bg_color', [
            'label'   => 'Background Color',
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#F9FAFB',
            'selectors' => ['{{WRAPPER}} .db-categories-section' => 'background-color: {{VALUE}}'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $cats = get_terms(['taxonomy'=>'listing_category','hide_empty'=>false,'parent'=>0]);
        $default_cats = [
            ['name'=>'Vehicles','emoji'=>'🚗'],
            ['name'=>'Electronics','emoji'=>'💻'],
            ['name'=>'Furniture','emoji'=>'🛋️'],
            ['name'=>'Clothing','emoji'=>'👕'],
            ['name'=>'Tools & Equipment','emoji'=>'🔧'],
            ['name'=>'Sports & Outdoors','emoji'=>'⚽'],
            ['name'=>'Home & Garden','emoji'=>'🏡'],
            ['name'=>'Toys & Kids','emoji'=>'🧸'],
        ];
        $cols = (int)($s['columns'] ?? 8);
        ?>
<section class="db-categories-section section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo esc_html($s['title']); ?></h2>
      <a href="<?php echo esc_url(home_url('/listings')); ?>" class="section-link"><?php echo esc_html($s['view_all_text']); ?></a>
    </div>
    <div class="categories-grid" style="grid-template-columns: repeat(<?php echo $cols; ?>, 1fr)">
      <?php if (!is_wp_error($cats) && !empty($cats)):
        foreach ($cats as $cat):
          $link = get_term_link($cat);
          if (is_wp_error($link)) $link = home_url('/listings');
      ?>
      <a href="<?php echo esc_url($link); ?>" class="category-card">
        <div class="category-icon"><span style="font-size:22px"><?php echo dealboard_category_icon($cat->slug); ?></span></div>
        <span class="category-name"><?php echo esc_html($cat->name); ?></span>
      </a>
      <?php endforeach;
      else:
        foreach ($default_cats as $dc):
      ?>
      <a href="<?php echo esc_url(home_url('/listings')); ?>" class="category-card">
        <div class="category-icon"><span style="font-size:22px"><?php echo $dc['emoji']; ?></span></div>
        <span class="category-name"><?php echo esc_html($dc['name']); ?></span>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>
        <?php
    }
}
