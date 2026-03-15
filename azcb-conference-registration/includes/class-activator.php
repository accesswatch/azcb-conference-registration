<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_Activator {

    /**
     * Run on plugin activation: create DB tables and WP pages.
     */
    public static function activate() {
        self::create_tables();
        self::create_pages();
        flush_rewrite_rules();
    }

    /* ─── Database tables ─────────────────────────────────────── */

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $registrations = $wpdb->prefix . 'azcb_conf_registrations';
        $tokens        = $wpdb->prefix . 'azcb_conf_tokens';

        $sql = "CREATE TABLE {$registrations} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(200) NOT NULL,
            mobile_phone varchar(30) DEFAULT '',
            zip_code varchar(20) NOT NULL DEFAULT '',
            is_member tinyint(1) NOT NULL DEFAULT 0,
            is_lifetime tinyint(1) NOT NULL DEFAULT 0,
            registered_at datetime NOT NULL,
            confirmation_sent tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) {$charset};

        CREATE TABLE {$tokens} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            email varchar(200) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            is_member tinyint(1) NOT NULL DEFAULT 0,
            is_lifetime tinyint(1) NOT NULL DEFAULT 0,
            member_data longtext,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY email (email)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'azcb_conf_db_version', AZCB_CONF_DB_VERSION );
    }

    /* ─── WordPress pages ─────────────────────────────────────── */

    private static function create_pages() {
        /*
         * Pages are created in dependency order so that child pages
         * can reference the parent ID stored in the option from the
         * previous iteration.
         *
         * The /conference/ page is assumed to already exist.
         */
        $pages = array(
            array(
                'key'         => 'azcb_conf_page_verify',
                'title'       => 'Email Verification',
                'slug'        => 'verify',
                'parent_path' => 'conference',
                'content'     => '[azcb_conference_verify]',
            ),
            array(
                'key'         => 'azcb_conf_page_register',
                'title'       => 'Register',
                'slug'        => 'register',
                'parent_path' => 'conference',
                'content'     => '[azcb_conference_register]',
            ),
            array(
                'key'        => 'azcb_conf_page_sent',
                'title'      => 'Check Your Email',
                'slug'       => 'sent',
                'parent_key' => 'azcb_conf_page_verify',
                'content'    => '[azcb_conference_sent]',
            ),
            array(
                'key'        => 'azcb_conf_page_confirmation',
                'title'      => 'Confirmation',
                'slug'       => 'confirmation',
                'parent_key' => 'azcb_conf_page_register',
                'content'    => '[azcb_conference_confirmation]',
            ),
        );

        foreach ( $pages as $page ) {
            $existing_id = get_option( $page['key'] );
            if ( $existing_id && get_post_status( $existing_id ) ) {
                continue; // already created
            }

            // Determine parent.
            $parent_id = 0;
            if ( ! empty( $page['parent_path'] ) ) {
                $parent = get_page_by_path( $page['parent_path'] );
                if ( $parent ) {
                    $parent_id = $parent->ID;
                }
            } elseif ( ! empty( $page['parent_key'] ) ) {
                $parent_id = (int) get_option( $page['parent_key'], 0 );
            }

            // Check whether the page already exists at the expected path.
            $full_path = $parent_id
                ? trailingslashit( get_page_uri( $parent_id ) ) . $page['slug']
                : $page['slug'];

            $found = get_page_by_path( $full_path );
            if ( $found ) {
                update_option( $page['key'], $found->ID );
                continue;
            }

            $new_id = wp_insert_post( array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
            ) );

            if ( $new_id && ! is_wp_error( $new_id ) ) {
                update_option( $page['key'], $new_id );
            }
        }
    }
}
