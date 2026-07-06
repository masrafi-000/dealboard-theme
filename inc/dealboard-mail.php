<?php
/**
 * DealBoard / American Alley — Outgoing Mail Identity
 *
 * Fixes the default WordPress sender shown in the screenshot
 * ("WordPress <wordpress@american-alley.com>") so all theme emails
 * (password reset, signup OTP, etc.) come from the support address.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The address all theme emails are sent from.
 * Override in wp-config.php with: define('DEALBOARD_MAIL_FROM', 'you@example.com');
 * or via the Payments & Mail settings page (option: dealboard_mail_from).
 */
if ( ! defined( 'DEALBOARD_MAIL_FROM' ) ) {
    define( 'DEALBOARD_MAIL_FROM', 'american.alley.support@gmail.com' );
}

function dealboard_mail_from_address() {
    $opt = get_option( 'dealboard_mail_from' );
    $addr = ( $opt && is_email( $opt ) ) ? $opt : DEALBOARD_MAIL_FROM;
    return sanitize_email( $addr );
}

function dealboard_mail_from_name() {
    // Use the site name (e.g. "American Alley") instead of "WordPress".
    $name = get_bloginfo( 'name' );
    return $name ? $name : 'American Alley';
}

/* Override the From: address on every outgoing email. */
add_filter( 'wp_mail_from', function( $email ) {
    return dealboard_mail_from_address();
}, 99 );

/* Override the From: display name on every outgoing email. */
add_filter( 'wp_mail_from_name', function( $name ) {
    return dealboard_mail_from_name();
}, 99 );

/*
 * Some hosts strip a From: that does not match the sending domain (SPF).
 * Adding a matching Reply-To keeps replies routed to the support inbox and
 * improves the chance the mail is accepted. Applied only when no explicit
 * Reply-To header was already set by the caller.
 */
add_filter( 'wp_mail', function( $args ) {
    $reply = dealboard_mail_from_address();

    $headers = $args['headers'] ?? '';
    $has_reply_to = false;

    if ( is_array( $headers ) ) {
        foreach ( $headers as $h ) {
            if ( stripos( (string) $h, 'reply-to:' ) !== false ) { $has_reply_to = true; break; }
        }
    } elseif ( is_string( $headers ) && stripos( $headers, 'reply-to:' ) !== false ) {
        $has_reply_to = true;
    }

    if ( ! $has_reply_to ) {
        $line = 'Reply-To: ' . dealboard_mail_from_name() . ' <' . $reply . '>';
        if ( is_array( $headers ) ) {
            $headers[] = $line;
        } else {
            $headers = trim( (string) $headers );
            $headers = $headers === '' ? $line : $headers . "\r\n" . $line;
        }
        $args['headers'] = $headers;
    }

    return $args;
}, 99 );
