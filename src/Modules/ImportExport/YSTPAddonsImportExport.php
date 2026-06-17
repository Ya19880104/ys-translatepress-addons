<?php
/**
 * 模組：翻譯匯出／匯入
 *
 * 將 TranslatePress 的 dictionary／gettext 翻譯匯出為 JSON，外部編修後再匯入。
 *
 * @package YangSheep\TPAddons\Modules\ImportExport
 * @since   0.4.0
 */

namespace YangSheep\TPAddons\Modules\ImportExport;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsTranslationService;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsImportExport implements YSTPAddonsModuleInterface {

    public function boot(): void {
        if ( is_admin() ) {
            add_action( 'wp_ajax_ys_tp_io_export', [ $this, 'export' ] );
            add_action( 'wp_ajax_ys_tp_io_import', [ $this, 'import' ] );
        }
    }

    /* ───────────── 匯出 ───────────── */

    public function export(): void {
        if ( ! current_user_can( 'manage_options' )
            || ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_key( wp_unslash( $_GET['nonce'] ) ) : '', 'ys_tp_io' ) ) {
            wp_die( esc_html__( '安全驗證失敗', 'ys-translatepress-addons' ), 403 );
        }

        $only_translated = ! empty( $_GET['only_translated'] );
        $data            = $this->build_export( $only_translated );
        $json            = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ys-tp-translations-' . gmdate( 'Ymd-His' ) . '.json"' );
        header( 'Content-Length: ' . strlen( (string) $json ) );
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * 組裝匯出資料
     *
     * @return array<string, mixed>
     */
    private function build_export( bool $only_translated ): array {
        global $wpdb;
        $source = YSTPAddonsTP::default_language();

        $out = [
            'plugin'       => 'ys-translatepress-addons',
            'version'      => YS_TP_VERSION,
            'site'         => home_url( '/' ),
            'source_lang'  => $source,
            'exported_at'  => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
            'languages'    => [],
        ];

        foreach ( YSTPAddonsTranslationService::target_languages() as $target ) {
            $cond = $only_translated ? " AND status <> 0 AND translated <> ''" : '';

            $dict_table = YSTPAddonsTranslationService::dictionary_table( $source, $target );
            $gx_table   = YSTPAddonsTranslationService::gettext_table( $target );

            $dictionary = [];
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dict_table ) ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( "SELECT original, translated, status FROM `$dict_table` WHERE original <> ''{$cond}", ARRAY_A );
                foreach ( (array) $rows as $r ) {
                    $dictionary[] = [ 'original' => $r['original'], 'translated' => $r['translated'], 'status' => (int) $r['status'] ];
                }
            }

            $gettext = [];
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gx_table ) ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( "SELECT original, translated, domain, status FROM `$gx_table` WHERE original <> ''{$cond}", ARRAY_A );
                foreach ( (array) $rows as $r ) {
                    $gettext[] = [ 'original' => $r['original'], 'translated' => $r['translated'], 'domain' => $r['domain'], 'status' => (int) $r['status'] ];
                }
            }

            $out['languages'][ $target ] = [ 'dictionary' => $dictionary, 'gettext' => $gettext ];
        }

        return $out;
    }

    /* ───────────── 匯入 ───────────── */

    public function import(): void {
        if ( ! check_ajax_referer( 'ys_tp_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( '安全驗證失敗', 'ys-translatepress-addons' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '權限不足', 'ys-translatepress-addons' ) ], 403 );
        }
        if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => __( '未收到檔案', 'ys-translatepress-addons' ) ] );
        }

        $raw  = file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = json_decode( (string) $raw, true );

        if ( ! is_array( $data ) || empty( $data['languages'] ) || ! is_array( $data['languages'] ) ) {
            wp_send_json_error( [ 'message' => __( 'JSON 格式不正確或無翻譯資料', 'ys-translatepress-addons' ) ] );
        }

        $result = $this->apply_import( $data );

        wp_send_json_success( [
            'message'  => sprintf(
                /* translators: %d: 匯入字串數 */
                __( '匯入完成，更新 %d 個翻譯。', 'ys-translatepress-addons' ),
                $result['updated']
            ),
            'updated'  => $result['updated'],
            'skipped'  => $result['skipped'],
        ] );
    }

    /**
     * 套用匯入
     *
     * @param array<string, mixed> $data
     * @return array{updated:int, skipped:int}
     */
    private function apply_import( array $data ): array {
        global $wpdb;
        $source  = YSTPAddonsTP::default_language();
        $updated = 0;
        $skipped = 0;
        $valid   = YSTPAddonsTP::published_language_codes();

        foreach ( $data['languages'] as $target => $payload ) {
            $target = (string) $target;
            if ( ! in_array( $target, $valid, true ) || $target === $source || ! is_array( $payload ) ) {
                continue;
            }

            $map = [
                'dictionary' => YSTPAddonsTranslationService::dictionary_table( $source, $target ),
                'gettext'    => YSTPAddonsTranslationService::gettext_table( $target ),
            ];

            foreach ( $map as $kind => $table ) {
                if ( empty( $payload[ $kind ] ) || ! is_array( $payload[ $kind ] ) ) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
                    continue;
                }

                foreach ( $payload[ $kind ] as $entry ) {
                    if ( empty( $entry['original'] ) || ! isset( $entry['translated'] ) || '' === $entry['translated'] ) {
                        $skipped++;
                        continue;
                    }
                    $status = isset( $entry['status'] ) ? (int) $entry['status'] : 2;
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $affected = $wpdb->query( $wpdb->prepare(
                        "UPDATE `$table` SET translated = %s, status = %d WHERE original = %s",
                        $entry['translated'],
                        $status,
                        $entry['original']
                    ) );
                    if ( $affected ) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return [ 'updated' => $updated, 'skipped' => $skipped ];
    }
}
