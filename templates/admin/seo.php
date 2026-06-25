<?php
/**
 * 模組設定頁：SEO 增強
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;
use YangSheep\TPAddons\Support\YSTPAddonsTP;

$meta_tr   = (int) YSTPAddonsSettingsRepo::get( 'seo_meta_translate', 1 );
$region    = (int) YSTPAddonsSettingsRepo::get( 'seo_hreflang_region', 1 );
$region_i  = (int) YSTPAddonsSettingsRepo::get( 'seo_hreflang_region_independent', 1 );
$xd_on     = (int) YSTPAddonsSettingsRepo::get( 'seo_xdefault_enabled', 0 );
$xd_lang   = (string) YSTPAddonsSettingsRepo::get( 'seo_xdefault_lang', YSTPAddonsTP::default_language() );
$slug_on   = (int) YSTPAddonsSettingsRepo::get( 'seo_slug_enabled', 0 );

$langs = YSTPAddonsTP::languages();

$has_yoast    = defined( 'WPSEO_VERSION' );
$has_rankmath = class_exists( 'RankMath' );
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">SEO 增強</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> SEO 增強</div>
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

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-translation"></span> SEO 中繼資料翻譯</div>
        <div class="ystp-panel-body ystp-form" data-form="seo-meta">
            <div class="ystp-field">
                <label><?php esc_html_e( '翻譯 SEO 中繼資料', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="seo_meta_translate" <?php checked( $meta_tr, 1 ); ?> /><span class="ystp-slider"></span></label>
                <p class="ystp-field-desc">
                    <?php esc_html_e( '開啟後，頁面標題（<title>）、meta 描述、Open Graph／Twitter 社群標籤與圖片 alt 會成為可翻譯字串，可逐語言翻譯，前台輸出時自動替換為對應語言版本。', 'ys-translatepress-addons' ); ?>
                </p>
            </div>
            <div class="ystp-note ystp-note-warn" style="margin:6px 0 14px;">
                <span class="dashicons dashicons-info-outline"></span>
                <?php esc_html_e( '翻譯方式：到 TranslatePress 的「翻譯編輯器」開啟任一頁面，即可在 <head> 中繼資料逐語言翻譯；或於前台瀏覽各頁讓字串被偵測後，到「字串翻譯」清單翻譯，也可用「AI 翻譯」模組一併處理。', 'ys-translatepress-addons' ); ?>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="seo-meta">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-admin-site-alt3"></span> hreflang 多語標記</div>
        <div class="ystp-panel-body ystp-form" data-form="seo">
            <p class="ystp-muted" style="margin-bottom:16px;"><?php esc_html_e( 'TranslatePress 已在頁面 <head> 自動輸出 hreflang，本區提供進一步控制。', 'ys-translatepress-addons' ); ?></p>

            <div class="ystp-field">
                <label><?php esc_html_e( '地區標籤（如 zh-TW、en-US）', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="seo_hreflang_region" <?php checked( $region, 1 ); ?> /><span class="ystp-slider"></span></label>
            </div>
            <div class="ystp-field">
                <label><?php esc_html_e( '地區無關標籤（如 zh、en）', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="seo_hreflang_region_independent" <?php checked( $region_i, 1 ); ?> /><span class="ystp-slider"></span></label>
            </div>

            <hr style="border:none;border-top:1px solid #eef1f4;margin:18px 0;" />

            <div class="ystp-field">
                <label><?php esc_html_e( '輸出 x-default 標籤', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="seo_xdefault_enabled" <?php checked( $xd_on, 1 ); ?> /><span class="ystp-slider"></span></label>
                <p class="ystp-field-desc"><?php esc_html_e( '指定無對應語言時搜尋引擎的預設版本（建議開啟）。', 'ys-translatepress-addons' ); ?></p>
            </div>
            <div class="ystp-field">
                <label><?php esc_html_e( 'x-default 指向語言', 'ys-translatepress-addons' ); ?></label>
                <select data-setting="seo_xdefault_lang">
                    <?php foreach ( $langs as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $xd_lang, $code ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="seo">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-admin-links"></span> 翻譯網址 slug</div>
        <div class="ystp-panel-body ystp-form" data-form="seo-slug">
            <div class="ystp-field">
                <label><?php esc_html_e( '啟用翻譯網址 slug', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="seo_slug_enabled" <?php checked( $slug_on, 1 ); ?> /><span class="ystp-slider"></span></label>
                <p class="ystp-field-desc">
                    <?php esc_html_e( '開啟後，可在每篇文章／頁面的編輯畫面為各語言設定自訂網址 slug，例如英文 /sample/ 對應中文 /範例/。', 'ys-translatepress-addons' ); ?>
                </p>
            </div>
            <div class="ystp-note ystp-note-warn" style="margin:6px 0 14px;">
                <span class="dashicons dashicons-info-outline"></span>
                <?php esc_html_e( '需使用「漂亮的永久連結」。變更此設定或大量調整 slug 後，建議到「設定 → 永久連結」按一次儲存以重整路由。', 'ys-translatepress-addons' ); ?>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="seo-slug">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-networking"></span> 多語 Sitemap</div>
        <div class="ystp-panel-body">
            <p><?php esc_html_e( '偵測到的 SEO 外掛：', 'ys-translatepress-addons' ); ?></p>
            <ul class="ystp-langlist">
                <li>
                    <span class="ystp-langlist-name">Yoast SEO</span>
                    <?php if ( $has_yoast ) : ?>
                        <span class="ystp-tag" style="background:#5b9a8b;"><?php esc_html_e( '已啟用 · 將加入語言 alternate', 'ys-translatepress-addons' ); ?></span>
                    <?php else : ?>
                        <code><?php esc_html_e( '未安裝', 'ys-translatepress-addons' ); ?></code>
                    <?php endif; ?>
                </li>
                <li>
                    <span class="ystp-langlist-name">RankMath</span>
                    <?php if ( $has_rankmath ) : ?>
                        <span class="ystp-tag" style="background:#5b9a8b;"><?php esc_html_e( '已啟用 · 將加入語言 alternate', 'ys-translatepress-addons' ); ?></span>
                    <?php else : ?>
                        <code><?php esc_html_e( '未安裝', 'ys-translatepress-addons' ); ?></code>
                    <?php endif; ?>
                </li>
            </ul>
            <p class="ystp-field-desc"><?php esc_html_e( '安裝 Yoast 或 RankMath 後，本模組會自動在其 sitemap 的每個網址加入各語言的 xhtml:link alternate，提升多語索引品質。', 'ys-translatepress-addons' ); ?></p>
        </div>
    </div>
</div>
