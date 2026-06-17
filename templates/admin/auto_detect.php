<?php
/**
 * 模組設定頁：語言自動偵測
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

$behavior = (string) YSTPAddonsSettingsRepo::get( 'detect_behavior', 'prompt' );
$style    = (string) YSTPAddonsSettingsRepo::get( 'detect_style', 'card' );
$position = (string) YSTPAddonsSettingsRepo::get( 'detect_position', 'bottom-right' );
$msg_t    = (string) YSTPAddonsSettingsRepo::get( 'detect_msg_title', '' );
$msg_a    = (string) YSTPAddonsSettingsRepo::get( 'detect_msg_accept', '' );

// 預覽用：取一個「非當前語言」的語言當示範
$langs   = YSTPAddonsTP::languages();
$current = YSTPAddonsTP::default_language();
$demo_code = '';
$demo_name = '';
foreach ( $langs as $code => $name ) {
    if ( $code !== $current ) {
        $demo_code = $code;
        $demo_name = $name;
        break;
    }
}
$demo_flag  = $demo_code ? YSTPAddonsTP::flag_url( $demo_code ) : '';
$demo_title = $msg_t !== '' ? $msg_t : sprintf( '要以「%s」瀏覽本網站嗎？', $demo_name );
$demo_accept = $msg_a !== '' ? $msg_a : sprintf( '切換到 %s', $demo_name );
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">語言自動偵測</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 語言自動偵測</div>
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
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-admin-settings"></span> 設定</div>
            <div class="ystp-panel-body ystp-form" data-form="detect">
                <div class="ystp-field">
                    <label><?php esc_html_e( '提示行為', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="detect_behavior">
                        <option value="prompt" <?php selected( $behavior, 'prompt' ); ?>><?php esc_html_e( '詢問訪客（顯示提示，由訪客決定）', 'ys-translatepress-addons' ); ?></option>
                        <option value="auto" <?php selected( $behavior, 'auto' ); ?>><?php esc_html_e( '自動切換（首次到訪直接切換）', 'ys-translatepress-addons' ); ?></option>
                    </select>
                    <p class="ystp-field-desc"><?php esc_html_e( '建議使用「詢問訪客」，較友善且不影響 SEO。', 'ys-translatepress-addons' ); ?></p>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '提示樣式', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="detect_style">
                        <option value="card" <?php selected( $style, 'card' ); ?>><?php esc_html_e( '角落提示卡', 'ys-translatepress-addons' ); ?></option>
                        <option value="bar" <?php selected( $style, 'bar' ); ?>><?php esc_html_e( '頂部橫幅', 'ys-translatepress-addons' ); ?></option>
                    </select>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '提示卡位置', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="detect_position">
                        <option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>><?php esc_html_e( '右下', 'ys-translatepress-addons' ); ?></option>
                        <option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>><?php esc_html_e( '左下', 'ys-translatepress-addons' ); ?></option>
                    </select>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '提示標題（留空使用預設）', 'ys-translatepress-addons' ); ?></label>
                    <input type="text" data-setting="detect_msg_title" value="<?php echo esc_attr( $msg_t ); ?>" placeholder="要以「中文」瀏覽本網站嗎？" style="max-width:100%;" />
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '切換按鈕文字（留空使用預設）', 'ys-translatepress-addons' ); ?></label>
                    <input type="text" data-setting="detect_msg_accept" value="<?php echo esc_attr( $msg_a ); ?>" placeholder="切換到 中文" style="max-width:280px;" />
                </div>
                <div class="ystp-form-foot">
                    <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="detect">
                        <span class="dashicons dashicons-saved"></span> 儲存設定
                    </button>
                </div>
            </div>
        </div>

        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-visibility"></span> 提示卡預覽</div>
            <div class="ystp-panel-body">
                <p class="ystp-muted" style="margin-bottom:16px;"><?php esc_html_e( '訪客首次到訪、且瀏覽器語言與當前頁面不同時，會看到：', 'ys-translatepress-addons' ); ?></p>
                <?php if ( $demo_code ) : ?>
                <div style="background:#eef2f5;border-radius:12px;padding:30px;display:flex;justify-content:center;">
                    <div class="ystp-ald ystp-ald--card is-in" style="position:static;transform:none;">
                        <div class="ystp-ald-card-head">
                            <img class="ystp-ald-flag" src="<?php echo esc_url( $demo_flag ); ?>" alt="" width="26" height="18" />
                            <div class="ystp-ald-card-title"><?php echo esc_html( $demo_title ); ?></div>
                        </div>
                        <div class="ystp-ald-card-actions">
                            <button type="button" class="ystp-ald-btn ystp-ald-accept" style="flex:1;"><?php echo esc_html( $demo_accept ); ?></button>
                            <button type="button" class="ystp-ald-btn ystp-ald-dismiss"><?php esc_html_e( '維持目前語言', 'ys-translatepress-addons' ); ?></button>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                    <p class="ystp-muted"><?php esc_html_e( '需要至少兩個語言才能預覽。', 'ys-translatepress-addons' ); ?></p>
                <?php endif; ?>
                <p class="ystp-field-desc" style="margin-top:18px;"><span class="dashicons dashicons-shield" style="color:#5b9a8b;"></span> <?php esc_html_e( '完全由 JavaScript 驅動，搜尋引擎爬蟲不受影響，不會造成重複內容問題。', 'ys-translatepress-addons' ); ?></p>
            </div>
        </div>
    </div>
</div>
