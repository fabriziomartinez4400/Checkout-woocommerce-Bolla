<?php
/**
 * FlowCheckout — Admin
 * WordPress admin settings page with tabbed navigation,
 * live preview links, and AJAX save.
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Admin {

    // ── Init ──────────────────────────────────────────────────────────────────
    public static function init() {
        $self = new self();

        add_action( 'admin_menu',            [ $self, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $self, 'enqueue_assets' ] );
        add_action( 'wp_ajax_flowcheckout_save_settings', [ $self, 'ajax_save_settings' ] );
    }

    // ── Register menu ─────────────────────────────────────────────────────────
    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'FlowCheckout', 'flowcheckout' ),
            __( 'FlowCheckout', 'flowcheckout' ),
            'manage_woocommerce',
            'flowcheckout',
            [ $this, 'render_page' ]
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────
    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_flowcheckout' !== $hook ) return;

        wp_enqueue_style(
            'flowcheckout-admin',
            FLOWCHECKOUT_ASSETS . 'css/flowcheckout-admin.css',
            [ 'wp-color-picker' ],
            FLOWCHECKOUT_VERSION
        );

        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_script(
            'flowcheckout-admin',
            FLOWCHECKOUT_ASSETS . 'js/flowcheckout-admin.js',
            [ 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ],
            FLOWCHECKOUT_VERSION,
            true
        );

        wp_localize_script( 'flowcheckout-admin', 'flowcheckoutAdmin', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( FlowCheckout_Helpers::nonce_action() ),
            'checkoutUrl'  => wc_get_checkout_url(),
            'settings'     => FlowCheckout_Settings::all(),
            'fields'       => FlowCheckout_Fields::get_config(),
            'i18n'         => [
                'saving'     => __( 'Saving…',         'flowcheckout' ),
                'saved'      => __( 'Settings saved!', 'flowcheckout' ),
                'saveError'  => __( 'Error saving settings.', 'flowcheckout' ),
                'confirmReset' => __( 'Reset all settings to defaults?', 'flowcheckout' ),
            ],
        ] );
    }

    // ── AJAX: Save settings ───────────────────────────────────────────────────
    public function ajax_save_settings() {
        check_ajax_referer( FlowCheckout_Helpers::nonce_action(), 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'flowcheckout' ) ] );
        }

        $raw = isset( $_POST['settings'] ) ? $_POST['settings'] : '';

        // Settings come as JSON from the JS admin UI
        $data = json_decode( wp_unslash( $raw ), true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'flowcheckout' ) ] );
        }

        // Handle checkbox booleans properly (JSON sends true/false)
        FlowCheckout_Settings::save( $data );

        wp_send_json_success( [
            'message'  => __( 'Settings saved successfully.', 'flowcheckout' ),
            'settings' => FlowCheckout_Settings::all(),
        ] );
    }

    // ── Render admin page ─────────────────────────────────────────────────────
    public function render_page() {
        $settings = FlowCheckout_Settings::all();
        $tabs     = $this->get_tabs();
        $active   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap fc-admin" id="flowcheckout-admin">

            <!-- Header -->
            <div class="fc-admin__header">
                <div class="fc-admin__header-inner">
                    <div class="fc-admin__logo">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                            <rect width="28" height="28" rx="7" fill="#2563EB"/>
                            <path d="M7 14l5 5 9-9" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>FlowCheckout</span>
                        <span class="fc-admin__version">v<?php echo FLOWCHECKOUT_VERSION; ?></span>
                    </div>
                    <div class="fc-admin__header-actions">
                        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" target="_blank" class="fc-btn fc-btn--ghost">
                            Preview Checkout ↗
                        </a>
                        <button id="fc-save-btn" class="fc-btn fc-btn--primary">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notice area -->
            <div id="fc-notice" class="fc-notice" style="display:none"></div>

            <!-- Main layout -->
            <div class="fc-admin__body">

                <!-- Sidebar nav -->
                <nav class="fc-admin__nav">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <a href="#fc-tab-<?php echo esc_attr( $slug ); ?>"
                           class="fc-admin__nav-item <?php echo $slug === $active ? 'is-active' : ''; ?>"
                           data-tab="<?php echo esc_attr( $slug ); ?>">
                            <span class="fc-admin__nav-icon"><?php echo $tab['icon']; ?></span>
                            <span><?php echo esc_html( $tab['label'] ); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Tab panels -->
                <div class="fc-admin__content">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <div id="fc-tab-<?php echo esc_attr( $slug ); ?>"
                             class="fc-tab-panel <?php echo $slug === $active ? 'is-active' : ''; ?>">
                            <h2 class="fc-tab-panel__title"><?php echo esc_html( $tab['label'] ); ?></h2>
                            <?php $this->render_tab( $slug, $settings ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- .fc-admin__body -->
        </div><!-- .fc-admin -->
        <?php
    }

    // ── Tab list ──────────────────────────────────────────────────────────────
    private function get_tabs() {
        return [
            'general'       => [ 'label' => 'General',        'icon' => '⚙️' ],
            'design'        => [ 'label' => 'Design & Brand',  'icon' => '🎨' ],
            'express'       => [ 'label' => 'Express Checkout','icon' => '⚡' ],
            'fields'        => [ 'label' => 'Form Fields',     'icon' => '📋' ],
            'order-summary' => [ 'label' => 'Order Summary',   'icon' => '🧾' ],
            'trust'         => [ 'label' => 'Trust Badges',    'icon' => '🛡️' ],
            'advanced'      => [ 'label' => 'Advanced / CSS',  'icon' => '💻' ],
        ];
    }

    // ── Render each tab ───────────────────────────────────────────────────────
    private function render_tab( $tab, $s ) {
        switch ( $tab ) {
            case 'general':        $this->tab_general( $s );        break;
            case 'design':         $this->tab_design( $s );         break;
            case 'express':        $this->tab_express( $s );        break;
            case 'fields':         $this->tab_fields();              break;
            case 'order-summary':  $this->tab_order_summary( $s );  break;
            case 'trust':          $this->tab_trust( $s );          break;
            case 'advanced':       $this->tab_advanced( $s );       break;
        }
    }

    // ── ── ── ── Tab: General ── ── ── ──
    private function tab_general( $s ) {
        ?>
        <div class="fc-fields">

            <?php $this->field_toggle( 'enabled', 'Enable FlowCheckout',
                'Replace the default WooCommerce checkout with FlowCheckout.',
                $s['enabled'] ); ?>

            <?php $this->field_select( 'checkout_layout', 'Checkout Layout', [
                'two-column'    => 'Two-column (form left, order summary right) — Recommended',
                'single-column' => 'Single-column (stacked)',
            ], $s['checkout_layout'] ); ?>

            <?php $this->field_toggle( 'hide_header_footer', 'Hide site header & footer on checkout',
                'Removes distractions. Proven to increase completion rates.',
                $s['hide_header_footer'] ); ?>

            <?php $this->field_toggle( 'progress_bar_enabled', 'Show progress bar',
                'Displays a step indicator at the top of the checkout.',
                $s['progress_bar_enabled'] ); ?>

            <?php $this->field_toggle( 'guest_checkout_enabled', 'Prominent guest checkout',
                'Moves "Continue as guest" to the top, before the login form.',
                $s['guest_checkout_enabled'] ); ?>

            <?php $this->field_text( 'guest_checkout_label', 'Guest checkout label',
                $s['guest_checkout_label'], 'Continue as guest' ); ?>

        </div>
        <?php
    }

    // ── ── ── ── Tab: Design ── ── ── ──
    private function tab_design( $s ) {
        ?>
        <div class="fc-fields">
            <div class="fc-field-group-title">Branding</div>

            <?php $this->field_image( 'logo_url', 'Checkout logo', $s['logo_url'],
                'Displayed at the top of the checkout. Leave empty to use your site name.' ); ?>

            <?php $this->field_color( 'brand_color',  'Brand colour',  $s['brand_color'],  '#111827' ); ?>
            <?php $this->field_color( 'accent_color', 'Accent / button colour', $s['accent_color'], '#2563EB' ); ?>

            <div class="fc-field-group-title" style="margin-top:24px">Shapes & Radius</div>

            <?php $this->field_range( 'button_radius', 'Button border radius (px)', $s['button_radius'], 0, 32 ); ?>
            <?php $this->field_range( 'field_radius',  'Input field border radius (px)', $s['field_radius'], 0, 24 ); ?>

            <div class="fc-field-group-title" style="margin-top:24px">Typography</div>

            <?php $this->field_select( 'font_family', 'Font family', [
                'system'  => 'System default (inherits theme font)',
                'custom'  => 'Custom Google Font URL',
            ], $s['font_family'] ); ?>

            <div id="fc-custom-font-row" style="<?php echo 'custom' === $s['font_family'] ? '' : 'display:none'; ?>">
                <?php $this->field_text( 'custom_font_url', 'Google Fonts embed URL', $s['custom_font_url'],
                    'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap' ); ?>
            </div>
        </div>
        <?php
    }

    // ── ── ── ── Tab: Express Checkout ── ── ── ──
    private function tab_express( $s ) {
        ?>
        <div class="fc-fields">
            <div class="fc-callout fc-callout--info">
                <strong>How it works:</strong> FlowCheckout renders the express checkout button area
                and each active payment gateway (Stripe, WooPayments, PayPal, etc.) automatically
                injects its own Apple Pay / Google Pay buttons into this area.
            </div>

            <?php $this->field_toggle( 'express_checkout_enabled', 'Enable express checkout section',
                'Show Apple Pay, Google Pay, PayPal and other one-click payment buttons.',
                $s['express_checkout_enabled'] ); ?>

            <?php $this->field_select( 'express_checkout_position', 'Button position', [
                'above' => 'Above the contact form (recommended)',
                'below' => 'Below the contact form',
                'none'  => 'Hidden',
            ], $s['express_checkout_position'] ); ?>

            <?php $this->field_select( 'express_checkout_style', 'Button style', [
                'auto'  => 'Auto (adapts to checkout background)',
                'light' => 'Light',
                'dark'  => 'Dark',
            ], $s['express_checkout_style'] ); ?>

            <?php $this->field_text( 'express_checkout_label', 'Section label',
                $s['express_checkout_label'], 'Express checkout' ); ?>

            <?php $this->field_text( 'express_checkout_divider', 'Divider text',
                $s['express_checkout_divider'], 'Or continue with' ); ?>
        </div>
        <?php
    }

    // ── ── ── ── Tab: Fields ── ── ── ──
    private function tab_fields() {
        ?>
        <div class="fc-fields">
            <div class="fc-callout fc-callout--info">
                Drag rows to reorder fields. Toggle the eye icon to show/hide a field.
                Edit labels and mark fields as required or optional.
            </div>

            <div id="fc-field-editor">
                <div class="fc-field-editor__header">
                    <span>Field</span>
                    <span>Label</span>
                    <span>Width</span>
                    <span>Required</span>
                    <span>Visible</span>
                </div>
                <ul id="fc-field-list" class="fc-field-list">
                    <!-- Populated by JS from flowcheckoutAdmin.fields -->
                    <li class="fc-field-list__loading">Loading fields…</li>
                </ul>
            </div>

            <div style="margin-top:16px">
                <button type="button" id="fc-fields-reset" class="fc-btn fc-btn--ghost fc-btn--sm">
                    Reset to defaults
                </button>
            </div>
        </div>
        <?php
    }

    // ── ── ── ── Tab: Order Summary ── ── ── ──
    private function tab_order_summary( $s ) {
        ?>
        <div class="fc-fields">
            <?php $this->field_toggle( 'order_summary_sticky', 'Sticky order summary',
                'The order summary column stays visible as the user scrolls down the form.',
                $s['order_summary_sticky'] ); ?>

            <?php $this->field_toggle( 'show_product_thumbnails', 'Show product thumbnails',
                'Display small product images in the order summary.',
                $s['show_product_thumbnails'] ); ?>

            <?php $this->field_toggle( 'show_subtotals', 'Show line-item subtotals',
                'Show the price for each item individually.',
                $s['show_subtotals'] ); ?>

            <?php $this->field_toggle( 'collapsible_on_mobile', 'Collapsible on mobile',
                'On small screens, the order summary is collapsed by default with a toggle button.',
                $s['collapsible_on_mobile'] ); ?>
        </div>
        <?php
    }

    // ── ── ── ── Tab: Trust Badges ── ── ── ──
    private function tab_trust( $s ) {
        ?>
        <div class="fc-fields">
            <?php $this->field_toggle( 'trust_badges_enabled', 'Enable trust badges',
                'Display security, returns, and shipping badges below the place-order button.',
                $s['trust_badges_enabled'] ); ?>

            <?php $this->field_select( 'trust_badges_position', 'Badge position', [
                'below_button' => 'Below the place order button',
                'above_button' => 'Above the place order button',
                'sidebar'      => 'Inside order summary sidebar',
            ], $s['trust_badges_position'] ); ?>

            <div class="fc-field-group-title" style="margin-top:20px">Individual Badges</div>

            <?php $this->field_toggle( 'trust_badge_ssl', 'SSL / Secure payment badge', '', $s['trust_badge_ssl'] ); ?>
            <?php $this->field_text( 'trust_badge_ssl_text', 'SSL badge text', $s['trust_badge_ssl_text'], '256-bit SSL encryption' ); ?>

            <?php $this->field_toggle( 'trust_badge_returns', 'Returns badge', '', $s['trust_badge_returns'] ); ?>
            <?php $this->field_text( 'trust_badge_returns_text', 'Returns badge text', $s['trust_badge_returns_text'], '30-day free returns' ); ?>

            <?php $this->field_toggle( 'trust_badge_shipping', 'Shipping badge', '', $s['trust_badge_shipping'] ); ?>
            <?php $this->field_text( 'trust_badge_shipping_text', 'Shipping badge text', $s['trust_badge_shipping_text'], 'Free shipping over £50' ); ?>

            <?php $this->field_text( 'trust_badge_custom_text', 'Custom badge text (optional)', $s['trust_badge_custom_text'], '' ); ?>

            <?php $this->field_toggle( 'trust_badge_card_icons', 'Show card brand icons (Visa, MC, Amex…)',
                '', $s['trust_badge_card_icons'] ); ?>
        </div>
        <?php
    }

    // ── ── ── ── Tab: Advanced ── ── ── ──
    private function tab_advanced( $s ) {
        ?>
        <div class="fc-fields">
            <?php $this->field_toggle( 'remove_default_wc_styles', 'Remove default WooCommerce stylesheets',
                'Dequeues woocommerce-layout.css and woocommerce-smallscreen.css on checkout. Reduces CSS conflicts.',
                $s['remove_default_wc_styles'] ); ?>

            <div class="fc-field">
                <label class="fc-field__label" for="fc-custom-css">Custom CSS</label>
                <p class="fc-field__desc">Additional CSS injected into the checkout page <code>&lt;head&gt;</code>. Scoped to <code>.flowcheckout-active</code>.</p>
                <textarea id="fc-custom-css" name="custom_css" class="fc-textarea fc-textarea--code" rows="12"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
            </div>

            <div class="fc-field">
                <p class="fc-field__label">Danger zone</p>
                <button type="button" id="fc-reset-all" class="fc-btn fc-btn--danger fc-btn--sm">
                    Reset all settings to defaults
                </button>
            </div>
        </div>
        <?php
    }

    // ── ── ── ── Field helper renderers ── ── ── ──

    private function field_toggle( $key, $label, $desc, $value ) {
        ?>
        <div class="fc-field fc-field--toggle">
            <div class="fc-field__body">
                <label class="fc-field__label" for="fc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                <?php if ( $desc ) : ?><p class="fc-field__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            </div>
            <label class="fc-toggle">
                <input type="checkbox"
                       id="fc-<?php echo esc_attr( $key ); ?>"
                       name="<?php echo esc_attr( $key ); ?>"
                       data-setting="<?php echo esc_attr( $key ); ?>"
                       <?php checked( $value ); ?>>
                <span class="fc-toggle__track"></span>
            </label>
        </div>
        <?php
    }

    private function field_text( $key, $label, $value, $placeholder = '' ) {
        ?>
        <div class="fc-field">
            <label class="fc-field__label" for="fc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <input type="text"
                   id="fc-<?php echo esc_attr( $key ); ?>"
                   name="<?php echo esc_attr( $key ); ?>"
                   data-setting="<?php echo esc_attr( $key ); ?>"
                   value="<?php echo esc_attr( $value ); ?>"
                   placeholder="<?php echo esc_attr( $placeholder ); ?>"
                   class="fc-input">
        </div>
        <?php
    }

    private function field_select( $key, $label, $options, $value ) {
        ?>
        <div class="fc-field">
            <label class="fc-field__label" for="fc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <select id="fc-<?php echo esc_attr( $key ); ?>"
                    name="<?php echo esc_attr( $key ); ?>"
                    data-setting="<?php echo esc_attr( $key ); ?>"
                    class="fc-select">
                <?php foreach ( $options as $opt_val => $opt_label ) : ?>
                    <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>>
                        <?php echo esc_html( $opt_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    private function field_color( $key, $label, $value, $default ) {
        ?>
        <div class="fc-field fc-field--color">
            <label class="fc-field__label"><?php echo esc_html( $label ); ?></label>
            <div class="fc-color-wrap">
                <input type="text"
                       id="fc-<?php echo esc_attr( $key ); ?>"
                       name="<?php echo esc_attr( $key ); ?>"
                       data-setting="<?php echo esc_attr( $key ); ?>"
                       value="<?php echo esc_attr( $value ?: $default ); ?>"
                       class="fc-color-picker"
                       data-default-color="<?php echo esc_attr( $default ); ?>">
            </div>
        </div>
        <?php
    }

    private function field_range( $key, $label, $value, $min, $max ) {
        ?>
        <div class="fc-field fc-field--range">
            <label class="fc-field__label" for="fc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <div class="fc-range-wrap">
                <input type="range"
                       id="fc-<?php echo esc_attr( $key ); ?>"
                       name="<?php echo esc_attr( $key ); ?>"
                       data-setting="<?php echo esc_attr( $key ); ?>"
                       value="<?php echo esc_attr( $value ); ?>"
                       min="<?php echo $min; ?>"
                       max="<?php echo $max; ?>"
                       class="fc-range">
                <span class="fc-range__value"><?php echo esc_html( $value ); ?>px</span>
            </div>
        </div>
        <?php
    }

    private function field_image( $key, $label, $value, $desc = '' ) {
        ?>
        <div class="fc-field fc-field--image">
            <label class="fc-field__label"><?php echo esc_html( $label ); ?></label>
            <?php if ( $desc ) : ?><p class="fc-field__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
            <div class="fc-image-upload">
                <?php if ( $value ) : ?>
                    <img src="<?php echo esc_url( $value ); ?>" alt="" class="fc-image-upload__preview" style="max-height:60px;">
                <?php endif; ?>
                <input type="hidden"
                       id="fc-<?php echo esc_attr( $key ); ?>"
                       name="<?php echo esc_attr( $key ); ?>"
                       data-setting="<?php echo esc_attr( $key ); ?>"
                       value="<?php echo esc_url( $value ); ?>">
                <button type="button" class="fc-btn fc-btn--ghost fc-btn--sm fc-image-upload__btn" data-target="fc-<?php echo esc_attr( $key ); ?>">
                    <?php echo $value ? 'Change image' : 'Upload image'; ?>
                </button>
                <?php if ( $value ) : ?>
                    <button type="button" class="fc-btn fc-btn--ghost fc-btn--sm fc-image-upload__remove" data-target="fc-<?php echo esc_attr( $key ); ?>">
                        Remove
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
