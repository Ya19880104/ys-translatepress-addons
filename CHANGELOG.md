# Changelog

本檔案記錄所有重要變更，格式依循 [Keep a Changelog](https://keepachangelog.com/)。

## [0.10.0] - 2026-06-25 — 階梯式智慧重導 + Sitemap 排除被隱藏語言

### 新增
- **內容語言規則：階梯式智慧重導** — 訪客造訪「被該語言隱藏」的單頁時，依序嘗試 ① 跳到最近一個「該語言可見」的**上層**（頁面／階層式內容）→ ② 該**內容類型**的 fallback 頁 → ③ **全域預設跳轉頁**；並可選擇 **301／302／404** 處理方式。後台「內容語言規則」新增：重導處理方式、優先跳上層開關、全域預設跳轉頁，各內容類型可設「未指定（用全域預設）」。

### 變更
- **多語 Sitemap**：被「內容語言規則」排除某語言的內容，不再於 Yoast／RankMath sitemap 為該語言輸出 hreflang alternate（避免 sitemap 指向會被重導／404 的網址）。

## [0.9.1] - 2026-06-25 — 後台介面品牌化

### 變更
- 後台設定介面改採 YANGSHEEP 品牌紫（`#cc99c2`）配色，與其他 YS 外掛後台調性一致；同步精修字體色階（WordPress 原生灰階）、卡片圓角與陰影、focus 樣式。純樣式調整，功能不變。

## [0.9.0] - 2026-06-17 — SEO 中繼資料翻譯 + 語言名稱自訂

### 新增
- **SEO 中繼資料翻譯** — 頁面標題（`<title>`）、meta 描述、Open Graph／Twitter 社群標籤與圖片 `alt` 成為可翻譯字串，可於 TranslatePress 翻譯編輯器逐語言翻譯，前台輸出時自動替換為對應語言版本。透過 TranslatePress 核心既有的 `trp_node_accessors` 機制實作，於「SEO 增強」模組以「翻譯 SEO 中繼資料」開關控制（預設開啟）。
  - 節點型別沿用核心約定鍵（`page_title`／`meta_desc`／`meta_desc_img`），編輯器自動將其歸入「Meta Information」群組並逐一標示（Page Title／Description／OG Title／OG Site Name／OG Image／OG Image Alt／Twitter 等），而非混入一般字串清單。
- **語言名稱自訂** — 於「語言切換器」設定頁可為每個語言自訂顯示名稱（例如把「Chinese／中文」改成「繁體中文」），透過 TranslatePress 核心 `trp_language_name` filter 套用於切換器、選單、hreflang 等所有語言名稱顯示處；留空＝沿用 TranslatePress 預設。
- **隱藏 TranslatePress 升級／推廣提示**（選用）— 可隱藏 TranslatePress 後台設定頁與翻譯編輯器的升級／推廣區塊，讓後台介面更精簡；僅以 CSS 隱藏既知容器、不更動 TranslatePress 任何功能，於「總覽 → 相容性」開關控制（預設關閉）。
- **內容語言規則：自訂列表過濾 filter** — 新增 `ys_tp_filter_ids`（過濾 post ID 陣列）與 `ys_tp_is_hidden`（判斷單篇是否於當前語言隱藏）兩個 filter，供頁面建構器迴圈、已存 ID 陣列等非標準 `WP_Query` 列表顯式套用語言顯示規則。

## [0.8.0] - 2026-06-17 — 一鍵沿用既有翻譯設定

### 新增
- **沿用既有翻譯設定** — 偵測既有的翻譯網址 slug（資料表與 meta 兩種格式）與選單語言設定（其他 TranslatePress 多語外掛留下的資料），自動轉為本外掛欄位並啟用對應模組（SEO 翻譯網址 slug、選單語言控制），讓功能無縫延續。
  - **自動**：當「衝突外掛自動停用」偵測並停用功能重疊的外掛時，連帶沿用其既有設定，停用通知一併顯示沿用結果。
  - **手動**：於「多語增強 → 總覽 → 沿用既有翻譯設定」提供「開始沿用」按鈕，可隨時執行。
  - 安全：僅在本外掛對應欄位尚未設定時才寫入，**不會覆蓋**既有設定；可重複執行（具冪等性）。`url-slug` 與 `locale` 兩種語言標示皆自動正規化。

## [0.7.2] - 2026-06-17 — 短代碼預覽整合 + 沿用既有翻譯欄位

### 新增
- 語言切換器設定頁：各樣式即時預覽下方直接顯示對應短代碼，可點擊複製。
- **沿用既有翻譯欄位**：翻譯網址 slug 與選單語言控制在查不到本外掛資料時，自動沿用既有的翻譯 slug（資料表與 meta 兩種格式）與選單語言欄位，原本設定可直接延續、不需重設。

## [0.7.1] - 2026-06-17 — 文案調整與套件精簡

### 變更
- 介面與文件文案中性化。
- 套件精簡：發布包不再含開發用檔案（不影響功能）。

## [0.7.0] - 2026-06-17 — 衝突外掛自動停用 + CPT／Blocksy 相容

### 新增
- **衝突外掛自動停用** — 偵測到與本外掛功能重疊、同時啟用會互相干擾的已知外掛時，自動停用以避免衝突，並保留本外掛所需的 TranslatePress 核心；於「多語增強 → 總覽 → 相容性」可開關，停用時顯示 admin notice 說明。

### 驗證
- **自訂文章類型（CPT）語言排除**：於「內容語言規則 → 套用的內容類型」勾選公開 CPT 後，即支援 meta box 設定、列表自動隱藏（`pre_get_posts`）與智慧重導（`template_redirect`）；翻譯 slug meta box 對所有公開 CPT 皆有。已以 `portfolio` CPT 實測通過。
- **Blocksy 主題相容**：文章列表（部落格／封存）排除、選單語言、翻譯內容、浮動切換器於 Blocksy 主題下實測正常。

## [0.6.0] - 2026-06-17 — AI 模型下拉 + 偵測提示母語化

### 新增
- **AI 模型欄位改為下拉選單** — 依供應商列出當前常見模型（OpenAI gpt-5.5／5.4／4o-mini…、Gemini 2.5-flash／3.5-flash／2.5-pro…、Claude haiku-4-5／sonnet-4-6／opus-4-8…），並提供「其他（自行輸入）」與「官方模型清單」查詢連結。
- 語言自動偵測提示改用**目標語言母語文案**：訪客看到的提示以「建議切換的目標語言」書寫（日文訪客看到日文、法文訪客看到法文…），語言名稱使用原生自稱；內建 18 種常見語言，未列出者回退英文。後台自訂文案仍優先。

### 變更
- 更新各供應商預設模型至 2026-06 當前版本（🔴 Gemini `gemini-2.0-flash` 已於 2026-06-01 停用，預設改為 `gemini-2.5-flash`；Claude 預設改為 `claude-haiku-4-5-20251001`）。

## [0.5.0] - 2026-06-17 — 翻譯網址 slug + 地圖切換器 + 文件

### 新增
- **翻譯網址 slug（URL rewrite，可開關）** — 可為各語言設定自訂網址 slug（如英文 `/sample/` 對應中文 `/範例/`）。
  - Outbound：站內連結與 hreflang 自動使用翻譯網址（掛 `trp_get_url_for_language`）。
  - Inbound：於 `plugins_loaded` priority 2（早於 TranslatePress）改寫 `$_SERVER['REQUEST_URI']`，把翻譯 slug 換回原始 slug，讓 WordPress 原生解析（支援階層頁面）。
  - 文章／頁面編輯頁 meta box 逐語言設定（meta `_ys_tp_slug_{locale}`）；同語言內自動確保唯一；原始網址向後相容。
  - 於 SEO 模組以「啟用翻譯網址 slug」開關控制。
- **語言切換器：世界地圖樣式** `[ys_language_switcher style="map"]` — 彈出視窗內以世界地圖呈現，各語言以旗幟標於對應地理位置，未列座標的語言列於下方卡片區。
- **使用手冊** `docs/USAGE.md` — 完整功能與短代碼 DEMO 範例。
- **i18n 翻譯範本** `languages/ys-translatepress-addons.pot`。

## [0.4.0] - 2026-06-16 — Phase 3：SEO 增強 + 翻譯匯出／匯入

### 新增
- **模組：SEO 增強** — hreflang 細控（地區標籤 zh-TW／en-US、地區無關標籤 zh／en 開關，掛 TranslatePress 既有 filter）、一鍵啟用 x-default 並指定指向語言；偵測 Yoast／RankMath 後自動在其 sitemap 每個網址加入各語言 xhtml:link alternate。
- **模組：翻譯匯出／匯入** — 將 dictionary／gettext 翻譯匯出為 JSON（可選只匯出已翻譯），外部編修後依「原文」比對匯入寫回；含各語言完成度統計。

### 說明
- 翻譯網址 slug（將 /zh/sample 變為 /zh/翻譯後）需重建 URL rewrite 機制，與免費版核心耦合度高，列為後續評估項目；現階段 SEO 聚焦在 TranslatePress 已支援、可靠的 hreflang 與 sitemap 強化。

## [0.3.0] - 2026-06-16 — Phase 2：AI 翻譯

### 新增
- **模組：AI 翻譯** — 支援 OpenAI／Google Gemini／Anthropic Claude 三家供應商（可切換、各自金鑰與模型、可自訂 system prompt）。
- 翻譯結果直接寫入 TranslatePress 的 dictionary／gettext 表（狀態＝機器翻譯），前台立即生效。
- **四種觸發**：
  - 排程背景（WP-Cron 時間預算迴圈分批翻譯，徹底解決前台即時翻譯卡頓）。
  - 手動全站（後台進度儀表板一鍵翻譯，前端輪詢顯示進度）。
  - 手動逐頁（文章編輯頁「AI 翻譯此頁」按鈕，探索並翻譯該頁字串）。
  - 存檔後自動（發佈內容後背景翻譯）。
- 供應商抽象層 + 穩健 JSON 結果解析（容忍程式碼圍欄）；測試模擬供應商可不需金鑰驗證管線。
- API 金鑰留空＝保留既有；停用模組／停用外掛時自動清除背景排程。

## [0.2.0] - 2026-06-16 — Phase 1：設定型四模組

### 新增
- **模組：選單語言控制** — 於「外觀 → 選單」每個項目設定顯示語言，前台 `wp_get_nav_menu_items` 依當前語言過濾（含子項目層級串連隱藏）。
- **模組：內容語言規則** — 文章／頁面／CPT 可設定「排除指定語言」或「只在指定語言顯示」；前台列表（封存／搜尋）自動排除；單頁直接造訪時智慧重導至「當前語言」的對應頁（文章→該語言文章列表、頁面→該語言首頁），fallback 目標可後台分別指定。
- **模組：語言切換器** — 全新短代碼 `[ys_language_switcher]`，四種樣式（下拉／並排／彈出視窗／固定浮動），可全站自動注入浮動切換器並自動抑制 TranslatePress 內建浮動器；後台即時預覽。
- **模組：語言自動偵測** — 依瀏覽器語言偵測並以精緻提示卡／橫幅建議切換，Cookie 記憶、JS 驅動 bot-safe；支援詢問與自動兩種模式、可自訂文案。
- TP 橋接層新增旗幟 URL、語言短碼、當前頁 URL、切換器資料等 helper。

## [0.1.0] - 2026-06-16 — Phase 0：地基與第一個模組

### 新增
- 外掛地基：PSR-4 autoload（含 fallback autoloader）、Singleton 主類別、自訂設定資料表、AJAX 設定儲存。
- TranslatePress 依賴檢查：未安裝／未啟用時顯示安裝提示且不初始化。
- `YSTPAddonsTP` TranslatePress 橋接層：集中存取當前語言、語言清單、URL 語言轉換等。
- 模組註冊表 `YSTPAddonsModules`：八大功能模組的中繼資料與獨立啟用／停用機制。
- 後台「多語增強」選單與總覽頁（模組卡片網格、啟用開關、語言概況）。
- **模組：解鎖語言數量** — 透過 `trp_secondary_languages` filter 移除免費版「僅 1 個次要語言」限制，可自訂上限（預設 1000）。
- Hub Client 自動更新整合。
- 乾淨白底 + 莫蘭迪藍灰 + navy/橙金的後台 UI。

### 規劃中（後續 Phase）
- 語言切換器、語言自動偵測、選單語言控制、內容語言規則（Phase 1）。
- AI 翻譯：OpenAI／Gemini／Claude，含排程背景翻譯（Phase 2）。
- SEO 增強、翻譯匯出／匯入（Phase 3）。
