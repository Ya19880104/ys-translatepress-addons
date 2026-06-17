<?php
/**
 * 模組設定頁：選單語言控制
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Support\YSTPAddonsTP;

$langs    = YSTPAddonsTP::languages();
$menus    = wp_get_nav_menus();
$menu_url = admin_url( 'nav-menus.php' );
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">選單語言控制</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 選單語言控制</div>
            <div class="ystp-hero-sub"><?php echo esc_html( $meta['desc'] ); ?></div>
        </div>
        <div class="ystp-hero-side">
            <?php if ( $enabled ) : ?>
                <span class="ystp-pill ystp-pill-on"><span class="ystp-dot"></span>模組啟用中</span>
            <?php else : ?>
                <span class="ystp-pill ystp-pill-off"><span class="ystp-dot"></span>模組已停用</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $enabled ) : ?>
        <div class="ystp-note ystp-note-warn">
            此模組目前為停用狀態。請至 <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-tp-addons' ) ); ?>">總覽</a> 啟用後，選單編輯器才會出現語言設定欄位。
        </div>
    <?php endif; ?>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-info-outline"></span> 使用說明</div>
        <div class="ystp-panel-body">
            <p>本模組讓你針對「外觀 → 選單」中的<strong>每一個選單項目</strong>，指定它要在哪些語言顯示。</p>
            <ol style="margin:0 0 14px 18px;line-height:1.9;">
                <li>前往 <a href="<?php echo esc_url( $menu_url ); ?>">外觀 → 選單</a>，展開任一選單項目。</li>
                <li>在「顯示語言（多語增強）」區塊，預設為「所有語言」。</li>
                <li>取消「所有語言」後，勾選此項目要顯示的語言。</li>
                <li>儲存選單。前台切換到未勾選的語言時，該項目（及其子項目）會自動隱藏。</li>
            </ol>
            <a class="ystp-btn ystp-btn-primary" href="<?php echo esc_url( $menu_url ); ?>">
                <span class="dashicons dashicons-menu"></span> 前往編輯選單
            </a>
        </div>
    </div>

    <div class="ystp-card-row">
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-translation"></span> 可用語言（<?php echo esc_html( (string) count( $langs ) ); ?>）</div>
            <div class="ystp-panel-body">
                <ul class="ystp-langlist">
                    <?php foreach ( $langs as $code => $name ) : ?>
                        <li><span class="ystp-langlist-name"><?php echo esc_html( $name ); ?></span> <code><?php echo esc_html( $code ); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-menu-alt"></span> 現有選單（<?php echo esc_html( (string) count( $menus ) ); ?>）</div>
            <div class="ystp-panel-body">
                <?php if ( $menus ) : ?>
                    <ul class="ystp-langlist">
                        <?php foreach ( $menus as $m ) : ?>
                            <li><span class="ystp-langlist-name"><?php echo esc_html( $m->name ); ?></span> <code><?php echo esc_html( (string) $m->count ); ?> 項</code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="ystp-muted">尚未建立任何選單。<a href="<?php echo esc_url( $menu_url ); ?>">立即建立</a>。</p>
                    <p class="ystp-muted" style="font-size:12.5px;">註：本功能作用於傳統選單（<code>wp_nav_menu</code>）。區塊主題的「導覽區塊」不適用此過濾。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
