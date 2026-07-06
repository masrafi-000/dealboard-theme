<?php
/**
 * DealBoard / American Alley — Stripe Subscriptions for Business Ads
 *
 * Business listings are billed as a $2 / 30-day Stripe subscription:
 *   - Payment is collected IN-PAGE via Stripe Payment Element (no redirect to Stripe).
 *   - Auto-pay ON  → Stripe charges $2 every 30 days; each paid invoice extends the
 *     listing another 30 days.
 *   - Auto-pay OFF → the subscription is set to cancel at period end; once the 30
 *     days lapse the ad stops showing.
 *
 * Dependency-free: talks to the Stripe REST API over the WordPress HTTP API.
 *
 * Listing meta used:
 *   listing_plan                 'business'
 *   listing_status               active | pending_payment | expired
 *   listing_expires              Y-m-d
 *   listing_stripe_customer      cus_xxx
 *   listing_stripe_subscription  sub_xxx
 *   listing_autopay              '1' | '0'
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ===========================================================
   CONFIG
=========================================================== */
function dealboard_stripe_opts() {
    return wp_parse_args( get_option( 'dealboard_stripe', [] ), [
        'secret_key'     => '',
        'publishable_key'=> '',
        'webhook_secret' => '',
        'amount'         => 200,    // cents → $2.00
        'currency'       => 'usd',
        'interval'       => 'month',
        'test_mode'      => 1,
    ] );
}

function dealboard_stripe_secret() {
    $o = dealboard_stripe_opts();
    return trim( $o['secret_key'] );
}

function dealboard_stripe_pub_key() {
    $o = dealboard_stripe_opts();
    return trim( $o['publishable_key'] );
}

function dealboard_stripe_is_ready() {
    $sk = dealboard_stripe_secret();
    $pk = dealboard_stripe_pub_key();
    return $sk && $pk;
}

/* ===========================================================
   LOW-LEVEL API WRAPPER
=========================================================== */
function dealboard_stripe_api( $method, $path, $body = [] ) {
    $secret = dealboard_stripe_secret();
    if ( ! $secret ) return new WP_Error( 'no_key', 'Stripe is not configured.' );

    $args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization'  => 'Bearer ' . $secret,
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Stripe-Version' => '2023-10-16',
        ],
    ];

    if ( ! empty( $body ) ) {
        $args['body'] = dealboard_stripe_flatten( $body );
    }

    $resp = wp_remote_request( 'https://api.stripe.com/v1/' . $path, $args );
    if ( is_wp_error( $resp ) ) return $resp;

    $code = wp_remote_retrieve_response_code( $resp );
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $code >= 200 && $code < 300 ) return $data;

    $msg = $data['error']['message'] ?? 'Stripe API error (HTTP ' . $code . ').';
    return new WP_Error( 'stripe_error', $msg, $data );
}

/**
 * Flatten nested PHP array into Stripe form-encoded format.
 * e.g. ['line_items'=>[['quantity'=>1]]] → "line_items[0][quantity]=1"
 */
function dealboard_stripe_flatten( $array, $prefix = '' ) {
    $result = [];
    foreach ( $array as $key => $value ) {
        $full_key = $prefix ? $prefix . '[' . $key . ']' : (string) $key;
        if ( is_array( $value ) ) {
            $result = array_merge( $result, dealboard_stripe_flatten( $value, $full_key ) );
        } else {
            $result[ $full_key ] = $value;
        }
    }
    return $result;
}

/* ===========================================================
   LISTING STATE HELPERS
=========================================================== */
function dealboard_activate_business_listing( $listing_id, $period_end_ts = 0 ) {
    $expires = $period_end_ts
        ? date( 'Y-m-d', $period_end_ts )
        : date( 'Y-m-d', strtotime( '+30 days', current_time( 'timestamp' ) ) );

    update_post_meta( $listing_id, 'listing_status', 'active' );
    update_post_meta( $listing_id, 'listing_expires', $expires );

    $post = get_post( $listing_id );
    if ( $post && in_array( $post->post_status, [ 'pending', 'draft' ], true ) ) {
        wp_update_post( [ 'ID' => $listing_id, 'post_status' => 'publish' ] );
    }
}

function dealboard_expire_business_listing( $listing_id ) {
    update_post_meta( $listing_id, 'listing_status', 'expired' );
    update_post_meta( $listing_id, 'listing_autopay', '0' );
    $post = get_post( $listing_id );
    if ( $post && $post->post_status === 'publish' ) {
        wp_update_post( [ 'ID' => $listing_id, 'post_status' => 'draft' ] );
    }
}

function dealboard_listing_by_subscription( $sub_id ) {
    if ( ! $sub_id ) return 0;
    $ids = get_posts( [
        'post_type'   => 'listing',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_key'    => 'listing_stripe_subscription',
        'meta_value'  => $sub_id,
    ] );
    return $ids ? (int) $ids[0] : 0;
}

