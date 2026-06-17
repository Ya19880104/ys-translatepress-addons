<?php
/**
 * 模組：語言自動偵測（重新設計）
 *
 * 依瀏覽器 Accept-Language 偵測訪客慣用語言，於首次到訪時以精緻的提示卡／橫幅
 * 建議切換語言。Cookie 記住選擇、不重複打擾；JS 驅動，對搜尋引擎爬蟲安全。
 *
 * @package YangSheep\TPAddons\Modules\AutoDetect
 * @since   0.2.0
 */

namespace YangSheep\TPAddons\Modules\AutoDetect;

use YangSheep\TPAddons\Modules\YSTPAddonsModuleInterface;
use YangSheep\TPAddons\Support\YSTPAddonsTP;
use YangSheep\TPAddons\Database\YSTPAddonsSettingsRepo;

defined( 'ABSPATH' ) || exit;

class YSTPAddonsAutoDetect implements YSTPAddonsModuleInterface {

    private const COOKIE = 'ys_tp_ald';

    public function boot(): void {
        if ( is_admin() || is_robots() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        $data = $this->detection_data();
        if ( null === $data['suggested'] ) {
            return; // 無建議（已是慣用語言）→ 不載入
        }

        wp_enqueue_style( 'ys-tp-detect', YS_TP_PLUGIN_URL . 'assets/css/ys-tp-detect.css', [], YS_TP_VERSION );
        wp_enqueue_script( 'ys-tp-detect', YS_TP_PLUGIN_URL . 'assets/js/ys-tp-detect.js', [], YS_TP_VERSION, true );
        wp_localize_script( 'ys-tp-detect', 'ysTPDetect', $data );
    }

    /**
     * 組裝偵測資料
     *
     * @return array<string, mixed>
     */
    private function detection_data(): array {
        $published = YSTPAddonsTP::published_language_codes();
        $current   = YSTPAddonsTP::current_language();
        $suggested = $this->detect_from_browser( $published );

        $payload = [
            'cookie'   => self::COOKIE,
            'behavior' => (string) YSTPAddonsSettingsRepo::get( 'detect_behavior', 'prompt' ),
            'style'    => (string) YSTPAddonsSettingsRepo::get( 'detect_style', 'card' ),
            'position' => (string) YSTPAddonsSettingsRepo::get( 'detect_position', 'bottom-right' ),
            'suggested' => null,
        ];

        if ( $suggested && $suggested !== $current ) {
            // 語言名稱用目標語言的原生自稱（訪客看得懂的）
            $name = YSTPAddonsTP::language_native_name( $suggested );

            // 提示框架文字依「建議的目標語言」採母語文案
            $strings = self::prompt_strings_for( $suggested );

            // 後台自訂文案優先（會套用到所有語言）
            $custom_title  = (string) YSTPAddonsSettingsRepo::get( 'detect_msg_title', '' );
            $custom_accept = (string) YSTPAddonsSettingsRepo::get( 'detect_msg_accept', '' );

            $payload['suggested'] = [
                'code' => $suggested,
                'name' => $name,
                'flag' => YSTPAddonsTP::flag_url( $suggested ),
                'url'  => YSTPAddonsTP::url_for_language( $suggested, YSTPAddonsTP::current_url() ),
            ];
            $payload['i18n'] = [
                'title'   => '' !== $custom_title ? $custom_title : sprintf( $strings['title'], $name ),
                'accept'  => '' !== $custom_accept ? $custom_accept : sprintf( $strings['accept'], $name ),
                'dismiss' => $strings['dismiss'],
            ];
        }

        return $payload;
    }

    /**
     * 依目標語言取得提示文案（母語）。%s 為語言名稱。
     *
     * 訪客看到的提示應以「建議切換的目標語言」書寫，例如日文訪客看到日文提示。
     * 未內建的語言回退英文。
     *
     * @return array{title:string, accept:string, dismiss:string}
     */
    private static function prompt_strings_for( string $locale ): array {
        $table = [
            'en'    => [ 'Browse this site in %s?', 'Switch to %s', 'Keep current language' ],
            'zh'    => [ '要以「%s」瀏覽本網站嗎？', '切換到 %s', '維持目前語言' ],
            'zh_CN' => [ '要以「%s」浏览本网站吗？', '切换到 %s', '保持当前语言' ],
            'ja'    => [ '%sでこのサイトを閲覧しますか？', '%sに切り替える', '現在の言語のまま' ],
            'ko'    => [ '이 사이트를 %s(으)로 보시겠어요?', '%s(으)로 전환', '현재 언어 유지' ],
            'fr'    => [ 'Consulter ce site en %s ?', 'Passer au %s', 'Garder la langue actuelle' ],
            'de'    => [ 'Diese Website auf %s anzeigen?', 'Zu %s wechseln', 'Aktuelle Sprache behalten' ],
            'es'    => [ '¿Ver este sitio en %s?', 'Cambiar a %s', 'Mantener el idioma actual' ],
            'pt'    => [ 'Navegar neste site em %s?', 'Mudar para %s', 'Manter o idioma atual' ],
            'it'    => [ 'Visualizzare questo sito in %s?', 'Passa a %s', 'Mantieni la lingua attuale' ],
            'ru'    => [ 'Просматривать сайт на %s?', 'Переключить на %s', 'Оставить текущий язык' ],
            'nl'    => [ 'Deze site in het %s bekijken?', 'Overschakelen naar %s', 'Huidige taal behouden' ],
            'ar'    => [ 'هل تريد تصفح هذا الموقع بـ %s؟', 'التبديل إلى %s', 'الإبقاء على اللغة الحالية' ],
            'th'    => [ 'ดูเว็บไซต์นี้เป็นภาษา %s ไหม?', 'เปลี่ยนเป็น %s', 'คงภาษาปัจจุบัน' ],
            'vi'    => [ 'Xem trang web này bằng %s?', 'Chuyển sang %s', 'Giữ ngôn ngữ hiện tại' ],
            'id'    => [ 'Lihat situs ini dalam %s?', 'Beralih ke %s', 'Tetap gunakan bahasa ini' ],
            'tr'    => [ 'Bu siteyi %s görüntülemek ister misiniz?', '%s geç', 'Mevcut dili koru' ],
            'pl'    => [ 'Przeglądać tę stronę w języku %s?', 'Przełącz na %s', 'Zachowaj bieżący język' ],
        ];

        // 完整 locale 特例（如 zh_CN 簡體）優先
        if ( isset( $table[ $locale ] ) ) {
            $row = $table[ $locale ];
        } else {
            $primary = (string) strtok( $locale, '_' );
            $row     = $table[ $primary ] ?? $table['en'];
        }

        return [ 'title' => $row[0], 'accept' => $row[1], 'dismiss' => $row[2] ];
    }

    /**
     * 依 Accept-Language 比對出最佳語言
     *
     * @param string[] $published 已發佈語言碼
     */
    private function detect_from_browser( array $published ): ?string {
        if ( empty( $published ) || empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            return null;
        }
        $header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );

        // 解析成 [code => quality]，依 quality 排序
        $prefs = [];
        foreach ( explode( ',', $header ) as $part ) {
            $part = trim( $part );
            if ( '' === $part ) {
                continue;
            }
            $q    = 1.0;
            $code = $part;
            if ( false !== strpos( $part, ';q=' ) ) {
                [ $code, $qs ] = array_pad( explode( ';q=', $part ), 2, '1' );
                $q = (float) $qs;
            }
            $prefs[] = [ 'code' => strtolower( trim( $code ) ), 'q' => $q ];
        }
        usort( $prefs, static fn( $a, $b ) => $b['q'] <=> $a['q'] );

        foreach ( $prefs as $pref ) {
            $bc = str_replace( '-', '_', $pref['code'] ); // zh-tw → zh_tw

            // 完全相符（不分大小寫）
            foreach ( $published as $p ) {
                if ( strtolower( $p ) === $bc ) {
                    return $p;
                }
            }
            // 語言前綴相符（zh / zh_tw → 任一 zh_*）
            $primary = strtok( $bc, '_' );
            foreach ( $published as $p ) {
                if ( strtolower( (string) strtok( $p, '_' ) ) === $primary ) {
                    return $p;
                }
            }
        }
        return null;
    }
}
