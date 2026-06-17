<?php
/**
 * 供應商：OpenAI
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsOpenAIProvider extends YSTPAddonsAbstractProvider {

    public function id(): string {
        return 'openai';
    }
    public function label(): string {
        return 'OpenAI';
    }
    protected function default_model(): string {
        return 'gpt-4o-mini';
    }
    protected function endpoint(): string {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function request_args( string $system, string $user ): array {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'       => $this->model,
                'temperature' => 0.2,
                'messages'    => [
                    [ 'role' => 'system', 'content' => $system ],
                    [ 'role' => 'user', 'content' => $user ],
                ],
            ] ),
        ];
    }

    protected function extract_text( array $json ): string {
        return (string) ( $json['choices'][0]['message']['content'] ?? '' );
    }
}
