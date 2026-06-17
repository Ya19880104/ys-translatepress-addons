<?php
/**
 * 功能模組介面
 *
 * 所有模組皆實作 boot()，於外掛初始化時掛載自身的 hooks。
 *
 * @package YangSheep\TPAddons\Modules
 * @since   0.1.0
 */

namespace YangSheep\TPAddons\Modules;

defined( 'ABSPATH' ) || exit;

interface YSTPAddonsModuleInterface {

    /**
     * 掛載模組的 hooks（前後台皆會呼叫）
     */
    public function boot(): void;
}