function dealboard_stripe_get_or_create_product() {
    $secret = dealboard_stripe_secret();
    $opt_key = 'dealboard_stripe_prod_' . md5( $secret );
    $product_id = get_option( $opt_key );
    if ( $product_id ) {
        return $product_id;
    }

    $prod = dealboard_stripe_api( 'POST', 'products', [
        'name'        => 'Business Listing Ad',
        'description' => 'Business Plan ad listing subscription',
    ] );

    if ( is_wp_error( $prod ) ) {
        return $prod;
    }

    $product_id = $prod['id'];
    update_option( $opt_key, $product_id );
    return $product_id;
}

/* ===========================================================
   STEP 1 — AJAX: Create / reuse Stripe Customer + Subscription,
   return the subscription's client_secret for the Payment Element.
=========================================================== */
add_action( 'wp_ajax_dealboard_create_payment_intent', 'dealboard_ajax_create_payment_intent' );

function dealboard_ajax_create_payment_intent() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dealboard_stripe_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'You must be logged in.' ] );
    }
    if ( ! dealboard_stripe_is_ready() ) {
        wp_send_json_error( [ 'message' => 'Stripe is not configured. Please contact support.' ] );
    }

    $listing_id = (int) ( $_POST['listing_id'] ?? 0 );
    if ( ! $listing_id ) {
        wp_send_json_error( [ 'message' => 'Invalid listing.' ] );
    }

    $post = get_post( $listing_id );
    if ( ! $post || $post->post_author != get_current_user_id() ) {
        wp_send_json_error( [ 'message' => 'Listing not found.' ] );
    }

    $o    = dealboard_stripe_opts();
    $user = wp_get_current_user();

    // ── 1. Get or create Stripe Customer ──────────────────────────────────
    $customer_id = get_post_meta( $listing_id, 'listing_stripe_customer', true )
                ?: get_user_meta( $user->ID, 'dealboard_stripe_customer', true );

    if ( ! $customer_id ) {
        $cust = dealboard_stripe_api( 'POST', 'customers', [
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'metadata' => [ 'wp_user_id' => $user->ID ],
        ] );
        if ( is_wp_error( $cust ) ) {
            wp_send_json_error( [ 'message' => $cust->get_error_message() ] );
        }
        $customer_id = $cust['id'];
        update_user_meta( $user->ID, 'dealboard_stripe_customer', $customer_id );
    }
    update_post_meta( $listing_id, 'listing_stripe_customer', $customer_id );

    // ── 2. Get or create Product ID ───────────────────────────────────────
    $product_id = dealboard_stripe_get_or_create_product();
    if ( is_wp_error( $product_id ) ) {
        wp_send_json_error( [ 'message' => 'Product setup failed: ' . $product_id->get_error_message() ] );
    }

    // ── 3. Create a Subscription with payment_behavior=default_incomplete ──
    //    This gives us a client_secret we can pass to the Payment Element.
    //    The subscription only becomes active when the Payment Element confirms.
    $sub = dealboard_stripe_api( 'POST', 'subscriptions', [
        'customer'         => $customer_id,
        'items'            => [ [
            'price_data' => [
                'currency'    => $o['currency'],
                'unit_amount' => (int) $o['amount'],
                'recurring'   => [ 'interval' => $o['interval'], 'interval_count' => 1 ],
                'product'     => $product_id,
            ],
        ] ],
        'payment_behavior' => 'default_incomplete',
        'payment_settings' => [ 'save_default_payment_method' => 'on_subscription' ],
        'expand'           => [ 'latest_invoice.payment_intent' ],
        'metadata'         => [ 'listing_id' => $listing_id, 'wp_user_id' => $user->ID ],
    ] );

    if ( is_wp_error( $sub ) ) {
        wp_send_json_error( [ 'message' => $sub->get_error_message() ] );
    }

    $client_secret = $sub['latest_invoice']['payment_intent']['client_secret'] ?? '';
    if ( ! $client_secret ) {
        wp_send_json_error( [ 'message' => 'Could not create payment intent. Please try again.' ] );
    }

    // Store the subscription ID immediately so we can activate on confirmation
    update_post_meta( $listing_id, 'listing_stripe_subscription', $sub['id'] );

    wp_send_json_success( [
        'client_secret'   => $client_secret,
        'publishable_key' => dealboard_stripe_pub_key(),
        'amount'          => $o['amount'],
        'currency'        => strtoupper( $o['currency'] ),
        'listing_title'   => get_the_title( $listing_id ),
        'sub_id'          => $sub['id'],
    ] );
}

