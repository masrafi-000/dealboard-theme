<?php
/**
 * DealBoard / American Alley — Signup Email OTP Verification
 *
 * On registration the account is NOT created immediately. Instead the
 * pending details are held in a short-lived transient and a 6-digit code
 * is emailed to the user. The account is created only after the code is
 * confirmed. The two-step UI lives in page-sign-up.php; this file holds
 * the reusable logic.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DEALBOARD_OTP_TTL', 15 * MINUTE_IN_SECONDS ); // code lifetime
define( 'DEALBOARD_OTP_MAX_TRIES', 5 );                // wrong-code attempts allowed

/** Cryptographically-random 6-digit code (zero-padded). */
function dealboard_generate_otp() {
    return str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
}

/**
 * Persist a pending signup and return its lookup token.
 *
 * @param array $data username, email, password
 * @return string token
 */
function dealboard_store_pending_signup( $data ) {
    $token = wp_generate_password( 32, false, false );
    $payload = [
        'username' => $data['username'],
        'email'    => $data['email'],
        'password' => $data['password'],
        'otp'      => $data['otp'],
        'tries'    => 0,
        'created'  => time(),
    ];
    set_transient( 'db_signup_' . $token, $payload, DEALBOARD_OTP_TTL );
    return $token;
}

function dealboard_get_pending_signup( $token ) {
    if ( empty( $token ) ) return false;
    return get_transient( 'db_signup_' . sanitize_text_field( $token ) );
}

function dealboard_update_pending_signup( $token, $payload ) {
    set_transient( 'db_signup_' . sanitize_text_field( $token ), $payload, DEALBOARD_OTP_TTL );
}

function dealboard_delete_pending_signup( $token ) {
    delete_transient( 'db_signup_' . sanitize_text_field( $token ) );
}

/**
 * Email the verification code. Uses the support sender configured in
 * inc/dealboard-mail.php.
 */
function dealboard_send_otp_email( $email, $name, $otp ) {
    $site = get_bloginfo( 'name' );
    $subject = sprintf( 'Your %s verification code: %s', $site, $otp );

    $name = $name ? $name : 'there';
    $message  = "Hi {$name},\r\n\r\n";
    $message .= "Welcome to {$site}! Use the verification code below to finish creating your account:\r\n\r\n";
    $message .= "    {$otp}\r\n\r\n";
    $message .= "This code expires in 15 minutes. If you didn't request this, you can safely ignore this email.\r\n\r\n";
    $message .= "— The {$site} Team\r\n";

    return wp_mail( $email, $subject, $message );
}
