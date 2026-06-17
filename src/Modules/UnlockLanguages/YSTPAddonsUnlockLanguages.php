<?php
/**
 * 模組：解鎖語言數量
 *
 * TranslatePress 透過 `trp_secondary_languages` filter 限制次要語言數量
 * （預設 1）。本模組將上限提高，可新增無限多語言。
 *
 * 參考：translatepress-multilingual/includes/class-abilities.php:366
 *
 * @package YangSheep\TPAddons\Modules\UnlockLanguages
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Modules\UnlockLanguages;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsUnlockLanguages implements YSTPAddonsModuleInterface {

    /** @var int 預設解鎖上限 */
    private const DEFAULT_MAX = 1000;

    /**
     * 掛載 hooks
     */
    public function boot(): void {
        // 優先度 5：較早掛載，確保我們設定的上限生效。
        add_filter( 'trp_secondary_languages', [ $this, 'raise_limit' ], 5 );
    }

    /**
     * 提高次要語言上限
     *
     * @param int $limit TranslatePress 預設上限
     * @return int
     */
    public function raise_limit( $limit ): int {
        $max = (int) YSTPAddonsSettingsRepo::get( 'unlock_max', self::DEFAULT_MAX );
        if ( $max < 1 ) {
            $max = self::DEFAULT_MAX;
        }
        // 取較大值，避免反而縮小其他來源設定的上限。
        return max( (int) $limit, $max );
    }

    /**
     * 目前生效的上限（供後台顯示）
     */
    public static function current_limit(): int {
        $max = (int) YSTPAddonsSettingsRepo::get( 'unlock_max', self::DEFAULT_MAX );
        return $max < 1 ? self::DEFAULT_MAX : $max;
    }
}
