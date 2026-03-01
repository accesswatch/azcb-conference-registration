<?php
/**
 * AZCB Membership Lookup — Code Snippet (recovered from user)
 *
 * Hooked into Gravity Forms Form 13 ("Find Membership").
 * After the user submits (First Name, Last Name, Email, Zip),
 * this function fetches a CSV of members and tries to match.
 * On match it populates ~20 hidden fields + a JSON payload.
 *
 * DATA SOURCE: https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv
 *   — A static CSV file uploaded to the WordPress Media Library.
 *   — Manually uploaded/updated by an admin.
 *   — CSV columns map to the hidden fields via $field_map.
 */

add_filter( 'gform_entry_post_save_13', 'azcb_membership_lookup_and_fill', 10, 2 );
function azcb_membership_lookup_and_fill( $entry, $form ) {

    // Ensure this only runs for Form 13.
    if ( (int) rgar( $form, 'id' ) !== 13 ) {
        return $entry;
    }

    // CSV URL: prefer a defined constant, then an admin option, then the original default.
    $csv_url = defined( 'AZCB_MEMBERS_CSV_URL' )
        ? AZCB_MEMBERS_CSV_URL
        : get_option( 'azcb_members_csv_url', 'https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv' );
    $field_match_found = 6;
    $field_is_life     = 9;
    $field_payload     = 10; // payload_json

    // Map EXISTING hidden fields on Form 13 to CSV column headers.
    // Column names must match the azcb_members.csv header row.
    $field_map = array(
        11 => 'Last Name',
        12 => 'First Name',
        13 => 'Middle Name',
        14 => 'Title',
        15 => 'Suffix',
        16 => 'Salutation',
        17 => 'Address 1',
        18 => 'Address 2',
        19 => 'City',
        20 => 'State/Province',
        21 => 'Zip',
        22 => 'Country',
        23 => 'Email Address',
        24 => 'Home Phone',
        25 => 'Mobile Phone',
        26 => 'Gender',
        27 => 'Ethnicity',
        28 => 'Vision Status',
        29 => 'BF Format',
        30 => 'Preferred Mail Format',
    );

    // Default flags: assume no match.
    $entry[ $field_match_found ] = 'no';
    $entry[ $field_is_life ]     = 'no';

    // Clear mapped fields + payload to avoid stale data.
    foreach ( $field_map as $fid => $col ) {
        $entry[ $fid ] = '';
        GFAPI::update_entry_field( $entry['id'], $fid, '' );
    }
    GFAPI::update_entry_field( $entry['id'], $field_match_found, 'no' );
    GFAPI::update_entry_field( $entry['id'], $field_is_life, 'no' );
    GFAPI::update_entry_field( $entry['id'], $field_payload, '' );

    // Values used for matching.
    $first = strtolower( trim( rgar( $entry, '1' ) ) );   // First Name (user input)
    $last  = strtolower( trim( rgar( $entry, '3' ) ) );   // Last Name  (user input)
    $email = strtolower( trim( rgar( $entry, '31' ) ) );  // Email      (user input)
    $zip   = substr( preg_replace( '/\D/', '', rgar( $entry, '5' ) ), 0, 5 ); // Zip (5-digit)

    if ( ! $first || ! $last || ! $email || ! $zip ) {
        error_log( 'AZCB FindMembership: missing input values.' );
        return $entry;
    }

    // Fetch CSV.
    $response = wp_remote_get( $csv_url );
    if ( is_wp_error( $response ) ) {
        error_log( 'AZCB FindMembership: CSV fetch failed: ' . $response->get_error_message() );
        return $entry;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( ! $body ) {
        error_log( 'AZCB FindMembership: empty CSV body.' );
        return $entry;
    }

    $lines = preg_split( '/\r\n|\r|\n/', trim( $body ) );
    if ( count( $lines ) < 2 ) {
        error_log( 'AZCB FindMembership: invalid CSV structure.' );
        return $entry;
    }

    // Parse header row.
    $raw_header = str_getcsv( array_shift( $lines ) );
    $header_map = array();

    foreach ( $raw_header as $i => $name_raw ) {
        $name = preg_replace( '/^\xEF\xBB\xBF/', '', $name_raw ); // strip BOM if present
        $name = trim( $name, " \t\n\r\0\x0B\"'" );                // trim spaces/quotes
        if ( $name !== '' ) {
            $header_map[ $name ] = $i;
        }
    }

    // Ensure required columns for match exist.
    $required_cols = array( 'First Name', 'Last Name', 'Email Address', 'Zip' );
    foreach ( $required_cols as $col ) {
        if ( ! isset( $header_map[ $col ] ) ) {
            error_log( 'AZCB FindMembership: missing required column ' . $col );
            return $entry;
        }
    }

    // Helper to read a column from a row.
    $get_col = function( $row, $col_name ) use ( $header_map ) {
        if ( ! isset( $header_map[ $col_name ] ) ) {
            return '';
        }
        $idx = $header_map[ $col_name ];
        return isset( $row[ $idx ] ) ? trim( $row[ $idx ] ) : '';
    };

    // Find matching row.
    $match_row = null;
    foreach ( $lines as $line ) {
        if ( trim( $line ) === '' ) {
            continue;
        }

        $row = str_getcsv( $line );

        $m_first = strtolower( $get_col( $row, 'First Name' ) );
        $m_last  = strtolower( $get_col( $row, 'Last Name' ) );
        $m_email = strtolower( $get_col( $row, 'Email Address' ) );
        $m_zip   = substr( preg_replace( '/\D/', '', $get_col( $row, 'Zip' ) ), 0, 5 );

        if ( $m_first === $first && $m_last === $last && $m_email === $email && $m_zip === $zip ) {
            $match_row = $row;
            break;
        }
    }

    if ( ! $match_row ) {
        error_log( "AZCB FindMembership: no match for $first $last ($email) $zip" );
        return $entry; // match_found stays "no"
    }

    // Work out life membership from Membership category or ACB Life.
    $life_value = '';
    if ( isset( $header_map['Membership category'] ) ) {
        $life_value = strtolower( $get_col( $match_row, 'Membership category' ) );
    } elseif ( isset( $header_map['ACB Life'] ) ) {
        $life_value = strtolower( $get_col( $match_row, 'ACB Life' ) );
    }

    $is_life = (
        strpos( $life_value, 'life' ) !== false ||
        in_array( $life_value, array( 'yes', 'y', '1' ), true )
    ) ? 'yes' : 'no';

    // Set flags.
    $entry[ $field_match_found ] = 'yes';
    $entry[ $field_is_life ]     = $is_life;
    GFAPI::update_entry_field( $entry['id'], $field_match_found, 'yes' );
    GFAPI::update_entry_field( $entry['id'], $field_is_life, $is_life );

    // Fill m_* hidden fields from CSV.
    $payload = array();
    foreach ( $field_map as $fid => $col_name ) {
        if ( isset( $header_map[ $col_name ] ) ) {
            $val = $get_col( $match_row, $col_name );
            $entry[ $fid ] = $val;
            GFAPI::update_entry_field( $entry['id'], $fid, $val );
            $payload[ $col_name ] = $val;
        }
    }

    // Optional: store JSON snapshot of what we filled.
    if ( $field_payload ) {
        $json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        GFAPI::update_entry_field( $entry['id'], $field_payload, $json );
        $entry[ $field_payload ] = $json;
    }

    error_log( "AZCB FindMembership: MATCH for $first $last ($email) $zip | life=$is_life | entry {$entry['id']}" );

    return $entry;
}


/**
 * Admin: simple settings page to set the AZCB members CSV URL.
 * Constant `AZCB_MEMBERS_CSV_URL` (if defined) will take precedence over this option.
 */
add_action( 'admin_menu', 'azcb_members_csv_admin_menu' );
function azcb_members_csv_admin_menu() {
    add_options_page(
        'AZCB Members CSV',
        'AZCB Members CSV',
        'manage_options',
        'azcb-members-csv',
        'azcb_members_csv_options_page'
    );
}

function azcb_members_csv_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle save
    if ( ! empty( $_POST ) && check_admin_referer( 'azcb_members_csv_save', 'azcb_members_csv_nonce' ) ) {
        $url = trim( wp_unslash( $_POST['azcb_members_csv_url'] ?? '' ) );
        $url = esc_url_raw( $url );
        update_option( 'azcb_members_csv_url', $url );
        echo '<div class="updated"><p>Saved.</p></div>';
    }

    $current = esc_attr( get_option( 'azcb_members_csv_url', 'https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv' ) );
    ?>
    <div class="wrap">
        <h1>AZCB Members CSV</h1>
        <form method="post">
            <?php
            settings_fields( 'azcb_members_csv_group' );
            do_settings_sections( 'azcb-members-csv' );
            wp_nonce_field( 'azcb_members_csv_save', 'azcb_members_csv_nonce' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="azcb_members_csv_url">CSV URL</label></th>
                    <td>
                        <input type="text" id="azcb_members_csv_url" name="azcb_members_csv_url" value="<?php echo $current; ?>" class="regular-text" />
                        <p class="description">Enter the URL to the members CSV in the Media Library. Define the constant <code>AZCB_MEMBERS_CSV_URL</code> to override.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Register the CSV URL setting with a sanitize callback.
 */
add_action( 'admin_init', 'azcb_members_csv_register_setting' );
function azcb_members_csv_register_setting() {
    register_setting( 'azcb_members_csv_group', 'azcb_members_csv_url', 'esc_url_raw' );
}
