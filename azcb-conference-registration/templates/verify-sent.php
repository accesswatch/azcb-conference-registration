<?php
/**
 * Template: Check Your Email
 *
 * Variables: $heading, $message
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="azcb-form-wrap azcb-sent">
    <h2><?php echo esc_html( $heading ); ?></h2>
    <div class="azcb-message"><?php echo wp_kses_post( wpautop( $message ) ); ?></div>
</div>
