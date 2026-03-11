<?php
/**
 * FlowCheckout — Elementor Widget
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class FlowCheckout_Elementor_Widget extends Widget_Base {

    public function get_name()  { return 'flowcheckout'; }
    public function get_title() { return __( 'FlowCheckout', 'flowcheckout' ); }
    public function get_icon()  { return 'eicon-checkout'; }

    public function get_categories() {
        return array( 'flowcheckout', 'woocommerce-elements' );
    }

    public function get_keywords() {
        return array( 'checkout', 'woocommerce', 'payment', 'order', 'cart' );
    }

    protected function register_controls() {

        // ── Layout ────────────────────────────────────────────────────────────
        $this->start_controls_section( 'section_layout', array(
            'label' => __( 'Layout', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'checkout_layout', array(
            'label'   => __( 'Layout', 'flowcheckout' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'two-column',
            'options' => array(
                'two-column'    => __( 'Two-column (recommended)', 'flowcheckout' ),
                'single-column' => __( 'Single column', 'flowcheckout' ),
            ),
        ) );

        $this->add_control( 'hide_header_footer', array(
            'label'        => __( 'Hide site header & footer', 'flowcheckout' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'label_on'     => __( 'Yes', 'flowcheckout' ),
            'label_off'    => __( 'No', 'flowcheckout' ),
            'return_value' => 'yes',
        ) );

        $this->add_control( 'progress_bar_enabled', array(
            'label'        => __( 'Show progress bar', 'flowcheckout' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'label_on'     => __( 'Yes', 'flowcheckout' ),
            'label_off'    => __( 'No', 'flowcheckout' ),
            'return_value' => 'yes',
        ) );

        $this->end_controls_section();

        // ── Express Checkout ──────────────────────────────────────────────────
        $this->start_controls_section( 'section_express', array(
            'label' => __( 'Express Checkout', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'express_checkout_enabled', array(
            'label'        => __( 'Show express buttons', 'flowcheckout' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ) );

        $this->add_control( 'express_checkout_label', array(
            'label'     => __( 'Section label', 'flowcheckout' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Express checkout', 'flowcheckout' ),
            'condition' => array( 'express_checkout_enabled' => 'yes' ),
        ) );

        $this->add_control( 'express_checkout_divider', array(
            'label'     => __( 'Divider text', 'flowcheckout' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Or continue with', 'flowcheckout' ),
            'condition' => array( 'express_checkout_enabled' => 'yes' ),
        ) );

        $this->end_controls_section();

        // ── Trust Badges ──────────────────────────────────────────────────────
        $this->start_controls_section( 'section_trust', array(
            'label' => __( 'Trust Badges', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'trust_badges_enabled', array(
            'label'        => __( 'Show trust badges', 'flowcheckout' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ) );

        $this->add_control( 'trust_badge_ssl_text', array(
            'label'     => __( 'SSL badge text', 'flowcheckout' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( '256-bit SSL encryption', 'flowcheckout' ),
            'condition' => array( 'trust_badges_enabled' => 'yes' ),
        ) );

        $this->add_control( 'trust_badge_returns_text', array(
            'label'     => __( 'Returns badge text', 'flowcheckout' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( '30-day free returns', 'flowcheckout' ),
            'condition' => array( 'trust_badges_enabled' => 'yes' ),
        ) );

        $this->add_control( 'trust_badge_shipping_text', array(
            'label'     => __( 'Shipping badge text', 'flowcheckout' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Free shipping over £50', 'flowcheckout' ),
            'condition' => array( 'trust_badges_enabled' => 'yes' ),
        ) );

        $this->end_controls_section();

        // ── Style: Colours ────────────────────────────────────────────────────
        $this->start_controls_section( 'style_colors', array(
            'label' => __( 'Colours', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'accent_color', array(
            'label'     => __( 'Accent / button colour', 'flowcheckout' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#2563EB',
            'selectors' => array(
                '{{WRAPPER}} .fc-checkout' => '--fc-accent: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'brand_color', array(
            'label'     => __( 'Brand colour', 'flowcheckout' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array(
                '{{WRAPPER}} .fc-checkout' => '--fc-brand: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        // ── Style: Fields ─────────────────────────────────────────────────────
        $this->start_controls_section( 'style_fields', array(
            'label' => __( 'Form Fields', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'field_radius', array(
            'label'     => __( 'Field border radius', 'flowcheckout' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
            'default'   => array( 'size' => 8, 'unit' => 'px' ),
            'selectors' => array(
                '{{WRAPPER}} .fc-checkout' => '--fc-field-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'field_border',
            'label'    => __( 'Field border', 'flowcheckout' ),
            'selector' => '{{WRAPPER}} .fc-field-input, {{WRAPPER}} .fc-field-select',
        ) );

        $this->end_controls_section();

        // ── Style: Button ─────────────────────────────────────────────────────
        $this->start_controls_section( 'style_button', array(
            'label' => __( 'Place Order Button', 'flowcheckout' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'button_radius', array(
            'label'     => __( 'Button border radius', 'flowcheckout' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
            'default'   => array( 'size' => 10, 'unit' => 'px' ),
            'selectors' => array(
                '{{WRAPPER}} .fc-checkout' => '--fc-btn-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'button_typography',
            'label'    => __( 'Button typography', 'flowcheckout' ),
            'selector' => '{{WRAPPER}} .fc-place-order',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'button_box_shadow',
            'selector' => '{{WRAPPER}} .fc-place-order',
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $overrides = array(
            'checkout_layout'           => isset( $settings['checkout_layout'] ) ? $settings['checkout_layout'] : null,
            'express_checkout_enabled'  => ! empty( $settings['express_checkout_enabled'] ),
            'express_checkout_label'    => isset( $settings['express_checkout_label'] ) ? $settings['express_checkout_label'] : null,
            'express_checkout_divider'  => isset( $settings['express_checkout_divider'] ) ? $settings['express_checkout_divider'] : null,
            'trust_badges_enabled'      => ! empty( $settings['trust_badges_enabled'] ),
            'trust_badge_ssl_text'      => isset( $settings['trust_badge_ssl_text'] ) ? $settings['trust_badge_ssl_text'] : null,
            'trust_badge_returns_text'  => isset( $settings['trust_badge_returns_text'] ) ? $settings['trust_badge_returns_text'] : null,
            'trust_badge_shipping_text' => isset( $settings['trust_badge_shipping_text'] ) ? $settings['trust_badge_shipping_text'] : null,
            'progress_bar_enabled'      => ! empty( $settings['progress_bar_enabled'] ),
        );

        add_filter( 'flowcheckout_setting', function( $value, $key ) use ( $overrides ) {
            return ( isset( $overrides[ $key ] ) && $overrides[ $key ] !== null ) ? $overrides[ $key ] : $value;
        }, 10, 2 );

        echo '<div class="fc-elementor-wrapper">';

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            echo '<p class="fc-elementor-placeholder">'
               . esc_html__( 'The checkout will appear here on the front-end.', 'flowcheckout' )
               . '</p>';
        } else {
            $template = FLOWCHECKOUT_TEMPLATES . 'checkout/form-checkout.php';
            if ( file_exists( $template ) ) {
                wc_get_template( 'checkout/form-checkout.php', array(), '', FLOWCHECKOUT_TEMPLATES );
            }
        }

        echo '</div>';
    }
}
