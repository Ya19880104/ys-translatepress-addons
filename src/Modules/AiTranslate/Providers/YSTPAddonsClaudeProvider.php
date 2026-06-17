<?php
/**
 * 供應商：Anthropic Claude
 *
 * @package YangSheep\TPAddons\Modules\AiTranslate\Providers
 * @since   0.3.0
 */

namespace YangSheep\TPAddons\Modules\AiTranslate\Providers;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsClaudeProvider extends YSTPAddonsAbstractProvider {

    public function id(): string {
        return 'claude';
    }
    public function label(): string {
        return 'Anthropic Claude';
    }
    protected function default_model(): string {
        return 'claude-haiku-4-5-20251001';
    }
    protected function endpoint(): string {
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function request_args( string $system, string $user ): array {
        return [
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'       => $this->model,
                'max_tokens'  => 4096,
                'temperature' => 0.2,
                'system'      => $system,
                'messages'    => [
                    [ 'role' => 'user', 'content' => $user ],
                ],
            ] ),
        ];
    }

    protected function extract_text( array $json ): string {
        return (string) ( $json['content'][0]['text'] ?? '' );
    }
}
