<?php
/**
 * 供應商：測試模擬（不呼叫外部 API）
 *
 * 用於驗證整條翻譯管線（讀取→翻譯→寫回 TP 表）而無需真實金鑰。
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsMockProvider implements YSTPAddonsProviderInterface {

    public function translate_batch( array $texts, string $target_lang, string $source_lang ) {
        $tag = strtoupper( (string) strtok( $target_lang, '_' ) );
        $out = [];
        foreach ( array_values( $texts ) as $t ) {
            $out[] = '[' . $tag . '] ' . $t;
        }
        return $out;
    }

    public function test_connection() {
        return true;
    }
    public function id(): string {
        return 'mock';
    }
    public function label(): string {
        return __( '測試模擬', 'ys-translatepress-addons' );
    }
}
