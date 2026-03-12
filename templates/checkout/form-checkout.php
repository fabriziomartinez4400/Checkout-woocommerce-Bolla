<?php
/**
 * FlowCheckout — Custom Checkout Template
 * Replaces WooCommerce's checkout/form-checkout.php
 *
 * @var WC_Checkout $checkout
 * @package FlowCheckout
 */

defined( 'ABSPATH' ) || exit;

$layout           = FlowCheckout_Settings::get( 'checkout_layout', 'two-column' );
$logo_url         = FlowCheckout_Settings::get( 'logo_url', '' );
$progress_enabled = FlowCheckout_Settings::get( 'progress_bar_enabled', true );
$progress_steps   = FlowCheckout_Settings::get( 'progress_bar_steps', array( 'Cart', 'Information', 'Shipping', 'Payment' ) );
$sticky_summary   = FlowCheckout_Settings::get( 'order_summary_sticky', true );
$collapsible_mob  = FlowCheckout_Settings::get( 'collapsible_on_mobile', true );
$show_thumbnails  = FlowCheckout_Settings::get( 'show_product_thumbnails', true );
$trust_position   = FlowCheckout_Settings::get( 'trust_badges_position', 'below_button' );
$ec_position      = FlowCheckout_Settings::get( 'express_checkout_position', 'above' );
$ec_enabled       = FlowCheckout_Settings::get( 'express_checkout_enabled', true );
$ec_label         = FlowCheckout_Settings::get( 'express_checkout_label', __( 'Express checkout', 'flowcheckout' ) );
$ec_divider       = FlowCheckout_Settings::get( 'express_checkout_divider', __( 'Or continue below', 'flowcheckout' ) );
$ec_style         = FlowCheckout_Settings::get( 'express_checkout_style', 'auto' );
?>
<div class="fc-checkout fc-checkout--<?php echo esc_attr( $layout ); ?>">

    <!-- ── HEADER ────────────────────────────────────────────────────────────── -->
    <header class="fc-checkout__header">
        <div class="fc-checkout__header-inner">

            <?php if ( $logo_url ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fc-checkout__logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?>">
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fc-checkout__site-name">
                    <?php bloginfo( 'name' ); ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="fc-checkout__cart-link">
                <?php echo FlowCheckout_Helpers::icon( 'bag', 'fc-icon' ); ?>
                <span><?php esc_html_e( 'Edit cart', 'flowcheckout' ); ?></span>
            </a>

        </div>

        <?php if ( $progress_enabled && ! empty( $progress_steps ) ) : ?>
            <div class="fc-progress">
                <div class="fc-progress__inner">
                    <?php
                    $total_steps = count( $progress_steps );
                    foreach ( $progress_steps as $index => $step ) :
                        if ( $index < $total_steps - 1 ) {
                            $step_class = 'fc-progress__step fc-progress__step--done';
                        } else {
                            $step_class = 'fc-progress__step fc-progress__step--active';
                        }
                    ?>
                        <div class="<?php echo esc_attr( $step_class ); ?>">
                            <span class="fc-progress__dot">
                                <?php if ( $index < $total_steps - 1 ) : ?>
                                    <?php echo FlowCheckout_Helpers::icon( 'check', 'fc-icon fc-icon--xs' ); ?>
                                <?php else : ?>
                                    <span class="fc-progress__num"><?php echo absint( $index + 1 ); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="fc-progress__label"><?php echo esc_html( $step ); ?></span>
                        </div>
                        <?php if ( $index < $total_steps - 1 ) : ?>
                            <div class="fc-progress__line"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <!-- ── MOBILE ORDER SUMMARY TOGGLE ───────────────────────────────────────── -->
    <?php if ( $collapsible_mob ) : ?>
        <div class="fc-mobile-summary-toggle" id="fc-mobile-summary-toggle">
            <button type="button"
                    class="fc-mobile-summary-toggle__btn"
                    aria-expanded="false"
                    aria-controls="fc-mobile-order-summary">
                <span class="fc-mobile-summary-toggle__left">
                    <?php echo FlowCheckout_Helpers::icon( 'bag', 'fc-icon' ); ?>
                    <span class="fc-mobile-summary-toggle__text"><?php esc_html_e( 'Show order summary', 'flowcheckout' ); ?></span>
                    <?php echo FlowCheckout_Helpers::icon( 'chevron-down', 'fc-icon fc-icon--toggle' ); ?>
                </span>
                <span class="fc-mobile-summary-toggle__total">
                    <?php wc_cart_totals_order_total_html(); ?>
                </span>
            </button>
            <div id="fc-mobile-order-summary" class="fc-mobile-summary-toggle__content" hidden>
                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── MAIN ──────────────────────────────────────────────────────────────── -->
    <div class="fc-checkout__main">

        <!-- ── LEFT: Form ────────────────────────────────────────────────────── -->
        <div class="fc-checkout__form-col">

            <?php do_action( 'woocommerce_before_checkout_form', $checkout ); ?>

            <form name="checkout"
                  id="fc-checkout-form"
                  class="fc-form woocommerce-checkout"
                  method="post"
                  enctype="multipart/form-data"
                  novalidate
                  action="<?php echo esc_url( wc_get_checkout_url() ); ?>">

                <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

                <!-- ── Contact ─────────────────────────────────────────────── -->
                <div class="fc-form__section" id="fc-section-contact">
                    <h2 class="fc-form__section-title"><?php esc_html_e( 'Contact information', 'flowcheckout' ); ?></h2>
                    <div class="fc-form__fields fc-form__fields--grid">
                        <?php
                        $billing_fields  = $checkout->get_checkout_fields( 'billing' );
                        $contact_keys    = array( 'billing_email', 'billing_phone' );
                        foreach ( $contact_keys as $key ) {
                            if ( isset( $billing_fields[ $key ] ) ) {
                                woocommerce_form_field( $key, $billing_fields[ $key ], $checkout->get_value( $key ) );
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- ── Shipping address ────────────────────────────────────── -->
                <div class="fc-form__section" id="fc-section-shipping">
                    <h2 class="fc-form__section-title"><?php esc_html_e( 'Shipping address', 'flowcheckout' ); ?></h2>
                    <div class="fc-form__fields fc-form__fields--grid">
                        <?php
                        $address_skip = array( 'billing_email', 'billing_phone' );
                        foreach ( $billing_fields as $key => $field ) {
                            if ( in_array( $key, $address_skip, true ) ) continue;
                            woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
                        }
                        ?>
                    </div>

                    <?php if ( WC()->cart->needs_shipping_address() ) : ?>
                        <?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>
                        <div class="fc-ship-different-wrap" style="margin-top:16px">
                            <?php
                            woocommerce_form_field( 'ship_to_different_address', array(
                                'type'  => 'checkbox',
                                'class' => array( 'form-row-wide', 'update_totals_on_change' ),
                                'label' => __( 'Ship to a different address?', 'flowcheckout' ),
                            ), $checkout->get_value( 'ship_to_different_address' ) );
                            ?>
                        </div>
                        <div class="fc-shipping-fields fc-form__fields fc-form__fields--grid" style="display:none;margin-top:14px">
                            <?php
                            $shipping_fields = $checkout->get_checkout_fields( 'shipping' );
                            foreach ( $shipping_fields as $key => $field ) {
                                woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
                            }
                            ?>
                        </div>
                        <?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>
                    <?php endif; ?>
                </div>

                <!-- ── Shipping method selector ────────────────────────────── -->
                <?php
                $shipping_packages    = WC()->shipping()->get_packages();
                $has_multiple_methods = false;
                foreach ( $shipping_packages as $pkg ) {
                    if ( count( $pkg['rates'] ) > 1 ) {
                        $has_multiple_methods = true;
                        break;
                    }
                }
                ?>
                <?php if ( WC()->cart->needs_shipping() && ! empty( $shipping_packages ) ) : ?>
                    <?php if ( $has_multiple_methods ) : ?>
                        <div class="fc-form__section" id="fc-section-shipping-methods">
                            <h2 class="fc-form__section-title"><?php esc_html_e( 'Shipping method', 'flowcheckout' ); ?></h2>
                            <?php foreach ( $shipping_packages as $package_index => $package ) :
                                $available_methods = $package['rates'];
                                if ( empty( $available_methods ) ) continue;
                                $chosen_method = isset( WC()->session->chosen_shipping_methods[ $package_index ] )
                                    ? WC()->session->chosen_shipping_methods[ $package_index ]
                                    : current( array_keys( $available_methods ) );
                            ?>
                            <div class="fc-shipping-methods" id="fc-shipping-methods-<?php echo absint( $package_index ); ?>">
                                <?php foreach ( $available_methods as $method ) :
                                    $is_selected = ( $method->id === $chosen_method );
                                    $input_id    = 'shipping_method_' . absint( $package_index ) . '_' . esc_attr( sanitize_title( $method->id ) );
                                ?>
                                <label class="fc-shipping-method <?php echo $is_selected ? 'fc-shipping-method--selected' : ''; ?>"
                                       for="<?php echo esc_attr( $input_id ); ?>">
                                    <input type="radio"
                                           id="<?php echo esc_attr( $input_id ); ?>"
                                           name="shipping_method[<?php echo absint( $package_index ); ?>]"
                                           data-index="<?php echo absint( $package_index ); ?>"
                                           value="<?php echo esc_attr( $method->id ); ?>"
                                           <?php checked( $method->id, $chosen_method ); ?>
                                           class="shipping_method" />
                                    <span class="fc-shipping-method__name"><?php echo esc_html( $method->get_label() ); ?></span>
                                    <span class="fc-shipping-method__cost"><?php echo wc_cart_totals_shipping_method_label( $method ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $shipping_packages as $package_index => $package ) :
                            $available_methods = $package['rates'];
                            if ( empty( $available_methods ) ) continue;
                            $method_id = current( array_keys( $available_methods ) );
                        ?>
                        <input type="hidden"
                               name="shipping_method[<?php echo absint( $package_index ); ?>]"
                               value="<?php echo esc_attr( $method_id ); ?>"
                               class="shipping_method"
                               data-index="<?php echo absint( $package_index ); ?>" />
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ── Additional fields ───────────────────────────────────── -->
                <?php $order_fields = $checkout->get_checkout_fields( 'order' ); ?>
                <?php if ( ! empty( $order_fields ) ) : ?>
                    <div class="fc-form__section" id="fc-section-notes">
                        <h2 class="fc-form__section-title"><?php esc_html_e( 'Additional information', 'flowcheckout' ); ?></h2>
                        <div class="fc-form__fields">
                            <?php foreach ( $order_fields as $key => $field ) :
                                woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
                            endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

                <!-- ── Payment ─────────────────────────────────────────────── -->
                <div class="fc-form__section fc-form__section--payment" id="fc-section-payment">
                    <h2 class="fc-form__section-title"><?php esc_html_e( 'Payment', 'flowcheckout' ); ?></h2>

                    <?php
                    // ── Shipping destination calculator (within payment section) ──────
                    $fc_ship_country  = WC()->customer ? WC()->customer->get_billing_country() : '';
                    $fc_ship_postcode = WC()->customer ? WC()->customer->get_billing_postcode() : '';
                    $fc_shipping_done = ! empty( $fc_ship_country );
                    ?>
                    <div class="fc-ship-calc-box" id="fc-ship-calc-box">
                        <div class="fc-ship-calc-box__header">
                            <span class="fc-ship-calc-box__label">
                                <?php esc_html_e( 'Shipping destination', 'flowcheckout' ); ?>
                            </span>
                            <?php if ( $fc_shipping_done ) : ?>
                                <span class="fc-ship-calc-box__current fc-ship-calc-box__current-info">
                                    <span class="fc-ship-calc-box__done-badge">
                                        ✓ <?php
                                            $countries = WC()->countries->get_countries();
                                            echo esc_html( $countries[ $fc_ship_country ] ?? $fc_ship_country );
                                            if ( $fc_ship_postcode ) echo ' &nbsp;' . esc_html( $fc_ship_postcode );
                                        ?>
                                    </span>
                                    <button type="button" class="fc-ship-calc-box__edit" id="fc-ship-calc-edit">
                                        <?php esc_html_e( 'Change', 'flowcheckout' ); ?>
                                    </button>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="fc-ship-calc-box__form" id="fc-ship-calc-form" <?php echo $fc_shipping_done ? 'hidden' : ''; ?>>
                            <p class="fc-ship-calc-box__desc">
                                <?php esc_html_e( 'Enter your country and ZIP code to see shipping costs and enable Google Pay / Apple Pay.', 'flowcheckout' ); ?>
                            </p>
                            <div class="fc-ship-calc-box__row">
                                <select id="fc-ship-country-calc" class="fc-ship-calc-box__select">
                                    <option value=""><?php esc_html_e( 'Select country…', 'flowcheckout' ); ?></option>
                                    <?php foreach ( WC()->countries->get_shipping_countries() as $code => $name ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>"<?php selected( $code, $fc_ship_country ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text"
                                       id="fc-ship-postcode-calc"
                                       class="fc-ship-calc-box__postcode"
                                       value="<?php echo esc_attr( $fc_ship_postcode ); ?>"
                                       placeholder="<?php esc_attr_e( 'ZIP / Postcode', 'flowcheckout' ); ?>">
                                <button type="button" id="fc-calc-ship-btn" class="fc-btn fc-btn--primary fc-btn--sm">
                                    <?php esc_html_e( 'Calculate', 'flowcheckout' ); ?>
                                </button>
                            </div>
                        </div>

                        <div id="fc-ship-calc-rates" <?php echo $fc_shipping_done ? '' : 'hidden'; ?>></div>
                    </div><!-- .fc-ship-calc-box -->

                    <!-- ── Express payment (Google Pay / Apple Pay) ────────────── -->
                    <!-- Shown after shipping is calculated; WooPayments renders here if configured -->
                    <div class="fc-express-wrap" id="fc-express-wrap" <?php echo $fc_shipping_done ? '' : 'style="display:none"'; ?>>
                        <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

                        <?php if ( $fc_shipping_done ) : ?>
                            <div class="fc-express-divider"><?php esc_html_e( 'Or pay with card', 'flowcheckout' ); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

                    <?php
                    // Renders payment method list + place order button.
                    woocommerce_checkout_payment();
                    ?>

                    <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

                    <!-- Trust badges (below or above button) -->
                    <?php if ( in_array( $trust_position, array( 'below_button', 'above_button' ), true ) ) : ?>
                        <?php do_action( 'flowcheckout_after_place_order_button' ); ?>
                    <?php endif; ?>
                </div>

            </form>

            <?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

        </div><!-- .fc-checkout__form-col -->

        <!-- ── RIGHT: Order Summary ──────────────────────────────────────────── -->
        <aside class="fc-checkout__summary-col <?php echo $sticky_summary ? 'fc-checkout__summary-col--sticky' : ''; ?>">
            <div class="fc-order-summary">

                <h2 class="fc-order-summary__title"><?php esc_html_e( 'Order summary', 'flowcheckout' ); ?></h2>

                <!-- Items -->
                <div class="fc-order-summary__items">
                    <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                        $product  = $cart_item['data'];
                        $quantity = $cart_item['quantity'];

                        if ( ! $product || ! $product->exists() || $quantity <= 0 ) continue;

                        $name      = $product->get_name();
                        $price     = WC()->cart->get_product_subtotal( $product, $quantity );
                        $variation = isset( $cart_item['variation'] ) ? $cart_item['variation'] : array();
                    ?>
                        <div class="fc-order-item">
                            <?php if ( $show_thumbnails ) : ?>
                                <div class="fc-order-item__thumb">
                                    <?php echo $product->get_image( array( 64, 64 ), array( 'class' => 'fc-order-item__img' ) ); ?>
                                    <span class="fc-order-item__qty"><?php echo esc_html( $quantity ); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="fc-order-item__details">
                                <span class="fc-order-item__name"><?php echo esc_html( $name ); ?></span>
                                <?php if ( ! empty( $variation ) ) : ?>
                                    <span class="fc-order-item__meta">
                                        <?php foreach ( $variation as $attr => $val ) :
                                            if ( ! $val ) continue;
                                            echo esc_html( wc_attribute_label( str_replace( 'attribute_', '', $attr ) ) . ': ' . $val . '  ' );
                                        endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="fc-order-item__price"><?php echo wp_kses_post( $price ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Coupon -->
                <?php if ( wc_coupons_enabled() ) : ?>
                    <div class="fc-coupon">
                        <button type="button" class="fc-coupon__toggle" aria-expanded="false" aria-controls="fc-coupon-form">
                            <?php esc_html_e( 'Have a discount code?', 'flowcheckout' ); ?>
                            <?php echo FlowCheckout_Helpers::icon( 'chevron-down', 'fc-icon fc-icon--sm fc-icon--toggle' ); ?>
                        </button>
                        <div id="fc-coupon-form" class="fc-coupon__form" hidden>
                            <div class="fc-coupon__row">
                                <input type="text"
                                       id="fc-coupon-code"
                                       name="coupon_code"
                                       class="fc-coupon__input"
                                       placeholder="<?php esc_attr_e( 'Discount code', 'flowcheckout' ); ?>"
                                       value="">
                                <button type="button" id="fc-apply-coupon" class="fc-btn fc-btn--outline fc-btn--sm">
                                    <?php esc_html_e( 'Apply', 'flowcheckout' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Totals — wrapped in #fc-totals-fragment so WooCommerce AJAX can refresh it -->
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
                </div><!-- #fc-totals-fragment -->

                <!-- Sidebar trust badges -->
                <?php if ( 'sidebar' === $trust_position ) : ?>
                    <?php do_action( 'flowcheckout_after_place_order_button' ); ?>
                <?php endif; ?>

            </div>
        </aside>

    </div><!-- .fc-checkout__main -->

</div><!-- .fc-checkout -->
