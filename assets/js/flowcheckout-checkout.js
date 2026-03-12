/**
 * FlowCheckout — Frontend JavaScript
 * Handles: mobile order summary toggle, coupon accordion,
 * inline validation, ship-to-different-address, shipping calculator,
 * express payment reveal, and loading state.
 *
 * Depends on: jQuery, wc-checkout (WooCommerce)
 * @package FlowCheckout
 */

( function ( $, data ) {
    'use strict';

    const FC = {

        _shippingCalcTimer: null,
        _shippingDone: false,

        // ── Init ───────────────────────────────────────────────────────────────
        init() {
            this._shippingDone = !! data.shippingDone;
            this.cacheDom();
            this.bindEvents();
            this.initMobileSummaryToggle();
            this.initCouponToggle();
            this.initShipToDifferentAddress();
            this.initInlineValidation();
            this.initPlaceOrderLoading();
            this.initStickyHeader();
            this.initShippingCalc();
            this.initCartShippingCalc();
            this.initShippingFieldTriggers();
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

            // Shipping method: update visual selected state when changed
            this.$body.on( 'change', 'input.shipping_method[type="radio"]', ( e ) => {
                const $radio    = $( e.target );
                const $siblings = $radio.closest( '.fc-shipping-methods' ).find( '.fc-shipping-method' );
                $siblings.removeClass( 'fc-shipping-method--selected' );
                $radio.closest( '.fc-shipping-method' ).addClass( 'fc-shipping-method--selected' );
            } );

            // Range sliders in admin
            $( '.fc-range' ).on( 'input', function () {
                $( this ).siblings( '.fc-range__value' ).text( $( this ).val() + 'px' );
            } );
        },

        // ── Explicit shipping field triggers (backup for WC's built-in) ───────
        initShippingFieldTriggers() {
            const shippingFields = '#billing_postcode, #billing_country, #billing_state, #shipping_postcode, #shipping_country, #shipping_state';

            this.$body.on( 'change input', shippingFields, () => {
                clearTimeout( this._shippingCalcTimer );
                this._shippingCalcTimer = setTimeout( () => {
                    this.$body.trigger( 'update_checkout' );
                }, 600 );
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

            setTimeout( () => {
                this.$applyBtn.prop( 'disabled', false ).text( data.i18n.apply || 'Apply' );
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

        // ── Shipping Calculator (inside payment section) ──────────────────────
        initShippingCalc() {
            const $calcBox   = $( '#fc-ship-calc-box' );
            if ( ! $calcBox.length ) return;

            const $form      = $( '#fc-ship-calc-form' );
            const $countryEl = $( '#fc-ship-country-calc' );
            const $postcodeEl= $( '#fc-ship-postcode-calc' );
            const $calcBtn   = $( '#fc-calc-ship-btn' );
            const $editBtn   = $( '#fc-ship-calc-edit' );
            const $ratesWrap = $( '#fc-ship-calc-rates' );
            const $expressWrap = $( '#fc-express-wrap' );

            // Edit / change button
            $calcBox.on( 'click', '#fc-ship-calc-edit', () => {
                $form.removeAttr( 'hidden' );
                $( '.fc-ship-calc-box__current-info' ).hide();
            } );

            // Calculate button
            $calcBox.on( 'click', '#fc-calc-ship-btn', () => {
                const country  = $countryEl.val();
                const postcode = $postcodeEl.val().trim();

                if ( ! country ) {
                    $countryEl.focus();
                    return;
                }

                // Sync values to the main WC billing fields in the form
                $( '#billing_country' ).val( country ).trigger( 'change' );
                $( '#billing_postcode' ).val( postcode ).trigger( 'input' );

                // Show loading state
                $ratesWrap.removeAttr( 'hidden' ).html(
                    '<div class="fc-ship-calc-box__loading">' + ( data.i18n.calculatingShipping || 'Calculating shipping…' ) + '</div>'
                );

                $calcBtn.prop( 'disabled', true );

                // Tell WC to recalculate
                this.$body.trigger( 'update_checkout' );

                // Wait for updated_checkout, then show rates
                this.$body.one( 'updated_checkout', () => {
                    $calcBtn.prop( 'disabled', false );

                    // Collect rates from updated WC DOM (rendered by woocommerce_checkout_payment)
                    const rates = [];
                    $( 'input.shipping_method' ).each( function () {
                        const $label = $( 'label[for="' + $( this ).attr( 'id' ) + '"]' );
                        if ( $label.length ) {
                            rates.push( $label.text().trim() );
                        }
                    } );

                    // Build result HTML
                    let html = '';
                    if ( rates.length ) {
                        html = rates.map( r =>
                            '<div class="fc-ship-calc-box__rate"><span>' + FC._escHtml( r ) + '</span></div>'
                        ).join( '' );
                    } else {
                        // Check if shipping is free / single method
                        const shippingText = $( '#fc-totals-fragment .fc-total-row' ).filter( function () {
                            return $( this ).find( 'span:first' ).text().toLowerCase().indexOf( 'ship' ) !== -1;
                        } ).find( 'span:last' ).text().trim();

                        if ( shippingText ) {
                            html = '<div class="fc-ship-calc-box__rate"><span>' + data.i18n.shipping || 'Shipping' + '</span><span class="fc-ship-calc-box__rate-cost">' + FC._escHtml( shippingText ) + '</span></div>';
                        } else {
                            html = '<div class="fc-ship-calc-box__no-rates">' + ( data.i18n.noShipping || 'Shipping calculated above.' ) + '</div>';
                        }
                    }

                    $ratesWrap.html( html );

                    // Collapse the form and show "done" state
                    $form.attr( 'hidden', true );
                    this._updateCalcDoneState( $calcBox, country, postcode, $countryEl );

                    // Reveal express payment buttons
                    this._shippingDone = true;
                    if ( $expressWrap.length ) {
                        $expressWrap.slideDown( 250 );
                    }
                } );
            } );

            // If shipping already set on load, ensure express section is visible
            if ( this._shippingDone && $expressWrap.length ) {
                $expressWrap.show();
            }
        },

        _updateCalcDoneState( $calcBox, country, postcode, $countryEl ) {
            const countryName = $countryEl.find( 'option[value="' + country + '"]' ).text() || country;
            const displayText = countryName + ( postcode ? ' &nbsp;' + postcode : '' );

            let $info = $calcBox.find( '.fc-ship-calc-box__current-info' );
            if ( ! $info.length ) {
                $info = $( '<span class="fc-ship-calc-box__current fc-ship-calc-box__current-info"></span>' );
                $calcBox.find( '.fc-ship-calc-box__header' ).append( $info );
            }

            $info.html(
                '<span class="fc-ship-calc-box__done-badge">✓ ' + displayText + '</span>' +
                ' <button type="button" class="fc-ship-calc-box__edit" id="fc-ship-calc-edit">' + ( data.i18n.change || 'Change' ) + '</button>'
            ).show();
        },

        _escHtml( str ) {
            return str.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
        },

        // ── Cart page shipping calculator ─────────────────────────────────────
        initCartShippingCalc() {
            const $widget = $( '.fc-ship-calc-widget' );
            if ( ! $widget.length ) return;

            $widget.on( 'click', '.fc-ship-calc-widget__btn', function () {
                const $w        = $( this ).closest( '.fc-ship-calc-widget' );
                const country   = $w.find( '.fc-ship-calc-widget__select' ).val();
                const postcode  = $w.find( '.fc-ship-calc-widget__postcode' ).val().trim();
                const $result   = $w.find( '.fc-ship-calc-widget__result' );
                const $btn      = $( this );

                if ( ! country ) {
                    $w.find( '.fc-ship-calc-widget__select' ).focus();
                    return;
                }

                $btn.prop( 'disabled', true ).text( '…' );
                $result.removeAttr( 'hidden' ).html(
                    '<div class="fc-ship-calc-box__loading">' + ( data.i18n.calculatingShipping || 'Calculating shipping…' ) + '</div>'
                );

                $.post( data.ajaxUrl, {
                    action : 'flowcheckout_update_shipping_location',
                    nonce  : data.nonce,
                    country : country,
                    postcode: postcode,
                }, function ( response ) {
                    $btn.prop( 'disabled', false ).text( data.i18n.calculate || 'Calculate' );

                    if ( response.success ) {
                        const rates = response.data.rates || [];
                        if ( rates.length ) {
                            const html = rates.map( r =>
                                '<div class="fc-ship-calc-box__rate">' +
                                    '<span>' + FC._escHtml( r.label ) + '</span>' +
                                    '<span class="fc-ship-calc-box__rate-cost">' + FC._escHtml( r.cost ) + '</span>' +
                                '</div>'
                            ).join( '' );
                            $result.html( '<div class="fc-ship-calc-box__rates">' + html + '</div>' );
                        } else {
                            $result.html( '<div class="fc-ship-calc-box__no-rates">' + ( data.i18n.noShippingAvail || 'No shipping methods available for this location.' ) + '</div>' );
                        }

                        // Trigger WooCommerce cart update
                        $( 'body' ).trigger( 'wc_update_cart' );
                        $( document.body ).trigger( 'update_checkout' );
                    } else {
                        $result.html( '<div class="fc-ship-calc-box__no-rates" style="color:var(--fc-error)">' + ( data.i18n.calcError || 'Could not calculate shipping. Please check your details.' ) + '</div>' );
                    }
                } ).fail( function () {
                    $btn.prop( 'disabled', false ).text( data.i18n.calculate || 'Calculate' );
                    $result.html( '<div class="fc-ship-calc-box__no-rates" style="color:var(--fc-error)">' + ( data.i18n.calcError || 'Could not calculate shipping.' ) + '</div>' );
                } );
            } );
        },

        // ── Inline real-time validation ───────────────────────────────────────
        initInlineValidation() {
            if ( ! this.$form.length ) return;

            this.$form.on( 'blur', 'input[type="email"]', function () {
                FC.validateEmail( $( this ) );
            } );

            this.$form.on( 'blur', 'input.input-text, select', function () {
                const $field = $( this );
                const $row   = $field.closest( '.form-row' );

                if ( $row.hasClass( 'validate-required' ) ) {
                    FC.validateRequired( $field, $row );
                }
            } );

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

            this.$body.on( 'checkout_error', () => {
                this.$placeOrder.removeClass( 'processing' );
                this.$placeOrder.text( this.$placeOrder.data( 'original-text' ) || 'Place order' );
            } );

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

        // ── Progress bar ──────────────────────────────────────────────────────
        injectProgressBar() {
            // Highlight last step (Payment) as current — multi-step tracking would use sessionStorage.
        },

        // ── On checkout updated (WooCommerce AJAX) ────────────────────────────
        onCheckoutUpdated() {
            this.cacheDom();
            this.initMobileSummaryToggle();
            this.initCouponToggle();

            $( 'input.shipping_method[type="radio"]:checked' ).each( function () {
                $( this ).closest( '.fc-shipping-method' ).addClass( 'fc-shipping-method--selected' );
            } );
        },
    };

    // ── Boot on DOM ready ────────────────────────────────────────────────────
    $( () => {
        FC.init();
    } );

} )( jQuery, window.flowcheckoutData || {} );
