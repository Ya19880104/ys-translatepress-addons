<?php
/**
 * 模組：SEO 增強
 *
 * 補強 TranslatePress 的多語 SEO：
 * - hreflang 細控（地區標籤、地區無關標籤、x-default）
 * - Yoast／RankMath sitemap 加入語言 alternate（外掛存在時才掛載）
 *
 * 註：免費版 TranslatePress 已在 <head> 輸出基本 hreflang，本模組提供控制與補強。
 *
 * @package YangSheep\TPAddons\Modules\Seo
 * @since   0.4.0
 */

namespace YangSheep\TPAddons\Modules\Seo;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsSeo implements YSTPAddonsModuleInterface {

    public function boot(): void {
        // hreflang 地區／地區無關標籤開關（掛 TranslatePress 既有 filter）
        add_filter( 'trp_add_country_hreflang_tags', [ $this, 'filter_region_tags' ] );
        add_filter( 'trp_add_region_independent_hreflang_tags', [ $this, 'filter_region_independent_tags' ] );

        // 翻譯網址 slug（可開關）
        if ( (int) YSTPAddonsSettingsRepo::get( 'seo_slug_enabled', 0 ) ) {
            ( new YSTPAddonsSlugTranslator() )->boot();
        }

        if ( ! is_admin() ) {
            // 自訂 x-default（TP 預設關閉時由我們補上）
            add_action( 'wp_head', [ $this, 'output_xdefault' ], 1 );

            // sitemap alternate（外掛存在才掛）
            add_filter( 'wpseo_sitemap_url', [ $this, 'yoast_sitemap_alternates' ], 10, 2 );
            add_filter( 'rank_math/sitemap/url', [ $this, 'rankmath_sitemap_alternates' ], 10, 2 );
        }
    }

    public function filter_region_tags( $default ) {
        return (bool) (int) YSTPAddonsSettingsRepo::get( 'seo_hreflang_region', 1 );
    }

    public function filter_region_independent_tags( $default ) {
        return (bool) (int) YSTPAddonsSettingsRepo::get( 'seo_hreflang_region_independent', 1 );
    }

    /* ───────────── x-default ───────────── */

    public function output_xdefault(): void {
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'seo_xdefault_enabled', 0 ) ) {
            return;
        }
        // 若 TP 自身已啟用 x-default，避免重複輸出
        $tp_adv = YSTPAddonsTP::settings()['trp_advanced_settings']['enable_hreflang_xdefault'] ?? '';
        if ( $tp_adv && 'disabled' !== $tp_adv ) {
            return;
        }

        $lang = (string) YSTPAddonsSettingsRepo::get( 'seo_xdefault_lang', YSTPAddonsTP::default_language() );
        if ( ! in_array( $lang, YSTPAddonsTP::published_language_codes(), true ) ) {
            $lang = YSTPAddonsTP::default_language();
        }

        $url = YSTPAddonsTP::url_for_language( $lang, YSTPAddonsTP::current_url() );
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '" />' . "\n";
    }

    /* ───────────── Sitemap alternates ───────────── */

    /**
     * 為 Yoast sitemap 的每個 <url> 加入語言 alternate
     *
     * @param string $output url XML 區塊
     * @param array  $url    url 資料
     */
    public function yoast_sitemap_alternates( $output, $url ) {
        $loc = is_array( $url ) ? ( $url['loc'] ?? '' ) : '';
        return $this->inject_alternates( (string) $output, (string) $loc );
    }

    public function rankmath_sitemap_alternates( $output, $url ) {
        $loc = is_array( $url ) ? ( $url['loc'] ?? '' ) : '';
        return $this->inject_alternates( (string) $output, (string) $loc );
    }

    /**
     * 在單一 <url> 區塊內插入各語言 xhtml:link alternate
     */
    private function inject_alternates( string $output, string $loc ): string {
        if ( '' === $output || '' === $loc ) {
            return $output;
        }
        $links = '';
        foreach ( YSTPAddonsTP::published_language_codes() as $code ) {
            $hreflang = str_replace( '_', '-', $code );
            $href     = YSTPAddonsTP::url_for_language( $code, $loc );
            $links   .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"" . esc_attr( $hreflang ) . '" href="' . esc_url( $href ) . "\" />\n";
        }
        if ( '' === $links ) {
            return $output;
        }
        // 在 </url> 前插入
        return str_replace( '</url>', $links . "\t</url>", $output );
    }
}
