<?php
/**
 * AI 翻譯供應商介面
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

interface YSTPAddonsProviderInterface {

    /**
     * 批次翻譯
     *
     * @param string[] $texts       待翻譯字串（保留順序）
     * @param string   $target_lang 目標語言碼（例 zh_TW）
     * @param string   $source_lang 來源語言碼（例 en_US）
     * @return string[]|\WP_Error    與輸入等長、同順序的翻譯結果；失敗回 WP_Error
     */
    public function translate_batch( array $texts, string $target_lang, string $source_lang );

    /**
     * 測試連線與金鑰
     *
     * @return true|\WP_Error
     */
    public function test_connection();

    /** 供應商代碼（openai/gemini/claude/mock） */
    public function id(): string;

    /** 供應商顯示名稱 */
    public function label(): string;
}
