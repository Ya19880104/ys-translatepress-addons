/**
 * YS TranslatePress Addons — 語言切換器（前台互動）
 * 原生 JS，無依賴。
 */
( function () {
    'use strict';

    function closeAll( except ) {
        document.querySelectorAll( '.ystp-ls[aria-open="true"]' ).forEach( function ( el ) {
            if ( el !== except ) {
                el.setAttribute( 'aria-open', 'false' );
                var t = el.querySelector( '[data-ls-toggle]' );
                if ( t ) { t.setAttribute( 'aria-expanded', 'false' ); }
            }
        } );
    }

    document.addEventListener( 'click', function ( e ) {
        // dropdown / floating 開合
        var toggle = e.target.closest( '[data-ls-toggle]' );
        if ( toggle ) {
            e.preventDefault();
            var root = toggle.closest( '[data-ls-root]' );
            var open = root.getAttribute( 'aria-open' ) === 'true';
            closeAll( root );
            root.setAttribute( 'aria-open', open ? 'false' : 'true' );
            toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
            return;
        }

        // popup 開啟
        var pOpen = e.target.closest( '[data-ls-popup-open]' );
        if ( pOpen ) {
            e.preventDefault();
            var modal = pOpen.closest( '[data-ls-root]' ).querySelector( '[data-ls-modal]' );
            if ( modal ) { modal.hidden = false; document.documentElement.style.overflow = 'hidden'; }
            return;
        }

        // popup 關閉
        var pClose = e.target.closest( '[data-ls-popup-close]' );
        if ( pClose ) {
            e.preventDefault();
            var m = pClose.closest( '[data-ls-modal]' );
            if ( m ) { m.hidden = true; document.documentElement.style.overflow = ''; }
            return;
        }

        // 點擊外部關閉 dropdown / floating
        if ( ! e.target.closest( '.ystp-ls-menu' ) ) {
            closeAll( null );
        }
    } );

    // ESC 關閉
    document.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            closeAll( null );
            document.querySelectorAll( '[data-ls-modal]:not([hidden])' ).forEach( function ( m ) {
                m.hidden = true;
                document.documentElement.style.overflow = '';
            } );
        }
    } );
} )();
