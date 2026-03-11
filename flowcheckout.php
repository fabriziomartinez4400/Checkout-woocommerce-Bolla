<?php
/**
 * Plugin Name:       FlowCheckout — Custom WooCommerce Checkout
 * Plugin URI:        https://flowtiva.com/flowcheckout
 * Description:       A high-converting, fully customisable WooCommerce checkout page with Elementor integration, express payment buttons, trust badges, sticky order summary, and a drag-and-drop field editor.
 * Version:           1.0.4
 * Author:            Flowtiva
 * Author URI:        https://flowtiva.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flowcheckout
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'FLOWCHECKOUT_VERSION',   '1.0.4' );
define( 'FLOWCHECKOUT_FILE',      __FILE__ );
define( 'FLOWCHECKOUT_DIR',       plugin_dir_path( __FILE__ ) );
define( 'FLOWCHECKOUT_URL',       plugin_dir_url( __FILE__ ) );
define( 'FLOWCHECKOUT_ASSETS',    FLOWCHECKOUT_URL . 'assets/' );
define( 'FLOWCHECKOUT_TEMPLATES', FLOWCHECKOUT_DIR . 'templates/' );

// ─── Load includes early so activation hook can use the classes ───────────────
// (activation runs before plugins_loaded, so we need files available immediately)
require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-helpers.php';
require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-settings.php';
require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-core.php';
require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-admin.php';
require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-fields.php';

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__,   array( 'FlowCheckout', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FlowCheckout', 'deactivate' ) );

// ─── WooCommerce HPOS Compatibility ──────────────────────────────────────────
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

// ─── Bootstrap ───────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', array( 'FlowCheckout', 'init' ), 20 );

/**
 * Main plugin class — acts as a service locator / bootstrapper.
 * Compatible with PHP 7.2+.
 */
final class FlowCheckout {

    /** @var FlowCheckout|null Singleton instance */
    private static $instance = null;

    /** Private constructor — use ::init() */
    private function __construct() {}

    // ── Singleton ─────────────────────────────────────────────────────────────
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Init (called on plugins_loaded) ───────────────────────────────────────
    public static function init() {
        if ( ! self::woocommerce_active() ) {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
            return;
        }

        $plugin = self::instance();
        $plugin->load_textdomain();
        $plugin->load_elementor();
        $plugin->init_components();
    }

    // ── WooCommerce check ─────────────────────────────────────────────────────
    private static function woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    public static function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>'
           . esc_html__( 'FlowCheckout requires WooCommerce to be installed and active.', 'flowcheckout' )
           . '</p></div>';
    }

    // ── Text domain ───────────────────────────────────────────────────────────
    private function load_textdomain() {
        load_plugin_textdomain(
            'flowcheckout',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }

    // ── Elementor (loaded on its own hook) ────────────────────────────────────
    private function load_elementor() {
        add_action( 'elementor/loaded', function () {
            require_once FLOWCHECKOUT_DIR . 'elementor/widgets/checkout-widget.php';
            require_once FLOWCHECKOUT_DIR . 'includes/class-flowcheckout-elementor.php';
        } );
    }

    // ── Boot components ───────────────────────────────────────────────────────
    private function init_components() {
        FlowCheckout_Settings::init();
        FlowCheckout_Core::init();
        FlowCheckout_Admin::init();
        FlowCheckout_Fields::init();
    }

    // ── Activation ────────────────────────────────────────────────────────────
    public static function activate() {
        if ( false === get_option( 'flowcheckout_settings' ) ) {
            update_option( 'flowcheckout_settings', FlowCheckout_Settings::defaults() );
        }
        flush_rewrite_rules();
    }

    // ── Deactivation ──────────────────────────────────────────────────────────
    public static function deactivate() {
        flush_rewrite_rules();
    }

    // ── Helper: get setting ───────────────────────────────────────────────────
    public static function get( $key, $default = null ) {
        return FlowCheckout_Settings::get( $key, $default );
    }
}
