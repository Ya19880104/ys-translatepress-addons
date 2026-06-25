<?php
/**
 * 模組：語言切換器（多樣式重設計）
 *
 * 短代碼 [ys_language_switcher]，支援四種樣式：
 *   dropdown（下拉）｜inline（並排）｜popup（彈出視窗）｜floating（固定浮動）
 * 並可於後台開啟「全站浮動切換器」自動注入頁尾。
 *
 * @package YangSheep\TPAddons\Modules\LanguageSwitcher
 * @since   0.2.0
 */

namespace YangSheep\TPAddons\Modules\LanguageSwitcher;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsLanguageSwitcher implements YSTPAddonsModuleInterface {

    /** @var bool 暫時略過自訂語言名稱（供後台取得 TranslatePress 預設名稱） */
    public static bool $bypass_name_filter = false;

    public function boot(): void {
        add_shortcode( 'ys_language_switcher', [ $this, 'shortcode' ] );

        // 自訂語言顯示名稱（套用於切換器、選單、hreflang 等所有 TP 語言名稱顯示處）
        add_filter( 'trp_language_name', [ self::class, 'filter_language_name' ], 20, 4 );

        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
            if ( (int) YSTPAddonsSettingsRepo::get( 'switcher_floating_enabled', 0 ) ) {
                add_action( 'wp_footer', [ $this, 'render_floating_auto' ] );
                // 啟用我們的浮動切換器時，抑制 TranslatePress 內建的浮動器，避免重複
                add_filter( 'trp_floating_ls_html', '__return_empty_string', 99 );
                add_filter( 'trp_floater_ls_html_v2', '__return_empty_string', 99 );
            }
        }
    }

    /**
     * 自訂語言顯示名稱（掛 TranslatePress `trp_language_name`）
     *
     * 優先序 20，晚於核心 beautify（10），確保自訂名稱為最終結果。
     * 設定鍵 `langname_{小寫語言碼}`，留空則沿用 TP 預設。
     *
     * @param string      $name              TranslatePress 計算出的名稱
     * @param string      $code              語言碼（如 zh_TW）
     * @param string|null $english_or_native english_name／native_name
     * @param array       $codes             查詢的語言碼陣列
     * @return string
     */
    public static function filter_language_name( $name, $code, $english_or_native = null, $codes = [] ) {
        if ( self::$bypass_name_filter ) {
            return $name;
        }
        $custom = (string) YSTPAddonsSettingsRepo::get( 'langname_' . strtolower( (string) $code ), '' );
        return '' !== trim( $custom ) ? $custom : (string) $name;
    }

    public function enqueue(): void {
        wp_enqueue_style(
            'ys-tp-switcher',
            YS_TP_PLUGIN_URL . 'assets/css/ys-tp-switcher.css',
            [],
            YS_TP_VERSION
        );
        wp_enqueue_script(
            'ys-tp-switcher',
            YS_TP_PLUGIN_URL . 'assets/js/ys-tp-switcher.js',
            [],
            YS_TP_VERSION,
            true
        );
    }

    /**
     * 短代碼處理
     */
    public function shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'style'    => YSTPAddonsSettingsRepo::get( 'switcher_default_style', 'dropdown' ),
            'show'     => YSTPAddonsSettingsRepo::get( 'switcher_show', 'both' ),
            'position' => 'bottom-right',
        ], $atts, 'ys_language_switcher' );

        return $this->render(
            sanitize_key( $atts['style'] ),
            sanitize_key( $atts['show'] ),
            sanitize_key( $atts['position'] )
        );
    }

    /**
     * 全站浮動切換器（頁尾自動注入）
     */
    public function render_floating_auto(): void {
        $show     = (string) YSTPAddonsSettingsRepo::get( 'switcher_show', 'both' );
        $position = (string) YSTPAddonsSettingsRepo::get( 'switcher_floating_position', 'bottom-right' );
        echo $this->render( 'floating', sanitize_key( $show ), sanitize_key( $position ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * 渲染切換器
     */
    public function render( string $style, string $show, string $position ): string {
        if ( ! YSTPAddonsTP::is_available() ) {
            return '';
        }
        $items = YSTPAddonsTP::switcher_items();
        if ( count( $items ) < 2 ) {
            return '';
        }

        $allowed_styles = [ 'dropdown', 'inline', 'popup', 'floating', 'map' ];
        if ( ! in_array( $style, $allowed_styles, true ) ) {
            $style = 'dropdown';
        }

        $current = null;
        foreach ( $items as $it ) {
            if ( $it['current'] ) {
                $current = $it;
                break;
            }
        }
        $current = $current ?: $items[0];

        switch ( $style ) {
            case 'inline':
                return $this->render_inline( $items, $show );
            case 'popup':
                return $this->render_popup( $items, $current, $show );
            case 'floating':
                return $this->render_floating( $items, $current, $show, $position );
            case 'map':
                return $this->render_map( $items, $current, $show );
            case 'dropdown':
            default:
                return $this->render_dropdown( $items, $current, $show );
        }
    }

    /* ───────────── 單一語言內容 ───────────── */

    private function item_inner( array $item, string $show ): string {
        $flag = '<img class="ystp-ls-flag" src="' . esc_url( $item['flag'] ) . '" alt="" loading="lazy" width="20" height="14" />';
        $name = '<span class="ystp-ls-name">' . esc_html( $item['name'] ) . '</span>';
        $shortc = '<span class="ystp-ls-short">' . esc_html( $item['short'] ) . '</span>';

        switch ( $show ) {
            case 'flag':
                return $flag;
            case 'name':
                return $name;
            case 'short':
                return $flag . $shortc;
            case 'both':
            default:
                return $flag . $name;
        }
    }

    private function links( array $items, string $show ): string {
        $html = '';
        foreach ( $items as $it ) {
            $html .= '<li class="ystp-ls-item' . ( $it['current'] ? ' is-current' : '' ) . '">'
                . '<a href="' . esc_url( $it['url'] ) . '" lang="' . esc_attr( $it['code'] ) . '"'
                . ( $it['current'] ? ' aria-current="true"' : '' ) . '>'
                . $this->item_inner( $it, $show )
                . '</a></li>';
        }
        return $html;
    }

    /* ───────────── 各樣式 ───────────── */

    private function render_dropdown( array $items, array $current, string $show ): string {
        return '<div class="ystp-ls ystp-ls--dropdown" data-ls-root>'
            . '<button type="button" class="ystp-ls-toggle" data-ls-toggle aria-haspopup="listbox" aria-expanded="false">'
            . $this->item_inner( $current, $show )
            . '<span class="ystp-ls-caret" aria-hidden="true"></span>'
            . '</button>'
            . '<ul class="ystp-ls-menu" role="listbox">' . $this->links( $items, $show ) . '</ul>'
            . '</div>';
    }

    private function render_inline( array $items, string $show ): string {
        return '<div class="ystp-ls ystp-ls--inline"><ul class="ystp-ls-list">'
            . $this->links( $items, $show ) . '</ul></div>';
    }

    private function render_popup( array $items, array $current, string $show ): string {
        return '<div class="ystp-ls ystp-ls--popup" data-ls-root>'
            . '<button type="button" class="ystp-ls-toggle" data-ls-popup-open>'
            . $this->item_inner( $current, $show )
            . '<span class="ystp-ls-caret" aria-hidden="true"></span></button>'
            . '<div class="ystp-ls-modal" data-ls-modal hidden>'
            . '<div class="ystp-ls-modal-backdrop" data-ls-popup-close></div>'
            . '<div class="ystp-ls-modal-box" role="dialog" aria-modal="true" aria-label="' . esc_attr__( '選擇語言', 'ys-translatepress-addons' ) . '">'
            . '<button type="button" class="ystp-ls-modal-close" data-ls-popup-close aria-label="' . esc_attr__( '關閉', 'ys-translatepress-addons' ) . '">&times;</button>'
            . '<div class="ystp-ls-modal-title">' . esc_html__( '選擇語言', 'ys-translatepress-addons' ) . '</div>'
            . '<ul class="ystp-ls-grid">' . $this->links( $items, $show ) . '</ul>'
            . '</div></div></div>';
    }

    private function render_floating( array $items, array $current, string $show, string $position ): string {
        $allowed_pos = [ 'bottom-right', 'bottom-left', 'top-right', 'top-left' ];
        if ( ! in_array( $position, $allowed_pos, true ) ) {
            $position = 'bottom-right';
        }
        return '<div class="ystp-ls ystp-ls--floating ystp-ls--pos-' . esc_attr( $position ) . '" data-ls-root>'
            . '<button type="button" class="ystp-ls-fab" data-ls-toggle aria-haspopup="listbox" aria-expanded="false">'
            . $this->item_inner( $current, $show )
            . '</button>'
            . '<ul class="ystp-ls-menu" role="listbox">' . $this->links( $items, $show ) . '</ul>'
            . '</div>';
    }

    /**
     * 地圖彈出視窗：世界地圖 + 語言 pin + 卡片
     */
    private function render_map( array $items, array $current, string $show ): string {
        $coords = self::map_coords();

        // 地圖上的 pin（有座標的語言）
        $pins = '';
        foreach ( $items as $it ) {
            if ( ! isset( $coords[ $it['code'] ] ) ) {
                continue;
            }
            [ $x, $y ] = $coords[ $it['code'] ];
            $pins .= '<a class="ystp-ls-pin' . ( $it['current'] ? ' is-current' : '' ) . '"'
                . ' style="left:' . esc_attr( (string) $x ) . '%;top:' . esc_attr( (string) $y ) . '%;"'
                . ' href="' . esc_url( $it['url'] ) . '" title="' . esc_attr( $it['name'] ) . '" lang="' . esc_attr( $it['code'] ) . '">'
                . '<img class="ystp-ls-flag" src="' . esc_url( $it['flag'] ) . '" alt="" width="22" height="15" loading="lazy" />'
                . '<span class="ystp-ls-pin-name">' . esc_html( $it['name'] ) . '</span>'
                . '</a>';
        }

        // 全部語言卡片（確保每個語言可選）
        $cards = '';
        foreach ( $items as $it ) {
            $cards .= '<li class="ystp-ls-item' . ( $it['current'] ? ' is-current' : '' ) . '">'
                . '<a href="' . esc_url( $it['url'] ) . '" lang="' . esc_attr( $it['code'] ) . '">'
                . $this->item_inner( $it, $show ) . '</a></li>';
        }

        return '<div class="ystp-ls ystp-ls--map" data-ls-root>'
            . '<button type="button" class="ystp-ls-toggle" data-ls-popup-open>'
            . '<span class="ystp-ls-globe" aria-hidden="true">' . self::globe_svg() . '</span>'
            . '<span class="ystp-ls-name">' . esc_html( $current['name'] ) . '</span>'
            . '<span class="ystp-ls-caret" aria-hidden="true"></span></button>'
            . '<div class="ystp-ls-modal" data-ls-modal hidden>'
            . '<div class="ystp-ls-modal-backdrop" data-ls-popup-close></div>'
            . '<div class="ystp-ls-modal-box ystp-ls-map-box" role="dialog" aria-modal="true" aria-label="' . esc_attr__( '選擇語言', 'ys-translatepress-addons' ) . '">'
            . '<button type="button" class="ystp-ls-modal-close" data-ls-popup-close aria-label="' . esc_attr__( '關閉', 'ys-translatepress-addons' ) . '">&times;</button>'
            . '<div class="ystp-ls-modal-title">' . esc_html__( '選擇你的語言', 'ys-translatepress-addons' ) . '</div>'
            . '<div class="ystp-ls-map">' . self::world_map_svg() . $pins . '</div>'
            . '<ul class="ystp-ls-grid ystp-ls-map-grid">' . $cards . '</ul>'
            . '</div></div></div>';
    }

    /**
     * 語言碼 → 地圖座標（x%, y%）。未列出者只出現在卡片區。
     *
     * @return array<string, array{0:float,1:float}>
     */
    private static function map_coords(): array {
        return [
            'en_US' => [ 19, 33 ], 'en_GB' => [ 46, 24 ], 'en_CA' => [ 21, 24 ], 'en_AU' => [ 85, 76 ],
            'fr_FR' => [ 48, 29 ], 'fr_CA' => [ 23, 26 ], 'de_DE' => [ 50, 26 ], 'de_CH' => [ 49, 28 ],
            'es_ES' => [ 45, 31 ], 'es_MX' => [ 17, 45 ], 'it_IT' => [ 50, 31 ], 'nl_NL' => [ 48, 25 ],
            'nl_BE' => [ 48, 26 ], 'pt_PT' => [ 44, 31 ], 'pt_BR' => [ 31, 70 ], 'ru_RU' => [ 66, 20 ],
            'pl_PL' => [ 52, 25 ], 'tr_TR' => [ 57, 32 ], 'ar' => [ 58, 40 ], 'he_IL' => [ 57, 37 ],
            'zh_CN' => [ 77, 35 ], 'zh_TW' => [ 82, 41 ], 'zh_HK' => [ 79, 43 ], 'ja' => [ 87, 36 ],
            'ko_KR' => [ 84, 34 ], 'th' => [ 77, 49 ], 'vi' => [ 79, 47 ], 'id_ID' => [ 81, 62 ],
            'hi_IN' => [ 70, 43 ], 'fa_IR' => [ 62, 36 ], 'el' => [ 54, 32 ], 'sv_SE' => [ 51, 18 ],
            'da_DK' => [ 50, 22 ], 'fi' => [ 54, 16 ], 'nb_NO' => [ 50, 18 ], 'cs_CZ' => [ 51, 27 ],
            'uk' => [ 57, 25 ], 'ro_RO' => [ 54, 29 ], 'hu_HU' => [ 53, 28 ],
        ];
    }

    /** 小地球 icon（按鈕用） */
    private static function globe_svg(): string {
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7">'
            . '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/></svg>';
    }

    /** 簡化世界地圖（低多邊形大陸，裝飾用） */
    private static function world_map_svg(): string {
        return '<svg class="ystp-ls-map-svg" viewBox="0 0 1000 500" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            . '<g fill="var(--ls-map-land,#cdd9e1)">'
            // North America
            . '<path d="M120,70 L260,60 L300,120 L250,150 L255,210 L200,250 L150,210 L110,150 L95,100 Z"/>'
            // Central / Mexico
            . '<path d="M210,250 L255,240 L270,300 L240,320 L215,295 Z"/>'
            // South America
            . '<path d="M270,310 L330,300 L355,360 L320,460 L285,455 L270,380 Z"/>'
            // Europe
            . '<path d="M455,200 L520,185 L560,210 L545,255 L495,265 L460,240 Z"/>'
            // Africa
            . '<path d="M470,275 L560,265 L600,340 L560,440 L500,455 L460,360 Z"/>'
            // Asia
            . '<path d="M560,150 L760,120 L880,170 L860,250 L780,270 L700,250 L620,260 L575,215 Z"/>'
            // India peninsula
            . '<path d="M675,255 L730,250 L725,320 L695,330 Z"/>'
            // SE Asia / Indonesia
            . '<path d="M770,300 L840,320 L860,360 L800,375 L765,345 Z"/>'
            // Australia
            . '<path d="M820,370 L900,360 L920,420 L850,445 L810,415 Z"/>'
            . '</g></svg>';
    }
}
