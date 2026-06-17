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

$def_style  = (string) YSTPAddonsSettingsRepo::get( 'switcher_default_style', 'dropdown' );
$show       = (string) YSTPAddonsSettingsRepo::get( 'switcher_show', 'both' );
$float_on   = (int) YSTPAddonsSettingsRepo::get( 'switcher_floating_enabled', 0 );
$float_pos  = (string) YSTPAddonsSettingsRepo::get( 'switcher_floating_position', 'bottom-right' );

$switcher = new YSTPAddonsLanguageSwitcher();

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
                <p><?php esc_html_e( '在任何文章、頁面或小工具中插入：', 'ys-translatepress-addons' ); ?></p>
                <p><code style="display:block;padding:10px 12px;background:#f4f6f8;border-radius:8px;">[ys_language_switcher]</code></p>
                <p style="margin-top:14px;"><?php esc_html_e( '可指定參數：', 'ys-translatepress-addons' ); ?></p>
                <ul class="ystp-langlist" style="font-size:13px;">
                    <li><code>[ys_language_switcher style="dropdown"]</code></li>
                    <li><code>[ys_language_switcher style="inline" show="flag"]</code></li>
                    <li><code>[ys_language_switcher style="popup" show="both"]</code></li>
                </ul>
                <p class="ystp-field-desc"><?php esc_html_e( 'style：dropdown／inline／popup／floating；show：both／flag／name／short', 'ys-translatepress-addons' ); ?></p>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-visibility"></span> 即時預覽</div>
        <div class="ystp-panel-body">
            <div style="display:flex;flex-wrap:wrap;gap:40px;align-items:flex-start;">
                <div>
                    <p class="ystp-muted" style="margin-bottom:8px;font-weight:600;">下拉選單</p>
                    <?php echo $switcher->render( 'dropdown', $show, 'bottom-right' ); // phpcs:ignore ?>
                </div>
                <div>
                    <p class="ystp-muted" style="margin-bottom:8px;font-weight:600;">並排清單</p>
                    <?php echo $switcher->render( 'inline', $show, 'bottom-right' ); // phpcs:ignore ?>
                </div>
                <div>
                    <p class="ystp-muted" style="margin-bottom:8px;font-weight:600;">彈出視窗</p>
                    <?php echo $switcher->render( 'popup', $show, 'bottom-right' ); // phpcs:ignore ?>
                </div>
                <div>
                    <p class="ystp-muted" style="margin-bottom:8px;font-weight:600;">世界地圖</p>
                    <?php echo $switcher->render( 'map', $show, 'bottom-right' ); // phpcs:ignore ?>
                </div>
            </div>
            <p class="ystp-field-desc" style="margin-top:18px;"><?php esc_html_e( '註：以上為實際前台元件，可直接點擊操作。「固定浮動」樣式需於前台頁面才會固定在角落。', 'ys-translatepress-addons' ); ?></p>
        </div>
    </div>
</div>
