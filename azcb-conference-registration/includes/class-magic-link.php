<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_Magic_Link {

    /**
     * Create a new magic-link token and store it in the DB.
     *
     * @param array $data {
     *     @type string $email
     *     @type string $first_name
     *     @type string $last_name
     *     @type int    $is_member   1 or 0
     *     @type int    $is_lifetime 1 or 0
     *     @type string $member_data JSON string (empty for non-members)
     * }
     * @return string|WP_Error  The token string, or WP_Error on failure.
     */
    public static function create( $data ) {
        global $wpdb;

        // Rate-limit check.
        $limit = self::check_rate_limit( $data['email'] );
        if ( is_wp_error( $limit ) ) {
            return $limit;
        }

        $token      = bin2hex( random_bytes( 32 ) );
        $expiry_min = max( 1, intval( azcb_conf_get_setting( 'magic_link_expiry_minutes' ) ) );
        $now        = current_time( 'mysql', true ); // UTC
        $expires    = gmdate( 'Y-m-d H:i:s', time() + $expiry_min * 60 );

        $wpdb->insert(
            $wpdb->prefix . 'azcb_conf_tokens',
            array(
                'token'       => $token,
                'email'       => $data['email'],
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'is_member'   => $data['is_member'],
                'is_lifetime' => $data['is_lifetime'],
                'member_data' => $data['member_data'],
                'created_at'  => $now,
                'expires_at'  => $expires,
                'used'        => 0,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
        );

        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'db_error', 'Could not create verification token.' );
        }

        return $token;
    }

    /**
     * Validate and return token data. Does NOT consume the token.
     *
     * @param string $token
     * @return array|WP_Error  Row data on success.
     */
    public static function validate( $token ) {
        global $wpdb;

        if ( ! $token || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            return new WP_Error( 'invalid_token', 'Invalid verification link.' );
        }

        $table = $wpdb->prefix . 'azcb_conf_tokens';
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token = %s LIMIT 1",
                $token
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return new WP_Error( 'not_found', 'This verification link is not valid.' );
        }

        if ( $row['used'] ) {
            return new WP_Error( 'used', 'This verification link has already been used.' );
        }

        if ( strtotime( $row['expires_at'] ) < time() ) {
            return new WP_Error( 'expired', 'This verification link has expired. Please start the registration process again.' );
        }

        return $row;
    }

    /**
     * Mark a token as used (single-use).
     *
     * @param string $token
     */
    public static function consume( $token ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'azcb_conf_tokens',
            array( 'used' => 1 ),
            array( 'token' => $token ),
            array( '%d' ),
            array( '%s' )
        );
    }

    /* ─── Rate limiting ───────────────────────────────────────── */

    /**
     * Enforce per-email rate limit using transients.
     *
     * @param string $email
     * @return true|WP_Error
     */
    private static function check_rate_limit( $email ) {
        $limit         = max( 1, intval( azcb_conf_get_setting( 'rate_limit_per_hour' ) ) );
        $transient_key = 'azcb_rl_' . md5( strtolower( trim( $email ) ) );
        $count         = (int) get_transient( $transient_key );

        if ( $count >= $limit ) {
            return new WP_Error(
                'rate_limited',
                'Too many verification requests. Please wait a while and try again.'
            );
        }

        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }
}
