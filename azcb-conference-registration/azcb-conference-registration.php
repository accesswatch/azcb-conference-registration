<?php
/*
Plugin Name: AZCB Conference Registration
Plugin URI:  https://github.com/accesswatch/azcb-conference-registration
Description: Conference registration system for the Arizona Council of the Blind 2026 Annual Conference and Business Meeting. Unified flow with magic-link email verification and automatic membership detection.
Version:     1.0.0
Author:      AccessWatch
License:     GPLv2 or later
Text Domain: azcb-conference
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AZCB_CONF_VERSION', '1.0.0' );
define( 'AZCB_CONF_FILE', __FILE__ );
define( 'AZCB_CONF_DIR', plugin_dir_path( __FILE__ ) );
define( 'AZCB_CONF_URL', plugin_dir_url( __FILE__ ) );
define( 'AZCB_CONF_DB_VERSION', '1.0' );

/* ─── Load classes ────────────────────────────────────────────────── */
require_once AZCB_CONF_DIR . 'includes/class-activator.php';
require_once AZCB_CONF_DIR . 'includes/class-csv-lookup.php';
require_once AZCB_CONF_DIR . 'includes/class-magic-link.php';
require_once AZCB_CONF_DIR . 'includes/class-email.php';
require_once AZCB_CONF_DIR . 'includes/class-registration.php';
require_once AZCB_CONF_DIR . 'includes/class-admin.php';

/* ─── Activation ──────────────────────────────────────────────────── */
register_activation_hook( __FILE__, array( 'AZCB_Conf_Activator', 'activate' ) );

/* ─── Initialise ──────────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'azcb_conf_init' );
function azcb_conf_init() {
    new AZCB_Conf_Registration();
    if ( is_admin() ) {
        new AZCB_Conf_Admin();
    }
}

/* ─── Frontend styles ─────────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'azcb_conf_enqueue_assets' );
function azcb_conf_enqueue_assets() {
    wp_enqueue_style(
        'azcb-conference',
        AZCB_CONF_URL . 'assets/style.css',
        array(),
        AZCB_CONF_VERSION
    );
}

/* ─── /convention/ → /conference/ redirect ────────────────────────── */
add_action( 'template_redirect', 'azcb_conf_convention_redirect' );
function azcb_conf_convention_redirect() {
    if ( ! azcb_conf_get_setting( 'enable_convention_redirect' ) ) {
        return;
    }
    $path = trim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( 'convention' === $path ) {
        wp_redirect( home_url( '/conference/' ), 302 );
        exit;
    }
}

/* ═══════════════════════════════════════════════════════════════════
   Settings helpers — single source of truth for defaults
   ═══════════════════════════════════════════════════════════════════ */

