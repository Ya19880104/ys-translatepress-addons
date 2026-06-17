<?php
/**
 * 模組設定頁：AI 翻譯
 *
 * @var array<string, mixed> $meta
 * @var bool                 $enabled
 * @var string               $key
 *
 * @package YangSheep\TPAddons
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;
use YangSheep\TPAddons\Modules\AiTranslate\YSTPAddonsTranslationService;

$provider = (string) YSTPAddonsSettingsRepo::get( 'ai_provider', 'openai' );
$auto     = (int) YSTPAddonsSettingsRepo::get( 'ai_auto_enabled', 0 );
$sched    = (int) YSTPAddonsSettingsRepo::get( 'ai_schedule_enabled', 0 );
$interval = (string) YSTPAddonsSettingsRepo::get( 'ai_schedule_interval', 'ys_tp_15min' );
$batch    = (int) YSTPAddonsSettingsRepo::get( 'ai_batch_size', 20 );
$prompt   = (string) YSTPAddonsSettingsRepo::get( 'ai_prompt', '' );

$providers = [
    'openai' => [ 'label' => 'OpenAI', 'model_ph' => 'gpt-4o-mini' ],
    'gemini' => [ 'label' => 'Google Gemini', 'model_ph' => 'gemini-2.0-flash' ],
    'claude' => [ 'label' => 'Anthropic Claude', 'model_ph' => 'claude-3-5-haiku-latest' ],
];

$mask = static function ( string $val ): string {
    $val = trim( $val );
    if ( '' === $val ) {
        return '';
    }
    $tail = substr( $val, -4 );
    return '已設定（••••' . $tail . '）';
};

$stats   = YSTPAddonsTranslationService::stats();
$pending = YSTPAddonsTranslationService::pending_total();
?>
<div class="wrap ystp-wrap">
    <h1 class="ystp-screen-reader">AI 翻譯</h1>

    <div class="ystp-hero ystp-hero-sm">
        <div class="ystp-hero-main">
            <div class="ystp-hero-title"><span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>"></span> AI 翻譯</div>
            <div class="ystp-hero-sub"><?php echo esc_html( $meta['desc'] ); ?></div>
        </div>
        <div class="ystp-hero-side">
            <?php if ( $enabled ) : ?>
                <span class="ystp-pill ystp-pill-on"><span class="ystp-dot"></span>模組啟用中</span>
            <?php else : ?>
                <span class="ystp-pill ystp-pill-off"><span class="ystp-dot"></span>模組已停用</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $enabled ) : ?>
        <div class="ystp-note ystp-note-warn">
            此模組目前為停用狀態。請至 <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-tp-addons' ) ); ?>">總覽</a> 啟用後生效。
        </div>
    <?php endif; ?>

    <!-- 供應商與金鑰 -->
    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-admin-network"></span> 供應商與 API 金鑰</div>
        <div class="ystp-panel-body ystp-form" data-form="ai">
            <div class="ystp-field">
                <label><?php esc_html_e( '使用的供應商', 'ys-translatepress-addons' ); ?></label>
                <select data-setting="ai_provider" id="ys-tp-ai-provider">
                    <?php foreach ( $providers as $pid => $p ) : ?>
                        <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $provider, $pid ); ?>><?php echo esc_html( $p['label'] ); ?></option>
                    <?php endforeach; ?>
                    <option value="mock" <?php selected( $provider, 'mock' ); ?>><?php esc_html_e( '測試模擬（不需金鑰）', 'ys-translatepress-addons' ); ?></option>
                </select>
            </div>

            <?php foreach ( $providers as $pid => $p ) :
                $k = (string) YSTPAddonsSettingsRepo::get( 'ai_key_' . $pid, '' );
                $m = (string) YSTPAddonsSettingsRepo::get( 'ai_model_' . $pid, '' );
                ?>
                <div class="ystp-ai-provider-block" data-provider="<?php echo esc_attr( $pid ); ?>" style="border:1px solid #eef1f4;border-radius:10px;padding:14px 16px;margin-bottom:12px;<?php echo $provider === $pid ? '' : 'display:none;'; ?>">
                    <strong style="color:#1f2d3d;"><?php echo esc_html( $p['label'] ); ?></strong>
                    <div class="ystp-field" style="margin-top:10px;">
                        <label>API 金鑰</label>
                        <input type="password" autocomplete="off" data-setting="ai_key_<?php echo esc_attr( $pid ); ?>"
                            placeholder="<?php echo esc_attr( $k !== '' ? $mask( $k ) : 'sk-...' ); ?>" value="" style="max-width:100%;" />
                        <p class="ystp-field-desc"><?php esc_html_e( '留空表示保留既有金鑰。', 'ys-translatepress-addons' ); ?></p>
                    </div>
                    <div class="ystp-field" style="margin-bottom:0;">
                        <label><?php esc_html_e( '模型', 'ys-translatepress-addons' ); ?></label>
                        <?php
                        $models   = \YangSheep\TPAddons\Modules\AiTranslate\Providers\YSTPAddonsProviderFactory::models( $pid );
                        $docs_url = \YangSheep\TPAddons\Modules\AiTranslate\Providers\YSTPAddonsProviderFactory::docs_url( $pid );
                        $in_list  = in_array( $m, $models, true );
                        $is_custom = ( '' !== $m && ! $in_list );
                        $first    = $models[0] ?? '';
                        $text_val = '' !== $m ? $m : $first;
                        ?>
                        <select class="ystp-ai-model-select" data-provider="<?php echo esc_attr( $pid ); ?>" style="max-width:320px;">
                            <?php foreach ( $models as $i => $mm ) : ?>
                                <option value="<?php echo esc_attr( $mm ); ?>" <?php selected( ( $in_list && $m === $mm ) || ( '' === $m && 0 === $i ) ); ?>>
                                    <?php echo esc_html( $mm ) . ( 0 === $i ? '（推薦）' : '' ); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom__" <?php selected( $is_custom ); ?>><?php esc_html_e( '其他（自行輸入）', 'ys-translatepress-addons' ); ?></option>
                        </select>
                        <input type="text" class="ystp-ai-model-custom" data-setting="ai_model_<?php echo esc_attr( $pid ); ?>"
                            placeholder="<?php echo esc_attr( $p['model_ph'] ); ?>" value="<?php echo esc_attr( $text_val ); ?>"
                            style="max-width:320px;margin-top:8px;<?php echo $is_custom ? '' : 'display:none;'; ?>" />
                        <p class="ystp-field-desc">
                            <?php esc_html_e( '選「其他」可自行輸入模型名稱。查詢可用模型：', 'ys-translatepress-addons' ); ?>
                            <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '官方模型清單 ↗', 'ys-translatepress-addons' ); ?></a>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="ystp-form-foot" style="display:flex;gap:10px;align-items:center;">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="ai">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
                <button type="button" class="ystp-btn" id="ys-tp-ai-test">
                    <span class="dashicons dashicons-admin-plugins"></span> 測試連線
                </button>
                <span id="ys-tp-ai-test-result" style="font-size:13px;"></span>
            </div>
        </div>
    </div>

    <!-- 觸發方式 -->
    <div class="ystp-card-row">
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-update"></span> 自動與排程</div>
            <div class="ystp-panel-body ystp-form" data-form="ai-trigger">
                <div class="ystp-field">
                    <label><?php esc_html_e( '存檔後自動翻譯', 'ys-translatepress-addons' ); ?></label>
                    <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="ai_auto_enabled" <?php checked( $auto, 1 ); ?> /><span class="ystp-slider"></span></label>
                    <p class="ystp-field-desc"><?php esc_html_e( '發佈／更新內容後，於背景自動翻譯該頁字串。', 'ys-translatepress-addons' ); ?></p>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '排程背景翻譯', 'ys-translatepress-addons' ); ?></label>
                    <label class="ystp-toggle" style="margin-top:4px;"><input type="checkbox" data-setting="ai_schedule_enabled" <?php checked( $sched, 1 ); ?> /><span class="ystp-slider"></span></label>
                    <p class="ystp-field-desc"><?php esc_html_e( '由 WP-Cron 在背景分批翻譯，徹底避免前台即時翻譯造成的卡頓。', 'ys-translatepress-addons' ); ?></p>
                </div>
                <div class="ystp-field">
                    <label><?php esc_html_e( '排程間隔', 'ys-translatepress-addons' ); ?></label>
                    <select data-setting="ai_schedule_interval">
                        <option value="ys_tp_5min" <?php selected( $interval, 'ys_tp_5min' ); ?>><?php esc_html_e( '每 5 分鐘', 'ys-translatepress-addons' ); ?></option>
                        <option value="ys_tp_15min" <?php selected( $interval, 'ys_tp_15min' ); ?>><?php esc_html_e( '每 15 分鐘', 'ys-translatepress-addons' ); ?></option>
                        <option value="ys_tp_30min" <?php selected( $interval, 'ys_tp_30min' ); ?>><?php esc_html_e( '每 30 分鐘', 'ys-translatepress-addons' ); ?></option>
                        <option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php esc_html_e( '每小時', 'ys-translatepress-addons' ); ?></option>
                    </select>
                </div>
                <div class="ystp-field" style="margin-bottom:0;">
                    <label><?php esc_html_e( '每批字串數', 'ys-translatepress-addons' ); ?></label>
                    <input type="number" min="1" max="100" data-setting="ai_batch_size" value="<?php echo esc_attr( (string) ( $batch ?: 20 ) ); ?>" style="max-width:120px;" />
                </div>
                <div class="ystp-form-foot">
                    <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="ai-trigger">
                        <span class="dashicons dashicons-saved"></span> 儲存設定
                    </button>
                </div>
            </div>
        </div>

        <!-- 全站翻譯與進度 -->
        <div class="ystp-panel">
            <div class="ystp-panel-head"><span class="dashicons dashicons-superhero"></span> 翻譯進度</div>
            <div class="ystp-panel-body" id="ys-tp-ai-dash">
                <div class="ystp-ai-progress-wrap" style="margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:#7a8a96;margin-bottom:6px;">
                        <span><?php esc_html_e( '全站待翻譯字串', 'ys-translatepress-addons' ); ?></span>
                        <span><strong id="ys-tp-ai-pending"><?php echo esc_html( (string) $pending ); ?></strong></span>
                    </div>
                    <div style="height:10px;background:#eef2f5;border-radius:6px;overflow:hidden;">
                        <div id="ys-tp-ai-bar" style="height:100%;width:0;background:#5b9a8b;transition:width .3s;"></div>
                    </div>
                </div>

                <ul class="ystp-langlist" id="ys-tp-ai-stats">
                    <?php foreach ( $stats as $code => $s ) : ?>
                        <li data-lang="<?php echo esc_attr( $code ); ?>">
                            <span class="ystp-langlist-name"><?php echo esc_html( $s['name'] ); ?></span>
                            <code class="ys-done"><?php echo esc_html( (string) $s['done'] ); ?></code> /
                            <code class="ys-total"><?php echo esc_html( (string) $s['total'] ); ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                    <button type="button" class="ystp-btn ystp-btn-primary" id="ys-tp-ai-run">
                        <span class="dashicons dashicons-controls-play"></span> 開始全站翻譯
                    </button>
                    <button type="button" class="ystp-btn" id="ys-tp-ai-stop" style="display:none;">
                        <span class="dashicons dashicons-controls-pause"></span> 停止
                    </button>
                    <span id="ys-tp-ai-run-status" style="font-size:13px;color:#7a8a96;"></span>
                </div>
                <p class="ystp-field-desc" style="margin-top:12px;"><?php esc_html_e( '提示：字串需先被「探索」才會出現在待翻譯清單（瀏覽頁面或用文章編輯頁的「AI 翻譯此頁」按鈕即可探索）。', 'ys-translatepress-addons' ); ?></p>
            </div>
        </div>
    </div>

    <!-- 進階：自訂 prompt -->
    <div class="ystp-panel">
        <div class="ystp-panel-head"><span class="dashicons dashicons-editor-code"></span> 進階：翻譯指令（選填）</div>
        <div class="ystp-panel-body ystp-form" data-form="ai-prompt">
            <div class="ystp-field" style="max-width:100%;">
                <label><?php esc_html_e( '自訂系統提示（System Prompt）', 'ys-translatepress-addons' ); ?></label>
                <textarea data-setting="ai_prompt" rows="4" style="width:100%;max-width:760px;border:1px solid #e3e8ec;border-radius:8px;padding:10px 12px;font-size:13px;" placeholder="<?php esc_attr_e( '留空使用內建專業翻譯指令。可用變數：%source_lang%、%target_lang%', 'ys-translatepress-addons' ); ?>"><?php echo esc_textarea( $prompt ); ?></textarea>
                <p class="ystp-field-desc"><?php esc_html_e( '進階使用者可自訂翻譯風格／術語規則。可用變數：%source_lang%、%target_lang%。', 'ys-translatepress-addons' ); ?></p>
            </div>
            <div class="ystp-form-foot">
                <button type="button" class="ystp-btn ystp-btn-primary ystp-save-btn" data-form="ai-prompt">
                    <span class="dashicons dashicons-saved"></span> 儲存設定
                </button>
            </div>
        </div>
    </div>
</div>
