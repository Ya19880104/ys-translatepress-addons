<?php
/**
 * 相容性：衝突外掛自動停用
 *
 * 部分外掛提供與本外掛重疊的功能（解鎖語言、翻譯 slug、語言偵測、選單語言…），
 * 同時啟用會互相干擾。偵測到這類已知會衝突的外掛時自動停用，避免重複與衝突，
 * 並保留本外掛所需的 TranslatePress 核心。
 *
 * @package YangSheep\TPAddons\Support
 * @since   0.7.0
 */

namespace YangSheep\TPAddons\Support;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsCompat {

    /** 已知會與本外掛功能衝突的外掛（不含 translatepress-multilingual 核心） */
    private const CONFLICT_PLUGINS = [
        'translatepress-developer/index.php',
        'translatepress-business/index.php',
        'translatepress-personal/index.php',
    ];

    private const NOTICE_KEY = 'ys_tp_compat_deactivated';

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'maybe_deactivate' ] );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    }

    /**
     * 偵測並停用會衝突的外掛
     */
    public function maybe_deactivate(): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'compat_auto_disable', 1 ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $active = (array) get_option( 'active_plugins', [] );
        $hit    = [];
        foreach ( $active as $plugin ) {
            // 已知付費包，或舊式獨立 add-on（tp-add-on-*）
            if ( in_array( $plugin, self::CONFLICT_PLUGINS, true ) || 0 === strpos( $plugin, 'tp-add-on-' ) ) {
                $hit[] = $plugin;
            }
        }

        if ( ! empty( $hit ) ) {
            deactivate_plugins( $hit );
            // 停用後沿用其既有的翻譯 slug／選單語言設定，讓功能無縫延續
            $report = YSTPAddonsMigration::run();
            set_transient( self::NOTICE_KEY, [ 'hit' => $hit, 'report' => $report ], MINUTE_IN_SECONDS );
        }
    }

    /**
     * 顯示已停用通知
     */
    public function render_notice(): void {
        $data = get_transient( self::NOTICE_KEY );
        if ( empty( $data ) ) {
            return;
        }
        delete_transient( self::NOTICE_KEY );

        // 相容舊格式（純 $hit 陣列）與新格式（['hit'=>, 'report'=>]）
        $hit    = isset( $data['hit'] ) ? (array) $data['hit'] : (array) $data;
        $report = isset( $data['report'] ) && is_array( $data['report'] ) ? $data['report'] : [];

        $names = array_map( static fn( $p ) => dirname( (string) $p ), $hit );
        echo '<div class="notice notice-warning is-dismissible"><p><strong>'
            . esc_html__( 'YS 多語增強', 'ys-translatepress-addons' ) . '：</strong> ';
        printf(
            /* translators: %s: 被停用的外掛清單 */
            esc_html__( '已自動停用與本外掛功能重疊的外掛（%s），以避免同時啟用造成衝突。如需自行管理，可至「多語增強 → 總覽 → 相容性」關閉「衝突外掛自動停用」。', 'ys-translatepress-addons' ),
            '<code>' . esc_html( implode( '、', $names ) ) . '</code>'
        );

        // 沿用既有設定的結果摘要
        $migrated = self::format_report( $report );
        if ( '' !== $migrated ) {
            echo '<br>' . esc_html__( '已沿用既有設定：', 'ys-translatepress-addons' ) . esc_html( $migrated );
        }
        echo '</p></div>';
    }

    /**
     * 將遷移報告整理為摘要文字
     *
     * @param array<string, mixed> $report
     */
    public static function format_report( array $report ): string {
        $parts = [];
        if ( ! empty( $report['slug'] ) ) {
            $parts[] = sprintf(
                /* translators: %d: 沿用的翻譯網址 slug 數量 */
                __( '翻譯網址 slug %d 筆', 'ys-translatepress-addons' ),
                (int) $report['slug']
            );
        }
        if ( ! empty( $report['menu'] ) ) {
            $parts[] = sprintf(
                /* translators: %d: 沿用的選單項目數量 */
                __( '選單語言 %d 項', 'ys-translatepress-addons' ),
                (int) $report['menu']
            );
        }
        return implode( '、', $parts );
    }
}
