<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /* ═══════════════════════════════════════════════════════════
       Admin menus
       ═══════════════════════════════════════════════════════════ */

    public function add_menus() {
        $count = $this->get_registration_count();
        $badge = $count ? ' <span class="awaiting-mod">' . $count . '</span>' : '';

        add_menu_page(
            'AZCB Conference',
            'AZCB Conference' . $badge,
            'manage_options',
            'azcb-conference',
            array( $this, 'page_registrations' ),
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'azcb-conference',
            'Registrations',
            'Registrations',
            'manage_options',
            'azcb-conference',
            array( $this, 'page_registrations' )
        );

        add_submenu_page(
            'azcb-conference',
            'Conference Settings',
            'Settings',
            'manage_options',
            'azcb-conference-settings',
            array( $this, 'page_settings' )
        );
    }

    /* ═══════════════════════════════════════════════════════════
       Registrations page
       ═══════════════════════════════════════════════════════════ */

    public function page_registrations() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $table = new AZCB_Conf_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Conference Registrations</h1>

            <?php
            $export_url = wp_nonce_url(
                admin_url( 'admin.php?page=azcb-conference&action=export_csv' ),
                'azcb_export_csv'
            );
            ?>
            <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">Export CSV</a>

            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['msg'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    $msgs = array(
                        'member'     => 'Registration updated: marked as member.',
                        'nonmember'  => 'Registration updated: marked as non-member.',
                        'deleted'    => 'Registration deleted.',
                        'resent'     => 'Confirmation email resent.',
                    );
                    echo esc_html( isset( $msgs[ $_GET['msg'] ] ) ? $msgs[ $_GET['msg'] ] : 'Done.' );
                    ?>
                </p></div>
            <?php endif; ?>

            <?php $table->views(); ?>

            <form method="get">
                <input type="hidden" name="page" value="azcb-conference">
                <?php if ( ! empty( $_GET['status'] ) ) : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['status'] ) ) ); ?>">
                <?php endif; ?>
                <?php $table->search_box( 'Search registrations', 'azcb-search' ); ?>
            </form>

            <form method="get">
                <input type="hidden" name="page" value="azcb-conference">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /* ─── Row actions (toggle member, delete, resend) ─────────── */

    public function handle_actions() {
        if ( empty( $_GET['page'] ) || 'azcb-conference' !== $_GET['page'] || empty( $_GET['action'] ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
        $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( 'export_csv' === $action ) {
            $this->export_csv();
            return;
        }

        if ( ! $id || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'azcb_conf_registrations';

        $msg = '';

        if ( 'mark_member' === $action && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'azcb_action_' . $id ) ) {
            $wpdb->update( $table, array( 'is_member' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
            $msg = 'member';
        } elseif ( 'mark_nonmember' === $action && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'azcb_action_' . $id ) ) {
            $wpdb->update( $table, array( 'is_member' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
            $msg = 'nonmember';
        } elseif ( 'delete_reg' === $action && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'azcb_action_' . $id ) ) {
            $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
            $msg = 'deleted';
        } elseif ( 'resend' === $action && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'azcb_action_' . $id ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
            if ( $row ) {
                $sent = AZCB_Conf_Email::send_confirmation( $row['email'], $row['first_name'], (bool) $row['is_member'] );
                if ( $sent ) {
                    $wpdb->update( $table, array( 'confirmation_sent' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
                }
            }
            $msg = 'resent';
        }

        if ( $msg ) {
            wp_safe_redirect( admin_url( 'admin.php?page=azcb-conference&msg=' . $msg ) );
            exit;
        }
    }

    /* ─── CSV export ──────────────────────────────────────────── */

    private function export_csv() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'azcb_export_csv' ) ) {
            wp_die( 'Unauthorized.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'azcb_conf_registrations';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY registered_at DESC", ARRAY_A );

        $filename = 'azcb-conference-registrations-' . gmdate( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        // BOM for Excel UTF-8.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, array( 'ID', 'First Name', 'Last Name', 'Email', 'Mobile Phone', 'Zip Code', 'Member', 'Lifetime', 'Registered At', 'Confirmation Sent' ) );

        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['mobile_phone'],
                $row['zip_code'],
                $row['is_member'] ? 'Yes' : 'No',
                $row['is_lifetime'] ? 'Yes' : 'No',
                $row['registered_at'],
                $row['confirmation_sent'] ? 'Yes' : 'No',
            ) );
        }

        fclose( $out );
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       Settings page
       ═══════════════════════════════════════════════════════════ */

    public function register_settings() {
        register_setting( 'azcb_conf_settings_group', 'azcb_conf_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    public function sanitize_settings( $input ) {
        $clean = array();

        $url_fields = array( 'csv_url', 'contact_url', 'membership_url' );
        foreach ( $url_fields as $f ) {
            $clean[ $f ] = isset( $input[ $f ] ) ? esc_url_raw( trim( $input[ $f ] ) ) : '';
        }

        $int_fields = array( 'csv_cache_minutes', 'magic_link_expiry_minutes', 'rate_limit_per_hour' );
        foreach ( $int_fields as $f ) {
            $clean[ $f ] = isset( $input[ $f ] ) ? max( 1, intval( $input[ $f ] ) ) : 1;
        }

        $clean['enable_convention_redirect'] = ! empty( $input['enable_convention_redirect'] ) ? 1 : 0;

        $text_fields = array(
            'verify_heading', 'verify_button_text', 'sent_heading',
            'register_heading', 'register_button_text',
            'member_confirm_heading', 'nonmember_confirm_heading',
            'magic_link_email_subject', 'member_email_subject', 'nonmember_email_subject',
        );
        foreach ( $text_fields as $f ) {
            $clean[ $f ] = isset( $input[ $f ] ) ? sanitize_text_field( $input[ $f ] ) : '';
        }

        $rich_fields = array(
            'verify_intro', 'verify_footer', 'sent_message',
            'register_intro',
            'member_confirm_message', 'nonmember_confirm_message',
            'magic_link_email_body', 'member_email_body', 'nonmember_email_body',
        );
        foreach ( $rich_fields as $f ) {
            $clean[ $f ] = isset( $input[ $f ] ) ? wp_kses_post( $input[ $f ] ) : '';
        }

        // Clear static settings cache.
        wp_cache_delete( 'azcb_conf_settings', 'options' );

        return $clean;
    }

    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tabs    = array( 'general' => 'General', 'pages' => 'Page Content', 'emails' => 'Email Templates' );
        $current = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        if ( ! isset( $tabs[ $current ] ) ) {
            $current = 'general';
        }
        ?>
        <div class="wrap">
            <h1>AZCB Conference Settings</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=azcb-conference-settings&tab=' . $slug ) ); ?>"
                       class="nav-tab <?php echo $current === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields( 'azcb_conf_settings_group' ); ?>

                <?php // Preserve values from other tabs by outputting them as hidden fields.
                $all = get_option( 'azcb_conf_settings', array() );
                $this->render_hidden_settings( $all, $current );
                ?>

                <table class="form-table" role="presentation">
                    <?php
                    if ( 'general' === $current ) {
                        $this->render_general_tab();
                    } elseif ( 'pages' === $current ) {
                        $this->render_pages_tab();
                    } elseif ( 'emails' === $current ) {
                        $this->render_emails_tab();
                    }
                    ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ─── Tab renderers ───────────────────────────────────────── */

    private function render_general_tab() {
        $this->text_field( 'csv_url', 'Members CSV URL', 'regular-text', 'Full URL to the membership CSV in the Media Library.' );
        $this->number_field( 'csv_cache_minutes', 'CSV Cache (minutes)', 'How long to cache the downloaded CSV locally.' );
        $this->number_field( 'magic_link_expiry_minutes', 'Magic Link Expiry (minutes)', 'How long verification links remain valid.' );
        $this->number_field( 'rate_limit_per_hour', 'Rate Limit (per email/hour)', 'Maximum verification emails per address per hour.' );
        $this->text_field( 'contact_url', 'Contact Page URL', 'regular-text', 'Used in {contact_url} placeholder.' );
        $this->text_field( 'membership_url', 'Membership Page URL', 'regular-text', 'Used in {membership_url} placeholder.' );
        $this->checkbox_field( 'enable_convention_redirect', 'Enable /convention/ → /conference/ Redirect', 'Redirect visitors from the old URL to the conference page.' );
    }

    private function render_pages_tab() {
        echo '<tr><td colspan="2"><h2>Email Verification Page</h2></td></tr>';
        $this->text_field( 'verify_heading', 'Heading' );
        $this->textarea_field( 'verify_intro', 'Intro Text', 'Shown above the form. HTML allowed.' );
        $this->text_field( 'verify_button_text', 'Button Text' );
        $this->textarea_field( 'verify_footer', 'Footer', 'Shown below the form. Supports {contact_url}.' );

        echo '<tr><td colspan="2"><h2>"Check Your Email" Page</h2></td></tr>';
        $this->text_field( 'sent_heading', 'Heading' );
        $this->textarea_field( 'sent_message', 'Message', 'Supports {expiry_minutes}.' );

        echo '<tr><td colspan="2"><h2>Registration Page</h2></td></tr>';
        $this->text_field( 'register_heading', 'Heading' );
        $this->textarea_field( 'register_intro', 'Intro Text' );
        $this->text_field( 'register_button_text', 'Button Text' );

        echo '<tr><td colspan="2"><h2>Member Confirmation</h2></td></tr>';
        $this->text_field( 'member_confirm_heading', 'Heading' );
        $this->textarea_field( 'member_confirm_message', 'Message', 'Supports {contact_url}.' );

        echo '<tr><td colspan="2"><h2>Non-Member Confirmation</h2></td></tr>';
        $this->text_field( 'nonmember_confirm_heading', 'Heading' );
        $this->textarea_field( 'nonmember_confirm_message', 'Message', 'Supports {contact_url}, {membership_url}.' );
    }

    private function render_emails_tab() {
        echo '<tr><td colspan="2"><h2>Magic Link Email</h2><p class="description">Placeholders: {first_name}, {last_name}, {link_url}, {expiry_minutes}, {contact_url}, {site_name}</p></td></tr>';
        $this->text_field( 'magic_link_email_subject', 'Subject' );
        $this->textarea_field( 'magic_link_email_body', 'Body', '', 12 );

        echo '<tr><td colspan="2"><h2>Member Confirmation Email</h2><p class="description">Placeholders: {first_name}, {last_name}, {contact_url}, {site_name}</p></td></tr>';
        $this->text_field( 'member_email_subject', 'Subject' );
        $this->textarea_field( 'member_email_body', 'Body', '', 10 );

        echo '<tr><td colspan="2"><h2>Non-Member Confirmation Email</h2><p class="description">Placeholders: {first_name}, {last_name}, {contact_url}, {membership_url}, {site_name}</p></td></tr>';
        $this->text_field( 'nonmember_email_subject', 'Subject' );
        $this->textarea_field( 'nonmember_email_body', 'Body', '', 10 );
    }

    /* ─── Field helpers ───────────────────────────────────────── */

    private function val( $key ) {
        return azcb_conf_get_setting( $key );
    }

    private function text_field( $key, $label, $class = 'regular-text', $desc = '' ) {
        ?>
        <tr>
            <th scope="row"><label for="azcb_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="text" id="azcb_<?php echo esc_attr( $key ); ?>"
                       name="azcb_conf_settings[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( $this->val( $key ) ); ?>"
                       class="<?php echo esc_attr( $class ); ?>">
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function number_field( $key, $label, $desc = '' ) {
        ?>
        <tr>
            <th scope="row"><label for="azcb_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="number" id="azcb_<?php echo esc_attr( $key ); ?>"
                       name="azcb_conf_settings[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( intval( $this->val( $key ) ) ); ?>"
                       min="1" class="small-text">
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function checkbox_field( $key, $label, $desc = '' ) {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="azcb_conf_settings[<?php echo esc_attr( $key ); ?>]"
                           value="1" <?php checked( $this->val( $key ), 1 ); ?>>
                    <?php echo esc_html( $desc ); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    private function textarea_field( $key, $label, $desc = '', $rows = 5 ) {
        ?>
        <tr>
            <th scope="row"><label for="azcb_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <textarea id="azcb_<?php echo esc_attr( $key ); ?>"
                          name="azcb_conf_settings[<?php echo esc_attr( $key ); ?>]"
                          rows="<?php echo esc_attr( $rows ); ?>" class="large-text"><?php echo esc_textarea( $this->val( $key ) ); ?></textarea>
                <?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render hidden inputs for settings on OTHER tabs so they
     * are not wiped when saving a single tab.
     */
    private function render_hidden_settings( $all, $current_tab ) {
        $general_keys = array( 'csv_url', 'csv_cache_minutes', 'magic_link_expiry_minutes', 'rate_limit_per_hour', 'contact_url', 'membership_url', 'enable_convention_redirect' );
        $pages_keys   = array( 'verify_heading', 'verify_intro', 'verify_button_text', 'verify_footer', 'sent_heading', 'sent_message', 'register_heading', 'register_intro', 'register_button_text', 'member_confirm_heading', 'member_confirm_message', 'nonmember_confirm_heading', 'nonmember_confirm_message' );
        $emails_keys  = array( 'magic_link_email_subject', 'magic_link_email_body', 'member_email_subject', 'member_email_body', 'nonmember_email_subject', 'nonmember_email_body' );

        $tab_map = array(
            'general' => $general_keys,
            'pages'   => $pages_keys,
            'emails'  => $emails_keys,
        );

        foreach ( $tab_map as $tab => $keys ) {
            if ( $tab === $current_tab ) {
                continue; // These will be rendered as visible fields.
            }
            foreach ( $keys as $key ) {
                $value = isset( $all[ $key ] ) ? $all[ $key ] : $this->val( $key );
                echo '<input type="hidden" name="azcb_conf_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
            }
        }
    }

    /* ─── Helpers ──────────────────────────────────────────────── */

    private function get_registration_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}azcb_conf_registrations"
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════
   WP_List_Table for registrations
   ═══════════════════════════════════════════════════════════════════ */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AZCB_Conf_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'registration',
            'plural'   => 'registrations',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'name'              => 'Name',
            'email'             => 'Email',
            'mobile_phone'      => 'Phone',
            'zip_code'          => 'Zip',
            'is_member'         => 'Member',
            'is_lifetime'       => 'Lifetime',
            'registered_at'     => 'Registered',
            'confirmation_sent' => 'Confirmed',
        );
    }

    public function get_sortable_columns() {
        return array(
            'name'          => array( 'last_name', false ),
            'email'         => array( 'email', false ),
            'is_member'     => array( 'is_member', false ),
            'registered_at' => array( 'registered_at', true ),
        );
    }

    public function get_views() {
        global $wpdb;
        $table   = $wpdb->prefix . 'azcb_conf_registrations';
        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $members = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_member = 1" );
        $non     = $total - $members;

        $current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
        $base    = admin_url( 'admin.php?page=azcb-conference' );

        return array(
            'all'     => '<a href="' . esc_url( $base ) . '"' . ( 'all' === $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>',
            'member'  => '<a href="' . esc_url( $base . '&status=member' ) . '"' . ( 'member' === $current ? ' class="current"' : '' ) . '>Members <span class="count">(' . $members . ')</span></a>',
            'nonmember' => '<a href="' . esc_url( $base . '&status=nonmember' ) . '"' . ( 'nonmember' === $current ? ' class="current"' : '' ) . '>Non-Members <span class="count">(' . $non . ')</span></a>',
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table    = $wpdb->prefix . 'azcb_conf_registrations';
        $per_page = 25;
        $paged    = $this->get_pagenum();

        $where = '1=1';

        // Filter by status.
        if ( ! empty( $_GET['status'] ) ) {
            $status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
            if ( 'member' === $status ) {
                $where .= ' AND is_member = 1';
            } elseif ( 'nonmember' === $status ) {
                $where .= ' AND is_member = 0';
            }
        }

        // Search.
        if ( ! empty( $_GET['s'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
            $where .= $wpdb->prepare( ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)', $search, $search, $search );
        }

        // Sorting.
        $allowed_order = array( 'last_name', 'email', 'is_member', 'registered_at' );
        $orderby       = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_order, true )
            ? sanitize_sql_orderby( $_GET['orderby'] )
            : 'registered_at';
        if ( ! $orderby ) {
            $orderby = 'registered_at';
        }
        $order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

        $offset = ( $paged - 1 ) * $per_page;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where, $orderby, $order are sanitized above.
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ) );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'mobile_phone':
            case 'zip_code':
                return esc_html( $item[ $column_name ] );
            case 'is_member':
                return $item['is_member']
                    ? '<span style="color:green;font-weight:bold;">Yes</span>'
                    : '<span style="color:#999;">No</span>';
            case 'is_lifetime':
                return $item['is_lifetime'] ? 'Yes' : '—';
            case 'registered_at':
                return esc_html( get_date_from_gmt( $item['registered_at'], 'M j, Y g:i A' ) );
            case 'confirmation_sent':
                return $item['confirmation_sent'] ? '&#10003;' : '—';
            default:
                return '';
        }
    }

    public function column_name( $item ) {
        $name = esc_html( $item['first_name'] . ' ' . $item['last_name'] );
        $id   = absint( $item['id'] );

        // Row actions.
        $toggle_label = $item['is_member'] ? 'Mark Non-Member' : 'Mark Member';
        $toggle_action = $item['is_member'] ? 'mark_nonmember' : 'mark_member';

        $actions = array(
            $toggle_action => '<a href="' . esc_url( wp_nonce_url(
                admin_url( "admin.php?page=azcb-conference&action={$toggle_action}&id={$id}" ),
                'azcb_action_' . $id
            ) ) . '">' . esc_html( $toggle_label ) . '</a>',
            'resend' => '<a href="' . esc_url( wp_nonce_url(
                admin_url( "admin.php?page=azcb-conference&action=resend&id={$id}" ),
                'azcb_action_' . $id
            ) ) . '">Resend Email</a>',
            'delete' => '<a href="' . esc_url( wp_nonce_url(
                admin_url( "admin.php?page=azcb-conference&action=delete_reg&id={$id}" ),
                'azcb_action_' . $id
            ) ) . '" style="color:#b32d2e;" onclick="return confirm(\'Delete this registration?\');">Delete</a>',
        );

        return $name . $this->row_actions( $actions );
    }

    public function column_email( $item ) {
        return '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>';
    }
}
