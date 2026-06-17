<?php
/**
 * 後台管理 — 選單框架
 *
 * 主選單「多語增強」+ 依模組註冊表動態產生子選單（僅顯示已實作模組）。
 *
 * @package YangSheep\TPAddons\Admin
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Admin;

use YangSheep\TPAddons\Support\YSTPAddonsModules;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsAdmin {

    /** @var string 主選單 slug */
    public const MENU_SLUG = 'ys-tp-addons';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * 註冊選單
     */
    public function register_menu(): void {
        add_menu_page(
            __( '多語增強', 'ys-translatepress-addons' ),
            __( '多語增強', 'ys-translatepress-addons' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_overview' ],
            'dashicons-translation',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( '總覽', 'ys-translatepress-addons' ),
            __( '總覽', 'ys-translatepress-addons' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_overview' ]
        );

        // 依模組註冊表動態加入子選單（僅顯示已實作 class 的模組）
        foreach ( YSTPAddonsModules::all() as $key => $meta ) {
            if ( empty( $meta['class'] ) ) {
                continue;
            }
            add_submenu_page(
                self::MENU_SLUG,
                (string) $meta['label'],
                (string) $meta['label'],
                'manage_options',
                (string) $meta['page'],
                function () use ( $key ) {
                    $this->render_module( $key );
                }
            );
        }
    }

    /**
     * 載入後台 CSS / JS（僅本外掛頁面）
     */
    public function enqueue_assets( string $hook ): void {
        $screen = get_current_screen();
        // 涵蓋主選單（toplevel_page_ys-tp-addons）與所有子頁（..._page_ys-tp-*）
        if ( ! $screen || false === strpos( (string) $screen->id, 'ys-tp-' ) ) {
            return;
        }

        wp_enqueue_style(
            'ys-tp-admin',
            YS_TP_PLUGIN_URL . 'assets/css/ys-tpaddons-admin.css',
            [],
            YS_TP_VERSION
        );

        wp_enqueue_script(
            'ys-tp-admin',
            YS_TP_PLUGIN_URL . 'assets/js/ys-tpaddons-admin.js',
            [ 'jquery' ],
            YS_TP_VERSION,
            true
        );

        wp_localize_script( 'ys-tp-admin', 'ysTPAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ys_tp_nonce' ),
            'i18n'     => [
                'saving'  => __( '儲存中…', 'ys-translatepress-addons' ),
                'saved'   => __( '已儲存', 'ys-translatepress-addons' ),
                'error'   => __( '操作失敗，請重試', 'ys-translatepress-addons' ),
            ],
        ] );

        // 語言切換器設定頁：載入前台切換器資源以提供即時預覽
        if ( false !== strpos( (string) $screen->id, 'ys-tp-switcher' ) ) {
            wp_enqueue_style( 'ys-tp-switcher', YS_TP_PLUGIN_URL . 'assets/css/ys-tp-switcher.css', [], YS_TP_VERSION );
            wp_enqueue_script( 'ys-tp-switcher', YS_TP_PLUGIN_URL . 'assets/js/ys-tp-switcher.js', [], YS_TP_VERSION, true );
        }

        // 語言自動偵測設定頁：載入提示卡樣式以提供預覽
        if ( false !== strpos( (string) $screen->id, 'ys-tp-detect' ) ) {
            wp_enqueue_style( 'ys-tp-detect', YS_TP_PLUGIN_URL . 'assets/css/ys-tp-detect.css', [], YS_TP_VERSION );
        }
    }

    /**
     * 渲染總覽頁
     */
    public function render_overview(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( '你沒有足夠的權限。', 'ys-translatepress-addons' ) );
        }
        $modules = YSTPAddonsModules::all();
        $status  = YSTPAddonsModules::status();
        $template = YS_TP_PLUGIN_DIR . 'templates/admin/overview.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * 渲染單一模組設定頁
     */
    public function render_module( string $key ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( '你沒有足夠的權限。', 'ys-translatepress-addons' ) );
        }

        $meta     = YSTPAddonsModules::get( $key );
        $enabled  = YSTPAddonsModules::is_enabled( $key );
        $template = YS_TP_PLUGIN_DIR . 'templates/admin/' . $key . '.php';

        if ( $meta && file_exists( $template ) ) {
            include $template;
            return;
        }

        // 後備：簡單佔位
        echo '<div class="wrap"><h1>' . esc_html( $meta['label'] ?? '' ) . '</h1>';
        echo '<p>' . esc_html__( '此模組設定頁尚未提供。', 'ys-translatepress-addons' ) . '</p></div>';
    }
}
