<?php
/**
 * DealBoard Theme Functions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DEALBOARD_VERSION', '1.1.0' );
define( 'DEALBOARD_DIR', get_template_directory() );
define( 'DEALBOARD_URI', get_template_directory_uri() );

/* ===========================
   FEATURE MODULES
   (order matters: mail before stripe)
=========================== */
require_once DEALBOARD_DIR . '/inc/dealboard-mail.php';   // sender identity fix
require_once DEALBOARD_DIR . '/inc/dealboard-otp.php';    // signup email OTP
require_once DEALBOARD_DIR . '/inc/dealboard-stripe.php'; // business ad subscriptions

/* ===========================
   THEME SETUP
=========================== */
function dealboard_setup() {
    load_theme_textdomain( 'dealboard', DEALBOARD_DIR . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );
    add_theme_support( 'woocommerce' );
    add_theme_support( 'elementor' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'custom-logo', [
        'height' => 40, 'width' => 160,
        'flex-height' => true, 'flex-width' => true,
    ]);
    register_nav_menus([
        'primary' => __( 'Primary Menu', 'dealboard' ),
        'footer'  => __( 'Footer Menu', 'dealboard' ),
    ]);
}
add_action( 'after_setup_theme', 'dealboard_setup' );

/* ===========================
   CONTENT WIDTH
=========================== */
function dealboard_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'dealboard_content_width', 1200 );
}
add_action( 'after_setup_theme', 'dealboard_content_width', 0 );

/* ===========================
   ENQUEUE SCRIPTS & STYLES
=========================== */
function dealboard_scripts() {
    wp_enqueue_style( 'google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        [], null );
    wp_enqueue_style( 'dealboard-style', get_stylesheet_uri(), [], DEALBOARD_VERSION );

    if ( did_action( 'elementor/loaded' ) ) {
        wp_enqueue_style( 'dealboard-elementor',
            DEALBOARD_URI . '/assets/css/elementor-compat.css',
            ['dealboard-style'], DEALBOARD_VERSION );
    }

    wp_enqueue_script( 'dealboard-main',
        DEALBOARD_URI . '/assets/js/main.js',
        ['jquery'], DEALBOARD_VERSION, true );

    wp_localize_script( 'dealboard-main', 'dealboard_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dealboard_nonce' ),
    ]);
}
add_action( 'wp_enqueue_scripts', 'dealboard_scripts' );

/* ===========================
   BODY CLASS
=========================== */
function dealboard_body_classes( $classes ) {
    $classes[] = 'dealboard-theme';
    return $classes;
}
add_filter( 'body_class', 'dealboard_body_classes' );

/* ===========================
   REMOVE BLOCK STYLES
=========================== */
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-block-style' );
    wp_dequeue_style( 'global-styles' );
}, 100 );

/* ===========================
   ELEMENTOR SUPPORT
=========================== */
function dealboard_register_elementor_locations( $manager ) {
    $manager->register_location( 'header' );
    $manager->register_location( 'footer' );
    $manager->register_location( 'single' );
    $manager->register_location( 'archive' );
}
add_action( 'elementor/theme/register_locations', 'dealboard_register_elementor_locations' );

add_action( 'elementor/query/listing_query', function( $query ) {
    $query->set( 'post_type', 'listing' );
    $query->set( 'meta_query', [[ 'key' => 'listing_status', 'value' => 'active' ]] );
});

