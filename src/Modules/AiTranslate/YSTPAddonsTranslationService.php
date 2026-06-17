<?php
/**
 * AI 翻譯核心服務
 *
 * 從 TranslatePress 的 dictionary／gettext 資料表讀取未翻譯字串（status=0），
 * 透過 AI 供應商批次翻譯後寫回（translated + status=2 機器翻譯）。
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate;

use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Modules\AiTranslate\Providers\YSTPAddonsProviderFactory;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsTranslationService {

    /** TP 狀態：機器翻譯 */
    private const STATUS_MACHINE = 2;

    /**
     * 目標語言（已發佈、排除預設語言）
     *
     * @return string[]
     */
    public static function target_languages(): array {
        $default = YSTPAddonsTP::default_language();
        $out     = [];
        foreach ( YSTPAddonsTP::published_language_codes() as $code ) {
            if ( $code !== $default ) {
                $out[] = $code;
            }
        }
        return $out;
    }

    public static function dictionary_table( string $source, string $target ): string {
        global $wpdb;
        return $wpdb->prefix . 'trp_dictionary_' . strtolower( $source ) . '_' . strtolower( $target );
    }

    public static function gettext_table( string $target ): string {
        global $wpdb;
        return $wpdb->prefix . 'trp_gettext_' . strtolower( $target );
    }

    private static function table_exists( string $table ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    /**
     * 各語言的翻譯統計
     *
     * @return array<string, array{total:int, done:int, pending:int}>
     */
    public static function stats(): array {
        global $wpdb;
        $source = YSTPAddonsTP::default_language();
        $names  = YSTPAddonsTP::languages();
        $result = [];

        foreach ( self::target_languages() as $target ) {
            $total = 0;
            $done  = 0;
            foreach ( [ self::dictionary_table( $source, $target ), self::gettext_table( $target ) ] as $table ) {
                if ( ! self::table_exists( $table ) ) {
                    continue;
                }
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE original <> ''" );
                $done  += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE status <> 0 AND translated <> ''" );
                // phpcs:enable
            }
            $result[ $target ] = [
                'name'    => $names[ $target ] ?? $target,
                'total'   => $total,
                'done'    => $done,
                'pending' => max( 0, $total - $done ),
            ];
        }
        return $result;
    }

    /** 全站待翻譯總數 */
    public static function pending_total(): int {
        $sum = 0;
        foreach ( self::stats() as $s ) {
            $sum += $s['pending'];
        }
        return $sum;
    }

    /**
     * 對單一目標語言翻譯一批（dictionary 優先，再 gettext）
     *
     * @return array{translated:int, remaining:int, error?:string}
     */
    public function run_batch_for( string $target, int $limit = 20 ): array {
        global $wpdb;
        $source   = YSTPAddonsTP::default_language();
        $provider = YSTPAddonsProviderFactory::from_settings();
        if ( ! $provider ) {
            return [ 'translated' => 0, 'remaining' => 0, 'error' => __( '找不到翻譯供應商', 'ys-translatepress-addons' ) ];
        }

        $translated = 0;

        foreach ( [ self::dictionary_table( $source, $target ), self::gettext_table( $target ) ] as $table ) {
            if ( $translated >= $limit || ! self::table_exists( $table ) ) {
                continue;
            }
            $take = $limit - $translated;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, original FROM `$table` WHERE status = 0 AND original <> '' ORDER BY id ASC LIMIT %d",
                $take
            ) );
            if ( empty( $rows ) ) {
                continue;
            }

            $texts = array_map( static fn( $r ) => (string) $r->original, $rows );
            $out   = $provider->translate_batch( $texts, $target, $source );
            if ( is_wp_error( $out ) ) {
                return [ 'translated' => $translated, 'remaining' => self::pending_for( $target ), 'error' => $out->get_error_message() ];
            }

            foreach ( $rows as $i => $row ) {
                $tr = $out[ $i ] ?? '';
                if ( '' === $tr ) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->update(
                    $table,
                    [ 'translated' => $tr, 'status' => self::STATUS_MACHINE ],
                    [ 'id' => (int) $row->id ],
                    [ '%s', '%d' ],
                    [ '%d' ]
                );
                $translated++;
            }
        }

        return [ 'translated' => $translated, 'remaining' => self::pending_for( $target ) ];
    }

    /** 單一語言待翻譯數 */
    public static function pending_for( string $target ): int {
        $stats = self::stats();
        return $stats[ $target ]['pending'] ?? 0;
    }

    /**
     * 探索某文章的可翻譯字串
     *
     * 以伺服器端請求抓取該文章各語言版本，觸發 TranslatePress 將字串寫入 dictionary。
     */
    public static function discover_post( int $post_id ): void {
        $url = get_permalink( $post_id );
        if ( ! $url ) {
            return;
        }
        foreach ( self::target_languages() as $target ) {
            $turl = YSTPAddonsTP::url_for_language( $target, $url );
            wp_remote_get( $turl, [
                'timeout'   => 20,
                'blocking'  => true,
                'sslverify' => false,
                'headers'   => [ 'X-YS-TP-Discover' => '1' ],
            ] );
        }
    }

    /**
     * 探索並翻譯某文章（供「自動」與「逐頁」使用）
     *
     * @return array{translated:int, remaining:int, error?:string}
     */
    public function discover_and_translate_post( int $post_id, int $batch = 20, int $max_batches = 25 ): array {
        self::discover_post( $post_id );

        $total = 0;
        for ( $i = 0; $i < $max_batches; $i++ ) {
            $res = $this->run_step( $batch );
            if ( ! empty( $res['error'] ) ) {
                return [ 'translated' => $total, 'remaining' => self::pending_total(), 'error' => $res['error'] ];
            }
            $total += (int) $res['translated'];
            if ( (int) $res['translated'] === 0 || (int) $res['remaining'] === 0 ) {
                break;
            }
        }
        return [ 'translated' => $total, 'remaining' => self::pending_total() ];
    }

    /**
     * 處理一個步驟：挑選待翻譯最多的語言翻一批（供 Cron／手動全站迴圈使用）
     *
     * @return array{translated:int, remaining:int, language?:string, error?:string}
     */
    public function run_step( int $limit = 20 ): array {
        $stats = self::stats();
        // 找出待翻譯最多的語言
        $target = '';
        $max    = 0;
        foreach ( $stats as $code => $s ) {
            if ( $s['pending'] > $max ) {
                $max    = $s['pending'];
                $target = $code;
            }
        }
        if ( '' === $target ) {
            return [ 'translated' => 0, 'remaining' => 0 ];
        }

        $res             = $this->run_batch_for( $target, $limit );
        $res['language'] = $target;
        $res['remaining'] = self::pending_total();
        return $res;
    }
}
