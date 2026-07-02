<?php
/**
 * 模組：內容語言規則（排除／指定顯示 + 智慧重導）
 *
 * - 每篇文章／頁面／CPT 可設定：所有語言顯示｜排除指定語言｜只在指定語言顯示
 * - 前台列表（封存／部落格／搜尋等）自動排除「當前語言被隱藏」的內容
 * - 若直接造訪被隱藏的單頁，依後台分別指定的 fallback 重導到「該語言」的對應頁
 *   （文章 → 該語言文章列表、頁面 → 該語言首頁，皆為當前語言版本而非預設語言）
 *
 * @package YangSheep\TPAddons\Modules\ContentRules
 * @since   0.2.0
 */

namespace YangSheep\TPAddons\Modules\ContentRules;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsContentRules implements YSTPAddonsModuleInterface {

    public const META_MODE = '_ys_tp_content_lang_mode';  // all | exclude | include
    public const META_LIST = '_ys_tp_content_lang_list';  // array of language codes
    private const CACHE_PREFIX = 'ys_tp_hidden_';

    public function boot(): void {
        if ( is_admin() ) {
            add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
            add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
            // 列表語言可用性欄位（小國旗 + 綠勾）
            add_action( 'admin_init', [ $this, 'register_lang_columns' ] );
        } else {
            add_action( 'pre_get_posts', [ $this, 'exclude_from_lists' ] );
            add_action( 'template_redirect', [ $this, 'maybe_redirect' ] );
            // 供自訂列表(非 WP_Query／已存 ID 陣列／頁面建構器迴圈)顯式過濾用
            add_filter( 'ys_tp_filter_ids', [ $this, 'filter_ids_hook' ], 10, 2 );
            add_filter( 'ys_tp_is_hidden', [ $this, 'is_hidden_hook' ], 10, 2 );
        }
    }

    /* ───────────── 設定讀取 ───────────── */

    /** @return string[] 套用規則的 post types（依各別 content_pt_{type} 設定） */
    private function post_types(): array {
        $candidates = get_post_types( [ 'public' => true ], 'names' );
        unset( $candidates['attachment'] );

        $result = [];
        foreach ( $candidates as $pt ) {
            $default = in_array( $pt, [ 'post', 'page' ], true ) ? 1 : 0;
            if ( (int) YSTPAddonsSettingsRepo::get( 'content_pt_' . $pt, $default ) ) {
                $result[] = $pt;
            }
        }
        return $result ?: [ 'post', 'page' ];
    }

    private function redirect_enabled(): bool {
        return (bool) (int) YSTPAddonsSettingsRepo::get( 'content_redirect_enabled', 1 );
    }

    /* ───────────── 後台 Meta Box ───────────── */