/* ===========================
   CUSTOM POST TYPES
=========================== */
function dealboard_register_post_types() {
    register_post_type( 'listing', [
        'labels' => [
            'name'          => __( 'Listings', 'dealboard' ),
            'singular_name' => __( 'Listing', 'dealboard' ),
            'add_new'       => __( 'Add New Listing', 'dealboard' ),
            'all_items'     => __( 'All Listings', 'dealboard' ),
        ],
        'public'          => true,
        'has_archive'     => true,
        'rewrite'         => [ 'slug' => 'listings' ],
        'supports'        => [ 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ],
        'show_in_rest'    => true,
        'menu_icon'       => 'dashicons-tag',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);

    register_post_type( 'garage_sale', [
        'labels' => [
            'name'          => __( 'Garage Sales', 'dealboard' ),
            'singular_name' => __( 'Garage Sale', 'dealboard' ),
            'add_new'       => __( 'Add Garage Sale', 'dealboard' ),
            'all_items'     => __( 'All Garage Sales', 'dealboard' ),
        ],
        'public'          => true,
        'has_archive'     => true,
        'rewrite'         => [ 'slug' => 'garage-sales' ],
        'supports'        => [ 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ],
        'show_in_rest'    => true,
        'menu_icon'       => 'dashicons-location',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
}
add_action( 'init', 'dealboard_register_post_types' );

/* ===========================
   TAXONOMIES
=========================== */
function dealboard_register_taxonomies() {
    register_taxonomy( 'listing_category', 'listing', [
        'labels' => [
            'name'          => __( 'Categories', 'dealboard' ),
            'singular_name' => __( 'Category', 'dealboard' ),
        ],
        'hierarchical'      => true,
        'public'            => true,
        'rewrite'           => [ 'slug' => 'listing-category' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

    register_taxonomy( 'listing_location', 'listing', [
        'labels' => [
            'name'          => __( 'Locations', 'dealboard' ),
            'singular_name' => __( 'Location', 'dealboard' ),
        ],
        'hierarchical'      => true,
        'public'            => true,
        'rewrite'           => [ 'slug' => 'location' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);
}
add_action( 'init', 'dealboard_register_taxonomies' );

/* ===========================
   LISTING META BOXES
=========================== */
function dealboard_add_meta_boxes() {
    add_meta_box( 'listing_details', __( 'Listing Details', 'dealboard' ), 'dealboard_listing_meta_box', 'listing', 'normal', 'high' );
    add_meta_box( 'garage_sale_details', __( 'Garage Sale Details', 'dealboard' ), 'dealboard_garage_meta_box', 'garage_sale', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'dealboard_add_meta_boxes' );

function dealboard_listing_meta_box( $post ) {
    wp_nonce_field( 'dealboard_listing_meta', 'dealboard_listing_nonce' );
    $fields = [
        'listing_price'    => [ 'label' => 'Price', 'type' => 'text', 'placeholder' => 'e.g. 150 or Free' ],
        'listing_currency' => [
            'label'   => 'Currency',
            'type'    => 'select',
            'options' => [
                'USD' => 'USD — US Dollar',
                'EUR' => 'EUR — Euro',
                'GBP' => 'GBP — British Pound',
                'CAD' => 'CAD — Canadian Dollar',
                'AUD' => 'AUD — Australian Dollar',
                'AED' => 'AED — UAE Dirham',
                'SAR' => 'SAR — Saudi Riyal',
                'BHD' => 'BHD — Bahraini Dinar',
                'OMR' => 'OMR — Omani Rial',
                'BDT' => 'BDT — Bangladeshi Taka',
            ],
        ],
        'listing_city'      => [ 'label' => 'City', 'type' => 'text' ],
        'listing_address'   => [ 'label' => 'Address', 'type' => 'text' ],
        'listing_condition' => [
            'label'   => 'Condition',
            'type'    => 'select',
            'options' => [
                ''     => 'Select...',
                'new'  => 'New',
                'used' => 'Used',
            ],
        ],
        'listing_phone'    => [ 'label' => 'Contact Phone', 'type' => 'text' ],
        'listing_email'    => [ 'label' => 'Contact Email', 'type' => 'email' ],
        'listing_expires'  => [ 'label' => 'Expiry Date', 'type' => 'date' ],
        'listing_status'   => [
            'label'   => 'Status',
            'type'    => 'select',
            'options' => [
                'active'          => 'Active',
                'sold'            => 'Sold',
                'pending'         => 'Pending',
                'pending_payment' => 'Pending Payment',
                'expired'         => 'Expired',
            ],
        ],
        'listing_featured' => [ 'label' => 'Featured?', 'type' => 'checkbox' ],
    ];

    echo '<table class="form-table"><tbody>';
    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($field['label']) . '</label></th><td>';
        if ( $field['type'] === 'select' ) {
            echo '<select name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" style="width:300px">';
            foreach ( $field['options'] as $v => $l ) {
                echo '<option value="' . esc_attr($v) . '"' . selected($value,$v,false) . '>' . esc_html($l) . '</option>';
            }
            echo '</select>';
        } elseif ( $field['type'] === 'checkbox' ) {
            echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1"' . checked($value,'1',false) . '>';
        } else {
            $ph = isset($field['placeholder']) ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : '';
            echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '"' . $ph . ' style="width:300px">';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

function dealboard_garage_meta_box( $post ) {
    wp_nonce_field( 'dealboard_garage_meta', 'dealboard_garage_nonce' );
    $fields = [
        'sale_date_start' => [ 'label' => 'Sale Date',      'type' => 'date' ],
        'sale_time_start' => [ 'label' => 'Start Time',     'type' => 'time' ],
        'sale_time_end'   => [ 'label' => 'End Time',       'type' => 'time' ],
        'sale_address'    => [ 'label' => 'Address',        'type' => 'text' ],
        'sale_city'       => [ 'label' => 'City',           'type' => 'text' ],
        'sale_items'      => [ 'label' => 'Items for Sale', 'type' => 'text' ],
    ];
    echo '<table class="form-table"><tbody>';
    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($field['label']) . '</label></th><td>';
        echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" style="width:300px">';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

function dealboard_save_meta( $post_id ) {
    if ( wp_is_post_revision( $post_id ) ) return;

    if ( isset($_POST['dealboard_listing_nonce']) && wp_verify_nonce($_POST['dealboard_listing_nonce'], 'dealboard_listing_meta') ) {
        $fields = ['listing_price','listing_currency','listing_city','listing_address','listing_condition','listing_phone','listing_email','listing_expires','listing_status','listing_lat','listing_lng','listing_map_address'];
        foreach ( $fields as $f ) {
            if ( isset($_POST[$f]) ) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
        }
        update_post_meta($post_id, 'listing_featured', isset($_POST['listing_featured']) ? '1' : '0');
    }

    if ( isset($_POST['dealboard_garage_nonce']) && wp_verify_nonce($_POST['dealboard_garage_nonce'], 'dealboard_garage_meta') ) {
        $fields = ['sale_date_start','sale_time_start','sale_time_end','sale_address','sale_city','sale_items'];
        foreach ( $fields as $f ) {
            if ( isset($_POST[$f]) ) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
        }
    }
}
add_action( 'save_post', 'dealboard_save_meta' );

/* ===========================
   AJAX: SUBMIT LISTING
=========================== */
function dealboard_submit_listing() {
    if ( ! isset($_POST['dealboard_submit_nonce']) || ! wp_verify_nonce($_POST['dealboard_submit_nonce'], 'dealboard_submit_listing') ) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'Please log in to post a listing']);
    }
    $title = sanitize_text_field($_POST['title'] ?? '');
    if ( empty($title) ) wp_send_json_error(['message' => 'Title is required']);

    $user_id = get_current_user_id();
    $post_id = wp_insert_post([
        'post_type'    => 'listing',
        'post_title'   => $title,
        'post_content' => sanitize_textarea_field($_POST['description'] ?? ''),
        'post_status'  => 'pending',
        'post_author'  => $user_id,
    ]);

    if ( is_wp_error($post_id) ) wp_send_json_error(['message' => 'Failed to create listing']);

    $meta = ['listing_price','listing_currency','listing_city','listing_condition','listing_phone','listing_email','listing_lat','listing_lng','listing_map_address'];
    foreach ( $meta as $f ) {
        if ( ! empty($_POST[$f]) ) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
    }
    update_post_meta($post_id, 'listing_status', 'active');

    if ( ! empty($_POST['listing_category']) ) {
        wp_set_post_terms($post_id, [(int)$_POST['listing_category']], 'listing_category');
    }

    if ( ! empty($_POST['photo_urls']) ) {
        $photo_urls = $_POST['photo_urls'];
        update_post_meta($post_id, 'listing_photo_urls', sanitize_textarea_field($photo_urls));
        $photos = array_filter( array_map('trim', explode("\n", $photo_urls)) );
        $first  = true;
        foreach ( $photos as $photo_data ) {
            if ( empty($photo_data) ) continue;
            if ( strpos($photo_data, 'data:image') === 0 ) {
                preg_match('/data:image\/(\w+);base64,/', $photo_data, $m);
                $ext      = $m[1] ?? 'jpg';
                $img_data = base64_decode( preg_replace('/^data:image\/\w+;base64,/', '', $photo_data) );
                if ( $img_data === false ) continue;
                $upload   = wp_upload_dir();
                $filename = 'listing-' . $post_id . '-' . uniqid() . '.' . $ext;
                $filepath = $upload['path'] . '/' . $filename;
                if ( file_put_contents($filepath, $img_data) === false ) continue;
                $filetype  = wp_check_filetype($filename);
                $attach_id = wp_insert_attachment(['post_mime_type'=>$filetype['type'],'post_title'=>sanitize_file_name($filename),'post_content'=>'','post_status'=>'inherit'], $filepath, $post_id);
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $filepath));
                if ( $first ) { set_post_thumbnail($post_id, $attach_id); $first = false; }
            } elseif ( filter_var($photo_data, FILTER_VALIDATE_URL) && $first ) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_id = media_sideload_image($photo_data, $post_id, null, 'id');
                if ( ! is_wp_error($attach_id) ) { set_post_thumbnail($post_id, $attach_id); $first = false; }
            }
        }
    }

    $video_data = $_POST['video_url'] ?? '';
    if ( ! empty($video_data) && strpos($video_data, 'data:video') === 0 ) {
        preg_match('/data:video\/(\w+);base64,/', $video_data, $vm);
        $vext      = $vm[1] ?? 'mp4';
        $v_data    = base64_decode( preg_replace('/^data:video\/\w+;base64,/', '', $video_data) );
        $vupload   = wp_upload_dir();
        $vfilename = 'listing-video-' . $post_id . '-' . uniqid() . '.' . $vext;
        $vfilepath = $vupload['path'] . '/' . $vfilename;
        file_put_contents($vfilepath, $v_data);
        $vfiletype  = wp_check_filetype($vfilename);
        $vattach_id = wp_insert_attachment(['post_mime_type'=>$vfiletype['type'],'post_title'=>sanitize_file_name($vfilename),'post_content'=>'','post_status'=>'inherit'], $vfilepath, $post_id);
        if ( ! is_wp_error($vattach_id) ) {
            update_post_meta($post_id, 'listing_video_id', $vattach_id);
            update_post_meta($post_id, 'listing_video_url', wp_get_attachment_url($vattach_id));
        }
    }

    $plan        = sanitize_text_field($_POST['post_plan'] ?? 'personal');
    $expiry_days = ( $plan === 'business' ) ? 30 : 14;
    update_post_meta($post_id, 'listing_expires', date('Y-m-d', strtotime("+{$expiry_days} days")));
    update_post_meta($post_id, 'listing_plan', $plan);
    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);

    wp_send_json_success([
        'message'  => 'Listing submitted successfully!',
        'post_id'  => $post_id,
        'redirect' => get_permalink($post_id),
    ]);
}
add_action('wp_ajax_dealboard_submit_listing', 'dealboard_submit_listing');
add_action('wp_ajax_nopriv_dealboard_submit_listing', function() {
    wp_send_json_error(['message' => 'Please log in to post a listing', 'redirect' => home_url('/sign-in')]);
});

/* ===========================
   AJAX: GET SUBCATEGORIES
=========================== */
function dealboard_get_subcategories() {
    $parent = (int)($_GET['parent'] ?? 0);
    $terms  = get_terms(['taxonomy'=>'listing_category','parent'=>$parent,'hide_empty'=>false]);
    if ( is_wp_error($terms) || empty($terms) ) { wp_send_json_success([]); }
    $data = array_map(fn($t) => ['id'=>$t->term_id,'name'=>$t->name], $terms);
    wp_send_json_success($data);
}
add_action('wp_ajax_get_subcategories', 'dealboard_get_subcategories');
add_action('wp_ajax_nopriv_get_subcategories', 'dealboard_get_subcategories');

/* ===========================
   VIEW COUNTER
=========================== */
add_action('wp_head', function() {
    if ( is_singular('listing') && ! is_user_logged_in() ) {
        $id         = get_the_ID();
        $cookie_key = 'viewed_listing_' . $id;
        if ( empty($_COOKIE[$cookie_key]) ) {
            $v = (int)get_post_meta($id, 'listing_views', true);
            update_post_meta($id, 'listing_views', $v + 1);
            setcookie($cookie_key, '1', time() + DAY_IN_SECONDS, '/');
        }
    }
});

/* ===========================
   HELPER FUNCTIONS
=========================== */
function dealboard_get_recent_listings( $count = 4 ) {
    return new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
}

function dealboard_get_garage_sales( $count = 3 ) {
    return new WP_Query([
        'post_type'      => 'garage_sale',
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'orderby'        => 'meta_value',
        'meta_key'       => 'sale_date_start',
        'order'          => 'ASC',
    ]);
}

function dealboard_category_icon( $slug ) {
    $icons = [
        'vehicles'=>'🚗','electronics'=>'💻','furniture'=>'🛋️','clothing'=>'👕',
        'tools-equipment'=>'🔧','sports-outdoors'=>'⚽','home-garden'=>'🏡',
        'toys-kids'=>'🧸','books-magazine'=>'📚','food-dining'=>'🍔',
        'jobs-careers'=>'💼','property-rentals'=>'🏠','pets'=>'🐾',
        'mobile-electronics'=>'📱','free-stuff'=>'🎁','beauty-personal-care'=>'💄',
        'agriculture'=>'🌾','apparel'=>'👔','art-collectables'=>'🎨',
        'baby-kids'=>'👶','boat-yacht'=>'⛵','business-sale'=>'🏢',
        'construction'=>'🏗️','event-tickets'=>'🎫','events-entertainment'=>'🎉',
        'furnitures'=>'🛋️','food-dinning'=>'🍔','health-wellness'=>'💊',
        'hobbies'=>'🎮','household-services'=>'🧹','it-software'=>'💻',
        'legal-services'=>'⚖️','marketing-advertising'=>'📢','music-instruments'=>'🎵',
        'office-supplies'=>'🖨️','property-services'=>'🏢','property-sale'=>'🏘️',
        'services'=>'🛠️','special-needs'=>'♿','sports-fitness'=>'⚽',
        'shoes-accessories'=>'👟','toys-games'=>'🧸','travel-tourism'=>'✈️',
        'transportation'=>'🚌','vehicles-parts'=>'🚗','all-others'=>'📦','exchange'=>'🔄',
    ];
    return $icons[$slug] ?? '📦';
}

/* ===========================
   REWRITE RULES
=========================== */
function dealboard_rewrite_rules() {
    add_rewrite_rule( 'post-ad/?$',   'index.php?pagename=post-ad',   'top' );
    add_rewrite_rule( 'sign-in/?$',   'index.php?pagename=sign-in',   'top' );
    add_rewrite_rule( 'sign-up/?$',   'index.php?pagename=sign-up',   'top' );
    add_rewrite_rule( 'dashboard/?$', 'index.php?pagename=dashboard', 'top' );
}
add_action( 'init', 'dealboard_rewrite_rules' );

/* ===========================
   ADMIN COLUMNS
=========================== */
add_filter('manage_listing_posts_columns', function($cols) {
    $cols['listing_price']       = 'Price';
    $cols['listing_city']        = 'City';
    $cols['listing_status_meta'] = 'Status';
    $cols['listing_views']       = 'Views';
    return $cols;
});

add_action('manage_listing_posts_custom_column', function($col, $id) {
    if ($col === 'listing_price')       echo esc_html(get_post_meta($id,'listing_price',true) ?: '—');
    if ($col === 'listing_city')        echo esc_html(get_post_meta($id,'listing_city',true) ?: '—');
    if ($col === 'listing_status_meta') echo esc_html(ucfirst(get_post_meta($id,'listing_status',true) ?: 'active'));
    if ($col === 'listing_views')       echo esc_html(get_post_meta($id,'listing_views',true) ?: '0');
}, 10, 2);

/* ===========================
   EXPIRY CRON
=========================== */
add_action('dealboard_daily_cron', function() {
    $expired = get_posts([
        'post_type'      => ['listing','garage_sale'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key'=>'listing_expires','value'=>date('Y-m-d'),'compare'=>'<','type'=>'DATE'],
            ['key'=>'listing_status','value'=>['active','pending_payment'],'compare'=>'IN'],
        ],
    ]);
    foreach($expired as $p) {
        update_post_meta($p->ID, 'listing_status', 'expired');
    }
});

add_action('after_switch_theme', function() {
    if (!wp_next_scheduled('dealboard_daily_cron')) {
        wp_schedule_event(time(), 'daily', 'dealboard_daily_cron');
    }
});

// Safety net: keep the daily cron scheduled even on already-active installs.
add_action('init', function() {
    if (!wp_next_scheduled('dealboard_daily_cron')) {
        wp_schedule_event(time() + 60, 'daily', 'dealboard_daily_cron');
    }
});

/* ===========================
   ONE-TIME FIX: Mark expired listings
=========================== */
add_action('init', function() {
    if(get_option('dealboard_expired_fix_v3')) return;
    $listings = get_posts([
        'post_type'      => 'listing',
        'post_status'    => ['publish','private'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [[
            'key'     => 'listing_expires',
            'value'   => date('Y-m-d'),
            'compare' => '<',
            'type'    => 'DATE',
        ]],
    ]);
    foreach($listings as $id) {
        wp_update_post(['ID'=>$id,'post_status'=>'publish']);
        update_post_meta($id, 'listing_status', 'expired');
    }
    update_option('dealboard_expired_fix_v3', true);
});

/* ===========================
   LOGIN REDIRECT
=========================== */
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->roles) && in_array('administrator', $user->roles)) return admin_url();
    if (strpos($redirect_to, 'reset-password') !== false) return $redirect_to;
    if (strpos($request,     'reset-password') !== false) return $request;
    return home_url('/dashboard');
}, 10, 3);

/* ===========================
   WIDGETS
=========================== */
add_action('widgets_init', function() {
    register_sidebar(['name'=>'Sidebar','id'=>'sidebar-1','before_widget'=>'<div class="widget">','after_widget'=>'</div>']);
});

/* ===========================
   AUTO SETUP ON ACTIVATION
=========================== */
function dealboard_theme_activation() {
    $pages = [
        'home'         => ['title'=>'Home',        'template'=>'',                   'content'=>'<!-- DealBoard Homepage -->'],
        'post-ad'      => ['title'=>'Post Ad',      'template'=>'page-post-ad.php',   'content'=>''],
        'sign-in'      => ['title'=>'Sign In',      'template'=>'page-sign-in.php',   'content'=>''],
        'sign-up'      => ['title'=>'Sign Up',      'template'=>'page-sign-up.php',   'content'=>''],
        'dashboard'    => ['title'=>'Dashboard',    'template'=>'page-dashboard.php', 'content'=>''],
        'garage-sales' => ['title'=>'Garage Sales', 'template'=>'',                   'content'=>''],
    ];
    $home_id = 0;
    foreach ( $pages as $slug => $data ) {
        $existing = get_page_by_path( $slug );
        if ( $existing ) { if($slug==='home') $home_id=$existing->ID; continue; }
        $page_id = wp_insert_post(['post_title'=>$data['title'],'post_name'=>$slug,'post_content'=>$data['content'],'post_status'=>'publish','post_type'=>'page','post_author'=>1]);
        if ( ! is_wp_error($page_id) ) {
            if ( ! empty($data['template']) ) update_post_meta($page_id, '_wp_page_template', $data['template']);
            if ( $slug === 'home' ) $home_id = $page_id;
        }
    }
    if ( $home_id ) { update_option('show_on_front','page'); update_option('page_on_front',$home_id); }
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'dealboard_theme_activation' );

/* ===========================
   LOAD ELEMENTOR WIDGETS
=========================== */
function dealboard_load_elementor_widgets() {
    if ( did_action('elementor/loaded') ) {
        require_once DEALBOARD_DIR . '/inc/elementor-widgets.php';
    }
}
add_action( 'elementor/loaded', 'dealboard_load_elementor_widgets' );

/* ===========================
   GARAGE SALE REWRITE + PAGE
=========================== */
function dealboard_garage_rewrite() {
    add_rewrite_rule( 'garage-sales/list/?$', 'index.php?pagename=list-garage-sale', 'top' );
}
add_action( 'init', 'dealboard_garage_rewrite' );

function dealboard_create_garage_page() {
    if ( ! get_page_by_path('list-garage-sale') ) {
        $id = wp_insert_post(['post_title'=>'List Garage Sale','post_name'=>'list-garage-sale','post_content'=>'','post_status'=>'publish','post_type'=>'page','post_author'=>1]);
        if ( ! is_wp_error($id) ) update_post_meta($id, '_wp_page_template', 'page-list-garage-sale.php');
    }
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'dealboard_create_garage_page' );

/* ===========================
   AUTO CREATE EXCHANGE PAGE
=========================== */
function dealboard_create_exchange_page() {
    if ( ! get_page_by_path('exchange') ) {
        $id = wp_insert_post(['post_title'=>'Exchange','post_name'=>'exchange','post_content'=>'','post_status'=>'publish','post_type'=>'page','post_author'=>1]);
        if ( ! is_wp_error($id) ) update_post_meta($id, '_wp_page_template', 'page-exchange.php');
    }
}
add_action( 'after_switch_theme', 'dealboard_create_exchange_page' );
add_action( 'init', function() {
    static $done = false;
    if ( ! $done && ! get_page_by_path('exchange') ) { dealboard_create_exchange_page(); $done = true; }
});

/* ===========================
   HIDE ADMIN BAR FOR NON-ADMINS
=========================== */
add_action('after_setup_theme', function() {
    if (!current_user_can('administrator')) show_admin_bar(false);
});

/* ===========================
   COUNTRY FILTER + SORT FOR LISTINGS
=========================== */
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && ($query->is_post_type_archive('listing') || $query->is_tax('listing_category'))) {
        // Country filter
        $country = sanitize_text_field($_GET['country'] ?? '');
        if (!empty($country)) {
            $meta_query   = $query->get('meta_query') ?: [];
            $meta_query[] = ['key'=>'listing_country','value'=>$country,'compare'=>'='];
            $query->set('meta_query', $meta_query);
        }
        // Sort
        $sort = sanitize_text_field($_GET['sort'] ?? 'newest');
        if ($sort === 'oldest') {
            $query->set('orderby', 'date');
            $query->set('order',   'ASC');
        } else {
            $query->set('orderby', 'date');
            $query->set('order',   'DESC');
        }
    }
});

