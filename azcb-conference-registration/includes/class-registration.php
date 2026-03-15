<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_Registration {

    /** @var array Form-validation errors carried from init to shortcode render. */
    private $errors = array();

    public function __construct() {
        add_action( 'init', array( $this, 'handle_form_submissions' ) );

        add_shortcode( 'azcb_conference_verify',       array( $this, 'shortcode_verify' ) );
        add_shortcode( 'azcb_conference_sent',         array( $this, 'shortcode_sent' ) );
        add_shortcode( 'azcb_conference_register',     array( $this, 'shortcode_register' ) );
        add_shortcode( 'azcb_conference_confirmation', array( $this, 'shortcode_confirmation' ) );
    }

    /* ═══════════════════════════════════════════════════════════
       POST handling (runs in init — before headers are sent)
       ═══════════════════════════════════════════════════════════ */

    public function handle_form_submissions() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['azcb_conf_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['azcb_conf_action'] ) );

        if ( 'verify' === $action ) {
            $this->process_verify();
        } elseif ( 'register' === $action ) {
            $this->process_register();
        }
    }

    /* ─── Verify form processing ──────────────────────────────── */

    private function process_verify() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'azcb_conf_verify' ) ) {
            $this->errors[] = 'Security check failed. Please try again.';
            return;
        }

        // Honeypot.
        if ( ! empty( $_POST['azcb_website'] ) ) {
            // Silently redirect — looks like success to a bot.
            $sent_page = get_option( 'azcb_conf_page_sent' );
            wp_safe_redirect( $sent_page ? get_permalink( $sent_page ) : home_url( '/conference/verify/sent/' ) );
            exit;
        }

        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        // Validate.
        if ( ! $first_name ) {
            $this->errors[] = 'Please enter your first name.';
        }
        if ( ! $last_name ) {
            $this->errors[] = 'Please enter your last name.';
        }
        if ( ! is_email( $email ) ) {
            $this->errors[] = 'Please enter a valid email address.';
        }
        if ( ! empty( $this->errors ) ) {
            return;
        }

        // Check if already registered.
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}azcb_conf_registrations WHERE email = %s LIMIT 1",
                strtolower( $email )
            )
        );
        if ( $existing ) {
            $this->errors[] = 'This email address has already been registered for the conference. If you need assistance, please <a href="' . esc_url( azcb_conf_get_setting( 'contact_url' ) ) . '">contact us</a>.';
            return;
        }

        // Membership lookup: CSV first, then Gravity Forms as fallback.
        $match       = AZCB_Conf_CSV_Lookup::find( $first_name, $last_name, $email );
        $is_member   = $match ? 1 : 0;
        $is_lifetime = $match ? (int) AZCB_Conf_CSV_Lookup::is_lifetime( $match ) : 0;
        $member_data = $match ? wp_json_encode( $match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';

        if ( ! $match ) {
            $gf_match = AZCB_Conf_GF_Lookup::find( $first_name, $last_name, $email );
            if ( $gf_match ) {
                $is_member   = 1;
                $is_lifetime = (int) AZCB_Conf_GF_Lookup::is_lifetime( $gf_match );
                $member_data = wp_json_encode( $gf_match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }
        }

        // Create token.
        $token = AZCB_Conf_Magic_Link::create( array(
            'email'       => strtolower( $email ),
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'is_member'   => $is_member,
            'is_lifetime' => $is_lifetime,
            'member_data' => $member_data,
        ) );

        if ( is_wp_error( $token ) ) {
            $this->errors[] = $token->get_error_message();
            return;
        }

        // Send magic-link email.
        AZCB_Conf_Email::send_magic_link( $email, $first_name, $token );

        // Redirect to "check your email" page.
        $sent_page = get_option( 'azcb_conf_page_sent' );
        wp_safe_redirect( $sent_page ? get_permalink( $sent_page ) : home_url( '/conference/verify/sent/' ) );
        exit;
    }

    /* ─── Register form processing ────────────────────────────── */

    private function process_register() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'azcb_conf_register' ) ) {
            $this->errors[] = 'Security check failed. Please try again.';
            return;
        }

        // Honeypot.
        if ( ! empty( $_POST['azcb_website'] ) ) {
            $confirm_page = get_option( 'azcb_conf_page_confirmation' );
            wp_safe_redirect( $confirm_page ? get_permalink( $confirm_page ) : home_url( '/conference/register/confirmation/' ) );
            exit;
        }

        // Validate token.
        $token_str = sanitize_text_field( wp_unslash( $_POST['azcb_token'] ?? '' ) );
        $token     = AZCB_Conf_Magic_Link::validate( $token_str );
        if ( is_wp_error( $token ) ) {
            $this->errors[] = $token->get_error_message();
            return;
        }

        $first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $mobile_phone = sanitize_text_field( wp_unslash( $_POST['mobile_phone'] ?? '' ) );
        $zip_code     = sanitize_text_field( wp_unslash( $_POST['zip_code'] ?? '' ) );

        // Use the verified email from the token — not from POST (field is readonly,
        // but could be tampered with; using the token value prevents email-squatting).
        $email = $token['email'];

        if ( ! $first_name ) {
            $this->errors[] = 'Please enter your first name.';
        }
        if ( ! $last_name ) {
            $this->errors[] = 'Please enter your last name.';
        }
        if ( ! $zip_code ) {
            $this->errors[] = 'Please enter your zip code.';
        }
        if ( ! empty( $this->errors ) ) {
            return;
        }

        // Persist registration.
        global $wpdb;
        $table       = $wpdb->prefix . 'azcb_conf_registrations';
        $is_member   = (int) $token['is_member'];
        $is_lifetime = (int) $token['is_lifetime'];

        $result = $wpdb->insert(
            $table,
            array(
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email'             => strtolower( $email ),
                'mobile_phone'      => $mobile_phone,
                'zip_code'          => $zip_code,
                'is_member'         => $is_member,
                'is_lifetime'       => $is_lifetime,
                'registered_at'     => current_time( 'mysql', true ),
                'confirmation_sent' => 0,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
        );

        if ( ! $result ) {
            // Likely duplicate email.
            $this->errors[] = 'This email address has already been registered for the conference. If you need assistance, please <a href="' . esc_url( azcb_conf_get_setting( 'contact_url' ) ) . '">contact us</a>.';
            return;
        }

        // Consume token.
        AZCB_Conf_Magic_Link::consume( $token_str );

        // Send confirmation email.
        $sent = AZCB_Conf_Email::send_confirmation( $email, $first_name, (bool) $is_member );
        if ( $sent ) {
            $wpdb->update(
                $table,
                array( 'confirmation_sent' => 1 ),
                array( 'email' => strtolower( $email ) ),
                array( '%d' ),
                array( '%s' )
            );
        }

        // Create short-lived confirmation token.
        $ct = bin2hex( random_bytes( 16 ) );
        set_transient( 'azcb_conf_ct_' . $ct, array( 'is_member' => $is_member ), 30 * MINUTE_IN_SECONDS );

        $confirm_page = get_option( 'azcb_conf_page_confirmation' );
        $redirect_url = $confirm_page
            ? add_query_arg( 'ct', $ct, get_permalink( $confirm_page ) )
            : home_url( '/conference/register/confirmation/?ct=' . $ct );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       Shortcodes
       ═══════════════════════════════════════════════════════════ */

    /** Email-verification form. */
    public function shortcode_verify( $atts ) {
        $form_data = array(
            'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
        );

        return $this->load_template( 'verify-form', array(
            'heading'     => azcb_conf_get_setting( 'verify_heading' ),
            'intro'       => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'verify_intro' ) ),
            'button_text' => azcb_conf_get_setting( 'verify_button_text' ),
            'footer'      => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'verify_footer' ) ),
            'errors'      => $this->errors,
            'form_data'   => $form_data,
        ) );
    }

    /** "Check your email" page. */
    public function shortcode_sent( $atts ) {
        return $this->load_template( 'verify-sent', array(
            'heading' => azcb_conf_get_setting( 'sent_heading' ),
            'message' => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'sent_message' ) ),
        ) );
    }

    /** Registration form (accessed via magic link). */
    public function shortcode_register( $atts ) {
        // Require a valid token.
        $token_str = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

        // If POST failed validation, the token is in the POST data.
        if ( empty( $token_str ) && ! empty( $_POST['azcb_token'] ) ) {
            $token_str = sanitize_text_field( wp_unslash( $_POST['azcb_token'] ) );
        }

        if ( ! $token_str ) {
            // No token — redirect to verify page.
            $verify_page = get_option( 'azcb_conf_page_verify' );
            if ( $verify_page ) {
                wp_safe_redirect( get_permalink( $verify_page ) );
                exit;
            }
            return '<p>' . esc_html__( 'Please start the registration process from the conference page.', 'azcb-conference' ) . '</p>';
        }

        $token = AZCB_Conf_Magic_Link::validate( $token_str );
        if ( is_wp_error( $token ) ) {
            $verify_url  = get_permalink( get_option( 'azcb_conf_page_verify' ) );
            $verify_link = $verify_url ? $verify_url : home_url( '/conference/verify/' );
            return '<div class="azcb-notice azcb-notice-error" role="alert"><p>'
                 . esc_html( $token->get_error_message() )
                 . '</p><p><a href="' . esc_url( $verify_link ) . '">Start again</a></p></div>';
        }

        // Pre-fill from token + member data.
        $form_data = array(
            'first_name'   => $token['first_name'],
            'last_name'    => $token['last_name'],
            'email'        => $token['email'],
            'mobile_phone' => '',
            'zip_code'     => '',
        );

        if ( $token['is_member'] && $token['member_data'] ) {
            $member = json_decode( $token['member_data'], true );
            if ( is_array( $member ) ) {
                if ( ! empty( $member['Mobile Phone'] ) ) {
                    $form_data['mobile_phone'] = $member['Mobile Phone'];
                }
                if ( ! empty( $member['Zip'] ) ) {
                    $form_data['zip_code'] = $member['Zip'];
                }
            }
        }

        // If this is a failed POST, overlay with submitted values.
        if ( ! empty( $_POST['azcb_conf_action'] ) && 'register' === $_POST['azcb_conf_action'] ) {
            $form_data = array(
                'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
                'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
                'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
                'mobile_phone' => sanitize_text_field( wp_unslash( $_POST['mobile_phone'] ?? '' ) ),
                'zip_code'     => sanitize_text_field( wp_unslash( $_POST['zip_code'] ?? '' ) ),
            );
        }

        return $this->load_template( 'register-form', array(
            'heading'     => azcb_conf_get_setting( 'register_heading' ),
            'intro'       => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'register_intro' ) ),
            'button_text' => azcb_conf_get_setting( 'register_button_text' ),
            'errors'      => $this->errors,
            'form_data'   => $form_data,
            'token'       => $token_str,
        ) );
    }

    /** Confirmation page. */
    public function shortcode_confirmation( $atts ) {
        $ct   = isset( $_GET['ct'] ) ? preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['ct'] ) ) ) : '';
        $data = $ct ? get_transient( 'azcb_conf_ct_' . $ct ) : false;

        if ( false !== $data ) {
            delete_transient( 'azcb_conf_ct_' . $ct );
            $is_member = ! empty( $data['is_member'] );
        } else {
            // Fallback: show generic thank-you.
            $is_member = null;
        }

        if ( true === $is_member ) {
            return $this->load_template( 'confirmation-member', array(
                'heading' => azcb_conf_get_setting( 'member_confirm_heading' ),
                'message' => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'member_confirm_message' ) ),
            ) );
        }

        if ( false === $is_member ) {
            return $this->load_template( 'confirmation-nonmember', array(
                'heading' => azcb_conf_get_setting( 'nonmember_confirm_heading' ),
                'message' => azcb_conf_replace_placeholders( azcb_conf_get_setting( 'nonmember_confirm_message' ) ),
            ) );
        }

        // Expired/missing ct — generic message.
        return '<div class="azcb-confirmation"><h2>Thank You</h2>'
             . '<p>Thank you for your interest in the 2026 AZCB Conference. If you have completed registration you will receive a confirmation email. '
             . 'For questions, please <a href="' . esc_url( azcb_conf_get_setting( 'contact_url' ) ) . '">contact us</a>.</p></div>';
    }

    /* ─── Template loader ─────────────────────────────────────── */

    private function load_template( $name, $vars = array() ) {
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        ob_start();
        require AZCB_CONF_DIR . 'templates/' . $name . '.php';
        return ob_get_clean();
    }
}
