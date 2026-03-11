/**
 * FlowCheckout — Frontend JavaScript
 * Handles: mobile order summary toggle, coupon accordion,
 * inline validation, ship-to-different-address, loading state.
 *
 * Depends on: jQuery, wc-checkout (WooCommerce)
 * @package FlowCheckout
 */

( function ( $, data ) {
    'use strict';

    const FC = {

        // ── Init ───────────────────────────────────────────────────────────────
        init() {
            this.cacheDom();
            this.bindEvents();
            this.initMobileSummaryToggle();
            this.initCouponToggle();
            this.initShipToDifferentAddress();
            this.initInlineValidation();
            this.initPlaceOrderLoading();
            this.initStickyHeader();
            this.injectProgressBar();
        },

        // ── DOM cache ──────────────────────────────────────────────────────────
        cacheDom() {
            this.$body         = $( 'body' );
            this.$form         = $( '#fc-checkout-form' );
            this.$placeOrder   = $( '#place_order' );
            this.$summaryToggle= $( '#fc-mobile-summary-toggle' );
            this.$summaryBtn   = this.$summaryToggle.find( '.fc-mobile-summary-toggle__btn' );
            this.$summaryBody  = $( '#fc-mobile-order-summary' );
            this.$couponToggle = $( '.fc-coupon__toggle' );
            this.$couponForm   = $( '#fc-coupon-form' );
            this.$applyBtn     = $( '#fc-apply-coupon' );
            this.$couponInput  = $( '#fc-coupon-code' );
            this.$shipChk      = $( '#ship_to_different_address' );
            this.$shipFields   = $( '.fc-shipping-fields' );
        },

        // ── Event binding ─────────────────────────────────────────────────────
        bindEvents() {
            // WooCommerce fires 'updated_checkout' whenever totals refresh
            this.$body.on( 'updated_checkout', () => {
                this.onCheckoutUpdated();
            } );

            // Apply coupon
            this.$body.on( 'click', '#fc-apply-coupon', () => {
                this.applyCoupon();
            } );

            // Coupon input: apply on Enter
            this.$body.on( 'keydown', '#fc-coupon-code', ( e ) => {
                if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    this.applyCoupon();
                }
            } );

            // Range sliders in admin (they exist on frontend custom CSS preview, ignore if not present)
            $( '.fc-range' ).on( 'input', function () {
                $( this ).siblings( '.fc-range__value' ).text( $( this ).val() + 'px' );
            } );
        },

        // ── Mobile order summary toggle ───────────────────────────────────────
        initMobileSummaryToggle() {
            if ( ! this.$summaryBtn.length ) return;

            this.$summaryBtn.on( 'click', () => {
                const isExpanded = this.$summaryBtn.attr( 'aria-expanded' ) === 'true';
                const newState   = ! isExpanded;

                this.$summaryBtn.attr( 'aria-expanded', newState );
                this.$summaryBody.prop( 'hidden', ! newState );

                const textEl = this.$summaryBtn.find( '.fc-mobile-summary-toggle__text' );
                textEl.text( newState ? data.i18n.hideSummary : data.i18n.showSummary );

                const icon = this.$summaryBtn.find( '.fc-icon--toggle' );
                icon.css( 'transform', newState ? 'rotate(180deg)' : '' );
            } );
        },

        // ── Coupon accordion ──────────────────────────────────────────────────
        initCouponToggle() {
            if ( ! this.$couponToggle.length ) return;

            this.$couponToggle.on( 'click', () => {
                const isExpanded = this.$couponToggle.attr( 'aria-expanded' ) === 'true';
                const newState   = ! isExpanded;

                this.$couponToggle.attr( 'aria-expanded', newState );
                this.$couponForm.prop( 'hidden', ! newState );

                if ( newState ) {
                    this.$couponInput.trigger( 'focus' );
                }
            } );
        },

        // ── Apply coupon ──────────────────────────────────────────────────────
        applyCoupon() {
            const code = this.$couponInput.val().trim();
            if ( ! code ) return;

            this.$applyBtn.prop( 'disabled', true ).text( '…' );

            $( 'body' ).trigger( 'apply_coupon', [ code ] );

            // WooCommerce handles the response via 'applied_coupon' / 'removed_coupon' events
            // We just reset the button state after a delay
            setTimeout( () => {
                this.$applyBtn.prop( 'disabled', false ).text( 'Apply' );
            }, 1500 );
        },

        // ── Ship to different address ─────────────────────────────────────────
        initShipToDifferentAddress() {
            if ( ! this.$shipChk.length ) return;

            const toggle = () => {
                if ( this.$shipChk.is( ':checked' ) ) {
                    this.$shipFields.slideDown( 200 );
                } else {
                    this.$shipFields.slideUp( 200 );
                }
            };

            toggle();
            this.$shipChk.on( 'change', toggle );
        },

        // ── Inline real-time validation ───────────────────────────────────────
        initInlineValidation() {
            if ( ! this.$form.length ) return;

            // Validate email on blur
            this.$form.on( 'blur', 'input[type="email"]', function () {
                FC.validateEmail( $( this ) );
            } );

            // Validate required fields on blur
            this.$form.on( 'blur', 'input.input-text, select', function () {
                const $field = $( this );
                const $row   = $field.closest( '.form-row' );

                if ( $row.hasClass( 'validate-required' ) ) {
                    FC.validateRequired( $field, $row );
                }
            } );

            // Clear error on input
            this.$form.on( 'input change', 'input.input-text, select', function () {
                const $row = $( this ).closest( '.form-row' );
                $row.removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                $row.find( '.fc-field-error' ).remove();
            } );
        },

        validateEmail( $input ) {
            const $row  = $input.closest( '.form-row' );
            const value = $input.val().trim();
            const re    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            $row.find( '.fc-field-error' ).remove();

            if ( value && ! re.test( value ) ) {
                $row.addClass( 'woocommerce-invalid' );
                $row.append( `<span class="fc-field-error" style="display:block;font-size:.75rem;color:var(--fc-error);margin-top:4px">${ data.i18n.invalidEmail }</span>` );
            } else {
                $row.removeClass( 'woocommerce-invalid' );
            }
        },

        validateRequired( $field, $row ) {
            $row.find( '.fc-field-error' ).remove();

            if ( ! $field.val().trim() ) {
                $row.addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                $row.append( `<span class="fc-field-error" style="display:block;font-size:.75rem;color:var(--fc-error);margin-top:4px">${ data.i18n.fieldRequired }</span>` );
            } else {
                $row.removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
            }
        },

        // ── Place order loading state ─────────────────────────────────────────
        initPlaceOrderLoading() {
            if ( ! this.$placeOrder.length ) return;

            this.$form.on( 'submit', () => {
                if ( ! this.$body.hasClass( 'processing' ) ) {
                    this.$placeOrder.addClass( 'processing' );
                    this.$placeOrder.text( data.i18n.processing );
                }
            } );

            // WooCommerce fires checkout_error when there's a problem
            this.$body.on( 'checkout_error', () => {
                this.$placeOrder.removeClass( 'processing' );
                this.$placeOrder.text( this.$placeOrder.data( 'original-text' ) || 'Place order' );
            } );

            // Store original text
            if ( this.$placeOrder.length ) {
                this.$placeOrder.data( 'original-text', this.$placeOrder.text() );
            }
        },

        // ── Sticky header offset (WP admin bar) ───────────────────────────────
        initStickyHeader() {
            const $adminBar = $( '#wpadminbar' );
            const $summary  = $( '.fc-checkout__summary-col--sticky' );

            if ( ! $summary.length ) return;

            const offset = $adminBar.length ? $adminBar.outerHeight() + 24 : 24;
            $summary.css( 'top', offset + 'px' );
        },

        // ── Progress bar: mark current step based on URL/WC state ────────────
        injectProgressBar() {
            // WooCommerce shows billing/shipping info on one step.
            // In a real multi-step flow you'd track steps in sessionStorage.
            // Here we just highlight the last step (Payment) as current.
        },

        // ── On checkout updated (WooCommerce AJAX) ────────────────────────────
        onCheckoutUpdated() {
            // Re-cache dynamic elements
            this.cacheDom();
            this.initMobileSummaryToggle();
            this.initCouponToggle();
        },
    };

    // ── Boot on DOM ready ────────────────────────────────────────────────────
    $( () => {
        FC.init();
    } );

} )( jQuery, window.flowcheckoutData || {} );
