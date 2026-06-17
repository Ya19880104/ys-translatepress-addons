<?php
/**
 * TranslatePress 橋接層
 *
 * 封裝對 TranslatePress 核心的存取，集中所有與 TRP 互動的入口，
 * 讓各模組不需直接碰 TRP 內部結構。
 *
 * @package YangSheep\TPAddons\Support
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Support;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsTP {

    /** @var object|null TRP 主實例快取 */
    private static ?object $trp = null;

    /** @var array<string, object> TRP 元件快取 */
    private static array $components = [];

    /**
     * TranslatePress 是否可用
     */
    public static function is_available(): bool {
        return class_exists( 'TRP_Translate_Press' );
    }

    /**
     * 取得 TRP 主實例
     */
    public static function trp(): ?object {
        if ( ! self::is_available() ) {
            return null;
        }
        if ( null === self::$trp ) {
            self::$trp = \TRP_Translate_Press::get_trp_instance();
        }
        return self::$trp;
    }

    /**
     * 取得 TRP 元件（settings / languages / url_converter / translation_render…）
     */
    public static function component( string $name ): ?object {
        if ( isset( self::$components[ $name ] ) ) {
            return self::$components[ $name ];
        }
        $trp = self::trp();
        if ( ! $trp ) {
            return null;
        }
        $component = $trp->get_component( $name );
        if ( $component ) {
            self::$components[ $name ] = $component;
        }
        return $component;
    }

    /**
     * 取得 TRP 設定陣列
     *
     * @return array<string, mixed>
     */
    public static function settings(): array {
        $settings = get_option( 'trp_settings', [] );
        return is_array( $settings ) ? $settings : [];
    }

    /**
     * 目前頁面語言碼（例：en_US、zh_TW）
     */
    public static function current_language(): string {
        global $TRP_LANGUAGE;
        if ( ! empty( $TRP_LANGUAGE ) ) {
            return (string) $TRP_LANGUAGE;
        }
        return self::default_language();
    }

    /**
     * 預設語言碼
     */
    public static function default_language(): string {
        $settings = self::settings();
        return (string) ( $settings['default-language'] ?? get_locale() );
    }

    /**
     * 已發佈語言碼清單
     *
     * @return string[]
     */
    public static function published_language_codes(): array {
        $settings = self::settings();
        $codes    = $settings['publish-languages'] ?? [];
        return is_array( $codes ) ? array_values( $codes ) : [];
    }

    /**
     * 已發佈語言（code => 顯示名稱）
     *
     * @return array<string, string>
     */
    public static function languages(): array {
        $codes = self::published_language_codes();
        if ( empty( $codes ) ) {
            return [];
        }

        $languages_component = self::component( 'languages' );
        if ( $languages_component && method_exists( $languages_component, 'get_language_names' ) ) {
            $settings = self::settings();
            $type     = ( ( $settings['native_or_english_name'] ?? 'english_name' ) === 'english_name' )
                ? 'english_name'
                : 'native_name';
            $names = $languages_component->get_language_names( $codes, $type );
            if ( is_array( $names ) && ! empty( $names ) ) {
                return $names;
            }
        }

        // fallback：以語言碼當名稱
        return array_combine( $codes, $codes ) ?: [];
    }

    /**
     * 將任意 URL 轉為指定語言版本
     *
     * @param string      $language 目標語言碼
     * @param string|null $url      原始 URL（null = 當前頁）
     */
    public static function url_for_language( string $language, ?string $url = null ): string {
        $converter = self::component( 'url_converter' );
        if ( $converter && method_exists( $converter, 'get_url_for_language' ) ) {
            // 第三參數傳空字串，避免 TranslatePress 附加 #TRPLINKPROCESSED 標記
            $result = $converter->get_url_for_language( $language, $url, '' );
            if ( is_string( $result ) && '' !== $result ) {
                // 防禦性去除可能殘留的內部標記
                return str_replace( '#TRPLINKPROCESSED', '', $result );
            }
        }
        return $url ?? home_url( '/' );
    }

    /**
     * 取得指定語言的首頁 URL
     */
    public static function home_url_for_language( string $language ): string {
        return self::url_for_language( $language, home_url( '/' ) );
    }

    /**
     * 語言旗幟圖 URL
     */
    public static function flag_url( string $code ): string {
        $url = plugins_url( 'translatepress-multilingual/assets/images/flags/' . $code . '.png' );
        return (string) apply_filters( 'ys_tp_flag_url', $url, $code );
    }

    /**
     * 語言的原生自稱（例：zh_TW → 繁體中文、ja → 日本語、en_US → English）
     *
     * 不受 TranslatePress「英文名／原生名」設定影響，一律回原生名。
     */
    public static function language_native_name( string $code ): string {
        $component = self::component( 'languages' );
        if ( $component && method_exists( $component, 'get_language_names' ) ) {
            $names = $component->get_language_names( [ $code ], 'native_name' );
            if ( ! empty( $names[ $code ] ) ) {
                return (string) $names[ $code ];
            }
        }
        $all = self::languages();
        return $all[ $code ] ?? $code;
    }

    /**
     * 語言短碼（例：en_US → EN、zh_TW → ZH）
     */
    public static function short_code( string $code ): string {
        $slug = self::settings()['url-slugs'][ $code ] ?? '';
        if ( is_string( $slug ) && '' !== $slug ) {
            return strtoupper( $slug );
        }
        return strtoupper( (string) strtok( $code, '_' ) );
    }

    /**
     * 當前頁面完整 URL
     */
    public static function current_url(): string {
        $converter = self::component( 'url_converter' );
        if ( $converter && method_exists( $converter, 'cur_page_url' ) ) {
            $u = $converter->cur_page_url();
            if ( is_string( $u ) && '' !== $u ) {
                return $u;
            }
        }
        $req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        return home_url( $req );
    }

    /**
     * 語言切換器資料（當前頁面在各語言的 URL）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function switcher_items( ?string $current_url = null ): array {
        $current_url  = $current_url ?? self::current_url();
        $current_lang = self::current_language();

        $items = [];
        foreach ( self::languages() as $code => $name ) {
            $items[] = [
                'code'    => $code,
                'name'    => $name,
                'short'   => self::short_code( $code ),
                'url'     => self::url_for_language( $code, $current_url ),
                'flag'    => self::flag_url( $code ),
                'current' => ( $code === $current_lang ),
            ];
        }
        return $items;
    }
}
