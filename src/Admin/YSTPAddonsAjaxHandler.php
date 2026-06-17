<?php
/**
 * 後台 AJAX 處理器
 *
 * - ys_tp_save_settings  ：通用設定儲存（存自訂資料表）
 * - ys_tp_toggle_module  ：啟用／停用功能模組
 *
 * @package YangSheep\TPAddons\Admin
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Admin;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;
use YangSheep\TPAddons\Support\YSTPAddonsModules;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsAjaxHandler {

    public function __construct() {
        add_action( 'wp_ajax_ys_tp_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_ys_tp_toggle_module', [ $this, 'toggle_module' ] );
    }

    /**
     * 共用安全檢查（nonce + 權限）
     */
    private function guard(): void {
        if ( ! check_ajax_referer( 'ys_tp_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( '安全驗證失敗', 'ys-translatepress-addons' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '權限不足', 'ys-translatepress-addons' ) ], 403 );
        }
    }

    /**
     * 遞迴清理輸入
     *
     * @param mixed $data
     * @return mixed
     */
    private function clean( $data ) {
        if ( is_array( $data ) ) {
            return array_map( [ $this, 'clean' ], $data );
        }
        return sanitize_text_field( $data );
    }

    /**
     * 儲存設定（AJAX）
     */
    public function save_settings(): void {
        $this->guard();

        // 取原始值（已 unslash，尚未清理）以便依欄位型別分別清理
        $raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
            ? wp_unslash( $_POST['settings'] )
            : [];

        if ( empty( $raw ) ) {
            wp_send_json_error( [ 'message' => __( '沒有收到設定資料', 'ys-translatepress-addons' ) ] );
        }

        // 多行欄位（保留換行）
        $multiline = [ 'ai_prompt' ];

        foreach ( $raw as $key => $value ) {
            $safe_key = sanitize_key( $key );
            if ( '' === $safe_key ) {
                continue;
            }
            // API 金鑰留空表示「保留既有」，不覆寫
            if ( 0 === strpos( $safe_key, 'ai_key_' ) && ( ! is_string( $value ) || '' === trim( $value ) ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $clean = $this->clean( $value );
            } elseif ( in_array( $safe_key, $multiline, true ) ) {
                $clean = sanitize_textarea_field( (string) $value );
            } else {
                $clean = sanitize_text_field( (string) $value );
            }

            YSTPAddonsSettingsRepo::set( $safe_key, $clean );
        }

        wp_send_json_success( [ 'message' => __( '已儲存', 'ys-translatepress-addons' ) ] );
    }

    /**
     * 啟用／停用模組（AJAX）
     */
    public function toggle_module(): void {
        $this->guard();

        $module  = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
        $enabled = isset( $_POST['enabled'] ) ? (bool) absint( $_POST['enabled'] ) : false;

        $meta = YSTPAddonsModules::get( $module );
        if ( ! $meta ) {
            wp_send_json_error( [ 'message' => __( '未知的模組', 'ys-translatepress-addons' ) ] );
        }

        YSTPAddonsModules::set_status( $module, $enabled );

        // 停用 AI 翻譯模組時清除背景排程
        if ( 'ai_translate' === $module && ! $enabled
            && class_exists( '\YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsCron' ) ) {
            \YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsCron::clear();
        }

        wp_send_json_success( [
            'message' => $enabled
                ? __( '模組已啟用', 'ys-translatepress-addons' )
                : __( '模組已停用', 'ys-translatepress-addons' ),
            'module'  => $module,
            'enabled' => $enabled,
        ] );
    }
}
