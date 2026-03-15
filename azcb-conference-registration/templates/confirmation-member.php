<?php
/**
 * Template: Member Confirmation
 *
 * Variables: $heading, $message
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="azcb-form-wrap azcb-confirmation azcb-confirmation-member">
    <h2><?php echo esc_html( $heading ); ?></h2>
    <div class="azcb-message"><?php echo wp_kses_post( wpautop( $message ) ); ?></div>
</div>