/* ===========================================================
   STEP 2 — AJAX: Confirm payment success (called after
   stripe.confirmPayment resolves on the client side).
=========================================================== */
add_action( 'wp_ajax_dealboard_confirm_payment', 'dealboard_ajax_confirm_payment' );

function dealboard_ajax_confirm_payment() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dealboard_stripe_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Not logged in.' ] );
    }

    $listing_id     = (int) ( $_POST['listing_id'] ?? 0 );
    $payment_intent = sanitize_text_field( $_POST['payment_intent'] ?? '' );
    $sub_id         = sanitize_text_field( $_POST['sub_id'] ?? '' );

    if ( ! $listing_id || ! $payment_intent ) {
        wp_send_json_error( [ 'message' => 'Missing data.' ] );
    }

    $post = get_post( $listing_id );
    if ( ! $post || $post->post_author != get_current_user_id() ) {
        wp_send_json_error( [ 'message' => 'Listing not found.' ] );
    }

    // Verify the PaymentIntent status with Stripe
    $pi = dealboard_stripe_api( 'GET', 'payment_intents/' . rawurlencode( $payment_intent ), [] );
    if ( is_wp_error( $pi ) ) {
        wp_send_json_error( [ 'message' => $pi->get_error_message() ] );
    }

    $pi_status = $pi['status'] ?? '';

    if ( $pi_status === 'succeeded' ) {
        // Activate the listing
        if ( $sub_id ) {
            update_post_meta( $listing_id, 'listing_stripe_subscription', $sub_id );
            update_post_meta( $listing_id, 'listing_autopay', '1' );
            // Fetch period end from subscription
            $sub = dealboard_stripe_api( 'GET', 'subscriptions/' . rawurlencode( $sub_id ), [] );
            $period_end = ( ! is_wp_error( $sub ) && ! empty( $sub['current_period_end'] ) )
                ? (int) $sub['current_period_end'] : 0;
            dealboard_activate_business_listing( $listing_id, $period_end );
        } else {
            dealboard_activate_business_listing( $listing_id );
        }
        wp_send_json_success( [
            'message'      => 'Payment successful! Your business listing is now live.',
            'redirect'     => home_url( '/dashboard/?payment_success=1' ),
        ] );
    } elseif ( in_array( $pi_status, [ 'processing', 'requires_capture' ], true ) ) {
        // Still processing — activate optimistically, webhook will confirm
        dealboard_activate_business_listing( $listing_id );
        wp_send_json_success( [
            'message'  => 'Payment is processing. Your listing will go live shortly.',
            'redirect' => home_url( '/dashboard/?payment_success=1' ),
        ] );
    } else {
        wp_send_json_error( [ 'message' => 'Payment not completed. Status: ' . $pi_status ] );
    }
}

/* ===========================================================
   LEGACY DIRECT PAY URL (?dealboard_pay=1&listing=ID)
   Kept for dashboard "Pay $2 & Activate" button backward compat.
   Opens the on-page modal instead of redirecting to Stripe.
=========================================================== */
add_action( 'template_redirect', function() {
    if ( empty( $_GET['dealboard_pay'] ) ) return;

    $listing_id = (int) ( $_GET['listing'] ?? 0 );
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/sign-in' ) ); exit;
    }
    if ( ! $listing_id ) {
        wp_safe_redirect( home_url( '/dashboard/' ) ); exit;
    }

    $nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'db_pay_' . $listing_id );
    if ( ! $nonce_ok ) {
        wp_die( 'Security check failed. Please go back and try again.', 'Security Error', [ 'response' => 403, 'back_link' => true ] );
    }

    $post = get_post( $listing_id );
    if ( ! $post || $post->post_author != get_current_user_id() ) {
        wp_die( 'Listing not found.' );
    }
    if ( ! dealboard_stripe_is_ready() ) {
        wp_safe_redirect( home_url( '/dashboard/?payment_error=' . rawurlencode( 'Stripe payments are not configured yet. Please contact support.' ) ) );
        exit;
    }

    // Redirect to dashboard with modal trigger param
    wp_safe_redirect( home_url( '/dashboard/?open_payment_modal=' . $listing_id ) );
    exit;
}, 5 );

