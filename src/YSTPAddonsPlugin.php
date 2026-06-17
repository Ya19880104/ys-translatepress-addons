<?php
/**
 * 主外掛類別（Singleton）
 *
 * 負責：schema 升級、模組載入（前後台共用）、後台元件初始化。
 *
 * @package YangSheep\TPAddons
 * @since   0.1.0
 */

namespace YangSheep\TPAddons;

use YangSheep\TPAddons\Admin\YSTPAddonsAdmin;
use YangSheep\TPAddons\Admin\YSTPAddonsAjaxHandler;
use YangSheep\TPAddons\Database\YSTPAddonsTableMaker;
use YangSheep\TPAddons\Support\YSTPAddonsModules;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsPlugin {

    /** @var self|null 單一實例 */
    private static ?self $instance = null;

    /** @var array<string, object> 已啟動的模組實例 */
    private array $modules = [];

    /**
     * 取得單一實例
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構子 — 私有，防止外部 new
     */
    private function __construct() {
        $this->maybe_upgrade_schema();

        add_action( 'init', [ $this, 'load_textdomain' ] );

        // 模組在前後台都需要啟動（解鎖、切換器、偵測等前台亦需作用）
        $this->boot_modules();

        if ( is_admin() ) {
            new YSTPAddonsAdmin();
            new YSTPAddonsAjaxHandler();
            ( new \YangSheep\TPAddons\Support\YSTPAddonsCompat() )->boot();
        }
    }

    /**
     * 載入翻譯檔
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'ys-translatepress-addons',
            false,
            dirname( YS_TP_BASENAME ) . '/languages'
        );
    }

    /**
     * 檢查 schema 版本，必要時升級資料表
     */
    private function maybe_upgrade_schema(): void {
        $current = get_option( 'ys_tp_schema_version', '0' );
        if ( version_compare( $current, YS_TP_VERSION, '<' ) ) {
            YSTPAddonsTableMaker::create_tables();
            update_option( 'ys_tp_schema_version', YS_TP_VERSION );
        }
    }

    /**
     * 啟動所有「已啟用且已實作」的模組
     */
    private function boot_modules(): void {
        foreach ( YSTPAddonsModules::enabled() as $key => $meta ) {
            $class = $meta['class'] ?? '';
            if ( $class && class_exists( $class ) ) {
                $module = new $class();
                if ( method_exists( $module, 'boot' ) ) {
                    $module->boot();
                }
                $this->modules[ $key ] = $module;
            }
        }
    }

    /**
     * 取得已啟動的模組實例
     */
    public function get_module( string $key ): ?object {
        return $this->modules[ $key ] ?? null;
    }
}
