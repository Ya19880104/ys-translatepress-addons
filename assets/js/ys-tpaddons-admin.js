/**
 * YS TranslatePress Addons — 後台互動
 */
( function ( $ ) {
    'use strict';

    var cfg = window.ysTPAdmin || {};

    function toast( message, isError ) {
        $( '.ystp-toast' ).remove();
        var icon = isError ? 'dashicons-warning' : 'dashicons-yes-alt';
        var $t = $( '<div class="ystp-toast"><span class="dashicons ' + icon + '"></span><span></span></div>' );
        $t.toggleClass( 'is-error', !! isError );
        $t.find( 'span' ).last().text( message );
        $( 'body' ).append( $t );
        // 強制 reflow 後加上 show
        $t.offset();
        $t.addClass( 'show' );
        setTimeout( function () {
            $t.removeClass( 'show' );
            setTimeout( function () { $t.remove(); }, 300 );
        }, 2600 );
    }

    /* 模組啟用／停用 */
    $( document ).on( 'change', '.ystp-module-toggle', function () {
        var $cb     = $( this );
        var module  = $cb.data( 'module' );
        var enabled = $cb.is( ':checked' ) ? 1 : 0;
        $cb.prop( 'disabled', true );

        $.post( cfg.ajax_url, {
            action:  'ys_tp_toggle_module',
            nonce:   cfg.nonce,
            module:  module,
            enabled: enabled
        } ).done( function ( res ) {
            if ( res && res.success ) {
                $cb.closest( '.ystp-card' ).toggleClass( 'is-on', !! enabled );
                toast( res.data.message );
            } else {
                $cb.prop( 'checked', ! enabled );
                toast( ( res && res.data && res.data.message ) || cfg.i18n.error, true );
            }
        } ).fail( function () {
            $cb.prop( 'checked', ! enabled );
            toast( cfg.i18n.error, true );
        } ).always( function () {
            $cb.prop( 'disabled', false );
        } );
    } );

    /* 儲存設定 */
    $( document ).on( 'click', '.ystp-save-btn', function () {
        var $btn = $( this );
        var form = $btn.data( 'form' );
        var $scope = form ? $( '.ystp-form[data-form="' + form + '"]' ) : $btn.closest( '.ystp-panel' );

        var settings = {};
        $scope.find( '[data-setting]' ).each( function () {
            var $f = $( this );
            var key = $f.data( 'setting' );
            var val;
            if ( $f.is( ':checkbox' ) ) {
                val = $f.is( ':checked' ) ? 1 : 0;
            } else {
                val = $f.val();
            }
            settings[ key ] = val;
        } );

        $btn.prop( 'disabled', true );
        var oldHtml = $btn.html();
        $btn.html( '<span class="dashicons dashicons-update"></span> ' + cfg.i18n.saving );

        $.post( cfg.ajax_url, {
            action:   'ys_tp_save_settings',
            nonce:    cfg.nonce,
            settings: settings
        } ).done( function ( res ) {
            if ( res && res.success ) {
                toast( res.data.message || cfg.i18n.saved );
            } else {
                toast( ( res && res.data && res.data.message ) || cfg.i18n.error, true );
            }
        } ).fail( function () {
            toast( cfg.i18n.error, true );
        } ).always( function () {
            $btn.prop( 'disabled', false ).html( oldHtml );
        } );
    } );

    /* ───────────── AI 翻譯頁 ───────────── */

    // 供應商區塊切換
    $( document ).on( 'change', '#ys-tp-ai-provider', function () {
        var v = $( this ).val();
        $( '.ystp-ai-provider-block' ).each( function () {
            $( this ).toggle( $( this ).data( 'provider' ) === v );
        } );
    } );

    // 模型下拉 ←→ 自行輸入連動
    $( document ).on( 'change', '.ystp-ai-model-select', function () {
        var $sel = $( this );
        var $custom = $sel.siblings( '.ystp-ai-model-custom' );
        if ( $sel.val() === '__custom__' ) {
            $custom.show().trigger( 'focus' );
        } else {
            $custom.val( $sel.val() ).hide();
        }
    } );

    // 測試連線
    $( document ).on( 'click', '#ys-tp-ai-test', function () {
        var $r = $( '#ys-tp-ai-test-result' ).text( cfg.i18n.saving ).css( 'color', '#7a8a96' );
        $.post( cfg.ajax_url, { action: 'ys_tp_ai_test', nonce: cfg.nonce } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    $r.text( '✓ ' + res.data.message ).css( 'color', '#5b9a8b' );
                } else {
                    $r.text( '✕ ' + ( ( res && res.data && res.data.message ) || cfg.i18n.error ) ).css( 'color', '#b3413a' );
                }
            } ).fail( function () { $r.text( cfg.i18n.error ).css( 'color', '#b3413a' ); } );
    } );

    // 全站翻譯迴圈
    var aiRunning = false;

    function aiUpdate( stats, pending ) {
        $( '#ys-tp-ai-pending' ).text( pending );
        if ( stats ) {
            $.each( stats, function ( code, s ) {
                var $li = $( '#ys-tp-ai-stats li[data-lang="' + code + '"]' );
                $li.find( '.ys-done' ).text( s.done );
                $li.find( '.ys-total' ).text( s.total );
            } );
        }
    }

    function aiStop( msg ) {
        aiRunning = false;
        $( '#ys-tp-ai-run' ).show();
        $( '#ys-tp-ai-stop' ).hide();
        if ( msg ) { $( '#ys-tp-ai-run-status' ).text( msg ); }
    }

    function aiStep( initialPending ) {
        if ( ! aiRunning ) { return; }
        $.post( cfg.ajax_url, { action: 'ys_tp_ai_run_step', nonce: cfg.nonce } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    aiUpdate( res.data.stats, res.data.pending );
                    var done = initialPending - res.data.pending;
                    var pct = initialPending > 0 ? Math.round( done / initialPending * 100 ) : 100;
                    $( '#ys-tp-ai-bar' ).css( 'width', Math.min( 100, Math.max( 0, pct ) ) + '%' );
                    $( '#ys-tp-ai-run-status' ).text( '翻譯中… 剩餘 ' + res.data.pending );
                    if ( res.data.pending > 0 && res.data.translated > 0 ) {
                        aiStep( initialPending );
                    } else {
                        $( '#ys-tp-ai-bar' ).css( 'width', '100%' );
                        aiStop( '完成，剩餘 ' + res.data.pending );
                    }
                } else {
                    aiStop( ( res && res.data && res.data.message ) || cfg.i18n.error );
                }
            } ).fail( function () { aiStop( cfg.i18n.error ); } );
    }

    $( document ).on( 'click', '#ys-tp-ai-run', function () {
        var pending = parseInt( $( '#ys-tp-ai-pending' ).text(), 10 ) || 0;
        if ( pending <= 0 ) { $( '#ys-tp-ai-run-status' ).text( '目前沒有待翻譯字串' ); return; }
        aiRunning = true;
        $( '#ys-tp-ai-run' ).hide();
        $( '#ys-tp-ai-stop' ).show();
        aiStep( pending );
    } );

    $( document ).on( 'click', '#ys-tp-ai-stop', function () { aiStop( '已停止' ); } );

    /* ───────────── 匯出／匯入 ───────────── */

    $( document ).on( 'click', '#ys-tp-io-export', function () {
        var base = $( this ).data( 'base' );
        var only = $( '#ys-tp-io-only' ).is( ':checked' ) ? '&only_translated=1' : '';
        window.location.href = base + only;
    } );

    $( document ).on( 'click', '#ys-tp-io-import', function () {
        var $btn = $( this );
        var file = ( document.getElementById( 'ys-tp-io-file' ).files || [] )[0];
        var $r = $( '#ys-tp-io-result' );
        if ( ! file ) { $r.text( '請先選擇 JSON 檔' ).css( 'color', '#b3413a' ); return; }

        var fd = new FormData();
        fd.append( 'action', 'ys_tp_io_import' );
        fd.append( 'nonce', cfg.nonce );
        fd.append( 'file', file );

        $btn.prop( 'disabled', true );
        $r.text( cfg.i18n.saving ).css( 'color', '#7a8a96' );

        fetch( cfg.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( res ) {
                if ( res && res.success ) {
                    $r.text( '✓ ' + res.data.message ).css( 'color', '#5b9a8b' );
                } else {
                    $r.text( '✕ ' + ( ( res && res.data && res.data.message ) || cfg.i18n.error ) ).css( 'color', '#b3413a' );
                }
            } )
            .catch( function () { $r.text( cfg.i18n.error ).css( 'color', '#b3413a' ); } )
            .finally( function () { $btn.prop( 'disabled', false ); } );
    } );

    /* ───────────── 短代碼點擊複製 ───────────── */
    $( document ).on( 'click', '.ystp-sc-code[data-copy]', function () {
        var $c = $( this );
        var text = $c.attr( 'data-copy' );
        function done() {
            $c.addClass( 'copied' );
            setTimeout( function () { $c.removeClass( 'copied' ); }, 1200 );
            toast( '已複製短代碼' );
        }
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( done, function () {} );
        } else {
            var ta = document.createElement( 'textarea' );
            ta.value = text;
            document.body.appendChild( ta );
            ta.select();
            try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
            document.body.removeChild( ta );
        }
    } );

} )( jQuery );
