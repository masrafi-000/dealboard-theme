<?php
/**
 * DealBoard / American Alley — Stripe Subscriptions for Business Ads
 *
 * Business listings are billed as a $2 / 30-day Stripe subscription:
 *   - Payment is collected via Stripe-hosted Checkout (redirect to Stripe).
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

/**
 * Returns a short fingerprint of the current secret key.
 * Stored alongside every cus_xxx / product_id so we can detect
 * key changes without making a live Stripe API call.
 */
function dealboard_stripe_key_fingerprint() {
    return substr( md5( dealboard_stripe_secret() ), 0, 12 );
}

/**
 * Called whenever the Stripe settings are saved.
 * Clears all cached customer IDs and product IDs that belong
 * to the old key, so users get fresh records on next payment.
 */
function dealboard_stripe_flush_on_key_change() {
    $new_opts = get_option( 'dealboard_stripe', [] );
    $old_fp   = get_option( 'dealboard_stripe_key_fp', '' );
    $new_fp   = substr( md5( trim( $new_opts['secret_key'] ?? '' ) ), 0, 12 );

    if ( $old_fp && $old_fp === $new_fp ) return; // key unchanged

    // Save new fingerprint
    update_option( 'dealboard_stripe_key_fp', $new_fp );

    // Clear cached Stripe product ID (tied to old key)
    $old_prod_key = 'dealboard_stripe_prod_' . md5( '' ); // fallback
    // Remove any product option whose key starts with our prefix
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dealboard_stripe_prod_%'" );

    // Clear Stripe customer meta from all users
    $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'dealboard_stripe_customer' ] );
    $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'dealboard_stripe_key_fp' ] );

    // Clear Stripe customer meta from all listings
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'listing_stripe_customer' ] );
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'listing_stripe_key_fp' ] );

    // Also clear any stale subscription IDs (they won't exist in the new account)
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'listing_stripe_subscription' ] );
}
add_action( 'update_option_dealboard_stripe', 'dealboard_stripe_flush_on_key_change' );
add_action( 'add_option_dealboard_stripe',    'dealboard_stripe_flush_on_key_change' );

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
   DIRECT PAY URL (?dealboard_pay=1&listing=ID)
   Creates a Stripe Checkout Session and redirects the user
   to Stripe's hosted payment page.
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

    $o    = dealboard_stripe_opts();
    $user = wp_get_current_user();

    // ── 1. Get or create Stripe Customer ──────────────────────────────────
    // Dynamic key detection: we store a fingerprint (hash) of the secret key
    // alongside each customer ID. If keys change, fingerprint mismatches instantly
    // — no live API call needed — and a fresh customer is created automatically.
    $current_fp  = dealboard_stripe_key_fingerprint();
    $stored_fp   = get_user_meta( $user->ID, 'dealboard_stripe_key_fp', true );
    $customer_id = ( $stored_fp === $current_fp )
        ? ( get_post_meta( $listing_id, 'listing_stripe_customer', true )
            ?: get_user_meta( $user->ID, 'dealboard_stripe_customer', true ) )
        : '';

    if ( ! $customer_id ) {
        $cust = dealboard_stripe_api( 'POST', 'customers', [
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'metadata' => [ 'wp_user_id' => $user->ID ],
        ] );
        if ( is_wp_error( $cust ) ) {
            wp_safe_redirect( home_url( '/dashboard/?payment_error=' . rawurlencode( $cust->get_error_message() ) ) );
            exit;
        }
        $customer_id = $cust['id'];
        update_user_meta( $user->ID, 'dealboard_stripe_customer', $customer_id );
        update_user_meta( $user->ID, 'dealboard_stripe_key_fp', $current_fp );  // save fingerprint
    }
    update_post_meta( $listing_id, 'listing_stripe_customer', $customer_id );

    // ── 2. Get or create Product ID ───────────────────────────────────────
    $product_id = dealboard_stripe_get_or_create_product();
    if ( is_wp_error( $product_id ) ) {
        wp_safe_redirect( home_url( '/dashboard/?payment_error=' . rawurlencode( 'Product setup failed: ' . $product_id->get_error_message() ) ) );
        exit;
    }

    // ── 3. Build return & cancel URLs ─────────────────────────────────────
    $return_nonce  = wp_create_nonce( 'db_stripe_return_' . $listing_id );
    $success_url   = add_query_arg( [
        'dealboard_stripe_return' => '1',
        'listing'                 => $listing_id,
        '_wpnonce'                => $return_nonce,
    ], home_url( '/dashboard/' ) );
    $cancel_url    = add_query_arg( [
        'dealboard_payment' => 'cancel',
        'listing'           => $listing_id,
    ], home_url( '/dashboard/' ) );

    // ── 4. Create Stripe Checkout Session ─────────────────────────────────
    // TEST MODE: use 1-day interval so renewal fires quickly for testing.
    // LIVE MODE: use the configured interval (default: month).
    // Note: Stripe minimum interval is 1 day — minute/hour not supported.
    $test_mode        = ! empty( $o['test_mode'] );
    $billing_interval = $test_mode ? 'day'  : $o['interval'];
    $billing_count    = $test_mode ? 1      : 1;

    $session = dealboard_stripe_api( 'POST', 'checkout/sessions', [
        'customer'            => $customer_id,
        'mode'                => 'subscription',
        'line_items'          => [ [
            'price_data' => [
                'currency'    => $o['currency'],
                'unit_amount' => (int) $o['amount'],
                'recurring'   => [ 'interval' => $billing_interval, 'interval_count' => $billing_count ],
                'product'     => $product_id,
            ],
            'quantity'   => 1,
        ] ],
        'payment_method_collection' => 'always',
        'subscription_data'   => [
            'metadata' => [
                'listing_id' => $listing_id,
                'wp_user_id' => $user->ID,
            ],
        ],
        'metadata'            => [
            'listing_id' => $listing_id,
            'wp_user_id' => $user->ID,
        ],
        'success_url'         => $success_url,
        'cancel_url'          => $cancel_url,
    ] );

    if ( is_wp_error( $session ) ) {
        wp_safe_redirect( home_url( '/dashboard/?payment_error=' . rawurlencode( $session->get_error_message() ) ) );
        exit;
    }

    // Mark listing as pending_payment while user is on Stripe's page
    update_post_meta( $listing_id, 'listing_status', 'pending_payment' );

    // Redirect user to Stripe-hosted Checkout page
    wp_redirect( $session['url'] );
    exit;
}, 5 );

