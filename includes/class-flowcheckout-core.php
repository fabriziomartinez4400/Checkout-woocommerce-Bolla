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

        // Inject the totals fragment so WooCommerce AJAX (update_checkout) refreshes our custom totals.
        add_filter( 'woocommerce_update_order_review_fragments', array( $self, 'refresh_totals_fragment' ) );

        add_action( 'wp_ajax_flowcheckout_refresh_totals',        array( $self, 'ajax_refresh_totals' ) );
        add_action( 'wp_ajax_nopriv_flowcheckout_refresh_totals', array( $self, 'ajax_refresh_totals' ) );

        // ── Shipping location AJAX (used by cart-page widget) ─────────────────
        add_action( 'wp_ajax_flowcheckout_update_shipping_location',        array( $self, 'ajax_update_shipping_location' ) );
        add_action( 'wp_ajax_nopriv_flowcheckout_update_shipping_location', array( $self, 'ajax_update_shipping_location' ) );

        // ── Cart page shipping calculator widget ──────────────────────────────
        add_action( 'woocommerce_before_cart_totals', array( $self, 'render_cart_shipping_calc' ) );

        // ── Shortcode: [flowcheckout_shipping_calc] ───────────────────────────
        add_shortcode( 'flowcheckout_shipping_calc', array( $self, 'shipping_calc_shortcode' ) );
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

        $ship_country  = WC()->customer ? WC()->customer->get_billing_country() : '';

        wp_localize_script( 'flowcheckout-checkout', 'flowcheckoutData', array(
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'flowcheckout_frontend' ),
            'layout'            => FlowCheckout_Settings::get( 'checkout_layout', 'two-column' ),
            'stickyOrderSum'    => (bool) FlowCheckout_Settings::get( 'order_summary_sticky', true ),
            'collapsibleMobile' => (bool) FlowCheckout_Settings::get( 'collapsible_on_mobile', true ),
            'progressSteps'     => FlowCheckout_Settings::get( 'progress_bar_steps', array() ),
            'shippingDone'      => ! empty( $ship_country ),
            'i18n'              => array(
                'orderSummaryToggle'  => __( 'Order summary', 'flowcheckout' ),
                'processing'          => __( 'Processing…', 'flowcheckout' ),
                'fieldRequired'       => __( 'This field is required.', 'flowcheckout' ),
                'invalidEmail'        => __( 'Please enter a valid email address.', 'flowcheckout' ),
                'showSummary'         => __( 'Show order summary', 'flowcheckout' ),
                'hideSummary'         => __( 'Hide order summary', 'flowcheckout' ),
                'apply'               => __( 'Apply', 'flowcheckout' ),
                'calculate'           => __( 'Calculate', 'flowcheckout' ),
                'calculatingShipping' => __( 'Calculating shipping…', 'flowcheckout' ),
                'noShipping'          => __( 'Shipping', 'flowcheckout' ),
                'noShippingAvail'     => __( 'No shipping methods available for this location.', 'flowcheckout' ),
                'calcError'           => __( 'Could not calculate shipping. Please check your details.', 'flowcheckout' ),
                'change'              => __( 'Change', 'flowcheckout' ),
                'shipping'            => __( 'Shipping', 'flowcheckout' ),
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

    // ── AJAX: Refresh totals fragment ─────────────────────────────────────────
    // WooCommerce calls woocommerce_update_order_review_fragments after every
    // update_checkout AJAX call. We inject #fc-totals-fragment so our custom
    // order summary (which bypasses WC's default #order_review div) stays in sync
    // when shipping costs change, coupons are applied, etc.
    public function refresh_totals_fragment( $fragments ) {
        if ( ! FlowCheckout_Helpers::is_checkout() || ! FlowCheckout_Helpers::is_active() ) {
            return $fragments;
        }

        ob_start();
        ?>
        <div id="fc-totals-fragment" class="fc-order-summary__totals">
            <div class="fc-total-row">
                <span><?php esc_html_e( 'Subtotal', 'flowcheckout' ); ?></span>
                <span><?php wc_cart_totals_subtotal_html(); ?></span>
            </div>

            <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                <div class="fc-total-row fc-total-row--discount">
                    <span><?php echo esc_html( wc_cart_totals_coupon_label( $coupon, false ) ); ?></span>
                    <span class="fc-total-row__discount"><?php wc_cart_totals_coupon_html( $coupon ); ?></span>
                </div>
            <?php endforeach; ?>

            <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
                <div class="fc-total-row">
                    <span><?php echo esc_html( $fee->name ); ?></span>
                    <span><?php echo wc_price( $fee->total ); ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                <div class="fc-total-row">
                    <span><?php esc_html_e( 'Shipping', 'flowcheckout' ); ?></span>
                    <span><?php wc_cart_totals_shipping_html(); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
                <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
                    <div class="fc-total-row">
                        <span><?php echo esc_html( $tax->label ); ?></span>
                        <span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="fc-total-row fc-total-row--grand">
                <span><?php esc_html_e( 'Total', 'flowcheckout' ); ?></span>
                <span class="fc-grand-total"><?php wc_cart_totals_order_total_html(); ?></span>
            </div>
        </div>
        <?php
        $fragments['#fc-totals-fragment'] = ob_get_clean();

        return $fragments;
    }

    // ── AJAX: Full order review refresh (legacy / fallback) ───────────────────
    public function ajax_refresh_totals() {
        check_ajax_referer( 'flowcheckout_frontend', 'nonce' );
        ob_start();
        woocommerce_order_review();
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    // ── AJAX: Update shipping location (used by cart page widget) ─────────────
    public function ajax_update_shipping_location() {
        check_ajax_referer( 'flowcheckout_frontend', 'nonce' );

        $country  = sanitize_text_field( wp_unslash( $_POST['country']  ?? '' ) );
        $postcode = sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) );

        if ( empty( $country ) ) {
            wp_send_json_error( array( 'message' => __( 'Country is required.', 'flowcheckout' ) ) );
        }

        // Update WooCommerce customer session
        WC()->customer->set_billing_country( $country );
        WC()->customer->set_billing_postcode( $postcode );
        WC()->customer->set_shipping_country( $country );
        WC()->customer->set_shipping_postcode( $postcode );
        WC()->customer->save();

        // Recalculate cart & shipping
        WC()->cart->calculate_totals();

        $packages = WC()->shipping()->get_packages();
        $rates    = array();

        foreach ( $packages as $package ) {
            foreach ( $package['rates'] as $method ) {
                $rates[] = array(
                    'id'    => $method->id,
                    'label' => $method->get_label(),
                    'cost'  => wc_cart_totals_shipping_method_label( $method ),
                );
            }
        }

        $countries    = WC()->countries->get_countries();
        $country_name = $countries[ $country ] ?? $country;

        wp_send_json_success( array(
            'rates'    => $rates,
            'country'  => $country_name,
            'postcode' => $postcode,
        ) );
    }

    // ── Cart page shipping calculator widget ──────────────────────────────────
    public function render_cart_shipping_calc() {
        if ( ! is_cart() ) return;

        $ship_country  = WC()->customer ? WC()->customer->get_billing_country() : '';
        $ship_postcode = WC()->customer ? WC()->customer->get_billing_postcode() : '';

        echo $this->get_shipping_calc_html( $ship_country, $ship_postcode ); // phpcs:ignore
    }

    // ── Shortcode: [flowcheckout_shipping_calc] ───────────────────────────────
    public function shipping_calc_shortcode( $atts = array() ) {
        if ( ! function_exists( 'WC' ) ) return '';

        $ship_country  = WC()->customer ? WC()->customer->get_billing_country() : '';
        $ship_postcode = WC()->customer ? WC()->customer->get_billing_postcode() : '';

        return $this->get_shipping_calc_html( $ship_country, $ship_postcode );
    }

    // ── Shared HTML: shipping calculator widget ───────────────────────────────
    private function get_shipping_calc_html( $ship_country = '', $ship_postcode = '' ) {
        // Enqueue assets if not already done (e.g. on cart page or via shortcode)
        if ( ! wp_script_is( 'flowcheckout-checkout', 'enqueued' ) ) {
            wp_enqueue_style(
                'flowcheckout-checkout',
                FLOWCHECKOUT_ASSETS . 'css/flowcheckout-checkout.css',
                array( 'woocommerce-general' ),
                FLOWCHECKOUT_VERSION
            );
            wp_enqueue_script(
                'flowcheckout-checkout',
                FLOWCHECKOUT_ASSETS . 'js/flowcheckout-checkout.js',
                array( 'jquery' ),
                FLOWCHECKOUT_VERSION,
                true
            );
            wp_localize_script( 'flowcheckout-checkout', 'flowcheckoutData', array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'flowcheckout_frontend' ),
                'shippingDone' => ! empty( $ship_country ),
                'i18n'         => array(
                    'calculate'           => __( 'Calculate', 'flowcheckout' ),
                    'calculatingShipping' => __( 'Calculating shipping…', 'flowcheckout' ),
                    'noShippingAvail'     => __( 'No shipping methods available for this location.', 'flowcheckout' ),
                    'calcError'           => __( 'Could not calculate shipping.', 'flowcheckout' ),
                    'apply'               => __( 'Apply', 'flowcheckout' ),
                ),
            ) );
        }

        ob_start();
        ?>
        <div class="fc-ship-calc-widget">
            <h3 class="fc-ship-calc-widget__title"><?php esc_html_e( 'Calculate shipping', 'flowcheckout' ); ?></h3>
            <p class="fc-ship-calc-widget__desc">
                <?php esc_html_e( 'Enter your country and ZIP code to calculate shipping costs.', 'flowcheckout' ); ?>
            </p>
            <div class="fc-ship-calc-widget__row">
                <select class="fc-ship-calc-widget__select">
                    <option value=""><?php esc_html_e( 'Select country…', 'flowcheckout' ); ?></option>
                    <?php foreach ( WC()->countries->get_shipping_countries() as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"<?php selected( $code, $ship_country ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text"
                       class="fc-ship-calc-widget__postcode"
                       value="<?php echo esc_attr( $ship_postcode ); ?>"
                       placeholder="<?php esc_attr_e( 'ZIP / Postcode', 'flowcheckout' ); ?>">
                <button type="button" class="fc-btn fc-btn--primary fc-btn--sm fc-ship-calc-widget__btn">
                    <?php esc_html_e( 'Calculate', 'flowcheckout' ); ?>
                </button>
            </div>
            <div class="fc-ship-calc-widget__result" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