/* ===========================================================
   CANCEL / RESUME AUTO-PAYMENT  (dashboard buttons)
=========================================================== */
add_action( 'template_redirect', function() {
    if ( empty( $_GET['dealboard_sub'] ) ) return;

    $action     = sanitize_text_field( $_GET['dealboard_sub'] );
    $listing_id = (int) ( $_GET['listing'] ?? 0 );

    if ( ! is_user_logged_in() ) { wp_safe_redirect( home_url( '/sign-in' ) ); exit; }
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'db_sub_' . $listing_id ) ) {
        wp_die( 'Security check failed.' );
    }
    $post = get_post( $listing_id );
    if ( ! $post || $post->post_author != get_current_user_id() ) wp_die( 'Listing not found.' );

    $sub_id = get_post_meta( $listing_id, 'listing_stripe_subscription', true );
    if ( ! $sub_id ) { wp_safe_redirect( home_url( '/dashboard/' ) ); exit; }

    if ( $action === 'cancel' ) {
        $res = dealboard_stripe_api( 'POST', 'subscriptions/' . rawurlencode( $sub_id ), [ 'cancel_at_period_end' => 'true' ] );
        if ( ! is_wp_error( $res ) ) update_post_meta( $listing_id, 'listing_autopay', '0' );
        wp_safe_redirect( home_url( '/dashboard/?autopay=off' ) );
        exit;
    }
    if ( $action === 'resume' ) {
        $res = dealboard_stripe_api( 'POST', 'subscriptions/' . rawurlencode( $sub_id ), [ 'cancel_at_period_end' => 'false' ] );
        if ( ! is_wp_error( $res ) ) update_post_meta( $listing_id, 'listing_autopay', '1' );
        wp_safe_redirect( home_url( '/dashboard/?autopay=on' ) );
        exit;
    }
}, 5 );

/* ===========================================================
   WEBHOOK  (/wp-json/dealboard/v1/stripe-webhook)
=========================================================== */
add_action( 'rest_api_init', function() {
    register_rest_route( 'dealboard/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'dealboard_stripe_webhook',
    ] );
} );

function dealboard_stripe_webhook( $request ) {
    $payload = $request->get_body();
    $secret  = dealboard_stripe_opts()['webhook_secret'];

    if ( $secret ) {
        $sig = $request->get_header( 'stripe_signature' );
        if ( ! dealboard_stripe_verify_sig( $payload, $sig, $secret ) ) {
            return new WP_REST_Response( [ 'error' => 'bad signature' ], 400 );
        }
    }

    $event = json_decode( $payload, true );
    if ( ! $event || empty( $event['type'] ) ) {
        return new WP_REST_Response( [ 'error' => 'invalid' ], 400 );
    }

    $type = $event['type'];
    $obj  = $event['data']['object'] ?? [];

    switch ( $type ) {

        case 'invoice.paid':
        case 'invoice.payment_succeeded':
            $sub_id     = $obj['subscription'] ?? '';
            $listing_id = dealboard_listing_by_subscription( $sub_id );
            if ( $listing_id ) {
                $period_end = (int) ( $obj['lines']['data'][0]['period']['end'] ?? 0 );
                update_post_meta( $listing_id, 'listing_autopay', '1' );
                dealboard_activate_business_listing( $listing_id, $period_end );
            }
            break;

        case 'invoice.payment_failed':
            $sub_id     = $obj['subscription'] ?? '';
            $listing_id = dealboard_listing_by_subscription( $sub_id );
            if ( $listing_id ) update_post_meta( $listing_id, 'listing_payment_failed', '1' );
            break;

        case 'customer.subscription.deleted':
            $listing_id = dealboard_listing_by_subscription( $obj['id'] ?? '' );
            if ( $listing_id ) dealboard_expire_business_listing( $listing_id );
            break;

        case 'customer.subscription.updated':
            $listing_id = dealboard_listing_by_subscription( $obj['id'] ?? '' );
            if ( $listing_id ) {
                $autopay = empty( $obj['cancel_at_period_end'] ) ? '1' : '0';
                update_post_meta( $listing_id, 'listing_autopay', $autopay );
                if ( ( $obj['status'] ?? '' ) === 'active' && ! empty( $obj['current_period_end'] ) ) {
                    dealboard_activate_business_listing( $listing_id, (int) $obj['current_period_end'] );
                }
            }
            break;
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

function dealboard_stripe_verify_sig( $payload, $sig_header, $secret ) {
    if ( ! $sig_header ) return false;
    $parts = [];
    foreach ( explode( ',', $sig_header ) as $piece ) {
        $kv = explode( '=', trim( $piece ), 2 );
        if ( count( $kv ) === 2 ) $parts[ $kv[0] ][] = $kv[1];
    }
    $t  = $parts['t'][0] ?? '';
    $v1 = $parts['v1'] ?? [];
    if ( ! $t || ! $v1 ) return false;

    $expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
    foreach ( $v1 as $candidate ) {
        if ( hash_equals( $expected, $candidate ) ) {
            if ( abs( time() - (int) $t ) <= 300 ) return true;
        }
    }
    return false;
}

/* ===========================================================
   DAILY RECONCILE
=========================================================== */
function dealboard_stripe_reconcile_subscriptions() {
    if ( ! dealboard_stripe_is_ready() ) return;

    $listings = get_posts( [
        'post_type'   => 'listing',
        'post_status' => [ 'publish', 'draft', 'pending' ],
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => [ [ 'key' => 'listing_stripe_subscription', 'compare' => 'EXISTS' ] ],
    ] );

    foreach ( $listings as $id ) {
        $sub_id = get_post_meta( $id, 'listing_stripe_subscription', true );
        if ( ! $sub_id ) continue;

        $sub = dealboard_stripe_api( 'GET', 'subscriptions/' . rawurlencode( $sub_id ), [] );
        if ( is_wp_error( $sub ) ) continue;

        $status  = $sub['status'] ?? '';
        $autopay = empty( $sub['cancel_at_period_end'] ) ? '1' : '0';
        update_post_meta( $id, 'listing_autopay', $autopay );

        if ( in_array( $status, [ 'active', 'trialing' ], true ) ) {
            if ( ! empty( $sub['current_period_end'] ) ) {
                dealboard_activate_business_listing( $id, (int) $sub['current_period_end'] );
            }
        } elseif ( in_array( $status, [ 'canceled', 'unpaid', 'incomplete_expired' ], true ) ) {
            dealboard_expire_business_listing( $id );
        }
    }

    // Expire auto-pay OFF listings past their expiry
    $expired = get_posts( [
        'post_type'   => 'listing',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => [
            'relation' => 'AND',
            [ 'key' => 'listing_plan',    'value' => 'business', 'compare' => '=' ],
            [ 'key' => 'listing_autopay', 'value' => '0',        'compare' => '=' ],
            [ 'key' => 'listing_expires', 'value' => date( 'Y-m-d' ), 'compare' => '<', 'type' => 'DATE' ],
        ],
    ] );
    foreach ( $expired as $id ) {
        dealboard_expire_business_listing( $id );
    }
}
add_action( 'dealboard_daily_cron', 'dealboard_stripe_reconcile_subscriptions' );

/* ===========================================================
   ENQUEUE STRIPE.JS + LOCALIZE DATA
=========================================================== */
add_action( 'wp_enqueue_scripts', function() {
    $o = dealboard_stripe_opts();
    if ( ! $o['publishable_key'] ) return;

    // Stripe.js — load globally so any page can open the payment modal
    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
    wp_add_inline_script( 'stripe-js', dealboard_stripe_modal_js(), 'after' );
    wp_add_inline_style_workaround();
} );

function wp_add_inline_style_workaround() {
    // Inline style for the modal (piggyback on dealboard-style handle)
    add_action( 'wp_head', function() {
        echo '<style id="dealboard-stripe-modal-css">' . dealboard_stripe_modal_css() . '</style>';
    }, 20 );
}

/* ===========================================================
   INLINE CSS FOR THE PAYMENT MODAL
=========================================================== */
function dealboard_stripe_modal_css() {
    return <<<'CSS'
/* ── Stripe Payment Modal ───────────────────────────── */
#db-payment-modal-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.55);
  backdrop-filter: blur(4px);
  z-index: 99990;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
#db-payment-modal-backdrop.db-modal-open {
  display: flex;
  animation: dbFadeIn .2s ease;
}
@keyframes dbFadeIn { from{opacity:0} to{opacity:1} }

#db-payment-modal {
  background: #fff;
  border-radius: 20px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 24px 60px rgba(0,0,0,.25);
  overflow: hidden;
  animation: dbSlideUp .25s ease;
}
@keyframes dbSlideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }

#db-payment-modal .db-modal-header {
  background: linear-gradient(135deg, #C8102E 0%, #9B000E 100%);
  padding: 24px 28px 20px;
  position: relative;
}
#db-payment-modal .db-modal-header h2 {
  color: #fff;
  font-size: 19px;
  font-weight: 800;
  margin: 0 0 4px;
  line-height: 1.2;
}
#db-payment-modal .db-modal-header p {
  color: rgba(255,255,255,.8);
  font-size: 13px;
  margin: 0;
}
#db-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: rgba(255,255,255,.15);
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
}
#db-modal-close:hover { background: rgba(255,255,255,.3); }

#db-payment-modal .db-modal-body {
  padding: 28px;
}

.db-price-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #FEF3C7;
  border: 1px solid #FCD34D;
  border-radius: 10px;
  padding: 10px 16px;
  margin-bottom: 22px;
  width: 100%;
  box-sizing: border-box;
}
.db-price-badge .db-price-amount {
  font-size: 22px;
  font-weight: 800;
  color: #92400E;
}
.db-price-badge .db-price-label {
  font-size: 12px;
  color: #92400E;
  line-height: 1.3;
}

#db-payment-element {
  margin-bottom: 20px;
  min-height: 80px;
}

.db-autopay-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
  cursor: pointer;
}
.db-autopay-toggle input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #C8102E;
  cursor: pointer;
  flex-shrink: 0;
}
.db-autopay-toggle label {
  font-size: 13px;
  color: #374151;
  cursor: pointer;
  line-height: 1.4;
}
.db-autopay-toggle label strong { color: #111827; }

#db-pay-btn {
  width: 100%;
  padding: 14px;
  background: #C8102E;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background .15s, opacity .15s;
}
#db-pay-btn:hover:not(:disabled) { background: #A50E26; }
#db-pay-btn:disabled { opacity: .6; cursor: not-allowed; }