/* ===========================
   PASSWORD RESET — intercept BEFORE WordPress login
=========================== */
add_action('login_init', function() {
    if (
        isset($_GET['action']) &&
        in_array($_GET['action'], ['rp','resetpass']) &&
        isset($_GET['key']) &&
        isset($_GET['login'])
    ) {
        $key   = sanitize_text_field($_GET['key']);
        $login = sanitize_text_field($_GET['login']);
        wp_redirect(home_url('/reset-password/?key='.$key.'&login='.rawurlencode($login)));
        exit;
    }
}, 1);

add_filter('lostpassword_redirect', function($redirect) {
    return home_url('/forgot-password/?sent=1');
});

add_filter('retrieve_password_message', function($message, $key, $user_login, $user_data) {
    $reset_url = home_url('/reset-password/?key='.$key.'&login='.rawurlencode($user_login));
    $site_name = get_bloginfo('name');
    $message   = "Hi " . $user_login . ",\r\n\r\n";
    $message  .= "Someone requested a password reset for your " . $site_name . " account.\r\n\r\n";
    $message  .= "If this was a mistake, just ignore this email.\r\n\r\n";
    $message  .= "To reset your password, click the link below:\r\n\r\n";
    $message  .= $reset_url . "\r\n\r\n";
    $message  .= "This link will expire in 24 hours.\r\n\r\n";
    $message  .= "— The " . $site_name . " Team\r\n";
    return $message;
}, 10, 4);

