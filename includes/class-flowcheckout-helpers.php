<?php
/**
 * FlowCheckout — Helpers
 * Static utility methods used across the plugin.
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Helpers {

    /**
     * Check whether the current page is the WooCommerce checkout page.
     */
    public static function is_checkout() {
        return function_exists( 'is_checkout' ) && is_checkout();
    }

    /**
     * Check whether FlowCheckout is active (enabled in settings).
     */
    public static function is_active() {
        return (bool) FlowCheckout_Settings::get( 'enabled', true );
    }

    /**
     * Render inline SVG icon by name.
     * Icons: lock, shield, truck, arrow-right, check, refresh
     */
    public static function icon( $name, $class = '' ) {
        $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

        $icons = [
            'lock'        => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'shield'      => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'truck'       => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
            'refresh'     => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
            'check'       => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            'arrow-right' => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
            'chevron-down'=> '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
            'bag'         => '<svg' . $class_attr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        ];

        return $icons[ $name ] ?? '';
    }

    /**
     * Generate dynamic inline CSS variables from settings.
     */
    public static function css_variables() {
        $accent  = FlowCheckout_Settings::get( 'accent_color', '#2563EB' );
        $brand   = FlowCheckout_Settings::get( 'brand_color',  '#111827' );
        $btn_r   = (int) FlowCheckout_Settings::get( 'button_radius', 10 );
        $fld_r   = (int) FlowCheckout_Settings::get( 'field_radius',  8  );

        // Derive hover colour (darken accent slightly)
        $accent_hover = self::adjust_brightness( $accent, -20 );

        return sprintf(
            ':root{--fc-accent:%s;--fc-accent-hover:%s;--fc-brand:%s;--fc-btn-radius:%dpx;--fc-field-radius:%dpx;}',
            esc_attr( $accent ),
            esc_attr( $accent_hover ),
            esc_attr( $brand ),
            $btn_r,
            $fld_r
        );
    }

    /**
     * Lighten or darken a hex colour by an amount (-255 to +255).
     */
    public static function adjust_brightness( $hex, $steps ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $steps ) );
        $g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $steps ) );
        $b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $steps ) );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Return card brand icons HTML (Visa, MC, Amex, etc.)
     */
    public static function card_icons_html() {
        $cards = [
            'visa'       => 'Visa',
            'mastercard' => 'MC',
            'amex'       => 'Amex',
            'paypal'     => 'PayPal',
            'applepay'   => '⬛ Pay',
            'googlepay'  => 'G Pay',
        ];
        $html = '<div class="fc-card-icons">';
        foreach ( $cards as $slug => $label ) {
            $html .= '<span class="fc-card-icon fc-card-icon--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Nonce action name for admin AJAX calls.
     */
    public static function nonce_action() {
        return 'flowcheckout_admin_nonce';
    }
}
