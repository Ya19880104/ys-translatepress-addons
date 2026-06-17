<?php
/**
 * 模組設定頁：解鎖語言數量
 *
 * @var array<string, mixed> $meta    模組中繼資料
 * @var bool                 $enabled 是否啟用
 * @var string               $key     模組鍵
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Modules\UnlockLanguages\YSTPAddonsUnlockLanguages;

$langs   = YSTPAddonsTP::languages();
$default = YSTPAddonsTP::default_language();
$limit   = YSTPAddonsUnlockLanguages::current_limit();
$tp_settings_url = admin_url( 'admin.php?page=translate-press' );
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">解鎖語言數量</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 解鎖語言數量</div>
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
            此模組目前為停用狀態，語言數量限制仍維持免費版的上限。請至
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-tp-addons' ) ); ?>">總覽</a> 啟用後生效。
        </div>
    <?php endif; ?>

    <div class="ystp-card-row">
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-unlock"></span> 解鎖狀態</div>
            <div class="ystp-panel-body">
                <p>免費版 TranslatePress 僅允許 <strong>1 個</strong> 次要語言。啟用本模組後，可新增的次要語言上限提高為：</p>
                <div class="ystp-bignum"><?php echo esc_html( (string) $limit ); ?> <span>種</span></div>
                <p class="ystp-muted">完全在地端運作、不需授權金鑰。</p>
                <a class="ystp-btn ystp-btn-primary" href="<?php echo esc_url( $tp_settings_url ); ?>">
                    <span class="dashicons dashicons-admin-site"></span> 前往 TranslatePress 新增語言
                </a>
            </div>
        </div>

        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-translation"></span> 目前已啟用語言（<?php echo esc_html( (string) count( $langs ) ); ?>）</div>
            <div class="ystp-panel-body">
                <ul class="ystp-langlist">
                    <?php foreach ( $langs as $code => $name ) : ?>
                        <li>
                            <span class="ystp-langlist-name"><?php echo esc_html( $name ); ?></span>
                            <code><?php echo esc_html( $code ); ?></code>
                            <?php if ( $code === $default ) : ?>
                                <span class="ystp-tag">預設</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-admin-settings"></span> 進階設定</div>
        <div class="ystp-panel-body ystp-form" data-form="unlock">
            <div class="ystp-field">
                <label for="ystp-unlock-max">解鎖上限</label>
                <input type="number" id="ystp-unlock-max" min="2" max="100000" step="1"
                    data-setting="unlock_max" value="<?php echo esc_attr( (string) $limit ); ?>" />
                <p class="ystp-field-desc">允許新增的次要語言數量上限（預設 1000，一般無需更動）。</p>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="unlock">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>
</div>
