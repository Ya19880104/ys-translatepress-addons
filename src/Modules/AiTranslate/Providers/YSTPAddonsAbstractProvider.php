<?php
/**
 * AI 翻譯供應商抽象基底
 *
 * 集中處理 prompt 組裝、HTTP 呼叫、JSON 結果解析；
 * 各供應商只需實作端點、請求格式與回應萃取。
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

abstract class YSTPAddonsAbstractProvider implements YSTPAddonsProviderInterface {

    protected string $api_key;
    protected string $model;
    protected string $prompt_tpl;

    /** 常見語言碼 → 英文名稱（提升翻譯品質的提示用） */
    private const LANG_NAMES = [
        'en_US' => 'English', 'en_GB' => 'English',
        'zh_TW' => 'Traditional Chinese (Taiwan)', 'zh_HK' => 'Traditional Chinese (Hong Kong)',
        'zh_CN' => 'Simplified Chinese', 'ja' => 'Japanese', 'ko_KR' => 'Korean',
        'fr_FR' => 'French', 'de_DE' => 'German', 'es_ES' => 'Spanish', 'it_IT' => 'Italian',
        'pt_BR' => 'Portuguese (Brazil)', 'pt_PT' => 'Portuguese', 'ru_RU' => 'Russian',
        'th' => 'Thai', 'vi' => 'Vietnamese', 'id_ID' => 'Indonesian', 'nl_NL' => 'Dutch',
        'ar' => 'Arabic', 'tr_TR' => 'Turkish', 'pl_PL' => 'Polish',
    ];

    public function __construct( string $api_key, string $model = '', string $prompt_tpl = '' ) {
        $this->api_key    = $api_key;
        $this->model      = $model !== '' ? $model : $this->default_model();
        $this->prompt_tpl = $prompt_tpl;
    }

    /* ── 各供應商需實作 ── */
    abstract protected function endpoint(): string;
    abstract protected function request_args( string $system, string $user ): array;
    abstract protected function extract_text( array $json ): string;
    abstract protected function default_model(): string;

    /* ── 共用流程 ── */

    public function translate_batch( array $texts, string $target_lang, string $source_lang ) {
        $texts = array_values( $texts );
        if ( empty( $texts ) ) {
            return [];
        }
        if ( '' === $this->api_key ) {
            return new \WP_Error( 'ys_tp_no_key', __( '尚未設定 API 金鑰', 'ys-translatepress-addons' ) );
        }

        $system = $this->system_prompt( $source_lang, $target_lang );
        $user   = wp_json_encode( $texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        $text = $this->call( $system, $user );
        if ( is_wp_error( $text ) ) {
            return $text;
        }

        $arr = $this->extract_json_array( $text );
        if ( null === $arr ) {
            return new \WP_Error( 'ys_tp_parse', __( '無法解析 AI 回傳的翻譯結果', 'ys-translatepress-addons' ) );
        }

        // 長度對齊：不足補原文、過長截斷
        $out = [];
        foreach ( $texts as $i => $orig ) {
            $out[] = isset( $arr[ $i ] ) && is_string( $arr[ $i ] ) && '' !== $arr[ $i ] ? $arr[ $i ] : $orig;
        }
        return $out;
    }

    public function test_connection() {
        $result = $this->translate_batch( [ 'Hello' ], 'zh_TW', 'en_US' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    protected function call( string $system, string $user ) {
        $args = $this->request_args( $system, $user );
        $args = wp_parse_args( $args, [ 'timeout' => 90 ] );

        $res = wp_remote_post( $this->endpoint(), $args );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $code = (int) wp_remote_retrieve_response_code( $res );
        $body = (string) wp_remote_retrieve_body( $res );

        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( 'ys_tp_api_http', sprintf( 'API HTTP %d: %s', $code, mb_substr( wp_strip_all_tags( $body ), 0, 300 ) ) );
        }
        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return new \WP_Error( 'ys_tp_api_json', __( 'API 回應非有效 JSON', 'ys-translatepress-addons' ) );
        }
        $text = $this->extract_text( $json );
        if ( '' === trim( $text ) ) {
            return new \WP_Error( 'ys_tp_api_empty', __( 'API 回應內容為空', 'ys-translatepress-addons' ) );
        }
        return $text;
    }

    protected function system_prompt( string $source, string $target ): string {
        $src_name = self::LANG_NAMES[ $source ] ?? $source;
        $tgt_name = self::LANG_NAMES[ $target ] ?? $target;

        if ( '' !== $this->prompt_tpl ) {
            return strtr( $this->prompt_tpl, [
                '%source_lang%' => $src_name,
                '%target_lang%' => $tgt_name,
            ] );
        }

        return "You are a professional website translator. Translate each string in the user's JSON array "
            . "from {$src_name} to {$tgt_name}. "
            . "Rules: (1) Preserve ALL HTML tags, attributes, and entities exactly. "
            . "(2) Preserve placeholders such as %s, %d, %1\$s, {variable}, [shortcode], and URLs unchanged. "
            . "(3) Keep leading/trailing whitespace. (4) Do NOT translate brand names or code. "
            . "(5) Return ONLY a valid JSON array of translated strings, in the SAME order and length as the input. "
            . "No markdown, no code fences, no explanations.";
    }

    /**
     * 從模型輸出中萃取 JSON 陣列（容忍程式碼圍欄與多餘文字）
     *
     * @return array<int, mixed>|null
     */
    protected function extract_json_array( string $text ): ?array {
        $text = trim( $text );
        // 去除 ```json ... ``` 圍欄
        $text = preg_replace( '/^```[a-zA-Z]*\s*|\s*```$/m', '', $text );

        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) ) {
            return array_is_list( $decoded ) ? $decoded : array_values( $decoded );
        }

        // 退而求其次：擷取第一個 [ 到最後一個 ]
        $start = strpos( $text, '[' );
        $end   = strrpos( $text, ']' );
        if ( false !== $start && false !== $end && $end > $start ) {
            $slice   = substr( $text, $start, $end - $start + 1 );
            $decoded = json_decode( $slice, true );
            if ( is_array( $decoded ) ) {
                return array_values( $decoded );
            }
        }
        return null;
    }
}
