<?php
/**
 * Plugin Name: YS TranslatePress Addons
 * Plugin URI:  https://yangsheep.com.tw
 * Description: TranslatePress 多語言強化套件 — 解鎖語言數量、SEO、AI 翻譯（OpenAI／Gemini／Claude）、選單語言控制、語言自動偵測、語言切換器、內容語言規則與翻譯匯出匯入。
 * Version:     0.11.1
 * Author:      YANGSHEEP DESIGN
 * Author URI:  https://yangsheep.com.tw
 * License:     GPL-2.0-or-later
 * Text Domain: ys-translatepress-addons
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: translatepress-multilingual
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

/* ──────────────────────────────────────────────
 * 常數定義
 * ────────────────────────────────────────────── */
define( 'YS_TP_VERSION', '0.11.1' );
define( 'YS_TP_PLUGIN_FILE', __FILE__ );
define( 'YS_TP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_TP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_TP_BASENAME', plugin_basename( __FILE__ ) );

/* ──────────────────────────────────────────────
 * Vendor autoload（Hub Client）
 * ────────────────────────────────────────────── */
if ( file_exists( YS_TP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once YS_TP_PLUGIN_DIR . 'vendor/autoload.php';
}

/* ──────────────────────────────────────────────
 * Fallback PSR-4 Autoloader
 * 永遠註冊自身 namespace，不放 else 分支
 * ────────────────────────────────────────────── */
spl_autoload_register( function ( $class ) {
    $prefix   = 'YangSheep\\TPAddons\\';
    $base_dir = YS_TP_PLUGIN_DIR . 'src/';
    $len      = strlen( $prefix );

    if ( 0 !== strncmp( $prefix, $class, $len ) ) {
        return;
    }

    $relative = substr( $class, $len );
    $file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/* ──────────────────────────────────────────────
 * 翻譯 slug Inbound（priority 2，早於 TranslatePress 處理 URL）
 *
 * 必須在 TP 解析語言路由前把翻譯 slug 換回原始 slug，故獨立於模組系統
 * （模組於 plugins_loaded 11 才 boot，太晚）。
 * ────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'TRP_Translate_Press' ) ) {
        return;
    }
    if ( \YangSheep\TPAddons\Support\YSTPAddonsModules::is_enabled( 'seo' )
        && (int) \YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo::get( 'seo_slug_enabled', 0 ) ) {
        \YangSheep\TPAddons\Modules\Seo\YSTPAddonsSlugTranslator::run_inbound();
    }
}, 2 );

/* ──────────────────────────────────────────────
 * Hub Client 註冊（priority 5，比其他 hook 早）
 * ────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
        \YangSheep\PluginHubClient\YSPluginHubClient::register( array(
            'slug'        => 'ys-translatepress-addons',
            'version'     => YS_TP_VERSION,
            'plugin_file' => __FILE__,
            'name'        => 'YS TranslatePress Addons',
        ) );
    }
}, 5 );

/* ──────────────────────────────────────────────
 * Activation — 建立資料表
 * ────────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    \YangSheep\TPAddons\Database\YSTPAddonsTableMaker::create_tables();
} );

/* ──────────────────────────────────────────────
 * Deactivation — 清除背景排程
 * ────────────────────────────────────────────── */
register_deactivation_hook( __FILE__, function () {
    if ( class_exists( '\YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsCron' ) ) {
        \YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsCron::clear();
    }
} );

/* ──────────────────────────────────────────────
 * 主外掛初始化（priority 11，在 Hub Client 與 TranslatePress 之後）
 *
 * 依賴檢查：未偵測到 TranslatePress 時顯示提示且不初始化。
 * ────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'TRP_Translate_Press' ) ) {
        add_action( 'admin_notices', function () {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }
            $install = wp_nonce_url(
                self_admin_url( 'update.php?action=install-plugin&plugin=translatepress-multilingual' ),
                'install-plugin_translatepress-multilingual'
            );
            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: TranslatePress 安裝連結 */
                esc_html__( '【YS TranslatePress Addons】需要先安裝並啟用 %s 才能運作。', 'ys-translatepress-addons' ),
                '<a href="' . esc_url( $install ) . '">TranslatePress Multilingual</a>'
            );
            echo '</p></div>';
        } );
        return;
    }

    \YangSheep\TPAddons\YSTPAddonsPlugin::instance();
}, 11 );

/* ──────────────────────────────────────────────
 * 外掛動作連結（設定頁快捷）
 * ────────────────────────────────────────────── */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    $url = admin_url( 'admin.php?page=ys-tp-addons' );
    array_unshift(
        $links,
        '<a href="' . esc_url( $url ) . '">' . esc_html__( '設定', 'ys-translatepress-addons' ) . '</a>'
    );
    return $links;
} );
