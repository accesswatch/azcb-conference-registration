<?php
/**
 * Template: Registration Form
 *
 * Variables: $heading, $intro, $button_text, $errors, $form_data, $token
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="azcb-form-wrap">
    <h2><?php echo esc_html( $heading ); ?></h2>

    <?php if ( $intro ) : ?>
        <div class="azcb-intro"><?php echo wp_kses_post( $intro ); ?></div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="azcb-notice azcb-notice-error" role="alert" aria-live="assertive">
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo wp_kses_post( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="azcb-form" novalidate>
        <?php wp_nonce_field( 'azcb_conf_register' ); ?>
        <input type="hidden" name="azcb_conf_action" value="register">
        <input type="hidden" name="azcb_token" value="<?php echo esc_attr( $token ); ?>">

        <div class="azcb-field">
            <label for="azcb_first_name">First Name <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="azcb_first_name" name="first_name"
                   value="<?php echo esc_attr( $form_data['first_name'] ); ?>"
                   required autocomplete="given-name"
                   aria-required="true">
        </div>

        <div class="azcb-field">
            <label for="azcb_last_name">Last Name <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="azcb_last_name" name="last_name"
                   value="<?php echo esc_attr( $form_data['last_name'] ); ?>"
                   required autocomplete="family-name"
                   aria-required="true">
        </div>

        <div class="azcb-field">
            <label for="azcb_email">Email Address <span class="required" aria-hidden="true">*</span></label>
            <input type="email" id="azcb_email" name="email"
                   value="<?php echo esc_attr( $form_data['email'] ); ?>"
                   required autocomplete="email"
                   aria-required="true"
                   readonly>
        </div>

        <div class="azcb-field">
            <label for="azcb_mobile_phone">Mobile Phone</label>
            <input type="tel" id="azcb_mobile_phone" name="mobile_phone"
                   value="<?php echo esc_attr( $form_data['mobile_phone'] ); ?>"
                   autocomplete="tel">
        </div>

        <div class="azcb-field">
            <label for="azcb_zip_code">Zip Code <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="azcb_zip_code" name="zip_code"
                   value="<?php echo esc_attr( $form_data['zip_code'] ); ?>"
                   required autocomplete="postal-code"
                   aria-required="true"
                   pattern="[0-9]{5}(-[0-9]{4})?"
                   inputmode="numeric"
                   maxlength="10">
        </div>

        <!-- Honeypot — hidden from humans, traps bots -->
        <div class="azcb-hp" aria-hidden="true">
            <label for="azcb_website2">Website</label>
            <input type="text" id="azcb_website2" name="azcb_website" value="" tabindex="-1" autocomplete="off">
        </div>

        <div class="azcb-actions">
            <button type="submit" class="azcb-button"><?php echo esc_html( $button_text ); ?></button>
        </div>
    </form>
</div>