/* ===========================================================
   STRIPE CHECKOUT RETURN  (?dealboard_stripe_return=1&listing=ID)
   Stripe redirects the user back here after payment.
   We verify the nonce, then check subscription status to activate.
=========================================================== */
add_action( 'template_redirect', function() {
    if ( empty( $_GET['dealboard_stripe_return'] ) ) return;

    $listing_id = (int) ( $_GET['listing'] ?? 0 );

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/sign-in' ) ); exit;
    }
    if ( ! $listing_id ) {
        wp_safe_redirect( home_url( '/dashboard/' ) ); exit;
    }

    // Verify the return nonce
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'db_stripe_return_' . $listing_id ) ) {
        wp_die( 'Security check failed.', 'Error', [ 'response' => 403, 'back_link' => true ] );
    }

    $post = get_post( $listing_id );
    if ( ! $post || $post->post_author != get_current_user_id() ) {
        wp_die( 'Listing not found.' );
    }

    // Fetch the subscription stored on the listing (webhook may have already set it),
    // or look it up via the Stripe customer.
    $sub_id = get_post_meta( $listing_id, 'listing_stripe_subscription', true );

    if ( ! $sub_id ) {
        // Try to find the most recent subscription for this customer
        $customer_id = get_post_meta( $listing_id, 'listing_stripe_customer', true );
        if ( $customer_id ) {
            $subs = dealboard_stripe_api( 'GET', 'subscriptions?customer=' . rawurlencode( $customer_id ) . '&limit=5&status=all', [] );
            if ( ! is_wp_error( $subs ) && ! empty( $subs['data'] ) ) {
                foreach ( $subs['data'] as $s ) {
                    $meta = $s['metadata'] ?? [];
                    if ( isset( $meta['listing_id'] ) && (int) $meta['listing_id'] === $listing_id ) {
                        $sub_id = $s['id'];
                        update_post_meta( $listing_id, 'listing_stripe_subscription', $sub_id );
                        break;
                    }
                }
            }
        }
    }

    if ( $sub_id ) {
        $sub        = dealboard_stripe_api( 'GET', 'subscriptions/' . rawurlencode( $sub_id ), [] );
        $sub_status = ! is_wp_error( $sub ) ? ( $sub['status'] ?? '' ) : '';
        $period_end = ( ! is_wp_error( $sub ) && ! empty( $sub['current_period_end'] ) )
            ? (int) $sub['current_period_end'] : 0;
        $autopay    = ( ! is_wp_error( $sub ) && empty( $sub['cancel_at_period_end'] ) ) ? '1' : '0';

        if ( in_array( $sub_status, [ 'active', 'trialing' ], true ) ) {
            update_post_meta( $listing_id, 'listing_autopay', $autopay );
            dealboard_activate_business_listing( $listing_id, $period_end );
            wp_safe_redirect( home_url( '/dashboard/?payment_success=1' ) );
            exit;
        }

        if ( in_array( $sub_status, [ 'incomplete', 'past_due' ], true ) ) {
            // Payment may still be processing — optimistically activate; webhook will finalize
            update_post_meta( $listing_id, 'listing_autopay', '1' );
            dealboard_activate_business_listing( $listing_id, $period_end );
            wp_safe_redirect( home_url( '/dashboard/?payment_success=1' ) );
            exit;
        }
    }

    // If the webhook hasn't fired yet but the user is back, redirect with success —
    // webhook will activate the listing when Stripe confirms.
    wp_safe_redirect( home_url( '/dashboard/?payment_pending=1' ) );
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

        case 'checkout.session.completed':
            // Stripe Checkout completed — link subscription to listing if not yet done
            $sub_id     = $obj['subscription'] ?? '';
            $meta       = $obj['metadata'] ?? [];
            $listing_id = (int) ( $meta['listing_id'] ?? 0 );
            if ( $listing_id && $sub_id ) {
                update_post_meta( $listing_id, 'listing_stripe_subscription', $sub_id );
                // Activation will happen via invoice.paid which fires right after
            }
            break;

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
                Payments are collected via <strong>Stripe-hosted Checkout</strong> (user is redirected to Stripe's secure payment page).<br>
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
                        Events: <code>checkout.session.completed</code>, <code>invoice.paid</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>.</p>
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
