<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_Email {

    /**
     * Send the magic-link verification email.
     *
     * @param string $to         Recipient email.
     * @param string $first_name Recipient first name.
     * @param string $token      The magic-link token.
     * @return bool  Whether wp_mail succeeded.
     */
    public static function send_magic_link( $to, $first_name, $token ) {
        $register_page = get_option( 'azcb_conf_page_register' );
        $link_url      = $register_page
            ? add_query_arg( 'token', $token, get_permalink( $register_page ) )
            : home_url( '/conference/register/?token=' . $token );

        $subject = azcb_conf_get_setting( 'magic_link_email_subject' );
        $body    = azcb_conf_get_setting( 'magic_link_email_body' );
        $body    = azcb_conf_replace_placeholders( $body, array(
            '{first_name}' => esc_html( $first_name ),
            '{last_name}'  => '',
            '{link_url}'   => esc_url( $link_url ),
        ) );

        return self::send( $to, $subject, $body );
    }

    /**
     * Send the post-registration confirmation email.
     *
     * @param string $to         Recipient email.
     * @param string $first_name Recipient first name.
     * @param bool   $is_member  Whether the registrant is a member.
     * @return bool
     */
    public static function send_confirmation( $to, $first_name, $is_member ) {
        if ( $is_member ) {
            $subject = azcb_conf_get_setting( 'member_email_subject' );
            $body    = azcb_conf_get_setting( 'member_email_body' );
        } else {
            $subject = azcb_conf_get_setting( 'nonmember_email_subject' );
            $body    = azcb_conf_get_setting( 'nonmember_email_body' );
        }

        $body = azcb_conf_replace_placeholders( $body, array(
            '{first_name}' => esc_html( $first_name ),
            '{last_name}'  => '',
        ) );

        return self::send( $to, $subject, $body );
    }

    /* ─── Shared send helper ──────────────────────────────────── */

    private static function send( $to, $subject, $body ) {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>'
              . '<body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen,Ubuntu,sans-serif;'
              . 'max-width:600px;margin:0 auto;padding:20px;line-height:1.6;color:#333;">'
              . wpautop( wp_kses_post( $body ) )
              . '</body></html>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        return wp_mail( $to, $subject, $html, $headers );
    }
}
