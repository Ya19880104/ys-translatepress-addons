<?php
/**
 * TranslatePress 後台介面精簡
 *
 * 可選擇隱藏 TranslatePress 在後台設定頁與翻譯編輯器顯示的升級／推廣區塊，
 * 讓後台介面更精簡。透過注入 CSS 隱藏既知的升級區塊容器（不更動 TP 任何邏輯），
 * 並移除外掛列表的推廣連結。預設關閉，由「總覽 → 相容性」開關控制。
 *
 * @package YangSheep\TPAddons\Support
 * @since   0.9.0
 */

namespace YangSheep\TPAddons\Support;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsTPCleanup {

    /** TranslatePress 升級／推廣區塊的容器選擇器（編輯器 + 後台 + 授權頁） */
    private const UPSELL_SELECTORS = '.trp-upsell-section,.trp-upsell-section-container,.trp-upsell-string-translation,.trp-upsell-button,.trp-upgrade-notice,.trp-upgrade-notice-button,.trp-upgrade-notice-table,.trp-license-page-upsell-container,.trp-ai-upsell-arrow,.trp-ai-upsell-body';

    public function boot(): void {
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'hide_tp_upsell', 0 ) ) {
            return;
        }

        // 後台 TranslatePress 設定／授權頁
        add_action( 'admin_head', [ $this, 'print_css' ] );

        // 翻譯編輯器 + 字串翻譯編輯器（前台模式）— 掛 TranslatePress 自身的
        // 編輯器 footer 動作，僅在編輯器情境觸發，無需猜測 URL 參數
        add_action( 'trp_translation_manager_footer', [ $this, 'print_css' ], 99 );
        add_action( 'trp_string_translation_editor_footer', [ $this, 'print_css' ], 99 );

        // 外掛列表的推廣連結
        add_filter( 'plugin_action_links_translatepress-multilingual/index.php', [ $this, 'remove_pro_link' ], 100 );
    }

    public function print_css(): void {
        echo '<style id="ys-tp-hide-upsell">' . self::UPSELL_SELECTORS . '{display:none !important;}</style>' . "\n";
    }

    /**
     * 移除外掛列表中 TranslatePress 的推廣連結
     *
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public function remove_pro_link( array $links ): array {
        unset( $links['go_pro'] );
        return $links;
    }
}
