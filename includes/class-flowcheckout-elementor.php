<?php
/**
 * FlowCheckout — Elementor Integration
 * Registers the FlowCheckout widget category and bootstraps widgets.
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

class FlowCheckout_Elementor {

    public static function init() {
        $self = new self();
        add_action( 'elementor/widgets/register',   [ $self, 'register_widgets' ] );
        add_action( 'elementor/elements/categories_registered', [ $self, 'register_category' ] );
        add_action( 'elementor/editor/enqueue_scripts', [ $self, 'editor_assets' ] );
    }

    public function register_category( $manager ) {
        $manager->add_category( 'flowcheckout', [
            'title' => __( 'FlowCheckout', 'flowcheckout' ),
            'icon'  => 'eicon-checkout',
        ] );
    }

    public function register_widgets( $manager ) {
        $manager->register( new FlowCheckout_Elementor_Widget() );
    }

    public function editor_assets() {
        wp_enqueue_style(
            'flowcheckout-elementor-editor',
            FLOWCHECKOUT_ASSETS . 'css/flowcheckout-checkout.css',
            [],
            FLOWCHECKOUT_VERSION
        );
    }
}

// Auto-init when Elementor is available
add_action( 'elementor/loaded', function () {
    FlowCheckout_Elementor::init();
} );
