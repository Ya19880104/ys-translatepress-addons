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
        } else {
            add_action( 'pre_get_posts', [ $this, 'exclude_from_lists' ] );
            add_action( 'template_redirect', [ $this, 'maybe_redirect' ] );
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

        $target = $this->fallback_url( $post->post_type, $lang );
        if ( $target ) {
            wp_safe_redirect( $target, 302 );
            exit;
        }
    }

    /**
     * 計算某 post type 在某語言的 fallback 目標 URL（當前語言版本）
     */
    private function fallback_url( string $post_type, string $lang ): string {
        if ( 'post' === $post_type ) {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_post', 'post_archive' );
        } elseif ( 'page' === $post_type ) {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_page', 'home' );
        } else {
            $opt = (string) YSTPAddonsSettingsRepo::get( 'content_fb_default', 'home' );
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
