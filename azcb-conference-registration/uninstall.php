<?php
/**
 * AZCB Conference Registration — Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Drops custom tables, removes options, and trashes created pages.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azcb_conf_registrations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azcb_conf_tokens" );

// Delete options.
$option_keys = array(
    'azcb_conf_settings',
    'azcb_conf_db_version',
    'azcb_conf_page_verify',
    'azcb_conf_page_register',
    'azcb_conf_page_sent',
    'azcb_conf_page_confirmation',
);

foreach ( $option_keys as $key ) {
    delete_option( $key );
}

// Trash created pages (don't force-delete — admin may want to recover).
$page_keys = array(
    'azcb_conf_page_confirmation',
    'azcb_conf_page_sent',
    'azcb_conf_page_register',
    'azcb_conf_page_verify',
);

foreach ( $page_keys as $key ) {
    // Options were just deleted, so look up pages by slug instead.
    $slugs = array(
        'azcb_conf_page_verify'       => 'conference/verify',
        'azcb_conf_page_register'     => 'conference/register',
        'azcb_conf_page_sent'         => 'conference/verify/sent',
        'azcb_conf_page_confirmation' => 'conference/register/confirmation',
    );

    if ( isset( $slugs[ $key ] ) ) {
        $page = get_page_by_path( $slugs[ $key ] );
        if ( $page ) {
            wp_trash_post( $page->ID );
        }
    }
}

// Clean up any leftover transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%azcb_conf_csv_rows%' OR option_name LIKE '%azcb_conf_ct_%' OR option_name LIKE '%azcb_rl_%'"
);
