<?php
/**
 * Vendor Autoloader
 *
 * 載入 Hub Client 及其他 vendor 套件
 *
 * @package YangSheep\StarterPlugin
 */

if ( file_exists( __DIR__ . '/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php' ) ) {
    require_once __DIR__ . '/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php';
}
