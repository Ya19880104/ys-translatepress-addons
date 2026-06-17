/**
 * YS TranslatePress Addons — 語言自動偵測（前台）
 * 原生 JS，無依賴。Cookie 記憶、bot-safe（爬蟲不執行 JS）。
 */
( function () {
    'use strict';

    var cfg = window.ysTPDetect;
    if ( ! cfg || ! cfg.suggested ) {
        return;
    }

    function getCookie( name ) {
        var m = document.cookie.match( '(^|;)\\s*' + name + '\\s*=\\s*([^;]+)' );
        return m ? m.pop() : '';
    }
    function setCookie( name, value, days ) {
        var d = new Date();
        d.setTime( d.getTime() + days * 864e5 );
        document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
    }

    // 已有選擇 → 不再打擾
    if ( getCookie( cfg.cookie ) ) {
        return;
    }

    var sug = cfg.suggested;

    // 自動模式：直接切換（不顯示提示）
    if ( cfg.behavior === 'auto' ) {
        setCookie( cfg.cookie, '1', 365 );
        window.location.href = sug.url;
        return;
    }

    function el( tag, cls, html ) {
        var e = document.createElement( tag );
        if ( cls ) { e.className = cls; }
        if ( html != null ) { e.innerHTML = html; }
        return e;
    }

    function dismiss( node ) {
        setCookie( cfg.cookie, '1', 365 );
        node.classList.remove( 'is-in' );
        setTimeout( function () { node.parentNode && node.parentNode.removeChild( node ); }, 300 );
    }

    function accept() {
        setCookie( cfg.cookie, '1', 365 );
        window.location.href = sug.url;
    }

    function buildButtons() {
        var wrap = el( 'div', 'ystp-ald-card-actions' );
        var acc = el( 'button', 'ystp-ald-btn ystp-ald-accept' );
        acc.type = 'button';
        acc.textContent = cfg.i18n.accept;
        acc.addEventListener( 'click', accept );
        var dis = el( 'button', 'ystp-ald-btn ystp-ald-dismiss' );
        dis.type = 'button';
        dis.textContent = cfg.i18n.dismiss;
        wrap.appendChild( acc );
        wrap.appendChild( dis );
        return { wrap: wrap, dismiss: dis };
    }

    var flagImg = '<img class="ystp-ald-flag" src="' + sug.flag + '" alt="" width="26" height="18" />';
    var node;

    if ( cfg.style === 'bar' ) {
        node = el( 'div', 'ystp-ald ystp-ald--bar' );
        var inner = el( 'div', 'ystp-ald-bar-inner' );
        inner.appendChild( el( 'div', 'ystp-ald-bar-title', flagImg + '<span>' + cfg.i18n.title + '</span>' ) );
        var b = buildButtons();
        b.dismiss.addEventListener( 'click', function () { dismiss( node ); } );
        inner.appendChild( b.wrap );
        node.appendChild( inner );
    } else {
        var pos = ( cfg.position === 'bottom-left' ) ? 'pos-bottom-left' : 'pos-bottom-right';
        node = el( 'div', 'ystp-ald ystp-ald--card ' + pos );
        var close = el( 'button', 'ystp-ald-close', '&times;' );
        close.type = 'button';
        close.setAttribute( 'aria-label', cfg.i18n.dismiss );
        close.addEventListener( 'click', function () { dismiss( node ); } );
        node.appendChild( close );
        node.appendChild( el( 'div', 'ystp-ald-card-head', flagImg + '<div class="ystp-ald-card-title">' + cfg.i18n.title + '</div>' ) );
        var bb = buildButtons();
        bb.dismiss.addEventListener( 'click', function () { dismiss( node ); } );
        node.appendChild( bb.wrap );
    }

    document.body.appendChild( node );
    requestAnimationFrame( function () {
        requestAnimationFrame( function () { node.classList.add( 'is-in' ); } );
    } );
} )();
