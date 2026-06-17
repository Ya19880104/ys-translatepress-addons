<?php
/**
 * AI 翻譯供應商工廠
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsProviderFactory {

    /** 可用供應商清單（id => 顯示名稱） */
    public static function available(): array {
        return [
            'openai' => 'OpenAI',
            'gemini' => 'Google Gemini',
            'claude' => 'Anthropic Claude',
        ];
    }

    /**
     * 各供應商常見模型清單（第一個為預設／推薦；2026-06 更新）
     *
     * @return string[]
     */
    public static function models( string $id ): array {
        switch ( $id ) {
            case 'openai':
                return [ 'gpt-4o-mini', 'gpt-5.4-mini', 'gpt-5.5', 'gpt-5.4', 'gpt-5.4-nano', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o' ];
            case 'gemini':
                return [ 'gemini-2.5-flash', 'gemini-3.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite', 'gemini-3.1-flash-lite' ];
            case 'claude':
                return [ 'claude-haiku-4-5-20251001', 'claude-sonnet-4-6', 'claude-opus-4-8', 'claude-3-5-haiku-latest', 'claude-3-5-sonnet-latest' ];
        }
        return [];
    }

    /** 各供應商「模型清單」官方文件連結 */
    public static function docs_url( string $id ): string {
        $map = [
            'openai' => 'https://platform.openai.com/docs/models',
            'gemini' => 'https://ai.google.dev/gemini-api/docs/models',
            'claude' => 'https://docs.anthropic.com/en/docs/about-claude/models',
        ];
        return $map[ $id ] ?? '';
    }

    /**
     * 依設定組裝供應商物件
     *
     * @param string                    $id  供應商代碼
     * @param array<string, string>     $cfg key/model/prompt
     */
    public static function make( string $id, array $cfg = [] ): ?YSTPAddonsProviderInterface {
        $key    = $cfg['key'] ?? '';
        $model  = $cfg['model'] ?? '';
        $prompt = $cfg['prompt'] ?? '';

        switch ( $id ) {
            case 'openai':
                return new YSTPAddonsOpenAIProvider( $key, $model, $prompt );
            case 'gemini':
                return new YSTPAddonsGeminiProvider( $key, $model, $prompt );
            case 'claude':
                return new YSTPAddonsClaudeProvider( $key, $model, $prompt );
            case 'mock':
                return new YSTPAddonsMockProvider();
        }
        return null;
    }

    /**
     * 依目前設定取得啟用中的供應商
     */
    public static function from_settings(): ?YSTPAddonsProviderInterface {
        $id = (string) YSTPAddonsSettingsRepo::get( 'ai_provider', 'openai' );

        if ( 'mock' === $id ) {
            return new YSTPAddonsMockProvider();
        }

        return self::make( $id, [
            'key'    => (string) YSTPAddonsSettingsRepo::get( 'ai_key_' . $id, '' ),
            'model'  => (string) YSTPAddonsSettingsRepo::get( 'ai_model_' . $id, '' ),
            'prompt' => (string) YSTPAddonsSettingsRepo::get( 'ai_prompt', '' ),
        ] );
    }
}