#db-modal-error {
  display: none;
  background: #FEE2E2;
  color: #DC2626;
  border: 1px solid #FECACA;
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 13px;
  margin-top: 14px;
}

#db-modal-success {
  display: none;
  text-align: center;
  padding: 16px 0 4px;
}
#db-modal-success .db-success-icon { font-size: 52px; margin-bottom: 12px; }
#db-modal-success h3 { font-size: 20px; font-weight: 800; color: #065F46; margin-bottom: 8px; }
#db-modal-success p  { font-size: 14px; color: #6B7280; margin-bottom: 20px; }
#db-modal-success a  {
  display: inline-block;
  padding: 12px 28px;
  background: #059669;
  color: #fff;
  font-weight: 700;
  border-radius: 10px;
  text-decoration: none;
  font-size: 15px;
}

.db-secure-note {
  text-align: center;
  font-size: 11px;
  color: #9CA3AF;
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

#db-modal-spinner {
  display: none;
  text-align: center;
  padding: 32px 0;
}
#db-modal-spinner .db-spin {
  width: 40px;
  height: 40px;
  border: 4px solid #E5E7EB;
  border-top-color: #C8102E;
  border-radius: 50%;
  animation: dbSpin .8s linear infinite;
  margin: 0 auto 12px;
}
@keyframes dbSpin { to { transform: rotate(360deg); } }
CSS;
}

