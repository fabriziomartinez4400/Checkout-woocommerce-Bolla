/**
 * FlowCheckout — Admin JavaScript
 * Handles: tab navigation, AJAX settings save, colour pickers,
 * range sliders, image uploader, drag-and-drop field editor.
 *
 * Depends on: jQuery, wp-color-picker, jquery-ui-sortable
 * @package FlowCheckout
 */

( function ( $ ) {
    'use strict';

    const admin = window.flowcheckoutAdmin || {};

    /* ── State ─────────────────────────────────────────────────────────────── */
    let currentSettings = $.extend( true, {}, admin.settings || {} );

    /* ══════════════════════════════════════════════════════════════════════════
       TABS
    ══════════════════════════════════════════════════════════════════════════ */
    function initTabs() {
        $( '.fc-admin__nav-item' ).on( 'click', function ( e ) {
            e.preventDefault();

            const tab = $( this ).data( 'tab' );

            $( '.fc-admin__nav-item' ).removeClass( 'is-active' );
            $( this ).addClass( 'is-active' );

            $( '.fc-tab-panel' ).removeClass( 'is-active' );
            $( '#fc-tab-' + tab ).addClass( 'is-active' );
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       SETTINGS COLLECTION & SAVE
    ══════════════════════════════════════════════════════════════════════════ */
    function collectSettings() {
        const settings = $.extend( true, {}, admin.settings || {} );

        // Collect all inputs with [data-setting]
        $( '[data-setting]' ).each( function () {
            const $el  = $( this );
            const key  = $el.data( 'setting' );
            const type = $el.attr( 'type' );

            if ( type === 'checkbox' ) {
                settings[ key ] = $el.is( ':checked' );
            } else if ( type === 'range' || type === 'number' ) {
                settings[ key ] = parseInt( $el.val(), 10 ) || 0;
            } else {
                settings[ key ] = $el.val();
            }
        } );

        return settings;
    }

    function saveSettings() {
        const $btn = $( '#fc-save-btn' );

        $btn.addClass( 'loading' ).text( admin.i18n.saving );

        $.ajax( {
            url:    admin.ajaxUrl,
            method: 'POST',
            data:   {
                action:   'flowcheckout_save_settings',
                nonce:    admin.nonce,
                settings: JSON.stringify( collectSettings() ),
            },
            success( res ) {
                if ( res.success ) {
                    showNotice( admin.i18n.saved, 'success' );
                    currentSettings = res.data.settings;
                } else {
                    showNotice( res.data?.message || admin.i18n.saveError, 'error' );
                }
            },
            error() {
                showNotice( admin.i18n.saveError, 'error' );
            },
            complete() {
                $btn.removeClass( 'loading' ).text( 'Save Changes' );
            },
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       NOTICE
    ══════════════════════════════════════════════════════════════════════════ */
    function showNotice( message, type = 'success' ) {
        const $notice = $( '#fc-notice' );
        $notice
            .removeClass( 'fc-notice--success fc-notice--error' )
            .addClass( 'fc-notice--' + type )
            .text( message )
            .slideDown( 200 );

        clearTimeout( $notice.data( 'timer' ) );
        $notice.data( 'timer', setTimeout( () => $notice.slideUp( 200 ), 3500 ) );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       COLOUR PICKERS
    ══════════════════════════════════════════════════════════════════════════ */
    function initColorPickers() {
        $( '.fc-color-picker' ).wpColorPicker( {
            change: function ( event, ui ) {
                $( this ).val( ui.color.toString() );
            },
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       RANGE SLIDERS
    ══════════════════════════════════════════════════════════════════════════ */
    function initRangeSliders() {
        $( '.fc-range' ).on( 'input', function () {
            $( this ).siblings( '.fc-range__value' ).text( $( this ).val() + 'px' );
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       IMAGE UPLOADER
    ══════════════════════════════════════════════════════════════════════════ */
    function initImageUploader() {
        $( document ).on( 'click', '.fc-image-upload__btn', function ( e ) {
            e.preventDefault();
            const targetId = $( this ).data( 'target' );
            const $input   = $( '#' + targetId );
            const $preview = $( this ).siblings( '.fc-image-upload__preview' );
            const $btn     = $( this );

            const frame = wp.media( {
                title:    'Select logo image',
                button:   { text: 'Use this image' },
                multiple: false,
                library:  { type: 'image' },
            } );

            frame.on( 'select', function () {
                const attachment = frame.state().get( 'selection' ).first().toJSON();
                $input.val( attachment.url ).trigger( 'change' );
                $btn.text( 'Change image' );

                if ( $preview.length ) {
                    $preview.attr( 'src', attachment.url );
                } else {
                    $btn.before( `<img src="${ attachment.url }" alt="" class="fc-image-upload__preview" style="max-height:60px;border-radius:6px;border:1px solid #e5e7eb;">` );
                }
            } );

            frame.open();
        } );

        $( document ).on( 'click', '.fc-image-upload__remove', function ( e ) {
            e.preventDefault();
            const targetId = $( this ).data( 'target' );
            $( '#' + targetId ).val( '' ).trigger( 'change' );
            $( this ).siblings( '.fc-image-upload__preview' ).remove();
            $( this ).siblings( '.fc-image-upload__btn' ).text( 'Upload image' );
            $( this ).remove();
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       FONT FAMILY TOGGLE
    ══════════════════════════════════════════════════════════════════════════ */
    function initFontToggle() {
        $( '[data-setting="font_family"]' ).on( 'change', function () {
            $( '#fc-custom-font-row' ).toggle( $( this ).val() === 'custom' );
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       FIELD EDITOR (drag-and-drop)
    ══════════════════════════════════════════════════════════════════════════ */
    const widthOptions = [
        { value: 'wide',       label: 'Full width' },
        { value: 'half-left',  label: 'Half (left)' },
        { value: 'half-right', label: 'Half (right)' },
    ];

    function buildFieldItem( field ) {
        const widthSelect = widthOptions.map( opt =>
            `<option value="${ opt.value }" ${ field.width === opt.value ? 'selected' : '' }>${ opt.label }</option>`
        ).join( '' );

        const enabledToggle = `
            <label class="fc-toggle" style="width:36px;height:20px">
                <input type="checkbox" class="fc-field-enabled"
                       ${ field.enabled ? 'checked' : '' }>
                <span class="fc-toggle__track" style="border-radius:10px"></span>
            </label>`;

        const requiredToggle = `
            <label class="fc-toggle" style="width:36px;height:20px">
                <input type="checkbox" class="fc-field-required"
                       ${ field.required ? 'checked' : '' }>
                <span class="fc-toggle__track" style="border-radius:10px"></span>
            </label>`;

        return `
        <li class="fc-field-list__item"
            data-id="${ field.id }"
            data-group="${ field.group }"
            data-type="${ field.type }">

            <div class="fc-field-list__id">
                <span class="fc-field-list__drag-handle">⠿</span>
                <span style="font-size:.75rem;color:#6b7280;font-weight:400">${ field.id }</span>
            </div>

            <input type="text"
                   class="fc-field-list__label-input fc-field-label"
                   value="${ esc( field.label ) }"
                   placeholder="Label">

            <select class="fc-field-list__width fc-field-width">
                ${ widthSelect }
            </select>

            <div class="fc-field-list__toggle">${ requiredToggle }</div>
            <div class="fc-field-list__toggle">${ enabledToggle }</div>
        </li>`;
    }

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /"/g, '&quot;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }

    function renderFieldEditor( fields ) {
        const $list = $( '#fc-field-list' );
        $list.empty();

        fields.forEach( f => {
            $list.append( buildFieldItem( f ) );
        } );

        // Init sortable
        $list.sortable( {
            handle: '.fc-field-list__drag-handle',
            axis:   'y',
            tolerance: 'pointer',
            update() {
                updateFieldPriorities();
            },
        } );
    }

    function updateFieldPriorities() {
        $( '#fc-field-list .fc-field-list__item' ).each( function ( i ) {
            $( this ).data( 'priority', ( i + 1 ) * 10 );
        } );
    }

    function collectFieldConfig() {
        const config = [];

        $( '#fc-field-list .fc-field-list__item' ).each( function ( i ) {
            const $row = $( this );
            config.push( {
                id:       $row.data( 'id' ),
                group:    $row.data( 'group' ),
                type:     $row.data( 'type' ),
                label:    $row.find( '.fc-field-label' ).val(),
                width:    $row.find( '.fc-field-width' ).val(),
                required: $row.find( '.fc-field-required' ).is( ':checked' ),
                enabled:  $row.find( '.fc-field-enabled' ).is( ':checked' ),
                priority: ( i + 1 ) * 10,
            } );
        } );

        return config;
    }

    function saveFieldConfig() {
        $.ajax( {
            url:    admin.ajaxUrl,
            method: 'POST',
            data: {
                action:        'flowcheckout_save_fields',
                nonce:         admin.nonce,
                fields_config: JSON.stringify( collectFieldConfig() ),
            },
            success( res ) {
                if ( res.success ) {
                    showNotice( 'Fields saved!', 'success' );
                } else {
                    showNotice( res.data?.message || 'Error saving fields.', 'error' );
                }
            },
            error() {
                showNotice( 'Error saving fields.', 'error' );
            },
        } );
    }

    function initFieldEditor() {
        const fields = admin.fields || [];
        if ( ! $( '#fc-field-list' ).length ) return;

        renderFieldEditor( fields );

        // Reset button
        $( '#fc-fields-reset' ).on( 'click', function () {
            if ( confirm( 'Reset fields to defaults?' ) ) {
                $.ajax( {
                    url:    admin.ajaxUrl,
                    method: 'POST',
                    data:   { action: 'flowcheckout_get_fields', nonce: admin.nonce },
                    success( res ) {
                        if ( res.success ) renderFieldEditor( res.data.fields );
                    },
                } );
            }
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       RESET ALL
    ══════════════════════════════════════════════════════════════════════════ */
    function initResetAll() {
        $( '#fc-reset-all' ).on( 'click', function () {
            if ( confirm( admin.i18n.confirmReset ) ) {
                $.ajax( {
                    url:    admin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action:   'flowcheckout_save_settings',
                        nonce:    admin.nonce,
                        settings: JSON.stringify( { _reset: true } ),
                    },
                    success() {
                        showNotice( 'Settings reset. Reloading…', 'success' );
                        setTimeout( () => location.reload(), 1200 );
                    },
                } );
            }
        } );
    }

    /* ══════════════════════════════════════════════════════════════════════════
       BOOT
    ══════════════════════════════════════════════════════════════════════════ */
    $( function () {
        initTabs();
        initColorPickers();
        initRangeSliders();
        initImageUploader();
        initFontToggle();
        initFieldEditor();
        initResetAll();

        // Save button
        $( '#fc-save-btn' ).on( 'click', function () {
            // Also save field config when on the fields tab
            if ( $( '#fc-tab-fields' ).hasClass( 'is-active' ) ) {
                saveFieldConfig();
            } else {
                saveSettings();
            }
        } );

        // Auto-save fields when leaving the fields tab
        $( '.fc-admin__nav-item' ).on( 'click', function () {
            if ( $( '#fc-tab-fields' ).hasClass( 'is-active' ) ) {
                saveFieldConfig();
            }
        } );
    } );

} )( jQuery );
