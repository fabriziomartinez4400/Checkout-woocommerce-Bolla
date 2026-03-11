<?php
/**
 * FlowCheckout — Core
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Core {

    public static function init() {
        $self = new self();

        // Priority 1 = beats theme/child-theme overrides
        add_filter( 'woocommerce_locate_template',      array( $self, 'override_checkout_template' ), 1, 3 );
        add_filter( 'woocommerce_locate_core_template', array( $self, 'override_checkout_template' ), 1, 3 );

        add_action( 'wp_enqueue_scripts', array( $self, 'enqueue_assets' ) );
        add_action( 'wp_head',            array( $self, 'inject_dynamic_styles' ), 99 );
        add_action( 'wp',                 array( $self, 'maybe_hide_header_footer' ) );
        add_filter( 'body_class',         array( $self, 'add_body_classes' ) );

        add_action( 'flowcheckout_after_place_order_button', array( $self, 'render_trust_badges' ) );
        add_action( 'flowcheckout_before_contact_fields',    array( $self, 'render_express_checkout' ) );

        add_action( 'wp_ajax_flowcheckout_refresh_totals',        array( $self, 'ajax_refresh_totals' ) );
        add_action( 'wp_ajax_nopriv_flowcheckout_refresh_totals', array( $self, 'ajax_refresh_totals' ) );
    }

    // ── Template Override ─────────────────────────────────────────────────────
    public function override_checkout_template( $template, $template_name, $template_path ) {
        if ( ! FlowCheckout_Helpers::is_active() ) {
            return $template;
        }

        if ( 'checkout/form-checkout.php' !== $template_name ) {
            return $template;
        }

        $plugin_template = FLOWCHECKOUT_TEMPLATES . 'checkout/form-checkout.php';

        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return $template;
    }

    // ── Assets ────────────────────────────────────────────────────────────────
    public function enqueue_assets() {
        if ( ! FlowCheckout_Helpers::is_checkout() || ! FlowCheckout_Helpers::is_active() ) {
            return;
        }

        wp_enqueue_style(
            'flowcheckout-checkout',
            FLOWCHECKOUT_ASSETS . 'css/flowcheckout-checkout.css',
            array( 'woocommerce-general' ),
            FLOWCHECKOUT_VERSION
        );

        if ( FlowCheckout_Settings::get( 'remove_default_wc_styles' ) ) {
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
        }

        wp_enqueue_script(
            'flowcheckout-checkout',
            FLOWCHECKOUT_ASSETS . 'js/flowcheckout-checkout.js',
            array( 'jquery', 'wc-checkout' ),
            FLOWCHECKOUT_VERSION,
            true
        );

        wp_localize_script( 'flowcheckout-checkout', 'flowcheckoutData', array(
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'flowcheckout_frontend' ),
            'layout'            => FlowCheckout_Settings::get( 'checkout_layout', 'two-column' ),
            'stickyOrderSum'    => (bool) FlowCheckout_Settings::get( 'order_summary_sticky', true ),
            'collapsibleMobile' => (bool) FlowCheckout_Settings::get( 'collapsible_on_mobile', true ),
            'progressSteps'     => FlowCheckout_Settings::get( 'progress_bar_steps', array() ),
            'i18n'              => array(
                'orderSummaryToggle' => __( 'Order summary', 'flowcheckout' ),
                'processing'         => __( 'Processing…', 'flowcheckout' ),
                'fieldRequired'      => __( 'This field is required.', 'flowcheckout' ),
                'invalidEmail'       => __( 'Please enter a valid email address.', 'flowcheckout' ),
                'showSummary'        => __( 'Show order summary', 'flowcheckout' ),
                'hideSummary'        => __( 'Hide order summary', 'flowcheckout' ),
            ),
        ) );
    }

    // ── Dynamic Styles ────────────────────────────────────────────────────────
    public function inject_dynamic_styles() {
        if ( ! FlowCheckout_Helpers::is_checkout() || ! FlowCheckout_Helpers::is_active() ) {
            return;
        }

        echo '<style id="flowcheckout-vars">' . FlowCheckout_Helpers::css_variables() . '</style>' . "\n";

        $custom_css = FlowCheckout_Settings::get( 'custom_css', '' );
        if ( $custom_css ) {
            echo '<style id="flowcheckout-custom">' . wp_strip_all_tags( $custom_css ) . '</style>' . "\n";
        }
    }

    // ── Hide Header / Footer ──────────────────────────────────────────────────
    public function maybe_hide_header_footer() {
        if ( ! FlowCheckout_Helpers::is_checkout() ) return;
        if ( ! FlowCheckout_Settings::get( 'hide_header_footer', true ) ) return;

        add_filter( 'body_class', function( $classes ) {
            $classes[] = 'flowcheckout-hide-chrome';
            return $classes;
        } );
    }

    // ── Body Classes ──────────────────────────────────────────────────────────
    public function add_body_classes( $classes ) {
        if ( FlowCheckout_Helpers::is_checkout() && FlowCheckout_Helpers::is_active() ) {
            $classes[] = 'flowcheckout-active';
            $classes[] = 'flowcheckout-layout--' . FlowCheckout_Settings::get( 'checkout_layout', 'two-column' );
        }
        return $classes;
    }

    // ── Trust Badges ──────────────────────────────────────────────────────────
    public function render_trust_badges() {
        if ( ! FlowCheckout_Settings::get( 'trust_badges_enabled', true ) ) return;

        $badges = array();

        if ( FlowCheckout_Settings::get( 'trust_badge_ssl', true ) ) {
            $badges[] = array(
                'icon' => 'lock',
                'text' => FlowCheckout_Settings::get( 'trust_badge_ssl_text', '256-bit SSL encryption' ),
            );
        }
        if ( FlowCheckout_Settings::get( 'trust_badge_returns', true ) ) {
            $badges[] = array(
                'icon' => 'refresh',
                'text' => FlowCheckout_Settings::get( 'trust_badge_returns_text', '30-day free returns' ),
            );
        }
        if ( FlowCheckout_Settings::get( 'trust_badge_shipping', true ) ) {
            $badges[] = array(
                'icon' => 'truck',
                'text' => FlowCheckout_Settings::get( 'trust_badge_shipping_text', 'Free shipping over £50' ),
            );
        }

        $custom = FlowCheckout_Settings::get( 'trust_badge_custom_text', '' );
        if ( $custom ) {
            $badges[] = array( 'icon' => 'shield', 'text' => $custom );
        }

        if ( empty( $badges ) ) return;

        echo '<div class="fc-trust-badges">';
        foreach ( $badges as $badge ) {
            echo '<div class="fc-trust-badge">';
            echo FlowCheckout_Helpers::icon( $badge['icon'], 'fc-trust-badge__icon' );
            echo '<span class="fc-trust-badge__text">' . esc_html( $badge['text'] ) . '</span>';
            echo '</div>';
        }
        if ( FlowCheckout_Settings::get( 'trust_badge_card_icons', true ) ) {
            echo FlowCheckout_Helpers::card_icons_html();
        }
        echo '</div>';
    }

    // ── Express Checkout ──────────────────────────────────────────────────────
    public function render_express_checkout() {
        if ( ! FlowCheckout_Settings::get( 'express_checkout_enabled', true ) ) return;

        $position = FlowCheckout_Settings::get( 'express_checkout_position', 'above' );
        if ( 'none' === $position ) return;

        $label   = FlowCheckout_Settings::get( 'express_checkout_label', 'Express checkout' );
        $divider = FlowCheckout_Settings::get( 'express_checkout_divider', 'Or continue with' );
        $style   = FlowCheckout_Settings::get( 'express_checkout_style', 'auto' );
        ?>
        <div class="fc-express-checkout" data-style="<?php echo esc_attr( $style ); ?>">
            <?php if ( $label ) : ?>
                <p class="fc-express-checkout__label"><?php echo esc_html( $label ); ?></p>
            <?php endif; ?>
            <div class="fc-express-checkout__buttons">
                <?php
                do_action( 'woocommerce_proceed_to_checkout' );
                do_action( 'flowcheckout_express_checkout_buttons' );
                ?>
            </div>
            <?php if ( $divider ) : ?>
                <div class="fc-express-checkout__divider">
                    <span><?php echo esc_html( $divider ); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── AJAX: Refresh totals ──────────────────────────────────────────────────
    public function ajax_refresh_totals() {
        check_ajax_referer( 'flowcheckout_frontend', 'nonce' );
        ob_start();
        woocommerce_order_review();
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }
}
