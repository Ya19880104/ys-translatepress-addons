<?php
/**
 * 模組設定頁：語言切換器
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;
use YangSheep\TPAddons\Modules\LanguageSwitcher\YSTPAddonsLanguageSwitcher;
use YangSheep\TPAddons\Support\YSTPAddonsTP;

$def_style  = (string) YSTPAddonsSettingsRepo::get( 'switcher_default_style', 'dropdown' );
$show       = (string) YSTPAddonsSettingsRepo::get( 'switcher_show', 'both' );
$float_on   = (int) YSTPAddonsSettingsRepo::get( 'switcher_floating_enabled', 0 );
$float_pos  = (string) YSTPAddonsSettingsRepo::get( 'switcher_floating_position', 'bottom-right' );

$switcher = new YSTPAddonsLanguageSwitcher();

// 取得各語言的 TranslatePress 預設名稱（暫時略過自訂覆寫），作為輸入框提示
YSTPAddonsLanguageSwitcher::$bypass_name_filter = true;
$default_names = YSTPAddonsTP::languages();
YSTPAddonsLanguageSwitcher::$bypass_name_filter = false;

$styles = [ 'dropdown' => '下拉選單', 'inline' => '並排清單', 'popup' => '彈出視窗', 'floating' => '固定浮動', 'map' => '世界地圖' ];
$shows  = [ 'both' => '旗幟 + 名稱', 'flag' => '只有旗幟', 'name' => '只有名稱', 'short' => '旗幟 + 短碼' ];
$poses  = [ 'bottom-right' => '右下', 'bottom-left' => '左下', 'top-right' => '右上', 'top-left' => '左上' ];
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">語言切換器</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 語言切換器</div>
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

    <div class="ystp-card-row">
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-admin-settings"></span> 設定</div>
            <div class="ystp-panel-body ystp-form" data-form="switcher">
                <div class="ystp-field">
                    <label><?php esc_html_e( '預設樣式（短代碼未指定時）', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="switcher_default_style">
                        <?php foreach ( $styles as $v => $l ) : ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $def_style, $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '顯示格式', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="switcher_show">
                        <?php foreach ( $shows as $v => $l ) : ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $show, $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr style="border:none;border-top:1px solid #eef1f4;margin:18px 0;" />
                <div class="ystp-field">
                    <label><?php esc_html_e( '全站浮動切換器', 'ys-translatepress-addons' ); ?></label>
                    <label class="ystp-toggle" style="margin-top:4px;">
                        <input type="checkbox" data-setting="switcher_floating_enabled" <?php checked( $float_on, 1 ); ?> />
                        <span class="ystp-slider"></span>
                    </label>
                    <p class="ystp-field-desc"><?php esc_html_e( '開啟後，前台每頁固定角落會自動顯示浮動語言切換器（免放短代碼）。', 'ys-translatepress-addons' ); ?></p>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '浮動位置', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="switcher_floating_position">
                        <?php foreach ( $poses as $v => $l ) : ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $float_pos, $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ystp-form-foot">
                    <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="switcher">
                        <span class="dashicons dashicons-saved"></span> 儲存設定
                    </button>
                </div>
            </div>
        </div>

        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-shortcode"></span> 短代碼用法</div>
            <div class="ystp-panel-body">
                <p><?php esc_html_e( '在任何文章、頁面或小工具中插入下方短代碼。不帶參數時使用上方「預設樣式」：', 'ys-translatepress-addons' ); ?></p>
                <p><code style="display:block;padding:10px 12px;background:#f4f6f8;border-radius:8px;">[ys_language_switcher]</code></p>
                <p class="ystp-field-desc" style="margin-top:12px;"><?php esc_html_e( '可指定參數 — style：dropdown／inline／popup／floating／map；show：both（旗幟+名稱）／flag／name／short。', 'ys-translatepress-addons' ); ?></p>
                <p class="ystp-field-desc"><?php esc_html_e( '範例：', 'ys-translatepress-addons' ); ?> <code>[ys_language_switcher style="inline" show="flag"]</code></p>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-translation"></span> 語言名稱自訂</div>
        <div class="ystp-panel-body ystp-form" data-form="langnames">
            <p class="ystp-field-desc" style="margin-top:0;"><?php esc_html_e( '自訂各語言在切換器、選單等處顯示的名稱（例如把「中文」改成「繁體中文」）。留空＝沿用 TranslatePress 預設；此名稱在所有語言版本下都顯示同一值。', 'ys-translatepress-addons' ); ?></p>
            <div class="ystp-langname-grid">
                <?php foreach ( $default_names as $code => $dname ) :
                    $lc  = strtolower( (string) $code );
                    $cur = (string) YSTPAddonsSettingsRepo::get( 'langname_' . $lc, '' );
                    ?>
                    <div class="ystp-field" style="margin-bottom:12px;">
                        <label><?php echo esc_html( (string) $code ); ?> <span class="ystp-muted" style="font-weight:400;">（<?php esc_html_e( '預設', 'ys-translatepress-addons' ); ?>：<?php echo esc_html( (string) $dname ); ?>）</span></label>
                        <input type="text" class="regular-text" style="width:100%;max-width:320px;margin-top:4px;" data-setting="langname_<?php echo esc_attr( $lc ); ?>" value="<?php echo esc_attr( $cur ); ?>" placeholder="<?php echo esc_attr( (string) $dname ); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="langnames">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-visibility"></span> 各樣式預覽與對應短代碼</div>
        <div class="ystp-panel-body">
            <p class="ystp-field-desc" style="margin-bottom:18px;"><?php esc_html_e( '以下為實際前台元件，可直接點擊操作；每個樣式下方為對應短代碼，點一下即可複製。', 'ys-translatepress-addons' ); ?></p>
            <div class="ystp-sc-grid">
                <?php
                $preview_styles = [
                    'dropdown' => '下拉選單',
                    'inline'   => '並排清單',
                    'popup'    => '彈出視窗',
                    'map'      => '世界地圖',
                    'floating' => '固定浮動',
                ];
                foreach ( $preview_styles as $skey => $stitle ) :
                    $sc = '[ys_language_switcher style="' . $skey . '"]';
                    ?>
                    <div class="ystp-sc-card">
                        <div class="ystp-sc-title"><?php echo esc_html( $stitle ); ?></div>
                        <div class="ystp-sc-preview">
                            <?php
                            if ( 'floating' === $skey ) {
                                echo '<span class="ystp-muted" style="font-size:12.5px;">' . esc_html__( '需於前台頁面才會固定在角落顯示', 'ys-translatepress-addons' ) . '</span>';
                            } else {
                                echo $switcher->render( $skey, $show, 'bottom-right' ); // phpcs:ignore
                            }
                            ?>
                        </div>
                        <code class="ystp-sc-code" data-copy="<?php echo esc_attr( $sc ); ?>" title="<?php esc_attr_e( '點一下複製', 'ys-translatepress-addons' ); ?>"><?php echo esc_html( $sc ); ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.ystp-sc-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
.ystp-sc-card{ border:1px solid #e3e8ec; border-radius:10px; padding:16px; background:#fff; }
.ystp-sc-title{ font-weight:600; color:#1f2d3d; margin-bottom:12px; font-size:13.5px; }
.ystp-sc-preview{ min-height:46px; margin-bottom:14px; display:flex; align-items:center; }
.ystp-sc-code{ display:block; padding:8px 10px; background:#f4f6f8; border:1px solid #e3e8ec; border-radius:7px; font-size:12px; cursor:pointer; word-break:break-all; transition:background .15s,border-color .15s; }
.ystp-sc-code:hover{ background:#eef2f5; border-color:#c4d0d8; }
.ystp-sc-code.copied{ background:#e7f0ed; border-color:#5b9a8b; }
</style>
