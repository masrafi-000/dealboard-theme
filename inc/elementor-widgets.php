<?php
/**
 * DealBoard Elementor Custom Widgets
 * এই file টা সব custom Elementor widgets register করে
 * প্রতিটা section Elementor editor এ drag & drop করা যাবে
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Register widgets when Elementor loads
add_action( 'elementor/widgets/register', 'dealboard_register_elementor_widgets' );

function dealboard_register_elementor_widgets( $widgets_manager ) {
    require_once DEALBOARD_DIR . '/inc/elementor-widgets/hero-widget.php';
    require_once DEALBOARD_DIR . '/inc/elementor-widgets/categories-widget.php';
    require_once DEALBOARD_DIR . '/inc/elementor-widgets/listings-widget.php';
    require_once DEALBOARD_DIR . '/inc/elementor-widgets/garage-sales-widget.php';
    require_once DEALBOARD_DIR . '/inc/elementor-widgets/cta-widget.php';

    $widgets_manager->register( new \DealBoard_Hero_Widget() );
    $widgets_manager->register( new \DealBoard_Categories_Widget() );
    $widgets_manager->register( new \DealBoard_Listings_Widget() );
    $widgets_manager->register( new \DealBoard_GarageSales_Widget() );
    $widgets_manager->register( new \DealBoard_CTA_Widget() );
}

// Add DealBoard category in Elementor widget panel
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
    $elements_manager->add_category( 'dealboard', [
        'title' => '🏷️ DealBoard',
        'icon'  => 'fa fa-tag',
    ]);
});