    public function register_meta_box(): void {
        foreach ( $this->post_types() as $pt ) {
            add_meta_box(
                'ys_tp_content_rules',
                __( '多語顯示規則', 'ys-translatepress-addons' ),
                [ $this, 'render_meta_box' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    public function render_meta_box( $post ): void {
        $languages = YSTPAddonsTP::languages();
        $mode      = get_post_meta( $post->ID, self::META_MODE, true ) ?: 'all';
        $list      = get_post_meta( $post->ID, self::META_LIST, true );
        $list      = is_array( $list ) ? $list : [];

        wp_nonce_field( 'ys_tp_content_' . $post->ID, 'ys_tp_content_nonce' );
        ?>
        <div class="ys-tp-cr">
            <p style="margin:.2em 0 .6em;">
                <label><input type="radio" name="ys_tp_cr_mode" value="all" <?php checked( $mode, 'all' ); ?> /> <?php esc_html_e( '所有語言顯示', 'ys-translatepress-addons' ); ?></label><br />
                <label><input type="radio" name="ys_tp_cr_mode" value="exclude" <?php checked( $mode, 'exclude' ); ?> /> <?php esc_html_e( '排除以下語言', 'ys-translatepress-addons' ); ?></label><br />
                <label><input type="radio" name="ys_tp_cr_mode" value="include" <?php checked( $mode, 'include' ); ?> /> <?php esc_html_e( '只在以下語言顯示', 'ys-translatepress-addons' ); ?></label>
            </p>
            <div class="ys-tp-cr-langs" style="<?php echo 'all' === $mode ? 'display:none;' : ''; ?>padding:6px 0 2px;border-top:1px solid #eee;">
                <?php foreach ( $languages as $code => $name ) : ?>
                    <label style="display:block;margin:3px 0;">
                        <input type="checkbox" name="ys_tp_cr_list[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $list, true ) ); ?> />
                        <?php echo esc_html( $name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="description" style="margin-top:6px;"><?php esc_html_e( '切換到被隱藏的語言時，此內容會從列表移除；若直接造訪則重導至設定的對應頁。', 'ys-translatepress-addons' ); ?></p>
        </div>
        <script>
        ( function () {
            var box = document.getElementById( 'ys_tp_content_rules' );
            if ( ! box ) return;
            box.addEventListener( 'change', function ( e ) {
                if ( e.target.name === 'ys_tp_cr_mode' ) {
                    box.querySelector( '.ys-tp-cr-langs' ).style.display = ( e.target.value === 'all' ) ? 'none' : 'block';
                }
            } );
        } )();
        </script>
        <?php
    }

    public function save_meta( $post_id, $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( empty( $_POST['ys_tp_content_nonce'] ) ||
            ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ys_tp_content_nonce'] ) ), 'ys_tp_content_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $mode = isset( $_POST['ys_tp_cr_mode'] ) ? sanitize_key( wp_unslash( $_POST['ys_tp_cr_mode'] ) ) : 'all';
        if ( ! in_array( $mode, [ 'all', 'exclude', 'include' ], true ) ) {
            $mode = 'all';
        }

        if ( 'all' === $mode ) {
            delete_post_meta( $post_id, self::META_MODE );
            delete_post_meta( $post_id, self::META_LIST );
        } else {
            $valid = array_keys( YSTPAddonsTP::languages() );
            $list  = [];
            if ( isset( $_POST['ys_tp_cr_list'] ) && is_array( $_POST['ys_tp_cr_list'] ) ) {
                $list = array_values( array_intersect(
                    array_map( 'sanitize_text_field', wp_unslash( $_POST['ys_tp_cr_list'] ) ),
                    $valid
                ) );
            }
            update_post_meta( $post_id, self::META_MODE, $mode );
            update_post_meta( $post_id, self::META_LIST, $list );
        }

        $this->flush_cache();
    }

    /* ───────────── 列表：語言可用性欄位 ───────────── */

    /**
     * 於各套用內容類型的列表加入「語言」欄（admin_init 時 CPT 已註冊完畢）
     */
    public function register_lang_columns(): void {
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'content_lang_column', 1 ) ) {
            return;
        }
        foreach ( $this->post_types() as $pt ) {
            add_filter( "manage_edit-{$pt}_columns", [ $this, 'add_lang_column' ] );
            add_action( "manage_{$pt}_posts_custom_column", [ $this, 'render_lang_column' ], 10, 2 );
        }
        add_action( 'admin_head', [ $this, 'lang_column_css' ] );
    }

    /**
     * 在「日期」欄前插入「語言」欄
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function add_lang_column( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $new['ys_tp_langs'] = __( '語言', 'ys-translatepress-addons' );
            }
            $new[ $key ] = $label;
        }
        if ( ! isset( $new['ys_tp_langs'] ) ) {
            $new['ys_tp_langs'] = __( '語言', 'ys-translatepress-addons' );
        }
        return $new;
    }

    /**
     * 渲染語言欄：各語言小國旗，可見語言帶綠勾、被隱藏語言灰階
     */
    public function render_lang_column( $column, $post_id ): void {
        if ( 'ys_tp_langs' !== $column ) {
            return;
        }
        $langs = YSTPAddonsTP::languages();
        if ( empty( $langs ) ) {
            echo '—';
            return;
        }
        echo '<span class="ys-tp-langcol">';
        foreach ( $langs as $code => $name ) {
            $hidden = self::is_hidden( (int) $post_id, $code );
            $title  = $name . ( $hidden
                ? '（' . __( '不顯示', 'ys-translatepress-addons' ) . '）'
                : '（' . __( '顯示', 'ys-translatepress-addons' ) . '）' );
            printf(
                '<span class="ys-tp-lf %s" title="%s"><img src="%s" alt="" width="18" height="12" loading="lazy" />%s</span>',
                $hidden ? 'is-off' : 'is-on',
                esc_attr( $title ),
                esc_url( YSTPAddonsTP::flag_url( $code ) ),
                $hidden ? '' : '<i class="ys-tp-lf-ck" aria-hidden="true"></i>' // phpcs:ignore
            );
        }
        echo '</span>';
    }

    /**
     * 語言欄樣式（僅在套用類型的列表頁輸出）
     */
    public function lang_column_css(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, $this->post_types(), true ) ) {
            return;
        }
        echo '<style>'
            . '.column-ys_tp_langs{width:118px;}'
            . '.ys-tp-langcol{display:inline-flex;flex-wrap:wrap;gap:7px;align-items:center;}'
            . '.ys-tp-lf{position:relative;display:inline-flex;line-height:0;}'
            . '.ys-tp-lf img{border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.12);}'
            . '.ys-tp-lf.is-off img{filter:grayscale(1);opacity:.35;}'
            . '.ys-tp-lf-ck{position:absolute;right:-5px;bottom:-5px;width:12px;height:12px;background:#46b450;border-radius:50%;border:1.5px solid #fff;box-shadow:0 1px 2px rgba(0,0,0,.2);}'
            . '.ys-tp-lf-ck::after{content:"";position:absolute;left:3.5px;top:1.5px;width:3px;height:5px;border:solid #fff;border-width:0 1.5px 1.5px 0;transform:rotate(45deg);}'
            . '</style>';
    }

    /* ───────────── 隱藏判斷 ───────────── */

    /**
     * 某內容在指定語言是否被隱藏
     */
    public static function is_hidden( int $post_id, string $lang ): bool {
        $mode = get_post_meta( $post_id, self::META_MODE, true );
        if ( ! $mode || 'all' === $mode ) {
            return false;
        }
        $list = get_post_meta( $post_id, self::META_LIST, true );
        $list = is_array( $list ) ? $list : [];

        if ( 'exclude' === $mode ) {
            return in_array( $lang, $list, true );
        }
        if ( 'include' === $mode ) {
            return ! in_array( $lang, $list, true );
        }
        return false;
    }

    /**
     * 取得某語言要隱藏的 post IDs（含快取）
     *
     * @return int[]
     */
    private function hidden_ids( string $lang ): array {
        $cache_key = self::CACHE_PREFIX . md5( $lang );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        // 只取有設定規則的內容（mode = exclude / include）
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '" . esc_sql( self::META_MODE ) . "'"
        );

        $hidden = [];
        foreach ( (array) $rows as $row ) {
            if ( self::is_hidden( (int) $row->post_id, $lang ) ) {
                $hidden[] = (int) $row->post_id;
            }
        }

        set_transient( $cache_key, $hidden, DAY_IN_SECONDS );
        return $hidden;
    }

    /**
     * 公開：過濾掉「指定(或當前)語言被隱藏」的 post IDs。
     * 供自訂列表(非 WP_Query／已存 ID 陣列／頁面建構器迴圈)顯式呼叫。
     *
     * @param int[]       $ids
     * @param string|null $lang 預設當前語言
     * @return int[]
     */
    public static function filter_ids( array $ids, ?string $lang = null ): array {
        $lang = $lang ?: YSTPAddonsTP::current_language();
        return array_values( array_filter( $ids, static function ( $id ) use ( $lang ) {
            return ! self::is_hidden( (int) $id, $lang );
        } ) );
    }

    /** filter：apply_filters( 'ys_tp_filter_ids', int[] $ids, ?string $lang ) → 過濾後 IDs */
    public function filter_ids_hook( $ids, $lang = null ): array {
        if ( ! is_array( $ids ) ) {
            return [];
        }
        return self::filter_ids( $ids, ( is_string( $lang ) && '' !== $lang ) ? $lang : null );
    }

    /** filter：apply_filters( 'ys_tp_is_hidden', bool $hidden, int $post_id ) → 當前語言是否隱藏 */
    public function is_hidden_hook( $hidden, $post_id = 0 ): bool {
        return $hidden ? true : self::is_hidden( (int) $post_id, YSTPAddonsTP::current_language() );
    }

    public function flush_cache(): void {
        foreach ( YSTPAddonsTP::published_language_codes() as $code ) {
            delete_transient( self::CACHE_PREFIX . md5( $code ) );
        }
    }

    /* ───────────── 前台：列表排除 ───────────── */

    public function exclude_from_lists( $query ): void {
        if ( is_admin() || ! $query instanceof \WP_Query ) {
            return;
        }
        // 單頁不在此處理（交給 maybe_redirect）
        if ( $query->is_singular() ) {
            return;
        }

        $lang   = YSTPAddonsTP::current_language();
        $hidden = $this->hidden_ids( $lang );
        if ( empty( $hidden ) ) {
            return;
        }

        $existing = (array) $query->get( 'post__not_in' );
        $query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $hidden ) ) ) );
    }

    /* ───────────── 前台：單頁智慧重導 ───────────── */

    public function maybe_redirect(): void {
        if ( ! $this->redirect_enabled() || ! is_singular() ) {
            return;
        }
        $post = get_queried_object();
        if ( ! $post instanceof \WP_Post ) {
            return;
        }
        if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
            return;
        }

        $lang = YSTPAddonsTP::current_language();
        if ( ! self::is_hidden( (int) $post->ID, $lang ) ) {
            return;
        }

        $status = (string) YSTPAddonsSettingsRepo::get( 'content_redirect_status', '302' );

        // 404 模式：不重導，直接回 404（搜尋引擎據此移除該語言索引）
        if ( '404' === $status ) {
            $this->trigger_404();
            return;
        }

        $code   = ( '301' === $status ) ? 301 : 302;
        $target = '';

        // ① 上層偵測：跳到最近一個「該語言可見」的上層（頁面／階層式 CPT）
        if ( (int) YSTPAddonsSettingsRepo::get( 'content_fb_parent', 1 ) ) {
            $ancestor = $this->nearest_visible_ancestor( $post, $lang );
            if ( $ancestor ) {
                $url = get_permalink( $ancestor );
                if ( $url ) {
                    $target = YSTPAddonsTP::url_for_language( $lang, $url );
                }
            }
        }

        // ② 無可用上層 → 文章類型 fallback（未指定則 fallback_url 內轉用全域預設）
        if ( '' === $target ) {
            $target = $this->fallback_url( $post->post_type, $lang );
        }

        if ( $target ) {
            wp_safe_redirect( $target, $code );
            exit;
        }
    }

    /**
     * 沿 post_parent 往上找最近一個「已發佈且在該語言可見」的上層
     *
     * @return int 上層 post ID；0 表示沒有可用上層
     */
    private function nearest_visible_ancestor( \WP_Post $post, string $lang ): int {
        $pid   = (int) $post->post_parent;
        $guard = 0;
        while ( $pid && $guard < 20 ) {
            $parent = get_post( $pid );
            if ( ! $parent instanceof \WP_Post ) {
                break;
            }
            if ( 'publish' === $parent->post_status && ! self::is_hidden( $pid, $lang ) ) {
                return $pid;
            }
            $pid = (int) $parent->post_parent;
            $guard++;
        }
        return 0;
    }

    /**
     * 直接回 404（不重導）
     */
    private function trigger_404(): void {
        global $wp_query;
        if ( $wp_query instanceof \WP_Query ) {
            $wp_query->set_404();
        }
        status_header( 404 );
        nocache_headers();
        $template = get_404_template();
        if ( $template ) {
            include $template;
        }
        exit;
    }

    /**
     * 計算某 post type 在某語言的 fallback 目標 URL（當前語言版本）
     *
     * 每個 post type 可設定目標，或設為「未指定」改用全域預設跳轉頁。
     */
    private function fallback_url( string $post_type, string $lang ): string {
        if ( 'post' === $post_type ) {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_post', 'post_archive' );
        } elseif ( 'page' === $post_type ) {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_page', 'home' );
        } else {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_default', 'home' );
        }

        // 未指定 → 全域預設跳轉頁
        if ( '' === $opt || 'inherit' === $opt ) {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_global', 'home' );
        }

        $base = $this->resolve_target_base( $opt, $post_type );
        return YSTPAddonsTP::url_for_language( $lang, $base );
    }

    /**
     * 將 fallback 設定值解析為「預設語言」的基底 URL
     *
     * 支援：home｜post_archive｜page:{ID}｜cpt_archive
     */
    private function resolve_target_base( string $opt, string $post_type ): string {
        if ( 'home' === $opt ) {
            return home_url( '/' );
        }
        if ( 'post_archive' === $opt ) {
            $page_for_posts = (int) get_option( 'page_for_posts' );
            if ( $page_for_posts ) {
                $url = get_permalink( $page_for_posts );
                if ( $url ) {
                    return $url;
                }
            }
            return home_url( '/' );
        }
        if ( 'cpt_archive' === $opt ) {
            $url = get_post_type_archive_link( $post_type );
            return $url ?: home_url( '/' );
        }
        if ( 0 === strpos( $opt, 'page:' ) ) {
            $pid = (int) substr( $opt, 5 );
            $url = $pid ? get_permalink( $pid ) : '';
            return $url ?: home_url( '/' );
        }
        return home_url( '/' );
    }
}
