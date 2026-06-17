<?php
/**
 * 模組：AI 翻譯（OpenAI／Gemini／Claude）
 *
 * 四種觸發：自動（存檔後背景）、手動逐頁、手動全站、排程背景。
 * 翻譯結果寫入 TranslatePress 表，狀態 = 機器翻譯。
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;
use YangSheep\TPAddons\Modules\AiTranslate\Providers\YSTPAddonsProviderFactory;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsAiTranslate implements YSTPAddonsModuleInterface {

    public const AUTO_HOOK = 'ys_tp_ai_auto_post';

    public function boot(): void {
        ( new YSTPAddonsCron() )->register();
        add_action( self::AUTO_HOOK, [ $this, 'cron_auto_post' ] );

        if ( is_admin() ) {
            add_action( 'wp_ajax_ys_tp_ai_test', [ $this, 'ajax_test' ] );
            add_action( 'wp_ajax_ys_tp_ai_run_step', [ $this, 'ajax_run_step' ] );
            add_action( 'wp_ajax_ys_tp_ai_progress', [ $this, 'ajax_progress' ] );
            add_action( 'wp_ajax_ys_tp_ai_translate_post', [ $this, 'ajax_translate_post' ] );
            add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );
            add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        }
    }

    private function guard(): void {
        if ( ! check_ajax_referer( 'ys_tp_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( '安全驗證失敗', 'ys-translatepress-addons' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '權限不足', 'ys-translatepress-addons' ) ], 403 );
        }
    }

    private function batch_size(): int {
        return max( 1, min( 100, (int) YSTPAddonsSettingsRepo::get( 'ai_batch_size', 20 ) ) );
    }

    /* ───────────── AJAX ───────────── */

    /** 測試供應商連線 */
    public function ajax_test(): void {
        $this->guard();
        $provider = YSTPAddonsProviderFactory::from_settings();
        if ( ! $provider ) {
            wp_send_json_error( [ 'message' => __( '找不到供應商設定', 'ys-translatepress-addons' ) ] );
        }
        $res = $provider->test_connection();
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }
        wp_send_json_success( [ 'message' => sprintf(
            /* translators: %s: 供應商名稱 */
            __( '%s 連線成功！', 'ys-translatepress-addons' ),
            $provider->label()
        ) ] );
    }

    /** 手動全站：執行一個批次步驟（前端輪詢直到完成） */
    public function ajax_run_step(): void {
        $this->guard();
        $svc = new YSTPAddonsTranslationService();
        $res = $svc->run_step( $this->batch_size() );

        if ( ! empty( $res['error'] ) ) {
            wp_send_json_error( [
                'message'   => $res['error'],
                'pending'   => YSTPAddonsTranslationService::pending_total(),
            ] );
        }
        wp_send_json_success( [
            'translated' => (int) $res['translated'],
            'pending'    => YSTPAddonsTranslationService::pending_total(),
            'language'   => $res['language'] ?? '',
            'stats'      => YSTPAddonsTranslationService::stats(),
        ] );
    }

    /** 取得進度 */
    public function ajax_progress(): void {
        $this->guard();
        wp_send_json_success( [
            'pending'  => YSTPAddonsTranslationService::pending_total(),
            'stats'    => YSTPAddonsTranslationService::stats(),
            'last_run' => (int) get_option( 'ys_tp_ai_last_run', 0 ),
            'schedule' => (int) YSTPAddonsSettingsRepo::get( 'ai_schedule_enabled', 0 ),
        ] );
    }

    /** 手動逐頁：探索並翻譯單一文章 */
    public function ajax_translate_post(): void {
        $this->guard();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( [ 'message' => __( '無效的文章', 'ys-translatepress-addons' ) ] );
        }
        $svc = new YSTPAddonsTranslationService();
        $res = $svc->discover_and_translate_post( $post_id, $this->batch_size() );
        if ( ! empty( $res['error'] ) ) {
            wp_send_json_error( [ 'message' => $res['error'] ] );
        }
        wp_send_json_success( [
            'message'    => sprintf(
                /* translators: %d: 翻譯字串數 */
                __( '已翻譯 %d 個字串', 'ys-translatepress-addons' ),
                (int) $res['translated']
            ),
            'translated' => (int) $res['translated'],
            'pending'    => (int) $res['remaining'],
        ] );
    }

    /* ───────────── 自動（存檔後背景）───────────── */

    public function on_save_post( $post_id, $post ): void {
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'ai_auto_enabled', 0 ) ) {
            return;
        }
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
            return;
        }
        if ( ! wp_next_scheduled( self::AUTO_HOOK, [ $post_id ] ) ) {
            wp_schedule_single_event( time() + 30, self::AUTO_HOOK, [ $post_id ] );
        }
    }

    public function cron_auto_post( $post_id ): void {
        $svc = new YSTPAddonsTranslationService();
        $svc->discover_and_translate_post( (int) $post_id, $this->batch_size() );
    }

    /* ───────────── 逐頁 Meta Box ───────────── */

    public function add_meta_box(): void {
        $types = get_post_types( [ 'public' => true ], 'names' );
        unset( $types['attachment'] );
        foreach ( $types as $pt ) {
            add_meta_box(
                'ys_tp_ai_translate',
                __( 'AI 翻譯（多語增強）', 'ys-translatepress-addons' ),
                [ $this, 'render_meta_box' ],
                $pt,
                'side',
                'low'
            );
        }
    }

    public function render_meta_box( $post ): void {
        ?>
        <p class="description" style="margin:.2em 0 .8em;">
            <?php esc_html_e( '抓取本頁各語言版本並以 AI 翻譯尚未翻譯的字串。', 'ys-translatepress-addons' ); ?>
        </p>
        <button type="button" class="button button-secondary" id="ys-tp-ai-translate-post" data-post="<?php echo esc_attr( (string) $post->ID ); ?>">
            <span class="dashicons dashicons-translation" style="vertical-align:middle;"></span>
            <?php esc_html_e( 'AI 翻譯此頁', 'ys-translatepress-addons' ); ?>
        </button>
        <span id="ys-tp-ai-post-status" style="display:block;margin-top:8px;color:#666;"></span>
        <script>
        ( function () {
            var btn = document.getElementById( 'ys-tp-ai-translate-post' );
            if ( ! btn ) return;
            var AJAX = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var NONCE = '<?php echo esc_js( wp_create_nonce( 'ys_tp_nonce' ) ); ?>';
            btn.addEventListener( 'click', function () {
                var status = document.getElementById( 'ys-tp-ai-post-status' );
                btn.disabled = true;
                status.textContent = '<?php echo esc_js( __( '翻譯中，請稍候…', 'ys-translatepress-addons' ) ); ?>';
                var body = new URLSearchParams();
                body.append( 'action', 'ys_tp_ai_translate_post' );
                body.append( 'nonce', NONCE );
                body.append( 'post_id', btn.dataset.post );
                fetch( AJAX, { method: 'POST', body: body, credentials: 'same-origin' } )
                    .then( function ( r ) { return r.json(); } )
                    .then( function ( res ) {
                        status.textContent = ( res && res.data && res.data.message ) || '完成';
                        btn.disabled = false;
                    } )
                    .catch( function () { status.textContent = '<?php echo esc_js( __( '發生錯誤', 'ys-translatepress-addons' ) ); ?>'; btn.disabled = false; } );
            } );
        } )();
        </script>
        <?php
    }
}
