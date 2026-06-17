<?php
/**
 * 模組設定頁：內容語言規則
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

$redirect_on = (int) YSTPAddonsSettingsRepo::get( 'content_redirect_enabled', 1 );
$fb_post     = (string) YSTPAddonsSettingsRepo::get( 'content_fb_post', 'post_archive' );
$fb_page     = (string) YSTPAddonsSettingsRepo::get( 'content_fb_page', 'home' );
$fb_default  = (string) YSTPAddonsSettingsRepo::get( 'content_fb_default', 'home' );

$pub_types = get_post_types( [ 'public' => true ], 'objects' );
unset( $pub_types['attachment'] );

$pages = get_posts( [ 'post_type' => 'page', 'numberposts' => 100, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ] );

/**
 * 輸出 fallback 下拉選單
 */
$render_fb = static function ( string $setting, string $current ) use ( $pages ) {
    $options = [
        'home'         => __( '該語言的首頁', 'ys-translatepress-addons' ),
        'post_archive' => __( '該語言的文章列表頁', 'ys-translatepress-addons' ),
    ];
    echo '<select data-setting="' . esc_attr( $setting ) . '">';
    foreach ( $options as $val => $label ) {
        echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    if ( $pages ) {
        echo '<optgroup label="' . esc_attr__( '指定頁面（該語言版本）', 'ys-translatepress-addons' ) . '">';
        foreach ( $pages as $p ) {
            $val = 'page:' . $p->ID;
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current, $val, false ) . '>' . esc_html( $p->post_title ) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';
};
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">內容語言規則</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> 內容語言規則</div>
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
        <div class="ystp-panel-head"><span class="dashicons dashicons-info-outline"></span> 運作方式</div>
        <div class="ystp-panel-body">
            <p>在每篇文章／頁面的編輯畫面右側「<strong>多語顯示規則</strong>」中，可選擇此內容要在哪些語言顯示。</p>
            <ul style="margin:0 0 4px 18px;line-height:1.9;">
                <li><strong>列表排除</strong>：切換到被隱藏的語言時，該內容自動從文章列表／封存／搜尋結果移除。</li>
                <li><strong>智慧重導</strong>：若訪客直接造訪被隱藏的單頁，會重導到下方設定的對應頁——且是<strong>當前語言</strong>的版本，而非回到預設語言。</li>
            </ul>
        </div>
    </div>

    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-admin-settings"></span> 規則設定</div>
        <div class="ystp-panel-body ystp-form" data-form="content">
            <div class="ystp-field">
                <label><?php esc_html_e( '啟用智慧重導', 'ys-translatepress-addons' ); ?></label>
                <label class="ystp-toggle" style="margin-top:4px;">
                    <input type="checkbox" data-setting="content_redirect_enabled" <?php checked( $redirect_on, 1 ); ?> />
                    <span class="ystp-slider"></span>
                </label>
                <p class="ystp-field-desc"><?php esc_html_e( '關閉後，被隱藏的單頁仍可直接造訪（僅從列表排除）。', 'ys-translatepress-addons' ); ?></p>
            </div>

            <div class="ystp-field">
                <label><?php esc_html_e( '套用的內容類型', 'ys-translatepress-addons' ); ?></label>
                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:4px;">
                    <?php foreach ( $pub_types as $pt ) :
                        $default = in_array( $pt->name, [ 'post', 'page' ], true ) ? 1 : 0;
                        $on      = (int) YSTPAddonsSettingsRepo::get( 'content_pt_' . $pt->name, $default );
                        ?>
                        <label style="display:inline-flex;align-items:center;gap:5px;">
                            <input type="checkbox" data-setting="content_pt_<?php echo esc_attr( $pt->name ); ?>" <?php checked( $on, 1 ); ?> />
                            <?php echo esc_html( $pt->labels->singular_name ); ?> <code style="font-size:11px;"><?php echo esc_html( $pt->name ); ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ystp-field" style="max-width:520px;">
                <label><?php esc_html_e( '文章被隱藏時，重導至', 'ys-translatepress-addons' ); ?></label>
                <?php $render_fb( 'content_fb_post', $fb_post ); ?>
                <p class="ystp-field-desc"><?php esc_html_e( '建議：該語言的文章列表頁。', 'ys-translatepress-addons' ); ?></p>
            </div>

            <div class="ystp-field" style="max-width:520px;">
                <label><?php esc_html_e( '頁面被隱藏時，重導至', 'ys-translatepress-addons' ); ?></label>
                <?php $render_fb( 'content_fb_page', $fb_page ); ?>
                <p class="ystp-field-desc"><?php esc_html_e( '建議：該語言的首頁。', 'ys-translatepress-addons' ); ?></p>
            </div>

            <div class="ystp-field" style="max-width:520px;">
                <label><?php esc_html_e( '其他內容類型被隱藏時，重導至', 'ys-translatepress-addons' ); ?></label>
                <?php $render_fb( 'content_fb_default', $fb_default ); ?>
            </div>

            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="content">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>
</div>
