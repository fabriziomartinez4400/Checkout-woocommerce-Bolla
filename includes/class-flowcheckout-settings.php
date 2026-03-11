<?php
/**
 * FlowCheckout — Settings
 * Central store for all plugin options.
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Settings {

    private static string $option_key = 'flowcheckout_settings';
    private static array  $cache      = [];

    // ── Init ──────────────────────────────────────────────────────────────────
    public static function init() {
        // Nothing to hook yet — settings are read on demand.
    }

    // ── Default values ────────────────────────────────────────────────────────
    public static function defaults() {
        return [
            // General
            'enabled'                    => true,
            'checkout_layout'            => 'two-column',   // two-column | single-column
            'hide_header_footer'         => true,
            'remove_default_wc_styles'   => true,

            // Branding
            'logo_url'                   => '',
            'brand_color'                => '#111827',
            'accent_color'               => '#2563EB',
            'button_radius'              => '10',           // px
            'field_radius'               => '8',            // px
            'font_family'                => 'system',       // system|custom
            'custom_font_url'            => '',

            // Express checkout
            'express_checkout_enabled'   => true,
            'express_checkout_position'  => 'above',        // above|below|both|none
            'express_checkout_style'     => 'auto',         // auto|light|dark
            'express_checkout_label'     => 'Express checkout',
            'express_checkout_divider'   => 'Or continue with',

            // Layout sections order (drag-and-drop saved here)
            'sections_order'             => [ 'contact', 'shipping', 'billing', 'shipping_method', 'payment' ],

            // Order summary
            'order_summary_sticky'       => true,
            'order_summary_position'     => 'right',        // right | top-mobile
            'show_product_thumbnails'    => true,
            'show_subtotals'             => true,
            'collapsible_on_mobile'      => true,

            // Trust badges
            'trust_badges_enabled'       => true,
            'trust_badges_position'      => 'below_button', // below_button | above_button | sidebar
            'trust_badge_ssl'            => true,
            'trust_badge_returns'        => true,
            'trust_badge_shipping'       => true,
            'trust_badge_custom_text'    => '',
            'trust_badge_card_icons'     => true,
            'trust_badge_ssl_text'       => '256-bit SSL encryption',
            'trust_badge_returns_text'   => '30-day free returns',
            'trust_badge_shipping_text'  => 'Free shipping over £50',

            // Fields config (serialised array)
            'fields_config'              => [],

            // Progress bar
            'progress_bar_enabled'       => true,
            'progress_bar_steps'         => [ 'Cart', 'Information', 'Shipping', 'Payment' ],

            // Guest checkout
            'guest_checkout_enabled'     => true,
            'guest_checkout_label'       => 'Continue as guest',

            // Custom CSS
            'custom_css'                 => '',
        ];
    }

    // ── Get all settings (merged with defaults) ───────────────────────────────
    public static function all() {
        if ( empty( self::$cache ) ) {
            $saved        = get_option( self::$option_key, [] );
            self::$cache  = wp_parse_args( $saved, self::defaults() );
        }
        return self::$cache;
    }

    // ── Get single setting ────────────────────────────────────────────────────
    public static function get( $key, $default = null ) {
        $all = self::all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    // ── Save all settings ─────────────────────────────────────────────────────
    public static function save( $data ) {
        $clean        = self::sanitize( $data );
        self::$cache  = [];                          // bust cache
        return update_option( self::$option_key, $clean );
    }

    // ── Sanitize incoming data ────────────────────────────────────────────────
    public static function sanitize( $data ) {
        $defaults = self::defaults();
        $clean    = [];

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $data[ $key ] ) ) {
                // Checkboxes that are unchecked won't be in POST
                $clean[ $key ] = is_bool( $default ) ? false : $default;
                continue;
            }

            $value = $data[ $key ];

            if ( is_bool( $default ) ) {
                $clean[ $key ] = (bool) $value;
            } elseif ( is_int( $default ) ) {
                $clean[ $key ] = absint( $value );
            } elseif ( is_array( $default ) ) {
                $clean[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default;
            } elseif ( $key === 'custom_css' ) {
                $clean[ $key ] = wp_strip_all_tags( $value );
            } elseif ( $key === 'logo_url' || $key === 'custom_font_url' ) {
                $clean[ $key ] = esc_url_raw( $value );
            } elseif ( strpos( $key, '_color' ) !== false ) {
                $clean[ $key ] = sanitize_hex_color( $value ) ?? $default;
            } else {
                $clean[ $key ] = sanitize_text_field( $value );
            }
        }

        return $clean;
    }
}
