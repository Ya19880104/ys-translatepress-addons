<?php
/**
 * 翻譯網址 slug（URL rewrite）
 *
 * 以 post meta 實作翻譯網址 slug：
 * - Outbound：掛 trp_get_url_for_language，把原始 slug 換成翻譯 slug
 * - Inbound：parse_request 之前修改 $_SERVER['REQUEST_URI']，把翻譯 slug 換回原始 slug，
 *            讓 WordPress 原生解析（含階層頁面）
 * - 後台：文章/頁面編輯頁 meta box，逐語言設定翻譯 slug（meta `_ys_tp_slug_{locale}`）
 *
 * 作為 SEO 模組的可開關子功能（seo_slug_enabled）。
 *
 * @package YangSheep\TPAddons\Modules\Seo
 * @since   0.5.0
 */

namespace YangSheep\TPAddons\Modules\Seo;

use YangSheep\TPAddons\Support\YSTPAddonsTP;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsSlugTranslator {

    /** meta key 前綴（後接 locale，如 _ys_tp_slug_zh_TW） */
    public const META_PREFIX = '_ys_tp_slug_';

    /** @var array<string, string> outbound 快取：「原始slug|locale」=>翻譯slug */
    private array $out_cache = [];

    public function boot(): void {
        if ( is_admin() ) {
            add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
            add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        } else {
            // Outbound：TranslatePress 產生語言 URL 時，把原始 slug 換成翻譯 slug
            add_filter( 'trp_get_url_for_language', [ $this, 'outbound_translate' ], 10, 3 );
        }
        // Inbound 由主檔在 plugins_loaded priority 2（早於 TranslatePress）呼叫 run_inbound()
    }

    /**
     * Inbound 入口（主檔早期 hook 呼叫）
     *
     * 必須早於 TranslatePress 處理 URL，故不走模組 boot（boot 在 plugins_loaded 11）。
     */
    public static function run_inbound(): void {
        ( new self() )->inbound_rewrite();
    }

    /* ───────────── 語言 slug 對應 ───────────── */

    /** url-slug（zh）=> locale（zh_TW） */
    private function slug_to_locale(): array {
        $map  = [];
        $slugs = YSTPAddonsTP::settings()['url-slugs'] ?? [];
        foreach ( YSTPAddonsTP::published_language_codes() as $code ) {
            $s = $slugs[ $code ] ?? '';
            if ( is_string( $s ) && '' !== $s ) {
                $map[ $s ] = $code;
            }
        }
        return $map;
    }

    /* ───────────── Outbound ───────────── */

    /**
     * @param string $new_url  TP 已產生的語言 URL
     * @param string $url      原始 URL
     * @param string $language 目標語言 locale
     */
    public function outbound_translate( $new_url, $url = '', $language = '' ) {
        if ( '' === $language || $language === YSTPAddonsTP::default_language() || empty( $new_url ) ) {
            return $new_url;
        }
        $parts = wp_parse_url( $new_url );
        $path  = $parts['path'] ?? '';
        if ( '' === $path || '/' === $path ) {
            return $new_url;
        }

        $segments = explode( '/', trim( $path, '/' ) );
        $changed  = false;
        foreach ( $segments as $i => $seg ) {
            if ( '' === $seg ) {
                continue;
            }
            $translated = $this->translated_for_original( rawurldecode( $seg ), $language );
            if ( '' !== $translated ) {
                $segments[ $i ] = rawurlencode( $translated );
                $changed        = true;
            }
        }
        if ( ! $changed ) {
            return $new_url;
        }

        $new_path = '/' . implode( '/', $segments ) . '/';
        // 保留原 query / fragment
        $suffix = '';
        if ( ! empty( $parts['query'] ) ) {
            $suffix .= '?' . $parts['query'];
        }
        return str_replace( $path, $new_path, $new_url );
    }

    /**
     * 由原始 slug 查該語言翻譯 slug（含快取）
     */
    private function translated_for_original( string $original_slug, string $locale ): string {
        $ck = $original_slug . '|' . $locale;
        if ( isset( $this->out_cache[ $ck ] ) ) {
            return $this->out_cache[ $ck ];
        }

        global $wpdb;
        // 找出 post_name = 此原始 slug 且設有翻譯 slug 的文章
        $meta_key = self::META_PREFIX . $locale;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_name = %s AND pm.meta_value <> ''
             LIMIT 1",
            $meta_key,
            $original_slug
        ) );

        $result = is_string( $val ) ? $val : '';
        $this->out_cache[ $ck ] = $result;
        return $result;
    }

    /* ───────────── Inbound ───────────── */

    public function inbound_rewrite(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || empty( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $uri  = wp_unslash( $_SERVER['REQUEST_URI'] );
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        if ( '' === $path ) {
            return;
        }

        // 去掉子目錄安裝的 home path
        $home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        $work      = trim( $path, '/' );
        if ( '' !== $home_path && 0 === strpos( $work, $home_path ) ) {
            $work = trim( substr( $work, strlen( $home_path ) ), '/' );
        }
        if ( '' === $work ) {
            return;
        }

        $segments = explode( '/', $work );

        // 第一段是否為語言 url-slug
        $map    = $this->slug_to_locale();
        $prefix = $segments[0];
        if ( ! isset( $map[ $prefix ] ) ) {
            return; // 非語言前綴（預設語言或非多語路徑）
        }
        $locale = $map[ $prefix ];
        if ( $locale === YSTPAddonsTP::default_language() ) {
            return;
        }

        // 反查：翻譯 slug → 原始 slug（語言前綴之後的段落）
        $changed = false;
        for ( $i = 1; $i < count( $segments ); $i++ ) {
            $seg = rawurldecode( $segments[ $i ] );
            if ( '' === $seg ) {
                continue;
            }
            $original = $this->original_for_translated( $seg, $locale );
            if ( '' !== $original ) {
                $segments[ $i ] = rawurlencode( $original );
                $changed        = true;
            }
        }

        if ( $changed ) {
            $new_path = '/' . ( '' !== $home_path ? $home_path . '/' : '' ) . implode( '/', $segments ) . '/';
            $query    = (string) wp_parse_url( $uri, PHP_URL_QUERY );
            $_SERVER['REQUEST_URI'] = $new_path . ( '' !== $query ? '?' . $query : '' );
        }
    }

    /**
     * 由翻譯 slug 反查原始 slug
     */
    private function original_for_translated( string $translated_slug, string $locale ): string {
        global $wpdb;
        $meta_key = self::META_PREFIX . $locale;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $post_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.post_name FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE pm.meta_key = %s AND pm.meta_value = %s
             AND p.post_status IN ('publish','private') LIMIT 1",
            $meta_key,
            $translated_slug
        ) );
        return is_string( $post_name ) ? $post_name : '';
    }

    /* ───────────── 後台 Meta Box ───────────── */

    public function add_meta_box(): void {
        $types = get_post_types( [ 'public' => true ], 'names' );
        unset( $types['attachment'] );
        foreach ( $types as $pt ) {
            add_meta_box(
                'ys_tp_slug',
                __( '多語網址 slug（多語增強）', 'ys-translatepress-addons' ),
                [ $this, 'render_meta_box' ],
                $pt,
                'normal',
                'low'
            );
        }
    }

    public function render_meta_box( $post ): void {
        $default = YSTPAddonsTP::default_language();
        $langs   = YSTPAddonsTP::languages();
        wp_nonce_field( 'ys_tp_slug_' . $post->ID, 'ys_tp_slug_nonce' );
        ?>
        <p class="description" style="margin:.2em 0 1em;">
            <?php
            printf(
                /* translators: %s: 原始 slug */
                esc_html__( '為各語言設定自訂網址 slug（留空＝沿用原始 slug「%s」）。', 'ys-translatepress-addons' ),
                esc_html( $post->post_name )
            );
            ?>
        </p>
        <table class="form-table" role="presentation"><tbody>
        <?php foreach ( $langs as $code => $name ) :
            if ( $code === $default ) {
                continue;
            }
            $val = (string) get_post_meta( $post->ID, self::META_PREFIX . $code, true );
            ?>
            <tr>
                <th style="width:180px;"><label for="ys-tp-slug-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?> <code><?php echo esc_html( $code ); ?></code></label></th>
                <td>
                    <input type="text" id="ys-tp-slug-<?php echo esc_attr( $code ); ?>" name="ys_tp_slug[<?php echo esc_attr( $code ); ?>]"
                        value="<?php echo esc_attr( $val ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $post->post_name ); ?>" />
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    public function save_meta( $post_id, $post ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( empty( $_POST['ys_tp_slug_nonce'] ) ||
            ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ys_tp_slug_nonce'] ) ), 'ys_tp_slug_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $input = isset( $_POST['ys_tp_slug'] ) && is_array( $_POST['ys_tp_slug'] )
            ? wp_unslash( $_POST['ys_tp_slug'] )
            : [];

        $default = YSTPAddonsTP::default_language();
        foreach ( YSTPAddonsTP::published_language_codes() as $code ) {
            if ( $code === $default ) {
                continue;
            }
            $raw = isset( $input[ $code ] ) ? sanitize_title( (string) $input[ $code ] ) : '';
            if ( '' === $raw ) {
                delete_post_meta( $post_id, self::META_PREFIX . $code );
                continue;
            }
            $unique = $this->make_unique( $raw, $code, $post_id );
            update_post_meta( $post_id, self::META_PREFIX . $code, $unique );
        }
    }

    /**
     * 確保翻譯 slug 在同語言內唯一（衝突時加 -2、-3…）
     */
    private function make_unique( string $slug, string $locale, int $post_id ): string {
        global $wpdb;
        $meta_key = self::META_PREFIX . $locale;
        $candidate = $slug;
        $suffix    = 2;
        while ( true ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $clash = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id <> %d",
                $meta_key,
                $candidate,
                $post_id
            ) );
            // 同時避免撞到其他文章的原始 slug（造成 inbound 後仍可能誤判）
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $clash_name = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND ID <> %d AND post_status IN ('publish','private')",
                $candidate,
                $post_id
            ) );
            if ( 0 === $clash && 0 === $clash_name ) {
                return $candidate;
            }
            $candidate = $slug . '-' . $suffix;
            $suffix++;
            if ( $suffix > 50 ) {
                return $candidate;
            }
        }
    }
}
