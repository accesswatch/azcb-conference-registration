<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AZCB_Conf_GF_Lookup {

    /**
     * Look up a person in a Gravity Forms form's entries.
     *
     * Requires the Gravity Forms plugin to be active.
     *
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @return array|false  Array with 'is_member' and 'is_lifetime' keys on match, false otherwise.
     */
    public static function find( $first_name, $last_name, $email ) {
        if ( ! class_exists( 'GFAPI' ) ) {
            return false;
        }

        $form_id = intval( azcb_conf_get_setting( 'gf_form_id' ) );
        if ( ! $form_id ) {
            return false;
        }

        $field_map = self::get_field_map( $form_id );
        if ( ! $field_map['email'] ) {
            return false;
        }

        // Search by email first (most unique identifier).
        $entries = GFAPI::get_entries( $form_id, array(
            'status'        => 'active',
            'field_filters' => array(
                array(
                    'key'   => $field_map['email'],
                    'value' => strtolower( trim( $email ) ),
                ),
            ),
        ) );

        if ( is_wp_error( $entries ) || empty( $entries ) ) {
            return false;
        }

        // Narrow by first + last name.
        $first = strtolower( trim( $first_name ) );
        $last  = strtolower( trim( $last_name ) );

        foreach ( $entries as $entry ) {
            $e_first = strtolower( trim( rgar( $entry, $field_map['first_name'] ) ) );
            $e_last  = strtolower( trim( rgar( $entry, $field_map['last_name'] ) ) );

            if ( $e_first === $first && $e_last === $last ) {
                return array(
                    'source'    => 'gravity_forms',
                    'entry_id'  => rgar( $entry, 'id' ),
                    'is_member' => true,
                );
            }
        }

        return false;
    }

    /**
     * Determine whether a GF match represents a lifetime member.
     *
     * GF entries don't carry membership-category data, so this defaults to false.
     * Lifetime detection remains the CSV's responsibility.
     *
     * @param array $match  The array returned by find().
     * @return bool
     */
    public static function is_lifetime( $match ) {
        return false;
    }

    /* ─── Field mapping ───────────────────────────────────────── */

    /**
     * Resolve the GF field IDs for first name, last name, and email.
     *
     * Uses admin-configured IDs when available, otherwise auto-discovers
     * them by walking the form's field labels.
     *
     * @param int $form_id
     * @return array  Keys: first_name, last_name, email — values are string field IDs or empty.
     */
    private static function get_field_map( $form_id ) {
        $map = array(
            'first_name' => azcb_conf_get_setting( 'gf_field_first_name' ),
            'last_name'  => azcb_conf_get_setting( 'gf_field_last_name' ),
            'email'      => azcb_conf_get_setting( 'gf_field_email' ),
        );

        // If any are empty, attempt auto-discovery from form metadata.
        if ( $map['first_name'] && $map['last_name'] && $map['email'] ) {
            return $map;
        }

        $form = GFAPI::get_form( $form_id );
        if ( ! $form || empty( $form['fields'] ) ) {
            return $map;
        }

        foreach ( $form['fields'] as $field ) {
            $label = strtolower( $field->label );

            // Name field with sub-inputs (First / Last).
            if ( 'name' === $field->type && ! empty( $field->inputs ) ) {
                foreach ( $field->inputs as $input ) {
                    $sub_label = strtolower( $input['label'] );
                    if ( ! $map['first_name'] && 'first' === $sub_label ) {
                        $map['first_name'] = (string) $input['id'];
                    }
                    if ( ! $map['last_name'] && 'last' === $sub_label ) {
                        $map['last_name'] = (string) $input['id'];
                    }
                }
                continue;
            }

            // Standalone text fields labeled "First Name" / "Last Name".
            if ( ! $map['first_name'] && false !== strpos( $label, 'first' ) && false !== strpos( $label, 'name' ) ) {
                $map['first_name'] = (string) $field->id;
            }
            if ( ! $map['last_name'] && false !== strpos( $label, 'last' ) && false !== strpos( $label, 'name' ) ) {
                $map['last_name'] = (string) $field->id;
            }

            // Email field.
            if ( ! $map['email'] && ( 'email' === $field->type || false !== strpos( $label, 'email' ) ) ) {
                $map['email'] = (string) $field->id;
            }
        }

        return $map;
    }
}