/* ===========================================================
   INLINE JS FOR THE PAYMENT MODAL
=========================================================== */
function dealboard_stripe_modal_js() {
    $ajax_url    = admin_url( 'admin-ajax.php' );
    $nonce       = is_user_logged_in() ? wp_create_nonce( 'dealboard_stripe_nonce' ) : '';
    $dashboard   = home_url( '/dashboard/' );

    return <<<JS
(function(){
  /* ── Modal HTML ─────────────────────────────────────────── */
  var html = `
  <div id="db-payment-modal-backdrop">
    <div id="db-payment-modal" role="dialog" aria-modal="true" aria-label="Complete Payment">
      <div class="db-modal-header">
        <button id="db-modal-close" aria-label="Close">&times;</button>
        <h2>💳 Complete Payment</h2>
        <p>Secure card payment powered by Stripe</p>
      </div>
      <div class="db-modal-body">
        <div id="db-modal-spinner">
          <div class="db-spin"></div>
          <div style="font-size:14px;color:#6B7280">Setting up secure payment...</div>
        </div>
        <div id="db-modal-form" style="display:none">
          <div class="db-price-badge">
            <span style="font-size:24px">🏢</span>
            <div>
              <div class="db-price-amount">$2.00</div>
              <div class="db-price-label">Business Listing · 30 days · auto-renews</div>
            </div>
          </div>
          <div id="db-payment-element"></div>
          <div class="db-autopay-toggle">
            <input type="checkbox" id="db-autopay-check" checked>
            <label for="db-autopay-check">
              <strong>Enable auto-renewal</strong> — charge $2 every 30 days to keep ad live. You can turn this off anytime from your dashboard.
            </label>
          </div>
          <button id="db-pay-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Pay $2.00 &amp; Activate Listing
          </button>
          <div id="db-modal-error"></div>
          <div class="db-secure-note">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            256-bit SSL encryption · Powered by Stripe
          </div>
        </div>
        <div id="db-modal-success">
          <div class="db-success-icon">🎉</div>
          <h3>Payment Successful!</h3>
          <p>Your business listing is now live for 30 days.</p>
          <a href="${dashboard}?payment_success=1">Go to Dashboard →</a>
        </div>
      </div>
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);

  var backdrop  = document.getElementById('db-payment-modal-backdrop');
  var closeBtn  = document.getElementById('db-modal-close');
  var spinner   = document.getElementById('db-modal-spinner');
  var form      = document.getElementById('db-modal-form');
  var payBtn    = document.getElementById('db-pay-btn');
  var errorBox  = document.getElementById('db-modal-error');
  var successEl = document.getElementById('db-modal-success');

  var stripeInstance = null;
  var elements       = null;
  var currentListing = 0;
  var currentSubId   = '';

  /* ── Open modal ─────────────────────────────────────────── */
  window.dbOpenPaymentModal = function(listingId) {
    currentListing = listingId;
    currentSubId   = '';
    backdrop.classList.add('db-modal-open');
    document.body.style.overflow = 'hidden';
    spinner.style.display  = 'block';
    form.style.display     = 'none';
    successEl.style.display= 'none';
    errorBox.style.display = 'none';
    payBtn.disabled = false;

    // Clear previous Payment Element
    var pe = document.getElementById('db-payment-element');
    pe.innerHTML = '';

    var fd = new FormData();
    fd.append('action',     'dealboard_create_payment_intent');
    fd.append('nonce',      '${nonce}');
    fd.append('listing_id', listingId);

    fetch('${ajax_url}', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(!res.success){
          dbShowError(res.data && res.data.message ? res.data.message : 'Payment setup failed.');
          return;
        }
        var d = res.data;
        currentSubId = d.sub_id || '';

        if(!window.Stripe){
          dbShowError('Stripe.js failed to load. Please refresh and try again.');
          return;
        }
        stripeInstance = Stripe(d.publishable_key);
        elements = stripeInstance.elements({
          clientSecret: d.client_secret,
          appearance: {
            theme: 'stripe',
            variables: {
              colorPrimary:       '#C8102E',
              colorBackground:    '#ffffff',
              colorText:          '#111827',
              colorDanger:        '#DC2626',
              fontFamily:         'Inter, system-ui, sans-serif',
              spacingUnit:        '4px',
              borderRadius:       '8px',
              fontSizeBase:       '14px',
            }
          }
        });

        var paymentEl = elements.create('payment', {
          layout: { type:'tabs', defaultCollapsed:false },
        });
        paymentEl.mount('#db-payment-element');
        paymentEl.on('ready', function(){
          spinner.style.display = 'none';
          form.style.display    = 'block';
        });
      })
      .catch(function(err){
        dbShowError('Network error. Please check your connection and try again.');
        console.error(err);
      });
  };

  /* ── Pay button ─────────────────────────────────────────── */
  payBtn.addEventListener('click', function(){
    if(!stripeInstance || !elements) return;
    payBtn.disabled = true;
    payBtn.innerHTML = '<span style="border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;width:16px;height:16px;display:inline-block;animation:dbSpin .7s linear infinite"></span> Processing...';
    errorBox.style.display = 'none';

    stripeInstance.confirmPayment({
      elements: elements,
      confirmParams: {
        // Return URL required by Stripe but we handle result inline
        return_url: '${dashboard}?payment_success=1',
      },
      redirect: 'if_required'  // stay on page if no 3D Secure needed
    })
    .then(function(result){
      if(result.error){
        dbShowError(result.error.message || 'Payment failed. Please try again.');
        payBtn.disabled = false;
        payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Pay $2.00 &amp; Activate Listing';
        return;
      }
      var pi = result.paymentIntent;
      if(pi && (pi.status === 'succeeded' || pi.status === 'processing')){
        dbConfirmServer(pi.id);
      } else {
        dbShowError('Unexpected payment status: ' + (pi ? pi.status : 'unknown'));
        payBtn.disabled = false;
        payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Pay $2.00 &amp; Activate Listing';
      }
    });
  });

  /* ── Confirm with server ────────────────────────────────── */
  function dbConfirmServer(piId) {
    var fd = new FormData();
    fd.append('action',         'dealboard_confirm_payment');
    fd.append('nonce',          '${nonce}');
    fd.append('listing_id',     currentListing);
    fd.append('payment_intent', piId);
    fd.append('sub_id',         currentSubId);

    fetch('${ajax_url}', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        form.style.display = 'none';
        if(res.success){
          successEl.style.display = 'block';
          // Auto-redirect after 3 seconds
          setTimeout(function(){
            window.location.href = '${dashboard}?payment_success=1';
          }, 3000);
        } else {
          dbShowError((res.data && res.data.message) || 'Confirmation failed.');
          payBtn.disabled = false;
          payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Pay $2.00 &amp; Activate Listing';
          form.style.display = 'block';
        }
      })
      .catch(function(){
        dbShowError('Server confirmation failed. Check your dashboard — if payment went through, your listing will activate shortly.');
        form.style.display = 'block';
        payBtn.disabled = false;
        payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Pay $2.00 &amp; Activate Listing';
      });
  }

  /* ── Close modal ─────────────────────────────────────────── */
  function dbCloseModal() {
    backdrop.classList.remove('db-modal-open');
    document.body.style.overflow = '';
    if (document.getElementById('post-ad-form')) {
      window.location.href = '${dashboard}?dealboard_payment=cancel&listing=' + currentListing;
    }
  }
  closeBtn.addEventListener('click', dbCloseModal);
  backdrop.addEventListener('click', function(e){
    if(e.target === backdrop) dbCloseModal();
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') dbCloseModal();
  });

  /* ── Error helper ────────────────────────────────────────── */
  function dbShowError(msg){
    spinner.style.display = 'none';
    if(form.style.display === 'none' && successEl.style.display === 'none'){
      form.style.display = 'block';
    }
    errorBox.textContent = '❌ ' + msg;
    errorBox.style.display = 'block';
    errorBox.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }

  /* ── Auto-open if URL has open_payment_modal param ─────── */
  document.addEventListener('DOMContentLoaded', function(){
    var params = new URLSearchParams(window.location.search);
    var lid = params.get('open_payment_modal');
    if(lid) {
      window.dbOpenPaymentModal(parseInt(lid, 10));
      // Clean URL
      var url = new URL(window.location.href);
      url.searchParams.delete('open_payment_modal');
      window.history.replaceState({}, '', url);
    }
  });
})();
JS;
}

/* ===========================================================
   ADMIN SETTINGS PAGE  (Settings → Payments & Mail)
=========================================================== */
add_action( 'admin_menu', function() {
    add_options_page(
        'Payments & Mail',
        'Payments & Mail',
        'manage_options',
        'dealboard-payments',
        'dealboard_payments_settings_page'
    );
} );

add_action( 'admin_init', function() {
    register_setting( 'dealboard_payments_group', 'dealboard_stripe', [
        'sanitize_callback' => function( $in ) {
            return [
                'secret_key'      => sanitize_text_field( $in['secret_key'] ?? '' ),
                'publishable_key' => sanitize_text_field( $in['publishable_key'] ?? '' ),
                'webhook_secret'  => sanitize_text_field( $in['webhook_secret'] ?? '' ),
                'amount'          => max( 50, (int) ( $in['amount'] ?? 200 ) ),
                'currency'        => sanitize_text_field( strtolower( $in['currency'] ?? 'usd' ) ),
                'interval'        => 'month',
                'test_mode'       => empty( $in['test_mode'] ) ? 0 : 1,
            ];
        },
    ] );
    register_setting( 'dealboard_payments_group', 'dealboard_mail_from', [
        'sanitize_callback' => 'sanitize_email',
    ] );
} );

function dealboard_payments_settings_page() {
    $o    = dealboard_stripe_opts();
    $mail = get_option( 'dealboard_mail_from', DEALBOARD_MAIL_FROM );
    $hook = rest_url( 'dealboard/v1/stripe-webhook' );
    ?>
    <div class="wrap">
        <h1>Payments &amp; Mail</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'dealboard_payments_group' ); ?>

            <h2>Outgoing Email</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="db_mail_from">From address</label></th>
                    <td>
                        <input type="email" id="db_mail_from" name="dealboard_mail_from"
                               value="<?php echo esc_attr( $mail ); ?>" class="regular-text">
                        <p class="description">Replaces the default "WordPress" sender on password-reset and verification emails.</p>
                    </td>
                </tr>
            </table>

            <h2>Stripe (Business Ad Subscriptions — $2 / 30 days)</h2>
            <p class="description" style="margin-bottom:16px">
                Payments are collected <strong>on-page</strong> via a card popup modal (no redirect to Stripe).<br>
                Auto-renewal charges $2 every 30 days. Users can turn it off from their dashboard.
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Secret key</label></th>
                    <td><input type="text" name="dealboard_stripe[secret_key]" value="<?php echo esc_attr( $o['secret_key'] ); ?>" class="regular-text" placeholder="sk_live_… or sk_test_…"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Publishable key</label></th>
                    <td><input type="text" name="dealboard_stripe[publishable_key]" value="<?php echo esc_attr( $o['publishable_key'] ); ?>" class="regular-text" placeholder="pk_live_… or pk_test_…"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Webhook signing secret</label></th>
                    <td>
                        <input type="text" name="dealboard_stripe[webhook_secret]" value="<?php echo esc_attr( $o['webhook_secret'] ); ?>" class="regular-text" placeholder="whsec_…">
                        <p class="description">Add a webhook in Stripe pointing to:<br><code><?php echo esc_html( $hook ); ?></code><br>
                        Events: <code>invoice.paid</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Price (cents)</label></th>
                    <td><input type="number" name="dealboard_stripe[amount]" value="<?php echo esc_attr( $o['amount'] ); ?>" min="50" step="1" class="small-text"> <span class="description">200 = $2.00 per 30 days</span></td>
                </tr>
                <tr>
                    <th scope="row"><label>Currency</label></th>
                    <td><input type="text" name="dealboard_stripe[currency]" value="<?php echo esc_attr( $o['currency'] ); ?>" class="small-text" maxlength="3"></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function dealboard_stripe_business_price_label() {
    $o   = dealboard_stripe_opts();
    $amt = number_format( $o['amount'] / 100, 2 );
    return strtoupper( $o['currency'] ) === 'USD' ? ( '$' . $amt ) : ( $amt . ' ' . strtoupper( $o['currency'] ) );
}
