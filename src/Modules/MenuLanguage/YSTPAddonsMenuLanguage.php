<?php
/**
 * 模組：選單語言控制
 *
 * 在「外觀 → 選單」每個項目加入「顯示語言」設定，前台依當前語言過濾選單項目。
 *
 * 後台 UI：wp_nav_menu_item_custom_fields
 * 儲存：    wp_update_nav_menu_item → post meta _ys_tp_menu_languages
 * 前台過濾：wp_get_nav_menu_items（含子項目層級串連隱藏）
 *
 * @package YangSheep\TPAddons\Modules\MenuLanguage
 * @since   0.2.0
 */

namespace YangSheep\TPAddons\Modules\MenuLanguage;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsMenuLanguage implements YSTPAddonsModuleInterface {

    /** @var string 選單項目語言設定的 meta key */
    public const META_KEY = '_ys_tp_menu_languages';

    public function boot(): void {
        if ( is_admin() ) {
            add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'render_fields' ], 10, 2 );
            add_action( 'wp_update_nav_menu_item', [ $this, 'save_fields' ], 10, 3 );
            add_action( 'admin_print_footer_scripts-nav-menus.php', [ $this, 'inline_js' ] );
        } else {
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_items' ], 20, 3 );
        }
    }

    /**
     * 選單編輯器內的「所有語言」連動腳本
     */
    public function inline_js(): void {
        ?>
        <script>
        ( function ( $ ) {
            function sync( $all ) {
                var $langs = $all.closest( 'p' ).find( '.ys-tp-menu-langs' );
                var on = $all.is( ':checked' );
                $langs.css( 'opacity', on ? .45 : 1 );
                $langs.find( 'input' ).prop( 'disabled', on );
            }
            $( document ).on( 'change', '.ys-tp-menu-all', function () { sync( $( this ) ); } );
            $( '.ys-tp-menu-all' ).each( function () { sync( $( this ) ); } );
        } )( jQuery );
        </script>
        <?php
    }

    /**
     * 後台：在每個選單項目渲染語言勾選欄位
     *
     * @param int      $item_id 選單項目 ID
     * @param \WP_Post $item    選單項目
     */
    public function render_fields( $item_id, $item ): void {
        $languages = YSTPAddonsTP::languages();
        if ( empty( $languages ) ) {
            return;
        }

        $saved = get_post_meta( $item_id, self::META_KEY, true );
        $saved = is_array( $saved ) ? $saved : [];
        $all   = empty( $saved ); // 未設定 = 所有語言

        wp_nonce_field( 'ys_tp_menu_' . $item_id, 'ys_tp_menu_nonce_' . $item_id );
        ?>
        <p class="field-ys-tp-menu-languages description description-wide">
            <label><strong><?php esc_html_e( '顯示語言（多語增強）', 'ys-translatepress-addons' ); ?></strong></label><br />
            <label style="display:inline-block;margin:4px 14px 4px 0;">
                <input type="checkbox" class="ys-tp-menu-all" data-item="<?php echo esc_attr( (string) $item_id ); ?>"
                    name="ys_tp_menu_all[<?php echo esc_attr( (string) $item_id ); ?>]" value="1" <?php checked( $all ); ?> />
                <?php esc_html_e( '所有語言', 'ys-translatepress-addons' ); ?>
            </label>
            <span class="ys-tp-menu-langs" style="<?php echo $all ? 'opacity:.45;' : ''; ?>">
                <?php foreach ( $languages as $code => $name ) : ?>
                    <label style="display:inline-block;margin:4px 14px 4px 0;">
                        <input type="checkbox"
                            name="ys_tp_menu_languages[<?php echo esc_attr( (string) $item_id ); ?>][]"
                            value="<?php echo esc_attr( $code ); ?>"
                            <?php checked( ! $all && in_array( $code, $saved, true ) ); ?> />
                        <?php echo esc_html( $name ); ?>
                    </label>
                <?php endforeach; ?>
            </span>
        </p>
        <?php
    }

    /**
     * 後台：儲存選單項目語言設定
     *
     * @param int $menu_id          選單 ID
     * @param int $menu_item_db_id  選單項目 ID
     */
    public function save_fields( $menu_id, $menu_item_db_id ): void {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return;
        }
        $nonce_field = 'ys_tp_menu_nonce_' . $menu_item_db_id;
        if ( empty( $_POST[ $nonce_field ] ) ||
            ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $nonce_field ] ) ), 'ys_tp_menu_' . $menu_item_db_id ) ) {
            return;
        }

        $all = ! empty( $_POST['ys_tp_menu_all'][ $menu_item_db_id ] );

        if ( $all ) {
            delete_post_meta( $menu_item_db_id, self::META_KEY );
            return;
        }

        $selected = [];
        if ( isset( $_POST['ys_tp_menu_languages'][ $menu_item_db_id ] ) && is_array( $_POST['ys_tp_menu_languages'][ $menu_item_db_id ] ) ) {
            $valid    = array_keys( YSTPAddonsTP::languages() );
            $selected = array_values( array_intersect(
                array_map( 'sanitize_text_field', wp_unslash( $_POST['ys_tp_menu_languages'][ $menu_item_db_id ] ) ),
                $valid
            ) );
        }

        if ( empty( $selected ) ) {
            // 取消勾選「所有語言」卻又沒選任何語言 → 視為所有語言（避免整個項目消失）
            delete_post_meta( $menu_item_db_id, self::META_KEY );
        } else {
            update_post_meta( $menu_item_db_id, self::META_KEY, $selected );
        }
    }

    /**
     * 前台：依當前語言過濾選單項目（含子項目層級串連隱藏）
     *
     * @param array $items 選單項目
     * @return array
     */
    public function filter_items( $items, $menu = null, $args = null ) {
        if ( is_admin() || empty( $items ) || ! is_array( $items ) ) {
            return $items;
        }

        $current = YSTPAddonsTP::current_language();
        $removed = [];

        // 第一輪：依語言設定移除
        foreach ( $items as $key => $item ) {
            $langs = get_post_meta( $item->ID, self::META_KEY, true );
            if ( ! empty( $langs ) && is_array( $langs ) && ! in_array( $current, $langs, true ) ) {
                $removed[ (int) $item->ID ] = true;
                unset( $items[ $key ] );
            }
        }

        // 後續：串連移除「父項目已被移除」的子項目，直到穩定
        if ( ! empty( $removed ) ) {
            do {
                $changed = false;
                foreach ( $items as $key => $item ) {
                    $parent = (int) $item->menu_item_parent;
                    if ( $parent && isset( $removed[ $parent ] ) ) {
                        $removed[ (int) $item->ID ] = true;
                        unset( $items[ $key ] );
                        $changed = true;
                    }
                }
            } while ( $changed );
        }

        return $items;
    }
}
