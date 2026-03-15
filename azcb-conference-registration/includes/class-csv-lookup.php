<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_CSV_Lookup {

    /**
     * Look up a person in the membership CSV.
     *
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @return array|false  Associative array of CSV columns on match, false otherwise.
     */
    public static function find( $first_name, $last_name, $email ) {
        $rows = self::get_csv_rows();
        if ( ! $rows ) {
            return false;
        }

        $first = strtolower( trim( $first_name ) );
        $last  = strtolower( trim( $last_name ) );
        $email = strtolower( trim( $email ) );

        foreach ( $rows as $row ) {
            $m_first = strtolower( trim( isset( $row['First Name'] ) ? $row['First Name'] : '' ) );
            $m_last  = strtolower( trim( isset( $row['Last Name'] ) ? $row['Last Name'] : '' ) );
            $m_email = strtolower( trim( isset( $row['Email Address'] ) ? $row['Email Address'] : '' ) );

            if ( $m_first === $first && $m_last === $last && $m_email === $email ) {
                return $row;
            }
        }

        return false;
    }

    /**
     * Determine whether a matched row represents a lifetime member.
     *
     * @param array $row  Associative CSV row.
     * @return bool
     */
    public static function is_lifetime( $row ) {
        $val = '';
        if ( ! empty( $row['Membership category'] ) ) {
            $val = strtolower( $row['Membership category'] );
        } elseif ( ! empty( $row['ACB Life'] ) ) {
            $val = strtolower( $row['ACB Life'] );
        }

        return (
            false !== strpos( $val, 'life' ) ||
            in_array( $val, array( 'yes', 'y', '1' ), true )
        );
    }

    /* ─── Internal helpers ────────────────────────────────────── */

    /**
     * Fetch and parse the CSV, using a transient cache.
     *
     * @return array|false  Array of associative rows, or false on error.
     */
    private static function get_csv_rows() {
        $cache_key     = 'azcb_conf_csv_rows';
        $cache_minutes = max( 1, intval( azcb_conf_get_setting( 'csv_cache_minutes' ) ) );
        $cached        = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $csv_url = azcb_conf_get_setting( 'csv_url' );
        if ( ! $csv_url ) {
            return false;
        }

        $response = wp_remote_get( $csv_url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            error_log( 'AZCB Conference: CSV fetch error — ' . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            error_log( 'AZCB Conference: CSV returned HTTP ' . $status );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            return false;
        }

        $rows = self::parse_csv( $body );
        if ( $rows ) {
            set_transient( $cache_key, $rows, $cache_minutes * MINUTE_IN_SECONDS );
        }

        return $rows;
    }

    /**
     * Parse raw CSV text into an array of associative rows,
     * using the first row as headers.
     */
    private static function parse_csv( $body ) {
        $lines = preg_split( '/\r\n|\r|\n/', trim( $body ) );
        if ( count( $lines ) < 2 ) {
            return false;
        }

        $raw_header = str_getcsv( array_shift( $lines ) );
        $headers    = array();

        foreach ( $raw_header as $name_raw ) {
            $name = preg_replace( '/^\xEF\xBB\xBF/', '', $name_raw ); // strip BOM
            $name = trim( $name, " \t\n\r\0\x0B\"'" );
            $headers[] = $name;
        }

        $rows = array();
        foreach ( $lines as $line ) {
            if ( '' === trim( $line ) ) {
                continue;
            }
            $cols = str_getcsv( $line );
            $row  = array();
            foreach ( $headers as $i => $h ) {
                $row[ $h ] = isset( $cols[ $i ] ) ? trim( $cols[ $i ] ) : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
