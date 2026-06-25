<?php
/**
 * 模組：SEO 增強
 *
 * 補強 TranslatePress 的多語 SEO：
 * - SEO 中繼資料翻譯（頁面標題、meta 描述、OG／Twitter 社群標籤、圖片 alt）
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
        // SEO 中繼資料翻譯：讓 <title>、meta 描述、OG／Twitter 標籤、圖片 alt
        // 可在 TranslatePress 編輯器中逐語言翻譯（透過免費核心的 trp_node_accessors 機制）
        if ( (int) YSTPAddonsSettingsRepo::get( 'seo_meta_translate', 1 ) ) {
            add_filter( 'trp_node_accessors', [ $this, 'register_seo_node_accessors' ] );
        }

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

    /* ───────────── SEO 中繼資料節點 ───────────── */

    /**
     * 註冊 SEO 中繼資料的「節點存取器」
     *
     * TranslatePress 核心以 `trp_node_accessors` filter 決定哪些 DOM 節點可被翻譯，
     * 預設不含 <head> 內的 SEO 中繼資料。此處補上頁面標題、meta 描述、社群分享標籤
     * 與圖片 alt，使其在翻譯編輯器中可逐語言翻譯，前台輸出時自動替換為翻譯版本。
     *
     * 🔑 節點型別名稱必須沿用核心約定的鍵（`page_title`／`meta_desc`／`meta_desc_img`），
     * 核心 `trp_node_type_categories`／`trp_node_type_descriptions` 會據此自動將其歸入
     * 編輯器「Meta Information」群組並逐一標示（Page Title／Description／OG Title／
     * OG Site Name／OG Image／OG Image Alt／Twitter…）。用自訂鍵則會落入一般「String List」。
     *
     * @param array<string, array<string, mixed>> $accessors
     * @return array<string, array<string, mixed>>
     */
    public function register_seo_node_accessors( $accessors ) {
        if ( ! is_array( $accessors ) ) {
            return $accessors;
        }

        // 頁面標題 <title> → 群組「Meta Information / Page Title」
        $accessors['page_title'] = [
            'selector'  => 'title',
            'accessor'  => 'innertext',
            'attribute' => false,
        ];

        // meta 描述與 OG／Twitter 文字標籤 → 各自於「Meta Information」標示
        $accessors['meta_desc'] = [
            'selector'  => 'meta[name="description"],meta[property="og:title"],meta[property="og:description"],meta[property="og:site_name"],meta[property="og:image:alt"],meta[name="twitter:title"],meta[name="twitter:description"],meta[name="twitter:image:alt"],meta[name="DC.Title"],meta[name="DC.Description"],meta[property="article:section"],meta[property="article:tag"]',
            'accessor'  => 'content',
            'attribute' => true,
        ];

        // 社群分享圖片網址 → 群組「Meta Information / OG Image」
        $accessors['meta_desc_img'] = [
            'selector'  => 'meta[property="og:image"],meta[property="og:image:secure_url"],meta[name="twitter:image"]',
            'accessor'  => 'content',
            'attribute' => true,
        ];

        // 圖片替代文字 alt（內容圖片，列於 String List）
        $accessors['image_alt'] = [
            'selector'  => 'img[alt]',
            'accessor'  => 'alt',
            'attribute' => true,
        ];

        return $accessors;
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
