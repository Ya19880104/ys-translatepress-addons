<?php
/**
 * 後台總覽頁
 *
 * @var array<string, array<string, mixed>> $modules 全部模組中繼資料
 * @var array<string, bool>                  $status  啟用狀態
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Support\YSTPAddonsTP;

$tp_langs   = YSTPAddonsTP::languages();
$tp_default = YSTPAddonsTP::default_language();
$enabled_count = count( array_filter( $status ) );
$impl_count    = count( array_filter( $modules, static fn( $m ) => ! empty( $m['class'] ) ) );
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">YS TranslatePress Addons</h1>

    <div class="ystp-hero">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title">多語增強</div>
            <div class="ystp-hero-sub">TranslatePress 增強套件 · 由 YANGSHEEP CLOUD 開發與維護</div>
        </div>
        <div class="ystp-hero-side">
            <span class="ystp-version">v<?php echo esc_html( YS_TP_VERSION ); ?></span>
            <span class="ystp-pill ystp-pill-on">
                <span class="ystp-dot"></span>TranslatePress 已連結
            </span>
        </div>
    </div>

    <div class="ystp-stats">
        <div class="ystp-stat">
            <span class="ystp-stat-num"><?php echo esc_html( (string) count( $tp_langs ) ); ?></span>
            <span class="ystp-stat-label">啟用語言</span>
        </div>
        <div class="ystp-stat">
            <span class="ystp-stat-num"><?php echo esc_html( (string) $enabled_count ); ?></span>
            <span class="ystp-stat-label">已啟用模組</span>
        </div>
        <div class="ystp-stat">
            <span class="ystp-stat-num"><?php echo esc_html( $impl_count . ' / ' . count( $modules ) ); ?></span>
            <span class="ystp-stat-label">可用功能</span>
        </div>
        <div class="ystp-stat ystp-stat-langs">
            <span class="ystp-stat-label">目前語言</span>
            <span class="ystp-langchips">
                <?php foreach ( $tp_langs as $code => $name ) : ?>
                    <span class="ystp-chip<?php echo $code === $tp_default ? ' is-default' : ''; ?>">
                        <?php echo esc_html( $name ); ?>
                    </span>
                <?php endforeach; ?>
            </span>
        </div>
    </div>

    <h2 class="ystp-section-title">功能模組</h2>
    <p class="ystp-section-desc">開啟需要的功能；每個模組可獨立啟用或停用。標示「即將推出」者將於後續版本提供。</p>

    <div class="ystp-grid">
        <?php foreach ( $modules as $key => $meta ) :
            $is_impl = ! empty( $meta['class'] );
            $is_on   = ! empty( $status[ $key ] );
            ?>
            <div class="ystp-card<?php echo $is_on ? ' is-on' : ''; ?><?php echo $is_impl ? '' : ' is-soon'; ?>" data-module="<?php echo esc_attr( $key ); ?>">
                <div class="ystp-card-head">
                    <span class="ystp-card-icon dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span>
                    <label class="ystp-toggle">
                        <input type="checkbox" class="ystp-module-toggle"
                            data-module="<?php echo esc_attr( $key ); ?>"
                            <?php checked( $is_on ); ?>
                            <?php disabled( ! $is_impl ); ?> />
                        <span class="ystp-slider"></span>
                    </label>
                </div>
                <h3 class="ystp-card-title"><?php echo esc_html( $meta['label'] ); ?></h3>
                <p class="ystp-card-desc"><?php echo esc_html( $meta['desc'] ); ?></p>
                <div class="ystp-card-foot">
                    <?php if ( $is_impl ) : ?>
                        <a class="ystp-card-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $meta['page'] ) ); ?>">
                            前往設定 <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    <?php else : ?>
                        <span class="ystp-badge-soon">即將推出</span>
                    <?php endif; ?>
                    <span class="ystp-phase">Phase <?php echo esc_html( (string) $meta['phase'] ); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php $compat_on = (int) \YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo::get( 'compat_auto_disable', 1 ); ?>
    <div class="ystp-panel" style="margin-top:26px;">
        <div class="ystp-panel-head"><span class="dashicons dashicons-shield-alt"></span> 相容性</div>
        <div class="ystp-panel-body ystp-form" data-form="compat">
            <div class="ystp-field" style="margin-bottom:0;">
                <label><?php esc_html_e( '衝突外掛自動停用', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="compat_auto_disable" <?php checked( $compat_on, 1 ); ?> /><span class="ystp-slider"></span></label>
                <p class="ystp-field-desc"><?php esc_html_e( '偵測到與本外掛功能重疊的多語外掛啟用時自動停用，避免同時運作造成衝突。', 'ys-translatepress-addons' ); ?></p>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="compat">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>
</div>
