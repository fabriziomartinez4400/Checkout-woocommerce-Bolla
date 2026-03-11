<?php
/**
 * FlowCheckout — Fields Manager
 * Handles the custom field editor: add, remove, reorder, and relabel
 * WooCommerce checkout fields based on admin configuration.
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Fields {

    /**
     * Default field configuration.
     * Each entry mirrors a WooCommerce billing/shipping field key.
     */
    private static array $default_config = [
        // ── Contact ───────────────────────────────────────────────────────────
        [
            'id'       => 'billing_email',
            'group'    => 'billing',
            'label'    => 'Email address',
            'type'     => 'email',
            'required' => true,
            'enabled'  => true,
            'priority' => 5,
            'width'    => 'wide',          // wide | half-left | half-right
        ],
        [
            'id'       => 'billing_phone',
            'group'    => 'billing',
            'label'    => 'Phone number',
            'type'     => 'tel',
            'required' => false,
            'enabled'  => true,
            'priority' => 10,
            'width'    => 'wide',
        ],
        // ── Name ──────────────────────────────────────────────────────────────
        [
            'id'       => 'billing_first_name',
            'group'    => 'billing',
            'label'    => 'First name',
            'type'     => 'text',
            'required' => true,
            'enabled'  => true,
            'priority' => 20,
            'width'    => 'half-left',
        ],
        [
            'id'       => 'billing_last_name',
            'group'    => 'billing',
            'label'    => 'Last name',
            'type'     => 'text',
            'required' => true,
            'enabled'  => true,
            'priority' => 30,
            'width'    => 'half-right',
        ],
        // ── Company ───────────────────────────────────────────────────────────
        [
            'id'       => 'billing_company',
            'group'    => 'billing',
            'label'    => 'Company (optional)',
            'type'     => 'text',
            'required' => false,
            'enabled'  => false,           // hidden by default
            'priority' => 40,
            'width'    => 'wide',
        ],
        // ── Address ───────────────────────────────────────────────────────────
        [
            'id'       => 'billing_address_1',
            'group'    => 'billing',
            'label'    => 'Street address',
            'type'     => 'text',
            'required' => true,
            'enabled'  => true,
            'priority' => 50,
            'width'    => 'wide',
        ],
        [
            'id'       => 'billing_address_2',
            'group'    => 'billing',
            'label'    => 'Apartment, suite, etc. (optional)',
            'type'     => 'text',
            'required' => false,
            'enabled'  => false,           // hidden by default — reduces friction
            'priority' => 60,
            'width'    => 'wide',
        ],
        [
            'id'       => 'billing_city',
            'group'    => 'billing',
            'label'    => 'City',
            'type'     => 'text',
            'required' => true,
            'enabled'  => true,
            'priority' => 70,
            'width'    => 'half-left',
        ],
        [
            'id'       => 'billing_postcode',
            'group'    => 'billing',
            'label'    => 'Postcode',
            'type'     => 'text',
            'required' => true,
            'enabled'  => true,
            'priority' => 80,
            'width'    => 'half-right',
        ],
        [
            'id'       => 'billing_country',
            'group'    => 'billing',
            'label'    => 'Country',
            'type'     => 'country',
            'required' => true,
            'enabled'  => true,
            'priority' => 90,
            'width'    => 'wide',
        ],
    ];

    // ── Init ──────────────────────────────────────────────────────────────────
    public static function init() {
        $self = new self();

        // Apply our field configuration to WooCommerce
        add_filter( 'woocommerce_checkout_fields', [ $self, 'apply_field_config' ], 20 );

        // AJAX: save field config from admin drag-and-drop editor
        add_action( 'wp_ajax_flowcheckout_save_fields', [ $self, 'ajax_save_fields' ] );

        // AJAX: get field config (for admin editor)
        add_action( 'wp_ajax_flowcheckout_get_fields',  [ $self, 'ajax_get_fields'  ] );
    }

    // ── Get merged field config ────────────────────────────────────────────────
    public static function get_config() {
        $saved = FlowCheckout_Settings::get( 'fields_config', [] );
        return ! empty( $saved ) ? $saved : self::$default_config;
    }

    // ── Apply config to WooCommerce fields ────────────────────────────────────
    public function apply_field_config( $fields ) {
        if ( ! FlowCheckout_Helpers::is_active() ) return $fields;

        $config = self::get_config();

        foreach ( $config as $field_cfg ) {
            $id    = $field_cfg['id']    ?? '';
            $group = $field_cfg['group'] ?? 'billing';

            if ( ! isset( $fields[ $group ][ $id ] ) ) continue;

            // Remove disabled fields
            if ( empty( $field_cfg['enabled'] ) ) {
                unset( $fields[ $group ][ $id ] );
                continue;
            }

            // Update label
            if ( ! empty( $field_cfg['label'] ) ) {
                $fields[ $group ][ $id ]['label'] = $field_cfg['label'];
            }

            // Update required
            $fields[ $group ][ $id ]['required'] = (bool) ( $field_cfg['required'] ?? false );

            // Update priority (controls order)
            $fields[ $group ][ $id ]['priority'] = (int) ( $field_cfg['priority'] ?? 10 );

            // Map width to WooCommerce class
            $width_map = [
                'wide'       => [ 'form-row-wide' ],
                'half-left'  => [ 'form-row-first' ],
                'half-right' => [ 'form-row-last' ],
            ];
            $width = $field_cfg['width'] ?? 'wide';
            if ( isset( $width_map[ $width ] ) ) {
                $fields[ $group ][ $id ]['class'] = $width_map[ $width ];
            }

            // Add autocomplete attributes for better UX
            $autocomplete_map = [
                'billing_first_name' => 'given-name',
                'billing_last_name'  => 'family-name',
                'billing_email'      => 'email',
                'billing_phone'      => 'tel',
                'billing_company'    => 'organization',
                'billing_address_1'  => 'address-line1',
                'billing_address_2'  => 'address-line2',
                'billing_city'       => 'address-level2',
                'billing_postcode'   => 'postal-code',
                'billing_country'    => 'country',
            ];
            if ( isset( $autocomplete_map[ $id ] ) ) {
                $fields[ $group ][ $id ]['autocomplete'] = $autocomplete_map[ $id ];
            }
        }

        return $fields;
    }

    // ── AJAX: Save field configuration ────────────────────────────────────────
    public function ajax_save_fields() {
        check_ajax_referer( FlowCheckout_Helpers::nonce_action(), 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'flowcheckout' ) ] );
        }

        $raw_config = isset( $_POST['fields_config'] ) ? $_POST['fields_config'] : '[]';

        // Sanitize
        $config = json_decode( wp_unslash( $raw_config ), true );
        if ( ! is_array( $config ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid field configuration.', 'flowcheckout' ) ] );
        }

        $clean = [];
        foreach ( $config as $field ) {
            $clean[] = [
                'id'       => sanitize_key( $field['id']    ?? '' ),
                'group'    => sanitize_key( $field['group'] ?? 'billing' ),
                'label'    => sanitize_text_field( $field['label']    ?? '' ),
                'type'     => sanitize_key( $field['type']            ?? 'text' ),
                'required' => (bool) ( $field['required']             ?? false ),
                'enabled'  => (bool) ( $field['enabled']              ?? true ),
                'priority' => absint( $field['priority']              ?? 10 ),
                'width'    => sanitize_key( $field['width']           ?? 'wide' ),
            ];
        }

        $settings                  = FlowCheckout_Settings::all();
        $settings['fields_config'] = $clean;
        FlowCheckout_Settings::save( $settings );

        wp_send_json_success( [ 'message' => __( 'Fields saved.', 'flowcheckout' ) ] );
    }

    // ── AJAX: Get field configuration ─────────────────────────────────────────
    public function ajax_get_fields() {
        check_ajax_referer( FlowCheckout_Helpers::nonce_action(), 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'flowcheckout' ) ] );
        }

        wp_send_json_success( [ 'fields' => self::get_config() ] );
    }
}
