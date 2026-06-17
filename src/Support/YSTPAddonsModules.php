<?php
/**
 * 模組註冊表
 *
 * 集中定義所有功能模組的中繼資料與啟用狀態。
 * 啟用狀態存在自訂設定表（key = module_status）。
 *
 * @package YangSheep\TPAddons\Support
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Support;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsModules {

    /** @var string 設定表中存放啟用狀態的鍵名 */
    private const STATUS_KEY = 'module_status';

    /** @var array<string, bool>|null 啟用狀態快取 */
    private static ?array $status_cache = null;

    /**
     * 所有模組的中繼資料
     *
     * class 為 null 表示「規劃中／尚未實作」，僅在總覽展示藍圖，不會 boot。
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array {
        return [
            'unlock_languages' => [
                'label'   => __( '解鎖語言數量', 'ys-translatepress-addons' ),
                'desc'    => __( '移除免費版「僅 1 個次要語言」的限制，可新增無限多語言。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-translation',
                'page'    => 'ys-tp-languages',
                'class'   => \YangSheep\TPAddons\Modules\UnlockLanguages\YSTPAddonsUnlockLanguages::class,
                'default' => true,
                'phase'   => 0,
            ],
            'language_switcher' => [
                'label'   => __( '語言切換器', 'ys-translatepress-addons' ),
                'desc'    => __( '全新設計的語言切換短代碼：固定浮動、彈出視窗、下拉選單等多種樣式。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-admin-site-alt3',
                'page'    => 'ys-tp-switcher',
                'class'   => \YangSheep\TPAddons\Modules\LanguageSwitcher\YSTPAddonsLanguageSwitcher::class,
                'default' => true,
                'phase'   => 1,
            ],
            'auto_detect' => [
                'label'   => __( '語言自動偵測', 'ys-translatepress-addons' ),
                'desc'    => __( '依瀏覽器語言自動建議語言，重新設計的精緻提示介面。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-location-alt',
                'page'    => 'ys-tp-detect',
                'class'   => \YangSheep\TPAddons\Modules\AutoDetect\YSTPAddonsAutoDetect::class,
                'default' => false,
                'phase'   => 1,
            ],
            'menu_language' => [
                'label'   => __( '選單語言控制', 'ys-translatepress-addons' ),
                'desc'    => __( '針對每個選單項目設定在哪些語言顯示或隱藏。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-menu',
                'page'    => 'ys-tp-menu',
                'class'   => \YangSheep\TPAddons\Modules\MenuLanguage\YSTPAddonsMenuLanguage::class,
                'default' => true,
                'phase'   => 1,
            ],
            'content_rules' => [
                'label'   => __( '內容語言規則', 'ys-translatepress-addons' ),
                'desc'    => __( '頁面／文章／自訂內容可排除或指定語言顯示，切換語言時自動重導至該語言的對應頁。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-filter',
                'page'    => 'ys-tp-content',
                'class'   => \YangSheep\TPAddons\Modules\ContentRules\YSTPAddonsContentRules::class,
                'default' => true,
                'phase'   => 1,
            ],
            'ai_translate' => [
                'label'   => __( 'AI 翻譯', 'ys-translatepress-addons' ),
                'desc'    => __( '支援 OpenAI／Gemini／Claude，提供自動、手動逐頁、全站與排程背景翻譯。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-superhero',
                'page'    => 'ys-tp-ai',
                'class'   => \YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsAiTranslate::class,
                'default' => false,
                'phase'   => 2,
            ],
            'seo' => [
                'label'   => __( 'SEO 增強', 'ys-translatepress-addons' ),
                'desc'    => __( 'hreflang 細控（地區標籤／x-default）與多語 sitemap（相容 Yoast／RankMath）。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-search',
                'page'    => 'ys-tp-seo',
                'class'   => \YangSheep\TPAddons\Modules\Seo\YSTPAddonsSeo::class,
                'default' => false,
                'phase'   => 3,
            ],
            'import_export' => [
                'label'   => __( '翻譯匯出／匯入', 'ys-translatepress-addons' ),
                'desc'    => __( '全站或單頁匯出翻譯文字，外部編修後再匯入。', 'ys-translatepress-addons' ),
                'icon'    => 'dashicons-database-export',
                'page'    => 'ys-tp-io',
                'class'   => \YangSheep\TPAddons\Modules\ImportExport\YSTPAddonsImportExport::class,
                'default' => false,
                'phase'   => 3,
            ],
        ];
    }

    /**
     * 取得單一模組中繼資料
     *
     * @return array<string, mixed>|null
     */
    public static function get( string $key ): ?array {
        $all = self::all();
        return $all[ $key ] ?? null;
    }

    /**
     * 取得所有模組的啟用狀態（合併預設值）
     *
     * @return array<string, bool>
     */
    public static function status(): array {
        if ( null !== self::$status_cache ) {
            return self::$status_cache;
        }

        $stored = YSTPAddonsSettingsRepo::get( self::STATUS_KEY, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        $status = [];
        foreach ( self::all() as $key => $meta ) {
            $status[ $key ] = isset( $stored[ $key ] )
                ? (bool) $stored[ $key ]
                : (bool) ( $meta['default'] ?? false );
        }

        self::$status_cache = $status;
        return $status;
    }

    /**
     * 模組是否啟用
     */
    public static function is_enabled( string $key ): bool {
        $status = self::status();
        return ! empty( $status[ $key ] );
    }

    /**
     * 取得「已啟用且已實作（class 非 null）」的模組，供 boot 使用
     *
     * @return array<string, array<string, mixed>>
     */
    public static function enabled(): array {
        $result = [];
        foreach ( self::all() as $key => $meta ) {
            if ( ! empty( $meta['class'] ) && self::is_enabled( $key ) ) {
                $result[ $key ] = $meta;
            }
        }
        return $result;
    }

    /**
     * 設定模組啟用狀態
     */
    public static function set_status( string $key, bool $enabled ): void {
        $stored = YSTPAddonsSettingsRepo::get( self::STATUS_KEY, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        $stored[ $key ] = $enabled;
        YSTPAddonsSettingsRepo::set( self::STATUS_KEY, $stored );
        self::$status_cache = null; // 清快取
    }
}
