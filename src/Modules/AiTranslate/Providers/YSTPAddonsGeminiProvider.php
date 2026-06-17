<?php
/**
 * 供應商：Google Gemini
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsGeminiProvider extends YSTPAddonsAbstractProvider {

    public function id(): string {
        return 'gemini';
    }
    public function label(): string {
        return 'Google Gemini';
    }
    protected function default_model(): string {
        return 'gemini-2.5-flash';
    }
    protected function endpoint(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode( $this->model ) . ':generateContent?key=' . rawurlencode( $this->api_key );
    }

    protected function request_args( string $system, string $user ): array {
        return [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
                'contents'          => [ [ 'parts' => [ [ 'text' => $user ] ] ] ],
                'generationConfig'  => [ 'temperature' => 0.2 ],
            ] ),
        ];
    }

    protected function extract_text( array $json ): string {
        return (string) ( $json['candidates'][0]['content']['parts'][0]['text'] ?? '' );
    }
}