add_filter('retrieve_password_title', function($title, $user_login, $user_data) {
    return 'Reset Your Password — ' . get_bloginfo('name');
}, 10, 3);

/* ===========================
   UPLOAD SIZE FOR VIDEOS
=========================== */
@ini_set('upload_max_filesize', '50M');
@ini_set('post_max_size', '50M');

/* ===========================
   RESET PASSWORD SHORTCODE
=========================== */
add_shortcode('reset_password_form', function() {
    $key   = sanitize_text_field($_GET['key'] ?? '');
    $login = sanitize_text_field($_GET['login'] ?? '');

    if (is_user_logged_in() && empty($key)) {
        return '<script>window.location.href="' . esc_url(home_url('/dashboard')) . '";</script>';
    }

    $error   = '';
    $success = false;
    $user    = ($key && $login) ? check_password_reset_key($key, $login) : false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rp_nonce'])) {
        if (!wp_verify_nonce($_POST['rp_nonce'], 'rp_action')) {
            $error = 'Security check failed.';
        } else {
            $pass1 = $_POST['new_password'] ?? '';
            $pass2 = $_POST['confirm_password'] ?? '';
            $rk    = sanitize_text_field($_POST['rp_key'] ?? '');
            $rl    = sanitize_text_field($_POST['rp_login'] ?? '');
            if (empty($pass1))           $error = 'Please enter a password.';
            elseif (strlen($pass1) < 8)  $error = 'Password must be at least 8 characters.';
            elseif ($pass1 !== $pass2)   $error = 'Passwords do not match.';
            else {
                $ru = check_password_reset_key($rk, $rl);
                if (is_wp_error($ru)) {
                    $error = 'Link expired. Please request a new one.';
                } else {
                    reset_password($ru, $pass1);
                    wp_set_auth_cookie($ru->ID, false);
                    $success = true;
                }
            }
        }
    }

    ob_start(); ?>
    <style>
    .rp-wrap{max-width:440px;margin:40px auto;padding:0 16px}
    .rp-icon-row{text-align:center;margin-bottom:20px}
    .rp-card{background:white;border:1px solid #E5E7EB;border-radius:16px;padding:36px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
    .rp-title{font-size:22px;font-weight:700;text-align:center;margin-bottom:6px}
    .rp-sub{font-size:14px;color:#6B7280;text-align:center;margin-bottom:24px}
    .rp-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
    .rp-field{margin-bottom:18px}
    .rp-input-wrap{position:relative}
    .rp-input{width:100%;padding:10px 42px 10px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:14px;outline:none;font-family:inherit;box-sizing:border-box}
    .rp-input:focus{border-color:#C8102E;box-shadow:0 0 0 3px rgba(200,16,46,.1)}
    .rp-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:16px;padding:4px;line-height:1}
    .rp-btn{width:100%;padding:13px;background:#C8102E;color:white;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;font-family:inherit;margin-top:4px}
    .rp-btn:hover{background:#A50E26}
    .rp-alert-error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:12px 16px;font-size:14px;margin-bottom:16px}
    .rp-back{text-align:center;margin-top:16px;font-size:13px}
    .rp-back a{color:#C8102E;font-weight:600;text-decoration:none}
    </style>

    <?php if ($success): ?>
    <div class="rp-wrap">
      <div class="rp-card" style="text-align:center;padding:48px 36px">
        <div style="font-size:56px;margin-bottom:16px">✅</div>
        <h2 class="rp-title">Password Reset!</h2>
        <p style="color:#6B7280;margin-bottom:24px;font-size:14px">Your password has been updated. You are now logged in.</p>
        <a href="<?php echo esc_url(home_url('/dashboard')); ?>"
          style="display:inline-block;padding:12px 28px;background:#C8102E;color:white;font-weight:700;border-radius:10px;text-decoration:none">
          Go to Dashboard →
        </a>
      </div>
    </div>

    <?php elseif (!$key || !$login || is_wp_error($user)): ?>
    <div class="rp-wrap">
      <div class="rp-card" style="text-align:center;padding:48px 36px">
        <div style="font-size:56px;margin-bottom:16px">⚠️</div>
        <h2 class="rp-title">Link Expired</h2>
        <p style="color:#6B7280;margin-bottom:24px;font-size:14px">This reset link is invalid or has expired.</p>
        <a href="<?php echo esc_url(home_url('/forgot-password')); ?>"
          style="display:inline-block;padding:12px 28px;background:#C8102E;color:white;font-weight:700;border-radius:10px;text-decoration:none">
          Request New Link
        </a>
      </div>
    </div>

    <?php else: ?>
    <div class="rp-wrap">
      <div class="rp-card">
        <div class="rp-icon-row"><div style="font-size:40px">🔐</div></div>
        <h2 class="rp-title">Set New Password</h2>
        <p class="rp-sub">Choose a strong password for your account.</p>

        <?php if ($error): ?>
        <div class="rp-alert-error">❌ <?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="POST">
          <?php wp_nonce_field('rp_action', 'rp_nonce'); ?>
          <input type="hidden" name="rp_key"   value="<?php echo esc_attr($key); ?>">
          <input type="hidden" name="rp_login" value="<?php echo esc_attr($login); ?>">

          <div class="rp-field">
            <label class="rp-label">New Password</label>
            <div class="rp-input-wrap">
              <input type="password" name="new_password" id="rp_pass1" class="rp-input"
                placeholder="Min. 8 characters" required>
              <button type="button" class="rp-eye" onclick="rpToggle('rp_pass1',this)" title="Show/Hide">👁</button>
            </div>
          </div>

          <div class="rp-field">
            <label class="rp-label">Confirm Password</label>
            <div class="rp-input-wrap">
              <input type="password" name="confirm_password" id="rp_pass2" class="rp-input"
                placeholder="Repeat your password" required>
              <button type="button" class="rp-eye" onclick="rpToggle('rp_pass2',this)" title="Show/Hide">👁</button>
            </div>
          </div>

          <button type="submit" class="rp-btn">🔐 Reset Password</button>
        </form>

        <div class="rp-back">
          <a href="<?php echo esc_url(home_url('/sign-in')); ?>">← Back to Sign In</a>
        </div>
      </div>
    </div>

    <script>
    function rpToggle(id, btn) {
        var input = document.getElementById(id);
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁';
        }
    }
    </script>
    <?php endif;

    return ob_get_clean();
});