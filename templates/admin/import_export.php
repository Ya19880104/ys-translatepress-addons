<?php
/**
 * 模組設定頁：翻譯匯出／匯入
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsTranslationService;

$export_base = add_query_arg(
    [ 'action' => 'ys_tp_io_export', 'nonce' => wp_create_nonce( 'ys_tp_io' ) ],
    admin_url( 'admin-ajax.php' )
);
$stats = YSTPAddonsTranslationService::stats();
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">翻譯匯出／匯入</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 翻譯匯出／匯入</div>
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
            此模組目前為停用狀態。請至 <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-tp-addons' ) ); ?>">總覽</a> 啟用後生效。
        </div>
    <?php endif; ?>

    <div class="ystp-card-row">
        <!-- 匯出 -->
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-database-export"></span> 匯出翻譯</div>
            <div class="ystp-panel-body">
                <p><?php esc_html_e( '將所有語言的翻譯匯出為 JSON 檔，可在外部編輯後再匯入。', 'ys-translatepress-addons' ); ?></p>
                <label style="display:inline-flex;align-items:center;gap:7px;margin:6px 0 16px;">
                    <input type="checkbox" id="ys-tp-io-only" />
                    <?php esc_html_e( '只匯出已翻譯的字串', 'ys-translatepress-addons' ); ?>
                </label>
                <div>
                    <button type="button" class="ystp-btn ystp-btn-primary" id="ys-tp-io-export" data-base="<?php echo esc_url( $export_base ); ?>">
                        <span class="dashicons dashicons-download"></span> 下載 JSON
                    </button>
                </div>
            </div>
        </div>

        <!-- 匯入 -->
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-database-import"></span> 匯入翻譯</div>
            <div class="ystp-panel-body">
                <p><?php esc_html_e( '上傳先前匯出的 JSON 檔，依「原文」比對寫回翻譯。', 'ys-translatepress-addons' ); ?></p>
                <input type="file" id="ys-tp-io-file" accept="application/json,.json" style="margin:6px 0 14px;display:block;" />
                <button type="button" class="ystp-btn ystp-btn-primary" id="ys-tp-io-import">
                    <span class="dashicons dashicons-upload"></span> 匯入
                </button>
                <span id="ys-tp-io-result" style="display:block;margin-top:12px;font-size:13px;"></span>
                <p class="ystp-field-desc" style="margin-top:8px;"><?php esc_html_e( '注意：匯入會覆寫相同原文的現有翻譯。', 'ys-translatepress-addons' ); ?></p>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-chart-bar"></span> 目前翻譯狀態</div>
        <div class="ystp-panel-body">
            <ul class="ystp-langlist">
                <?php foreach ( $stats as $code => $s ) : ?>
                    <li>
                        <span class="ystp-langlist-name"><?php echo esc_html( $s['name'] ); ?></span>
                        <code><?php echo esc_html( $s['done'] . ' / ' . $s['total'] ); ?></code>
                        <?php if ( $s['total'] > 0 ) : ?>
                            <span class="ystp-tag" style="background:#5b9a8b;"><?php echo esc_html( (string) round( $s['done'] / $s['total'] * 100 ) ); ?>%</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
