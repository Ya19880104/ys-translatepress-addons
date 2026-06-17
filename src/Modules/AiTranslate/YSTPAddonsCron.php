<?php
/**
 * AI 翻譯背景排程（WP-Cron）
 *
 * 以時間預算迴圈在背景批次翻譯，避免「進入網頁即時翻譯」造成的逾時卡頓。
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsCron {

    public const HOOK = 'ys_tp_ai_translate_cron';

    public function register(): void {
        add_filter( 'cron_schedules', [ $this, 'add_schedules' ] );
        add_action( self::HOOK, [ self::class, 'run' ] );
        $this->sync_schedule();
    }

    /**
     * 新增自訂排程間隔
     *
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public function add_schedules( $schedules ) {
        $schedules['ys_tp_5min']  = [ 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'YS 每 5 分鐘' ];
        $schedules['ys_tp_15min'] = [ 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'YS 每 15 分鐘' ];
        $schedules['ys_tp_30min'] = [ 'interval' => 30 * MINUTE_IN_SECONDS, 'display' => 'YS 每 30 分鐘' ];
        return $schedules;
    }

    /**
     * 依設定排定／取消背景事件
     */
    public function sync_schedule(): void {
        $enabled  = (int) YSTPAddonsSettingsRepo::get( 'ai_schedule_enabled', 0 );
        $interval = (string) YSTPAddonsSettingsRepo::get( 'ai_schedule_interval', 'ys_tp_15min' );
        $allowed  = [ 'ys_tp_5min', 'ys_tp_15min', 'ys_tp_30min', 'hourly' ];
        if ( ! in_array( $interval, $allowed, true ) ) {
            $interval = 'ys_tp_15min';
        }

        $next = wp_next_scheduled( self::HOOK );

        if ( $enabled && ! $next ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::HOOK );
        } elseif ( ! $enabled && $next ) {
            wp_unschedule_event( $next, self::HOOK );
        }
    }

    /**
     * 清除排程（停用模組／反安裝時呼叫）
     */
    public static function clear(): void {
        $ts = wp_next_scheduled( self::HOOK );
        while ( $ts ) {
            wp_unschedule_event( $ts, self::HOOK );
            $ts = wp_next_scheduled( self::HOOK );
        }
    }

    /**
     * 背景執行：時間預算內盡量多翻
     */
    public static function run(): void {
        if ( ! (int) YSTPAddonsSettingsRepo::get( 'ai_schedule_enabled', 0 ) ) {
            return;
        }
        // 避免重入
        if ( get_transient( 'ys_tp_ai_cron_lock' ) ) {
            return;
        }
        set_transient( 'ys_tp_ai_cron_lock', 1, 5 * MINUTE_IN_SECONDS );

        $batch  = max( 1, (int) YSTPAddonsSettingsRepo::get( 'ai_batch_size', 20 ) );
        $budget = max( 5, (int) YSTPAddonsSettingsRepo::get( 'ai_cron_budget', 25 ) );
        $svc    = new YSTPAddonsTranslationService();
        $start  = time();

        do {
            $res = $svc->run_step( $batch );
            if ( ! empty( $res['error'] ) ) {
                set_transient( 'ys_tp_ai_last_error', $res['error'], DAY_IN_SECONDS );
                break;
            }
            update_option( 'ys_tp_ai_last_run', time() );
            if ( (int) $res['translated'] === 0 ) {
                break; // 已無待翻譯或卡住
            }
        } while ( (int) $res['remaining'] > 0 && ( time() - $start ) < $budget );

        delete_transient( 'ys_tp_ai_cron_lock' );
    }
}