function azcb_conf_defaults() {
    return array(
        /* ── General ────────────────────────────────────────────── */
        'csv_url'                    => 'https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv',
        'csv_cache_minutes'          => 15,
        'magic_link_expiry_minutes'  => 30,
        'rate_limit_per_hour'        => 5,
        'contact_url'                => 'https://azcb.org/contact-us/',
        'membership_url'             => 'https://azcb.org/membership/',
        'enable_convention_redirect' => 1,

        /* ── Verify page ────────────────────────────────────────── */
        'verify_heading'     => 'Conference Registration — Email Verification',
        'verify_intro'       => 'Welcome! To register for the 2026 AZCB Conference and Annual Business Meeting, please provide the following information. We will send you an email with a link to complete your registration.',
        'verify_button_text' => 'Continue',
        'verify_footer'      => 'Have Questions? If you have questions about the conference, or if you wish to inquire about sponsoring the conference or making a donation, please <a href="{contact_url}">Send us a Message</a>.',

        /* ── Sent page ──────────────────────────────────────────── */
        'sent_heading' => 'Check Your Email',
        'sent_message' => 'Thanks! We\'ve sent a registration link to your email address. Please check your inbox and click the link to continue with conference registration. Note: This link expires in <strong>{expiry_minutes} minutes</strong>.',

        /* ── Register page ──────────────────────────────────────── */
        'register_heading'     => '2026 Arizona Council of the Blind Annual Conference and Business Meeting — Registration Page',
        'register_intro'       => 'We\'re excited to welcome you to the Arizona Council of the Blind\'s 2026 Annual Conference and Business Meeting. There is no cost to attend this virtual event, but you do need to register by providing the following information.',
        'register_button_text' => 'Complete your Registration',

        /* ── Member confirmation ────────────────────────────────── */
        'member_confirm_heading' => 'Confirmation',
        'member_confirm_message' => 'Thank you for registering for the 2026 AZCB Conference and Annual Business Meeting! As a member of AZCB, you will receive links for all conference related meetings, including the AZCB Annual Business Meeting. If you do not receive these links by Thursday, April 10, and/or if you have questions about the conference, please <a href="{contact_url}">Contact us Here</a>.',

        /* ── Non-member confirmation ────────────────────────────── */
        'nonmember_confirm_heading' => 'Confirmation',
        'nonmember_confirm_message' => 'Thank you for registering for the 2026 AZCB Conference! You will receive links for conference-related meetings.'
            . "\n\n" . 'Our records did not show a current AZCB membership associated with your information. If you believe you are a member in good standing (meaning that you have registered and paid dues for 2026), please <a href="{contact_url}">Contact us Here</a> and we will be happy to verify your status and ensure you receive access to the Annual Business Meeting.'
            . "\n\n" . 'If you would like to become a member of the AZCB, please visit the <a href="{membership_url}">Membership Page</a>, fill in the required information, and provide the required dues, and we will happily add you to our growing organization. If you do so before the start of the convention, you will then be able to join us for our 2026 Annual Business Meeting.'
            . "\n\n" . 'If you have other questions about the conference, please <a href="{contact_url}">Contact us Here</a>.',

        /* ── Magic link email ───────────────────────────────────── */
        'magic_link_email_subject' => 'AZCB Conference Registration — Verify Your Email',
        'magic_link_email_body'    => 'Hi {first_name},'
            . "\n\n" . 'Thank you for starting your registration for the 2026 AZCB Conference and Annual Business Meeting.'
            . "\n\n" . 'Please click the link below to continue with your registration:'
            . "\n\n" . '<a href="{link_url}">{link_url}</a>'
            . "\n\n" . 'This link will expire in {expiry_minutes} minutes.'
            . "\n\n" . 'If you did not request this link, you can safely ignore this email.'
            . "\n\n" . 'Arizona Council of the Blind',

        /* ── Member confirmation email ──────────────────────────── */
        'member_email_subject' => 'AZCB Conference Registration — Confirmation',
        'member_email_body'    => 'Hi {first_name},'
            . "\n\n" . 'Thank you for registering for the 2026 AZCB Conference and Annual Business Meeting! As a member of AZCB, you will receive links for all conference related meetings, including the AZCB Annual Business Meeting.'
            . "\n\n" . 'If you do not receive these links by Thursday, April 10, and/or if you have questions about the conference, please visit <a href="{contact_url}">{contact_url}</a>.'
            . "\n\n" . 'Arizona Council of the Blind',

        /* ── Non-member confirmation email ──────────────────────── */
        'nonmember_email_subject' => 'AZCB Conference Registration — Confirmation',
        'nonmember_email_body'    => 'Hi {first_name},'
            . "\n\n" . 'Thank you for registering for the 2026 AZCB Conference! You will receive links for conference-related meetings.'
            . "\n\n" . 'Our records did not show a current AZCB membership associated with your information. If you believe you are a member in good standing, please visit <a href="{contact_url}">{contact_url}</a> and we will be happy to verify your status.'
            . "\n\n" . 'If you would like to become a member, please visit <a href="{membership_url}">{membership_url}</a> for more information.'
            . "\n\n" . 'Arizona Council of the Blind',
    );
}

/**
 * Retrieve a single plugin setting, falling back to the default.
 */
function azcb_conf_get_setting( $key ) {
    static $settings = null;
    if ( null === $settings ) {
        $settings = get_option( 'azcb_conf_settings', array() );
    }
    $defaults = azcb_conf_defaults();
    if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
        return $settings[ $key ];
    }
    return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * Replace {placeholder} tokens in a string with live values.
 *
 * Extra key/value pairs can be passed for context-specific tokens
 * like {first_name} or {link_url}.
 */
function azcb_conf_replace_placeholders( $text, $extra = array() ) {
    $replacements = array_merge(
        array(
            '{contact_url}'    => esc_url( azcb_conf_get_setting( 'contact_url' ) ),
            '{membership_url}' => esc_url( azcb_conf_get_setting( 'membership_url' ) ),
            '{expiry_minutes}' => intval( azcb_conf_get_setting( 'magic_link_expiry_minutes' ) ),
            '{site_name}'      => get_bloginfo( 'name' ),
        ),
        $extra
    );
    return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
}
