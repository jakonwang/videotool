# 鍔熻兘闇€姹備笌瀹炵幇璇存槑锛圱ikStar OPS 2.0锛?
> 鏇存柊鏃ユ湡锛?026-04-07  
> 缁存姢绾﹀畾锛歚docs/requirements.md` 涓烘鏈紝鏍圭洰褰?`requirements.md` 涓庡叾淇濇寔涓€鑷淬€?
## 1. 鎬讳綋鏂瑰悜锛堝凡钀藉湴锛?
- 涓荤嚎锛氬厛鍋?**杈句汉 CRM 娴佺▼鑷姩鍖?V1**锛屽苟琛屼笂绾?**琛屼笟/绔炲搧/骞垮憡鎯呮姤 MVP**銆?- Zalo / WhatsApp 绛栫暐锛氶噰鐢?**鍗婅嚜鍔ㄩ棴鐜?*锛堢敓鎴愬唴瀹?+ 澶嶅埗 + 鎵撳紑浼氳瘽 + 浜哄伐鍙戦€侊級锛屼笉鍋氭棤浜哄€煎畧鑷姩鍙戦€併€?- 鏁版嵁绛栫暐锛氬疄鐜?**鍙彃鎷旀暟鎹眰**锛圕SV 瀵煎叆鍙敤锛孉PI Adapter 宸查鐣欙級銆?- 澶氳瑷€锛氬悗鍙?UI 鏂囨缁熶竴璧?`AppI18n.t`锛屾敮鎸?`zh/en/vi`銆?
## 2. 褰撳墠淇℃伅鏋舵瀯锛堜晶鏍忓姩鎬佹覆鏌擄級

渚ф爮鐢?`app/service/ModuleManagerService.php` 鐨?`getEnabledMenus()` 鍔ㄦ€佺敓鎴愶紝骞舵寜妯″潡鍚敤鐘舵€佷笌瑙掕壊杩囨护銆?
| 涓€绾у垎缁?| 浜岀骇鑿滃崟 | 璺敱 | 璇存槑 |
|---|---|---|---|
| 姒傝 | 浠〃鐩?| `/admin.php` | KPI 涓庢€昏 |
| 瀵绘 | 娆惧紡妫€绱€佺嚎涓嬮瀹?| `/admin.php/product_search`銆乣/admin.php/offline_order` | 鍥炬悳銆佸浘鍐屻€侀瀹氳鍗?|
| 澧為暱涓彴 | 琛屼笟瓒嬪娍銆佺珵鍝佸垎鏋愩€佸箍鍛婃儏鎶ャ€佹暟鎹鍏?| `/admin.php/industry_trend`銆乣/admin.php/competitor_analysis`銆乣/admin.php/ad_insight`銆乣/admin.php/data_import` | 鎯呮姤 MVP + 瀵煎叆浠诲姟 |
| 杈句汉杩愯惀 | 鍒嗙被閰嶇疆銆佽揪浜哄悕褰曘€佸鑱斿伐浣滃彴銆佸瘎鏍风鐞嗐€佽瘽鏈ā鏉裤€佽揪浜洪摼璺?| `/admin.php/category`銆乣/admin.php/influencer`銆乣/admin.php/outreach_workspace`銆乣/admin.php/sample`銆乣/admin.php/message_template`銆乣/admin.php/distribute` | CRM 鑷姩鍖栦富娴佺▼ |
| 绱犳潗涓庡晢鍝?| 瑙嗛銆佹壒閲忎笂浼犮€佸唴瀹瑰垎鍙?| `/admin.php/video`銆乣/admin.php/video/batchUpload`銆乣/admin.php/product` | 绱犳潗褰掓。涓庡晢鍝佸垎鍙?|
| 缁堢 | 骞冲彴銆佽澶?| `/admin.php/platform`銆乣/admin.php/device` | 缁堢绠＄悊 |
| 绯荤粺 | 璁剧疆銆佽繍缁翠腑蹇冦€佸紓甯搞€佺敤鎴枫€佹ā鍧楃鐞?| `/admin.php/settings`銆乣/admin.php/ops_center`銆乣/admin.php/download_log`銆乣/admin.php/user`銆乣/admin.php/extension` | 瓒呯鍩燂紙operator 榛樿涓嶅彲瑙佺郴缁熺粍锛?|

## 3. 杈句汉 CRM 鑷姩鍖?V1锛堝凡瀹炵幇锛?
### 3.1 鍒嗙被鑱斿姩

- 鍒嗙被缁熶竴鏉ユ簮锛歚GET /admin.php/category/options?type=influencer`銆?- 浣跨敤浣嶇疆锛?  - 杈句汉鍒楄〃绛涢€夋爮鐨勨€滃垎绫烩€濅笅鎷夈€?  - 杈句汉缂栬緫寮圭獥鐨勨€滃垎绫烩€濋€夋嫨鍣ㄣ€?- 琛ㄦ牸鏄剧ず `category_name`锛屼负绌烘椂鏄剧ず鈥滄湭鍒嗙被鈥濄€?
### 3.2 鐘舵€佹満涓庡璁?
- 杈句汉鐘舵€佸浐瀹氫负锛?  - `0 寰呰仈绯籤
  - `1 宸插彂绉佷俊`
  - `2 宸插洖澶峘
  - `3 寰呭瘎鏍穈
  - `4 宸插瘎鏍穈
  - `5 鍚堜綔涓璥
  - `6 榛戝悕鍗昤
- 鍙樻洿鍏ュ彛锛氳揪浜虹紪杈戙€佸揩鎹锋搷浣溿€佸鑱斿伐浣滃彴鍔ㄤ綔銆佸瘎鏍峰姩浣溿€?- 鐘舵€佹祦杞棩蹇楋細`influencer_status_logs`銆?- 缁熶竴娴佽浆鏈嶅姟锛歚app/service/InfluencerStatusFlowService.php`銆?
### 3.3 澶栬仈宸ヤ綔鍙帮紙Outreach Workspace锛?
- 椤甸潰锛歚/admin.php/outreach_workspace`銆?- 浠诲姟闃熷垪琛細`influencer_outreach_tasks`銆?- 浠诲姟鐘舵€侊細
  - `0 pending`
  - `1 copied`
  - `2 jumped`
  - `3 completed`
  - `4 skipped`
- 鏍稿績鎺ュ彛锛?  - `POST /admin.php/outreach_workspace/generate` 鎵归噺鐢熸垚浠诲姟銆?  - `GET /admin.php/outreach_workspace/nextTask` 鎷夊彇涓嬩竴鏉′换鍔°€?  - `POST /admin.php/outreach_workspace/action` 鎵ц鍔ㄤ綔锛坈opy / jump / complete / skip / reset / to_wait_sample锛夈€?- 寮圭獥鏀寔鑱旂郴鏂瑰紡閫氶亾閫夋嫨锛歚auto / WhatsApp / Zalo`銆?- 澶嶅埗閫昏緫鍏煎 `navigator.clipboard` 涓?`document.execCommand('copy')` 鍥為€€銆?
### 3.4 澶栬仈鍘嗗彶涓庢渶杩戣仈绯绘椂闂?
- 澶栬仈鏃ュ織琛細`outreach_logs`銆?- 璁板綍鍏ュ彛锛?  - `POST /admin.php/message_template/render`
  - `POST /admin.php/influencer/logOutreachAction`
- 鏃ュ織瀛楁鍖呭惈锛氭ā鏉?ID銆佸晢鍝?ID銆佹搷浣滅被鍨嬨€佹覆鏌撴鏂囥€佹椂闂淬€?- 鑷姩鏇存柊锛歚influencers.last_contacted_at`銆?
### 3.5 鏍囩绯荤粺

- 鏁版嵁瀛楁锛歚influencers.tags_json`銆?- 鍓嶇浜や簰锛氬鏍囩杈撳叆銆佹爣绛剧瓫閫夛紙鍒楄〃椤舵爮锛夈€?- 鍒楄〃鏀寔鎸夋爣绛惧叧閿瓧杩囨护銆?
### 3.6 瀵勬牱绠＄悊鎷嗗垎

- 鐙珛椤甸潰锛歚/admin.php/sample`銆?- 鏁版嵁琛細`sample_shipments`銆?- 鏀寔瀛楁锛氬崟鍙枫€佸揩閫掑叕鍙搞€佸瘎鏍风姸鎬併€佺鏀剁姸鎬併€佸瘎鍑?绛炬敹鏃堕棿銆佸娉ㄣ€?- 鍙粠杈句汉鍚嶅綍蹇嵎鈥滃瘎鏍封€濆苟鑱斿姩鐘舵€佸埌 `4 宸插瘎鏍穈銆?
## 4. 璇濇湳娓叉煋涓庡璇█鍙橀噺锛堝凡瀹炵幇锛?
### 4.1 鍙橀噺寮曟搸

`app/service/MessageOutreachService.php` 鏀寔锛?
- `{{current_time_period}}`锛氭寜鏈嶅姟鍣ㄦ椂闂磋緭鍑鸿秺鍗楄闂€欍€?  - `05-11` 鈫?`Ch脿o bu峄昳 s谩ng`
  - `12-18` 鈫?`Ch脿o bu峄昳 chi峄乽`
  - `19-23` / `00-04` 鈫?`Ch脿o bu峄昳 t峄慽`
- `{{random_emoji}}`锛氫粠棰勮 Emoji 姹犻殢鏈烘娊鍙栥€?
### 4.2 妯℃澘璇█鍖归厤

- 妯℃澘琛細`message_templates`锛屾敮鎸?`lang` 涓?`template_key`銆?- 娓叉煋鎺ュ彛锛歚POST /admin.php/message_template/render`銆?- 璇█绛栫暐锛氭寜杈句汉 `region` 鎺ㄦ柇璇█锛屼紭鍏堝尮閰嶅悓 `template_key` 鐨勭洰鏍囪瑷€妯℃澘锛屽け璐ュ洖閫€鑻辨枃鐗堟湰銆?
### 4.3 璺宠浆鍗忚

- `wa_url`锛氭湁 WhatsApp 鏃剁敓鎴?`https://wa.me/{number}?text=...`銆?- `zalo_url`锛氭湁 Zalo 鏃剁敓鎴?`https://zalo.me/{id}`銆?- 娓叉煋杩斿洖锛歚text`銆乣wa_url`銆乣zalo_url`銆?
## 5. 澧為暱涓彴鎯呮姤 MVP锛堝凡瀹炵幇锛?
### 5.1 琛屼笟瓒嬪娍

- 椤甸潰锛歚/admin.php/industry_trend`銆?- 琛細`growth_industry_metrics`銆?- 鑳藉姏锛氬垪琛ㄣ€佹眹鎬汇€丆SV 瀵煎叆銆丆SV 瀵煎嚭銆?
### 5.2 绔炲搧鍒嗘瀽

- 椤甸潰锛歚/admin.php/competitor_analysis`銆?- 琛細`growth_competitors`銆乣growth_competitor_metrics`銆?- 鑳藉姏锛氱珵鍝佷富浣撶淮鎶ゃ€佹寚鏍囧鍏ュ鍑恒€佸姣旀煡鐪嬨€?
### 5.3 骞垮憡鎯呮姤

- 椤甸潰锛歚/admin.php/ad_insight`銆?- 琛細`growth_ad_creatives`銆乣growth_ad_metrics`銆?- 鑳藉姏锛氬垱鎰忓簱绠＄悊銆佹棩缁村害鎸囨爣瀵煎叆瀵煎嚭銆?
### 5.4 鍙彃鎷旀暟鎹眰

- 椤甸潰锛歚/admin.php/data_import`銆?- 琛細`data_sources`銆乣import_jobs`銆乣import_job_logs`銆?- 鑳藉姏锛?  - 鏁版嵁婧愰厤缃紙CSV / API Adapter锛夈€?  - 浠诲姟鎵ц銆佹棩蹇楁煡鐪嬨€佸け璐ラ噸璇曘€?
## 6. 瀵绘涓庣嚎涓嬮瀹氶棴鐜紙宸插疄鐜帮級

### 6.1 瀵绘鍚庡彴

- 椤甸潰锛歚/admin.php/product_search`銆?- 鏀寔锛欳SV/Excel 瀵煎叆銆丄I 鎻忚堪銆佹壒閲忓垹闄ゃ€佺紪杈戙€佸鍑恒€佺ず渚嬩笅杞姐€?- 鍥剧墖鍒楁敮鎸佺偣鍑绘斁澶ч瑙堬紙`el-image` 棰勮锛夈€?- 鏀寔鐢熸垚瀹㈡埛鍥惧唽閾炬帴 Token 涓庡垎浜捣鎶ャ€?
### 6.2 鍥炬悳 API锛堝叏閲忔瘮瀵癸級

- 鎺ュ彛锛歚POST /index.php/api/product_search/searchByImage`锛堝吋瀹?`/api/search/searchByImage`锛夈€?- 閫昏緫锛氭寜鎵规閬嶅巻鍙敤绱㈠紩锛屼笉鍐嶄粎鍥哄畾 50 鏉℃牱鏈€?- 鏃犵簿纭尮閰嶆椂鏀寔鍏抽敭璇嶅洖閫€銆?
### 6.3 鏁版嵁鎵╁睍锛堜环鏍间笌璁㈠崟锛?
- 娆惧紡琛ㄦ柊澧烇細
  - `wholesale_price`
  - `min_order_qty`
  - `price_levels_json`锛堝垎绾т环锛?- 璁㈠崟琛細`offline_orders`銆?  - `order_no`銆乣customer_info`銆乣items_json`銆乣total_amount`銆乣status`銆?
### 6.4 瀹㈡埛鍥惧唽 H5

- 椤甸潰锛歚/index.php/styleCatalog`锛堝吋瀹?`/index.php/product_search`锛夈€?- 灞曠ず锛氭寜鍏抽敭璇?鍒嗙被娴忚锛岀Щ鍔ㄧ鐎戝竷娴侊紝鏄剧ず鎵瑰彂浠蜂笌璧锋壒閲忋€?- 浜や簰锛氬姞鍏ラ瀹氥€佽喘鐗╄溅銆佹彁浜よ仈绯讳汉锛堝鍚?鐢佃瘽/WhatsApp/Zalo锛夈€?- 涓嬪崟鎺ュ彛锛歚POST /index.php/api/product_search/offlineOrder`銆?
### 6.5 绾夸笅棰勫畾鍚庡彴

- 椤甸潰锛歚/admin.php/offline_order`銆?- 鑳藉姏锛氬垪琛ㄣ€佺姸鎬佹洿鏂帮紙寰呯‘璁?宸茶浆姝ｅ紡璁㈠崟/宸插彇娑堬級銆佹槑缁嗗脊绐椼€丒xcel 瀵煎嚭銆?
### 6.6 鍒嗙骇浠锋牸 Token

- 鏈嶅姟锛歚app/service/CatalogTokenService.php`銆?- 鍚庡彴鎺ュ彛锛歚POST /admin.php/product_search/generateCatalogToken`銆?- 鐢ㄩ€旓細閫氳繃甯?Token 鐨勫浘鍐岄摼鎺ュ悜涓嶅悓瀹㈡埛灞曠ず涓嶅悓浠锋牸灞傜骇銆?
## 7. i18n 娌荤悊锛堝凡瀹炵幇锛?
- 鍓嶇瑙勮寖锛氭柊澧?UI 鏂囨蹇呴』閫氳繃 `AppI18n.t` 鑾峰彇銆?- 璇嶅吀鏂囦欢锛?  - `public/static/i18n/i18n.js`
  - `public/static/i18n/i18n.ops2.js`
  - 瀹㈡埛鍥惧唽浣跨敤 `public/static/i18n/influencer_i18n.js`
- 鏍￠獙鑴氭湰锛歚node scripts/check_i18n_keys.js --scope=all`銆?- CI锛歚.github/workflows/i18n-check.yml` 鍦?`main` 涓?PR 涓婅嚜鍔ㄦ牎楠屻€?
## 8. 鏁版嵁杩佺Щ涓庢墽琛岄『搴?
### 8.1 鏂板簱鍒濆鍖?
- 鐩存帴鎵ц `database/schema.sql`銆?
### 8.2 澧為噺鍗囩骇锛堟帹鑽愰『搴忥級

1. `php database/run_migration_tikstar_ops2.php`
2. `php database/run_migration_product_style_orders.php`
3. `php database/run_migration_product_style_price_levels.php`

濡傚巻鍙插簱杈冩棫锛屽啀鎸夐渶琛ユ墽琛岋細

- `php database/run_migration_extensions.php`
- `php database/run_migration_module_governance.php`
- `php database/run_migration_category_crm_outreach.php`
- `php database/run_migration_product_style_search.php`

## 9. 鍐掔儫楠岃瘉

- 涓€閿剼鏈細`powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`
- 瑕嗙洊鑼冨洿锛?  - CRM锛氬垎绫汇€佽揪浜恒€佷换鍔＄敓鎴愩€佸鍒?璺宠浆鍔ㄤ綔銆佺姸鎬佽仈鍔ㄣ€佹棩蹇椼€?  - 鎯呮姤锛氳涓?绔炲搧/骞垮憡鏁版嵁璇诲啓銆佸鍏ヤ换鍔′笌閲嶈瘯銆?  - 鏁版嵁灞傦細鏁版嵁婧愪笌浣滀笟鏃ュ織銆?
## 10. 褰撳墠鏈仛锛堝埢鎰忎繚鐣欙級

- 涓嶅疄鐜版棤浜哄€煎畧鑷姩鍙戦€?Zalo/WhatsApp锛堜粎鍗婅嚜鍔紝闄嶄綆灏佹帶涓庡悎瑙勯闄╋級銆?- 鎯呮姤妯″潡棣栨湡涓嶅仛鐖櫕锛屽厛璧?CSV/API 鎺ュ叆灞傘€?- 璞嗗寘 AI 鑷姩瀹氫环鍔熻兘褰撳墠涓嶇撼鍏ュ疄鐜拌寖鍥淬€?
## 11. 缁存姢璇存槑

1. 鑻ユ牴鐩綍 `requirements.md` 琚伐鍏疯鍐欎负涔辩爜锛岃浠?`docs/requirements.md` 涓哄噯銆?2. 鍚屾鍛戒护锛?   - PowerShell锛歚Copy-Item -Force docs\requirements.md requirements.md`
   - Linux/macOS锛歚cp docs/requirements.md requirements.md`

## 12. Product Search UI Refactor (2026-04-07)

### 12.1 Admin UI changes
- view/admin/product_search/index.html switched from el-table to responsive card grid (el-row + el-col + el-card).
- Each card now includes: image preview, style name/category, code, wholesale price, min order qty.
- Hover action layer added: Details, Edit, Share Poster, Delete.
- Existing customer catalog token flow is preserved (generate/copy token link).
- AI quick access button (AI) added on card corner; supports copying AI text for Zalo outreach.

### 12.2 Filter and search interaction
- Primary row keeps only: keyword search + image search entry.
- Image search supports click-to-upload and drag-drop upload; request API:
  - POST /index.php/api/product_search/searchByImage
- Advanced filters moved into collapse panel:
  - category
  - price_min / price_max
  - moq_min
- Batch delete remains available via card selection on current page.
- Share poster keeps QR generation based on token link and supports level/expire params.

### 12.3 Backend list API extension
- app/controller/admin/ProductSearch.php:listJson() now supports params:
  - keyword (matches product_code, hot_type, ai_description)
  - category
  - price_min, price_max
  - moq_min
- Response now includes:
  - updated_at
  - category_options
  - current_role
- Backward compatibility: if legacy DB lacks `ai_description` column, list/filter/export/update now auto-degrade without SQL errors.

### 12.4 Price level display rules
- In admin view (current_role != viewer), card shows level price badges parsed from price_levels_json.
- Priority order: level1, level2, level3, then custom keys.

### 12.5 i18n compliance
- Added zh/en/vi keys under page.styleSearch.* in public/static/i18n/i18n.js for:
  - card actions
  - advanced filters
  - image-search status and errors
  - detail modal and AI copy actions

### 12.6 Usage notes
1. Drag an image into the image-search box or click to upload.
2. System auto-runs image matching and fills matched style code into keyword field.
3. Use Advanced filters for category/price/MOQ constraints.
4. Hover a card to run operations, or open AI modal to copy selling points for customer chat.

## 13. 2026-04-08 深度优化补充（素材风控 + SaaS 隔离）

### 13.1 素材风控（Material）
- `videos` 新增字段：
  - `video_md5`：用于上传去重（同租户范围）
  - `ad_creative_code`：用于关联广告创意表现
- 上传去重规则：
  - 入口覆盖 `batchUpload`、分片上传 `uploadChunk`、编辑替换视频
  - 若同租户存在相同 `video_md5`，拒绝上传并返回“素材已存在，请进行混剪去重”

### 13.2 素材表现归因（与广告情报联动）
- `GET /admin.php/video/listJson` 每条素材新增返回：
  - `used_by_influencers`：该素材所属商品被多少达人使用（按 `product_links` 聚合）
  - `total_gmv`：按 `ad_creative_code -> growth_ad_creatives -> growth_ad_metrics.est_gmv` 聚合
  - `ad_creative_code`
- 素材列表页 `view/admin/video/index.html` 新增列：
  - 使用达人数
  - 总 GMV
  - 创意编码

### 13.3 AI 混剪建议
- 新接口：`POST /admin.php/video/mixSuggestion`
  - 入参：`video_id`
  - 出参：`suggestion`、`source`、`used_by_influencers`、`total_gmv`
- 页面入口：素材操作菜单新增“AI 混剪建议”
  - 优先调用 `VolcArkVisionService::generateVideoRemixSuggestion()`
  - 失败时使用后端 fallback 策略生成可执行建议文本
  - 前端自动复制建议文本（兼容 `navigator.clipboard` 与 `execCommand` 回退）

### 13.4 SaaS 租户隔离增强
- 新迁移脚本：`database/run_migration_tenant_saas_material.php`
  - 新增 `tenants`
  - 批量为业务表补 `tenant_id` + 索引
  - 新增 `tenant_module_subscriptions`
  - 新增 `admin_logs`（含硬件指纹字段）
  - `videos` 扩展字段与索引
  - `growth_ad_metrics` 增加 `est_gmv`
- 权限与菜单：
  - `AdminAuthMiddleware` 增加“模块到期/禁用 -> API 403”
  - `ModuleManagerService::getEnabledMenus()` 按租户订阅与角色动态过滤
- 敏感审计：
  - 新增 `AdminAuditService`
  - 覆盖联系人导出、批发价变更等敏感动作，记录操作者指纹

### 13.5 外联话术服务的租户安全
- `app/service/MessageOutreachService.php` 已重构：
  - 模板语言回退查询（`pickTemplateVariantByRegion`）增加租户过滤
  - 变量构建（`buildRenderVars`）中达人/商品查询增加租户过滤
  - 分发链接解析（`resolveDistributeLink`）增加租户过滤
- `MessageTemplate::render` 调用已传入当前租户 ID，避免跨租户串读

### 13.6 业务控制器补齐租户过滤
- 本轮补齐：
  - `app/controller/admin/Product.php`
  - `app/controller/admin/Distribute.php`
- 关键点：
  - 列表、详情、创建、删除、状态切换均使用 `scopeTenant()`
  - 新增写入统一经 `withTenantPayload()` 注入 `tenant_id`

### 13.7 执行与验证（Windows 开发 / Linux 部署通用）
1. 执行迁移（先基础，再本轮）：
   - `php database/run_migration_tikstar_ops2.php`
   - `php database/run_migration_product_style_orders.php`
   - `php database/run_migration_product_style_price_levels.php`
   - `php database/run_migration_tenant_saas_material.php`
2. 语法检查：
   - `php -l app/service/MessageOutreachService.php`
   - `php -l app/controller/admin/Video.php`
   - `php -l app/controller/admin/Product.php`
   - `php -l app/controller/admin/Distribute.php`
3. 冒烟脚本：
   - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

### 13.8 当前已知验证前提
- 若冒烟脚本提示 `SQLSTATE[HY000] [2002]`（目标计算机拒绝连接），表示 MySQL 未启动或连接参数不通。
- 需先确认 `.env` 中数据库主机/端口与 phpStudy/MySQL 实例一致，再执行冒烟。

## 14. 2026-04-08 移动端强触达（Android + Appium）

### 14.1 目标与边界
- 执行层切换为：`后台编排 -> Mobile Agent -> 手机 App 执行 -> 日志回传`。
- 自动化边界固定为：**自动填充 + 人工发送**（不做无人值守自动发送）。
- 渠道支持：TikTok 评论预热、TikTok DM、Zalo、WhatsApp。

### 14.2 数据结构与迁移
- 新迁移脚本：`database/run_migration_mobile_outreach.php`
- 新增表：
  - `mobile_devices`
  - `mobile_action_tasks`
  - `mobile_action_logs`
- `influencers` 新增字段：
  - `last_commented_at`
  - `quality_score`
  - `quality_grade`
  - `contact_confidence`

### 14.3 后端接口
- 任务域：
  - `POST /admin.php/mobile_task/create_batch`
  - `GET /admin.php/mobile_task/list`（兼容 `listJson`）
  - `POST /admin.php/mobile_task/retry`
- 设备域：
  - `GET /admin.php/mobile_device/list`（兼容 `listJson`）
- Agent 域：
  - `POST /admin.php/mobile_agent/pull`
  - `POST /admin.php/mobile_agent/report`

### 14.4 中间件与鉴权
- `AdminAuthMiddleware` 已放行 `mobile_agent/pull|report`，改为 `token` 鉴权（设备级）。
- `mobile_task/mobile_device/mobile_agent` 已纳入模块映射（`creator_crm`）。

### 14.5 页面改造
- `view/admin/influencer/index.html`
  - 新增按钮：
    - `预热评论（手机）`
    - `私信触达（手机）`
  - 点击后调用 `mobile_task/create_batch` 为单个达人生成移动任务。
- `view/admin/outreach_workspace/index.html`
  - 新增“移动端任务队列”区块。
  - 支持设备筛选、查看失败原因、任务重试。
  - 支持从“下一条任务”直接下发：
    - 评论预热任务
    - 私信触达任务

### 14.6 Agent 执行脚本
- 目录：`tools/mobile_agent/`
  - `agent.py`
  - `requirements.txt`
  - `README.md`
- 技术栈：ADB + Appium（可降级 ADB-only）。
- 回传动作支持：
  - `comment_prepared`
  - `comment_sent`
  - `dm_prepared`
  - `im_prepared`

### 14.7 i18n
- 新增移动触达相关文案，覆盖 `zh/en/vi`：
  - `public/static/i18n/i18n.js`（达人页新增键）
  - `public/static/i18n/i18n.ops2.js`（外联工作台新增键）
- 新增 UI 文案均通过 `AppI18n.t` 调用。

### 14.8 执行顺序（Windows 开发 / Linux 部署）
1. 执行数据库迁移：
   - `php database/run_migration_mobile_outreach.php`
2. 启动 Web 服务并登录后台，验证：
   - 达人页“手机触达”按钮可创建任务
   - 外联工作台可看到移动任务队列
3. 启动 Agent：
   - 参考 `tools/mobile_agent/README.md` 设置环境变量并运行 `agent.py`
4. 冒烟：
   - `创建任务 -> Agent 拉取 -> 手机打开渠道并准备文本 -> 人工发送 -> report 回传`
5. 配额说明：
   - `mobile_devices.daily_used` 仅在任务终态（`done/failed/skipped/canceled`）计数，`prepared` 不计数。

### 14.9 Android App Agent（本轮新增，2026-04-08）
- 目录：`android_app/`
- 目标：把“执行层”封装为手机 App，电脑后台仅负责创建任务。
- 架构：
  - `MainActivity`：配置页 + 任务操作页（启动/停止/拉取/已发送/跳过/失败）
  - `MobileAgentService`：前台服务常驻轮询 `pull`，并回传 `report`
  - `AgentApiClient`：统一 HTTP 协议层（`/mobile_agent/pull`、`/mobile_agent/report`）
  - `MobileTaskExecutor`：自动复制文案 + 打开 TikTok/Zalo/WA，停在人工发送前
  - `AgentPrefs`：本地持久化配置与当前任务
- 默认执行边界：
  - 自动：拉任务、复制内容、打开目标应用、回传 `*_prepared`
  - 人工：最终点击发送，再在 App 中点“标记已发送”
- 运行步骤（手机 App）：
  1. 后台先配置 `mobile_devices`（`agent_token`、`device_code`）
  2. 手机安装 APK，填写 `admin_base/token/device_code`
  3. 点击“启动 Agent”
  4. 后台创建移动任务后，手机自动拉取并打开目标渠道
  5. 人工发送后在 App 点击“标记已发送”
- 当前限制：
  - 本地编译依赖 JDK；若未配置 `JAVA_HOME`，无法在开发机执行 `gradlew assembleDebug`

## 15. 2026-04-08 Mobile Agent V1（多语言 + 分端登录 + 模块化执行）

### 15.1 后端新增接口
- 新增：`GET /admin.php/mobile_console/bootstrap?lang=zh|en|vi`
- 控制器：`app/controller/admin/MobileConsole.php`
- 返回结构：
  - `user(id, username, role, tenant_id)`
  - `portal`（`viewer -> influencer`，其余角色为 `merchant`）
  - `menus`（来自 `ModuleManagerService::getEnabledMenus()`）
  - `enabled_modules`（来自 `ModuleManagerService::modulesForCurrentRole(true)`）

### 15.2 Android 端架构（V1）
- 入口改为路由：
  - `MainActivity` 仅做跳转：已登录 -> `ModuleConsoleActivity`，未登录 -> `LoginActivity`
- 登录页：
  - 复用后台登录：`POST /admin.php/auth/login`
  - 会话持久化：`PersistentCookieJar` + `HttpClientProvider`
- 分端首页：
  - `ModuleConsoleActivity` 根据 `bootstrap` 的 `portal + menus` 动态渲染模块
  - `super_admin/operator` 走商家端；`viewer` 走达人端
- 模块执行：
  - 通用动作：模块“打开后台页面”（`WebModuleActivity` + WebView）
  - `creator_crm` 快捷动作：
    - 创建评论预热任务：`POST /admin.php/mobile_task/create_batch (comment_warmup)`
    - 创建私信任务：`POST /admin.php/mobile_task/create_batch (tiktok_dm)`
    - 查看待处理：`GET /admin.php/mobile_task/listJson?task_status=0`
    - 查看设备：`GET /admin.php/mobile_device/listJson`
- 执行中心：
  - 仍保留 `AgentControlActivity`，不改变“自动填充 + 人工发送”边界

### 15.3 多语言与资源
- Android 新增资源目录：
  - `android_app/app/src/main/res/values-en/strings.xml`
  - `android_app/app/src/main/res/values-zh-rCN/strings.xml`
  - `android_app/app/src/main/res/values-vi/strings.xml`
- 语言策略：
  - 首次跟随系统语言（`zh/en/vi`）
  - 支持手动切换并持久化（重启后生效）

### 15.4 兼容性与修复
- `SessionApiClient.logout()` 使用空 `FormBody`，避免 `RequestBody.create(..., null)` 兼容问题。
- `MobileAgentService` 通知点击入口改为 `AgentControlActivity`（执行中心直达）。
- `AndroidManifest.xml` 已注册：
  - `.console.LoginActivity`
  - `.console.ModuleConsoleActivity`
  - `.console.WebModuleActivity`
  - `.AgentControlActivity`

### 15.5 本地验证记录（Windows）
1. 编译：
   - `cd android_app`
   - `set JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot`
   - `gradlew.bat :app:assembleDebug`
2. 设备安装：
   - `adb install -r android_app\app\build\outputs\apk\debug\app-debug.apk`
3. 启动验证：
   - `adb shell am start -W -n com.videotool/.MainActivity`
   - 已确认顶层 Activity 为：`com.videotool/.console.LoginActivity`

### 15.6 2026-04-08 A/B/C 交互增强（移动端首页 + 悬浮触达 + 智能跳转）

#### A. ModuleConsoleActivity 视觉重构
- 首页统一为 `Modern SaaS` 轻量风格：
  - 页面底色：`#F5F7FA`
  - 顶部统计区：固定 `120dp`，三指标（今日已联系、待回复、已寄样）
  - 任务条目：白色圆角卡片 + `elevation=2dp`
- 卡片信息层级：
  - 左侧：达人头像位（当前无可用头像时使用 Handle 首字母占位）
  - 中间：`@handle` + `昵称/GPM`
  - 右侧：状态 Badge（按任务状态映射不同底色）
- 底部动作栏固定三键：
  - `加Zalo`（绿色）
  - `去评论`（蓝色）
  - `发私信`（灰色）

#### B. 悬浮窗一键触达（评论预热）
- 点击 `去评论` 时：
  1. 自动复制 `comment_warmup` 话术到剪贴板
  2. 深链打开 TikTok：`snssdk1128://user/profile/{uid}`
  3. 启动 `40x40dp` 半透明左侧悬浮球
- 点击悬浮球后通过 `AccessibilityService` 自动执行：
  - 点击评论入口 -> 填充/粘贴话术 -> 点击发送
- 回传规则：
  - 调用 `POST /admin.php/mobile_task/update_status`
  - 发送成功后任务保持为“已预热”（`comment_prepared`）
- 交互提示已三语化：`zh/en/vi`（无待处理任务、评论区未就绪、评论按钮未找到、成功/失败提示）

#### C. Zalo / WhatsApp 智能跳转
- 渠道优先级：
  1. 若存在 `zalo_id`，优先走 `zalo://...`
  2. 若仅手机号且达人 `region != VN`，走 `whatsapp://send?phone={no}`
  3. 若无站外联系方式，自动回退 TikTok 站内私信并提示原因

#### A/B/C 验证命令（Windows）
1. 编译 APK：
   - `cd android_app`
   - `gradlew.bat :app:assembleDebug`
2. 安装到设备：
   - `adb install -r app\build\outputs\apk\debug\app-debug.apk`
3. 启动并检查首页：
   - `adb shell am start -W -n com.videotool/.MainActivity`
4. 自动化链路检查：
   - 在首页点击“去评论” -> TikTok 打开 -> 悬浮球出现 -> 点击悬浮球 -> 后台任务状态更新为已预热




## 15. Mobile UI Workbench V3 (2026-04-08)

### 15.1 Module Console UI
- `ModuleConsoleActivity` rebuilt to "dashboard + workbench" structure:
  - Top gradient KPI panel: `Today Outreach`, `Pending Tasks`, `Active Devices`.
  - Middle module board changed to 3-column tile grid (`item_module_tile.xml`) with glass icon style.
  - Task list switched to card-first interaction with skeleton loading (`item_task_skeleton.xml`).
  - Bottom navigation added (`menu_console_bottom_nav.xml`): Workbench, Product Search, Messages, Me.
- Navigation behavior:
  - Workbench/Me switch in-page sections.
  - Product Search/Messages open backend modules in `WebModuleActivity`.

### 15.2 Task Card Interaction
- `item_task_card.xml` interaction is now two-stage:
  - Main CTA button `Next Action` executes by task type:
    - `comment_warmup` -> prepare comment + open TikTok profile.
    - `tiktok_dm` -> prepare DM + open TikTok profile.
    - others -> contact route (Zalo/WA, fallback TikTok DM).
  - Tapping card primary area toggles optional actions (`Add Zalo`, `Send DM`).
- Status badge remains quick-operable for fast status update.

### 15.3 Agent Detail Panel Upgrade
- `AgentControlActivity` now binds new hero/detail widgets in `activity_main.xml`:
  - Cover + avatar letter + handle subtitle.
  - 2x2 metric panel (`fans`, `engagement`, `region`, `quality`).
  - Fixed bottom action bar (`favorite`, `note`, `contact now`).
- Runtime task state now updates both old control area and new detail panel.

### 15.4 Validation (Windows local)
- Build command:
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`
- Device smoke:
  - `adb install -r app\\build\\outputs\\apk\\debug\\app-debug.apk`
  - `adb shell am start -W -n com.videotool/.MainActivity`
## 16. Mobile UI Refinement V4 (2026-04-08)

### 16.1 Visual System
- Global page background unified to `#F8FAFC`.
- Primary action style switched to deep-blue gradient (`#0052FF -> #0039B5`).
- Card style normalized toward larger rounded corners and stronger floating depth.

### 16.2 Module Console (ModuleConsoleActivity)
- Header condensed and refocused to three KPI items:
  - `Creators`
  - `Today Outreach`
  - `Waiting Sample`
- Module entry area changed from 3-column to 2-column grid layout.
- Added `To-do Reminders` block under modules (up to 3 urgent items, click-through to outreach workspace).

### 16.3 Task Card (item_task_card.xml)
- Task card rebuilt to action-first structure:
  - Left: avatar + online activity dot
  - Middle: handle + category chip + compact meta
  - Right: circular arrow CTA for next-best action
- Secondary actions now slide from bottom on long-press:
  - `Add Zalo`
  - `Write Note`
  - `Blacklist`
- Blacklist action calls backend influencer status update (`status=6`) and syncs task state to skipped.

### 16.4 Agent Detail (AgentControlActivity)
- Hero section upgraded with image + frosted overlay and centered handle focus.
- Metric grid adjusted to:
  - Fans
  - GPM
  - Region
  - Cooperations
- Sticky footer changed to two-action pattern:
  - `Copy Zalo`
  - `Contact Now`

### 16.5 Backend support update
- `app/controller/admin/MobileTask.php::buildDashboardSummary()` now returns:
  - `influencer_total`
  - `wait_sample_count`
- Existing summary fields remain compatible.

## 17. ModuleConsole Premium SaaS 样式重构（2026-04-08）

### 17.1 设计基线（本轮落地）
- 主页 `ModuleConsoleActivity` 视觉基线统一为 Premium SaaS：
  - 页面背景：`#F8FAFC`
  - 品牌主色：`#1E293B`
  - 主动作渐变：`#0052FF -> #3B82F6`
- 卡片体系统一：
  - 圆角：`12dp`
  - 浅阴影：`elevation=3dp`
  - 去除细描边，统一为低对比纯卡面风格

### 17.2 布局与层级优化
- 文件：`android_app/app/src/main/res/layout/activity_module_console.xml`
  - 头部统计卡、筛选卡、分页卡、空态卡、个人信息卡全部统一 `12dp + elevation=3dp`
  - 组件间距收敛到 `16/24` 节奏，减少紧凑感
  - 标题与分区标题保留加粗 + `letterSpacing`，提升信息层级
- 文件：`android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
  - 模块入口两列网格行距改为 `16dp`
  - 卡片之间横向间隔统一（左右各 `8dp`，总间隔 `16dp`）
  - 状态 Badge 圆角统一为 `12dp`
  - 次级动作展开动画位移统一为 `12dp`

### 17.3 组件细节统一
- 文件：`item_task_card.xml`、`item_task_skeleton.xml`、`item_module_tile.xml`
  - 任务卡片/骨架屏/模块卡片统一卡面厚度与间距
  - 任务卡内容内边距提升到 `16dp`，信息更易读
- 文件：`bg_input.xml`、`bg_spinner.xml`
  - 移除 1dp 描边，保持无边框卡面输入风格
- 文件：`bg_bottom_nav.xml`、`bg_glass_icon.xml`
  - 底部导航改为上圆角白卡面
  - 模块图标底板改为更浅的冷色系
- 文件：`values/styles.xml`
  - 状态栏/导航栏背景色统一为 `#F8FAFC`

### 17.4 使用与验证（Windows 开发）
1. 编译：
   - `cd android_app`
   - `gradlew.bat :app:assembleDebug`
2. 安装：
   - `adb install -r app\\build\\outputs\\apk\\debug\\app-debug.apk`
3. 启动：
   - `adb shell am start -W -n com.videotool/.MainActivity`
4. 验证点：
   - 首页背景、卡片圆角、阴影和间距风格是否统一
   - 模块卡与任务卡是否保持 16dp 网格节奏
   - 输入框/下拉框是否已移除细边框

## 18. Mobile Console UI V5（沉浸式 Header + 极简任务卡 + 交互动效）

### 18.1 顶部区域重构（ModuleConsoleActivity）
- 顶部改为沉浸式 Header：
  - 高度 `150dp`
  - 深蓝到近黑渐变背景（`#123B9B -> #0B1F55 -> #020617`）
  - 底部增加弧形切割过渡（`bg_console_header_arc`）
- 浮动统计卡片：
  - Header 上覆盖白色横向统计卡（`达人总数 / 今日外联 / 待寄样`）
  - 数值字体放大并加粗，标签使用淡灰小字，弱化视觉噪音

### 18.2 功能矩阵图标风格（Duotone）
- 动态菜单仍按 `mobile_console/bootstrap` 返回渲染；
- 模块图标改为“双色线性”视觉：
  - 图标使用深色主色
  - 背景圆圈使用对应主色 10% 透明度
- 按模块路由自动映射色系（如寻款紫系、达人蓝系、样品绿系、系统灰系）。

### 18.3 任务卡重构（item_task_card）
- 结构改为：
  - 左：圆角矩形头像块（首字母占位，按达人生成主色）
  - 中：`Handle + 细胶囊状态`、`GPM`、`分类·地区`
  - 右：单一蓝色圆形操作图标（下一步动作）
- 状态标签弱化：
  - 胶囊更细、更浅底色、更深文字
  - 位置靠近 Handle，不再占据右侧主视觉位

### 18.4 动效增强
- 列表入场动画：
  - 新增 `res/anim/item_slide_up_fade.xml`
  - 新增 `res/anim/layout_task_stagger_in.xml`
  - 任务列表加载后按顺序由下向上滑入
- 点击反馈：
  - 按钮/操作控件按下缩放到 `0.95`
  - 触发轻微触感反馈（haptic）
- 骨架屏：
  - 数据加载期显示灰色闪烁骨架块
  - 采用分段错峰 Alpha Pulse（非空白、非旋转等待）

### 18.5 关键文件
- `android_app/app/src/main/res/layout/activity_module_console.xml`
- `android_app/app/src/main/res/layout/item_task_card.xml`
- `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
- `android_app/app/src/main/res/drawable/bg_console_header.xml`
- `android_app/app/src/main/res/drawable/bg_console_header_arc.xml`
- `android_app/app/src/main/res/drawable/bg_avatar_rect.xml`
- `android_app/app/src/main/res/drawable/bg_task_status_soft.xml`
- `android_app/app/src/main/res/drawable/bg_skeleton_block.xml`
- `android_app/app/src/main/res/anim/item_slide_up_fade.xml`
- `android_app/app/src/main/res/anim/layout_task_stagger_in.xml`

## 19. Auto DM V1（站外 IM 无人值守发送）

### 19.1 范围与目标
- 新增“自动私信活动”域，支持在后台批量生成 Zalo / WhatsApp 自动私信任务。
- 移动 Agent 增加自动模式，可拉取自动任务并回传状态，CRM 自动更新外联状态与日志。
- 保留现有半自动任务链路（`comment_warmup / tiktok_dm`），不破坏旧流程。

### 19.2 数据结构（新增/扩展）
- 新迁移脚本：`database/run_migration_auto_dm_v1.php`
- 扩展：
  - `influencers`: `do_not_contact`, `last_auto_dm_at`, `auto_dm_fail_count`, `cooldown_until`
  - `outreach_logs`: `action_type`
- 新增表：
  - `auto_dm_campaigns`
  - `auto_dm_tasks`
  - `auto_dm_events`
  - `contact_policies`

### 19.3 后端接口
- 活动编排：
  - `POST /admin.php/auto_dm/campaign/create`
  - `GET /admin.php/auto_dm/campaign/list`
  - `POST /admin.php/auto_dm/campaign/pause`
  - `POST /admin.php/auto_dm/campaign/resume`
- Agent 自动链路：
  - `POST /admin.php/mobile_agent/pull_auto`
  - `POST /admin.php/mobile_agent/report_auto`
- 页面入口：
  - `/admin.php/auto_dm`（达人运营菜单下“自动私信活动”）

### 19.4 状态机与策略
- 自动任务状态：`pending -> assigned -> sending -> sent -> failed -> blocked -> cooling`
- 渠道策略：优先 `zalo`，其次 `wa`（可在活动中改为指定优先）
- 拦截规则：
  - 黑名单 / `do_not_contact`
  - 冷却窗口（`cooldown_until`）
  - 最小发送间隔（`min_interval_sec`）
- 回传成功后自动联动：
  - `influencers.last_contacted_at`
  - `influencers.last_auto_dm_at`
  - `influencers.status`（`0/1 -> 1`）
  - `outreach_logs.action_type = auto_dm_sent`

### 19.5 移动端 Agent（Android）
- `AgentConfig` 新增 `auto_mode` 开关并持久化。
- `AgentApiClient` 新增自动接口调用：
  - `pullTaskAuto()` -> `/mobile_agent/pull_auto`
  - `reportAuto()` -> `/mobile_agent/report_auto`
- `MobileAgentService`：
  - 自动模式下优先拉取 `zalo_auto_dm/wa_auto_dm`
  - 自动任务执行后回传 `sending/sent` 事件
  - 非自动任务仍保持原“准备后人工发送”流程
- `AgentControlActivity` 新增“自动模式”勾选项，并在“测试拉取”时自动切换到 auto 接口。

### 19.6 执行步骤（Windows 开发 / Linux 部署通用）
1. 执行迁移：
   - Windows: `php database\\run_migration_auto_dm_v1.php`
   - Linux: `php database/run_migration_auto_dm_v1.php`
2. 语法检查：
   - `php -l app/controller/admin/AutoDm.php`
   - `php -l app/controller/admin/MobileAgent.php`
   - `php -l app/service/AutoDmService.php`
   - `php -l app/middleware/AdminAuthMiddleware.php`
3. Android 构建：
   - `cd android_app`
   - `gradlew.bat :app:assembleDebug`（Linux 用 `./gradlew :app:assembleDebug`）

### 19.7 已知边界
- 本期仅包含站外 IM（Zalo / WhatsApp）自动链路；TikTok 站内 DM 仍按半自动流程。
- 若设备端 App 未登录、深链失败或网络异常，任务会回传 `failed/blocked/cooling`，不会静默吞错。

## 20. 运维中心一键更新（数据表 + Git）

### 20.1 新增目标
- 在后台 `运维中心` 增加“部署维护”Tab，支持：
  - 一键执行数据库迁移脚本（幂等，保留现有数据）
  - 一键执行 `git pull --ff-only`
- 目标是减少上线时对命令行的依赖。

### 20.2 后端实现
- 新增服务：`app/service/OpsMaintenanceService.php`
  - 扫描并排序 `database/run_migration*.php`
  - 自动识别 CLI `php` 可执行文件（避免误用 `php-fpm` 导致 `code=64`）
  - 自动维护迁移历史表 `ops_migration_history`（脚本名 + checksum + 状态 + 最后执行时间）
  - 仅执行“未执行或脚本内容已变更”的迁移，已应用脚本自动跳过
  - 汇总每个脚本的输出与退出码（含 `skipped` 标识）
  - 读取 Git 状态（分支、提交、脏工作区）
  - 执行 `git pull --ff-only`（可选允许 dirty）
- 控制器扩展：`app/controller/admin/OpsCenter.php`
  - `GET /admin.php/ops_center/status`
  - `POST /admin.php/ops_center/runMigrations`
  - `POST /admin.php/ops_center/gitPull`
- 权限：仅 `super_admin` 可执行上述运维动作。

### 20.3 前端实现
- 页面：`view/admin/ops_center/index.html`
  - 在原有发卡/版本/缓存/异常之外新增 `部署维护` Tab
  - 展示运行环境状态（PHP 路径、Git 分支、未提交变更数、迁移脚本总数、已应用数、待执行数）
  - 接口调用兼容两种路由模式：`/admin.php/ops_center/*` 与 `/admin.php?s=/ops_center/*`（自动回退）
  - 按钮：
    - `刷新状态`
    - `更新数据表`
    - `Git 更新`
  - 支持日志回显（迁移日志 / Git 日志）
- i18n：
  - `public/static/i18n/i18n.js` 补齐 `page.opsCenter.*` 三语键

### 20.4 使用说明
1. 后台进入：`系统设置 -> 运维中心 -> 部署维护`
2. 点击 `刷新状态` 确认运行环境正常
3. 点击 `更新数据表` 执行增量迁移（已执行且 checksum 未变化的脚本会自动跳过）
4. 点击 `Git 更新` 拉取远端最新代码
5. 若提示工作区有未提交变更，可先处理变更，或在确认风险后勾选“允许带本地修改执行 Pull”
6. Linux 若 `PHP_BINARY` 指向 `php-fpm`，系统会自动回退探测 CLI `php`；如需强制指定可在运行环境设置 `OPS_PHP_BIN=/usr/bin/php`

## 21. Mobile Console 极简 UI 重构（ModuleConsoleActivity）

### 21.1 视觉规范落地
- 画布背景统一为 `#F8FAFC`
- 首页与模块入口改为纯白卡片（16dp 圆角，2dp 阴影）
- 字体色阶统一：
  - 主标题：`#1E293B`
  - 次要信息：`#64748B`
  - 动作点：`#0052FF`
- 清理任务卡片与模块卡片中的高饱和红/绿/黄文字，状态色改为低饱和胶囊背景 + 中性文字

### 21.2 代码落点
- `android_app/app/src/main/res/layout/activity_module_console.xml`
- `android_app/app/src/main/res/layout/item_module_tile.xml`
- `android_app/app/src/main/res/layout/item_task_card.xml`
- `android_app/app/src/main/res/values/colors.xml`
- `android_app/app/src/main/res/drawable/bg_card*.xml`
- `android_app/app/src/main/res/drawable/bg_input.xml`
- `android_app/app/src/main/res/drawable/bg_spinner.xml`
- `android_app/app/src/main/res/drawable/bg_button_primary.xml`
- `android_app/app/src/main/res/color/bottom_nav_item_tint.xml`
- `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`

### 21.3 验证
- Windows 本地构建通过：
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`

### 21.4 功能网格 RecyclerView 规范（2026-04-09）
- 功能入口网格改为标准 `RecyclerView + GridLayoutManager(2列)`，不再手工拼接 `LinearLayout` 行列。
- Item 统一高度（`item_module_tile.xml` 固定高度）并由栅格间距装饰器控制左右/底部间距，保证两列整齐对齐。
- 图标风格统一为单色线性矢量图（stroke 宽度 `1.5`），并使用极淡品牌色圆形底板（约 5% 透明度）。
- 卡片内间距规范：
  - 图标与文字垂直间距：`12dp`
  - 文字下方额外留白：`paddingBottom=20dp`

### 21.5 视觉修复（按钮着色问题）
- 修复 Material 主题下按钮被系统 `backgroundTint` 强制染色导致的“全部变蓝、文案不清晰”问题。
- 在 `AppButtonBase` 中显式关闭按钮 tint，确保 `Primary / Secondary / Neutral` 使用各自背景与文字色。
- 该修复覆盖：
  - 工作台筛选区按钮
  - “我的”页（执行中心 / 设备管理 / 退出登录）按钮

### 21.6 DingTalk 风格首页重构（2026-04-09）
- 目标：解决“全白页面、层次不清”的视觉问题，统一为钉钉风格的蓝色头部 + 灰底白卡。
- 结构调整：
  - 首页顶部改为品牌蓝渐变头部（`bg_console_header`），并保留语言切换与用户信息。
  - 指标区改为悬浮统计卡（达人总数/今日外联/待寄样），形成“头部 + 卡片”层次。
  - 功能模块、提醒区、任务筛选区、分页区全部统一白色卡片容器，背景统一浅灰底。
- 样式统一：
  - 主色改为钉钉系蓝：`#1677FF`（含深色与渐变色阶）。
  - 卡片圆角 `16dp`，按钮圆角 `12dp`，底部导航改为圆角顶边白底。
  - 按钮维持 `Primary / Secondary / Neutral` 三级语义，避免单一纯蓝大块。
- 兼容修复：
  - `ModuleConsoleActivity` 新增状态栏/导航栏配色控制，顶部深色背景下状态栏图标可读。
- 主要变更文件：
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
  - `android_app/app/src/main/res/layout/item_task_card.xml`
  - `android_app/app/src/main/res/values/colors.xml`
  - `android_app/app/src/main/res/values/styles.xml`
  - `android_app/app/src/main/res/drawable/bg_console_header.xml`
  - `android_app/app/src/main/res/drawable/bg_console_header_arc.xml`
  - `android_app/app/src/main/res/drawable/bg_card.xml`
  - `android_app/app/src/main/res/drawable/bg_card_alt.xml`
  - `android_app/app/src/main/res/drawable/bg_button_primary.xml`
  - `android_app/app/src/main/res/drawable/bg_button_secondary.xml`
  - `android_app/app/src/main/res/drawable/bg_button_neutral.xml`
  - `android_app/app/src/main/res/drawable/bg_spinner.xml`
  - `android_app/app/src/main/res/drawable/bg_bottom_nav.xml`
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
- 本地验证：
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`
  - `adb install -r app/build/outputs/apk/debug/app-debug.apk`

### 21.7 DingTalk 1:1 细化（二次微调，2026-04-09）
- 底部导航：
  - 图标改为统一线性图标（替换系统默认图标）。
  - 图标尺寸固定 `22dp`，激活/未激活文字采用独立 `TextAppearance`（11sp）。
  - 调整上下内边距，贴近钉钉导航密度。
- 模块卡片（工作台入口）：
  - 卡片高度由 152dp 收敛到 136dp，圆角 14dp。
  - 图标容器收敛到 42dp，图标 20dp，标题改为 12sp 常规字重。
  - 网格间距由 12dp 调整为 10dp，提升信息密度。
- 任务卡片：
  - 卡片外边距、内边距、头像、标题、状态胶囊、次级文案全面收敛。
  - 主动作按钮改为线性箭头图标；次要动作（备注/黑名单）替换为自定义线性图标。
  - 次要动作行高度压缩到 34dp，满足“高信息密度 + 可点按”平衡。
- 交互细节：
  - 按压缩放由 `0.95` 改为 `0.97`，触感更轻、更接近企业工具风格。
- 主要代码文件：
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
  - `android_app/app/src/main/res/layout/item_task_card.xml`
  - `android_app/app/src/main/res/menu/menu_console_bottom_nav.xml`
  - `android_app/app/src/main/res/values/styles.xml`
  - `android_app/app/src/main/res/drawable/bg_glass_icon.xml`
  - `android_app/app/src/main/res/drawable/ic_line_note.xml`
  - `android_app/app/src/main/res/drawable/ic_line_ban.xml`
  - `android_app/app/src/main/res/drawable/ic_line_chevron_right.xml`
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
- i18n 修正：补齐中文语言包中遗留英文文案（Creators/Waiting Sample/To-do Reminders）。

### 21.8 功能模块改为每行 4 个（2026-04-09）
- 按用户要求，工作台模块入口由 2 列改为 4 列。
- 适配策略：
  - `ModuleConsoleActivity` 使用固定 `4` 列网格布局。
  - 网格间距压缩到 `8dp`，避免内容拥挤。
  - 模块卡片密度收敛：高度 `104dp`，图标容器 `30dp`，图标 `16dp`，标题 `11sp`。
- 变更文件：
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`

### 21.9 工作台改为“分组九宫格”与导航压缩（2026-04-09）
- 工作台模块区改为“分组切换 + 九宫格”：
  - 顶部增加模块分组切换条（按后台菜单一级分组）。
  - 每次仅显示当前分组前 9 个模块（3x3），避免全部模块堆叠。
  - 切换分组后即时刷新对应模块网格。
- 网格规则：
  - 列数固定为 3（九宫格）。
  - 模块卡片改为小尺寸高密度（适配 3 列）。
- 底部导航优化：
  - 高度压缩到 `52dp`。
  - 图标缩小到 `20dp`，文字 `10sp`，减少“行高过高”的视觉负担。
- 新增样式资源：
  - `bg_module_section_chip_active.xml`
  - `bg_module_section_chip_inactive.xml`
- 主要代码文件：
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
  - `android_app/app/src/main/res/values/styles.xml`

### 21.10 紧凑化修正（行高/间距，2026-04-09）
- 针对“行高、间距过大”问题，执行全局紧凑化：
  - 头部区域高度与内边距收敛（标题、语言选择器、统计卡数字与标签均降级）。
  - 工作台区块间距从 10-12dp 收敛到 6-8dp。
  - 任务筛选区输入框/下拉/按钮高度统一收敛到 `36dp`，分页按钮收敛到 `34dp`。
  - 模块卡片进一步缩小：`88dp` 高度、`26dp` 图标底板、`10sp` 文案。
- 底部导航进一步压缩：
  - 高度收敛到 `46dp`。
  - 图标收敛到 `18dp`。
  - 切换为 `unlabeled` 模式（仅图标），彻底压低“底部行高”观感。
- 分组芯片同步紧凑化：
  - 高度 `26dp`，字号 `10sp`，左右间距和内边距减小。
- 变更文件：
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
  - `android_app/app/src/main/res/values/styles.xml`
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`

### 21.11 紧凑化二次修正（行高/间距，2026-04-09）
- 在 21.10 基础上再次收敛垂直密度，目标为“同屏更多信息 + 保持可点击”：
  - 顶部头图高度由 `202dp` 进一步收敛到 `186dp`，统计卡数字由 `22sp` 收敛到 `20sp`。
  - 首页内容区域左右留白由 `12dp` 收敛到 `10dp`，分区间距统一收敛到 `4-6dp`。
  - 任务筛选区控件高度统一收敛到 `34dp`，分页按钮收敛到 `32dp`。
  - 底部导航再次收敛到 `42dp`，图标 `17dp`，继续保持仅图标模式。
- 任务卡片与骨架屏密度优化：
  - 任务卡片圆角 `12dp`，内边距 `10dp`，头像 `42dp`，次级动作行高度 `30dp`。
  - 骨架屏由 `96dp` 收敛到 `84dp`，间距与占位尺寸同步缩小，减少加载态“空洞感”。
- 模块区进一步紧凑：
  - 九宫格卡片高度由 `88dp` 收敛到 `82dp`，图标底板 `24dp`，标题 `9sp`。
  - 分组芯片高度由 `26dp` 收敛到 `24dp`，字号 `9sp`。
- 交互控件细化：
  - `Spinner` 文案和内边距收敛（selected `11sp`，dropdown `12sp`，上下 padding 缩小）。
  - 基础按钮样式 `AppButtonBase` 收敛到 `minHeight=34dp`、`textSize=10sp`。
- 本地验证：
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`
  - `adb install -r app/build/outputs/apk/debug/app-debug.apk`
  - `adb shell am start -n com.videotool/.MainActivity`

### 21.12 可读性回调（字体/行高上调，2026-04-09）
- 根据“整体行高和字体太小”的反馈，对移动端控制台执行可读性回调：
  - 顶部区域与统计卡回调到中等密度：头部高度与字体上调，指标数字回调到 `22sp`。
  - 模块九宫格回调：模块卡片高度 `96dp`，图标与标题字号上调（标题 `11sp`）。
  - 任务区回调：筛选控件与按钮高度统一到 `36dp`，分页按钮回调 `34dp`。
  - 任务卡片回调：头像、Handle、状态胶囊、次级文案与次要动作按钮字号/高度同步上调。
  - 分组 chips 回调：高度 `26dp`，字号 `10sp`，提升分组可读性。
  - 底部导航回调：高度 `48dp`，图标 `19dp`，避免点击区域过小。
- 相关变更文件：
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
  - `android_app/app/src/main/res/layout/item_task_card.xml`
  - `android_app/app/src/main/res/layout/item_spinner_selected.xml`
  - `android_app/app/src/main/res/layout/item_spinner_dropdown.xml`
  - `android_app/app/src/main/res/drawable/bg_spinner.xml`
  - `android_app/app/src/main/res/values/styles.xml`
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
- 本地验证：
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`
  - `adb install -r app/build/outputs/apk/debug/app-debug.apk`

### 21.13 个人中心高级化重构（2026-04-09）
- 将 `ModuleConsoleActivity` 的“我的”页重构为高级账号面板：
  - 顶部 Hero：渐变背景 + 头像首字母 + 门户 Badge（商家端/达人端）。
  - 信息芯片：角色、租户、服务地址（Endpoint）三类账号信息分层展示。
  - 快捷操作区：执行中心、设备管理、退出登录改为三张可点击动作卡（主次色分级 + 图标 + 描述文案）。
- 交互与架构调整：
  - `bindButtons()` 从 `Button` 强类型改为 `View` 通用绑定，降低 UI 组件耦合，后续可自由替换为卡片/行项。
  - `renderUserHeader()` 增加个人中心字段映射：
    - `text_profile_avatar`（用户名首字母）
    - `text_profile_portal_badge`
    - `text_profile_role_value`
    - `text_profile_tenant_value`
    - `text_profile_endpoint_value`（无地址时显示 `Not configured`）
- 新增资源：
  - `bg_profile_hero.xml`
  - `bg_profile_avatar.xml`
  - `bg_profile_chip.xml`
  - `bg_profile_meta_chip.xml`
  - `bg_profile_action_primary.xml`
  - `bg_profile_action_secondary.xml`
  - `bg_profile_action_logout.xml`
- 新增 i18n key（Android strings）：
  - `console_profile_quick_actions`
  - `console_profile_label_role`
  - `console_profile_label_tenant`
  - `console_profile_label_endpoint`
  - `console_profile_endpoint_unknown`
  - `console_profile_action_exec_desc`
  - `console_profile_action_device_desc`
  - `console_profile_action_logout_desc`
- 主要改动文件：
  - `android_app/app/src/main/res/layout/activity_module_console.xml`
  - `android_app/app/src/main/java/com/videotool/console/ModuleConsoleActivity.java`
  - `android_app/app/src/main/res/values/strings.xml`
  - `android_app/app/src/main/res/values-en/strings.xml`
  - `android_app/app/src/main/res/drawable/bg_profile_*.xml`

### 21.14 模块卡片垂直居中修正（2026-04-09）
- 针对“模块区域下边距视觉偏大”问题，修正模块卡片的纵向对齐方式：
  - `item_module_tile.xml` 内部容器由 `center_horizontal` 调整为 `center`，使图标与标题整体垂直居中。
  - 收敛上下内边距（`8dp -> 4dp`），减少底部空白感。
  - 标题与图标间距微调（`6dp -> 5dp`），避免视觉断层。
- 变更文件：
  - `android_app/app/src/main/res/layout/item_module_tile.xml`
- 验证：
  - `cd android_app`
  - `gradlew.bat :app:assembleDebug`
  - `adb install -r app/build/outputs/apk/debug/app-debug.apk`

## 22. 2026-04-09 达人联系深度优化 V2（回复率优先）

### 22.1 统一外联引擎（工作台 + 自动私信联动）
- `outreach_workspace/listJson` 新增返回：
  - `summary`：半自动任务漏斗（pending/copied/jumped/completed）
  - `auto_dm`：自动私信漏斗（pending/sending/sent/reply_pending/converted/blocked）
- 外联工作台页面增加：
  - 外联漏斗卡片
  - 自动私信漏斗卡片
  - 回复待确认列表与一键确认流转

### 22.2 自动私信活动升级（序列化 + A/B）
- `POST /admin.php/auto_dm/campaign/create` 扩展参数：
  - `ab_templates`
  - `followup_plan`
  - `stop_on_reply`
  - `reply_confirm_mode`
- 新增 `POST /admin.php/auto_dm/campaign/rebuild_followups`：
  - 对“已发送且未回复”的达人补生成 Step1/Step2
  - 命中 `stop_on_reply=1` 的达人不再补发后续步骤

### 22.3 回复待确认闭环
- 新增接口：
  - `GET /admin.php/auto_dm/reply_queue/list`
  - `POST /admin.php/auto_dm/reply_queue/confirm`
- 规则分类（V1）：`intent/inquiry/reject/unsubscribe/other`
- 确认后自动：
  - 写入 `auto_dm_reply_reviews`
  - 写入 `outreach_logs.action_type=auto_dm_reply_confirmed`
  - 根据确认结果联动 CRM 状态（如 `2已回复/3待寄样/6黑名单`）

### 22.4 Mobile Agent 回传扩展
- `POST /admin.php/mobile_agent/report_auto` 支持：
  - `delivery_status`
  - `reply_detected`
  - `reply_text`
  - `reply_time`
  - `conversation_snippet`
- 命中退订词时，自动写入 `do_not_contact` 并停发后续步骤。

### 22.5 数据结构与迁移
- 新增迁移脚本：`database/run_migration_auto_dm_v2.php`
- 主要变更：
  - `auto_dm_campaigns`：`ab_config_json`、`sequence_config_json`、`stop_on_reply`、`reply_confirm_mode`
  - `auto_dm_tasks`：`step_no`、`variant_template_id`、`reply_state`、`reply_text`、`reply_at`、`next_execute_at`、`delivery_status`、`conversation_snippet`
  - 新表：`auto_dm_reply_reviews`
- 运维中心迁移顺序已加入：`run_migration_auto_dm_v2.php`

### 22.6 执行命令（Windows / Linux）
1. Windows：
   - `php database\run_migration_auto_dm_v1.php`
   - `php database\run_migration_auto_dm_v2.php`
2. Linux：
   - `php database/run_migration_auto_dm_v1.php`
   - `php database/run_migration_auto_dm_v2.php`

### 22.7 Zalo 协议优先级与全自动发送闭环（2026-04-09）
- Android 端 Zalo 打开策略统一为：`https://zalo.me/{id}` 优先。
  - 适配位置：
    - `AgentTask.resolveZaloUrl()`
    - `ModuleConsoleActivity.handleContactAction()`
- Mobile Agent 自动私信链路修正为“真实回执闭环”：
  - 旧逻辑：`sending -> sent` 由服务端本地直接连发（未真实点击发送）。
  - 新逻辑：
    1. 任务准备成功后先上报 `sending`
    2. 写入无障碍待执行上下文（`CommentAutomationBridge.saveImAutoPending`）
    3. 触发 `ReachAccessibilityService` 自动执行：定位输入框 -> 填充文本 -> 点击发送
    4. 成功回调 `ACTION_MARK_SENT`，失败回调 `ACTION_MARK_FAIL`
    5. Mobile Agent 再向 `/admin.php/mobile_agent/report_auto` 上报最终态（`sent/failed`）
- 增加自动发送超时保护：
  - 自动任务在触发后 `45s` 未收到无障碍回执，自动上报 `failed`（`auto_send_timeout`），避免任务卡死。
- 兼容边界：
  - 仅对 `zalo_auto_dm / wa_auto_dm` 生效。
  - `comment_warmup / tiktok_dm` 继续保留原半自动模式。
- 本地验证命令（Windows）：
  - `cd android_app`
  - `gradlew.bat :app:compileDebugJavaWithJavac`

### 22.8 Auto DM 线上热修复迁移（2026-04-10）
- 新增脚本：`database/run_migration_auto_dm_hotfix.php`
- 目的：
  - 修复历史库中 `auto_dm` 迁移记录已存在但字段未落库的问题（重点是 `influencers.last_auto_dm_at`）。
- 脚本会幂等补齐字段：
  - `influencers.do_not_contact`
  - `influencers.last_auto_dm_at`
  - `influencers.auto_dm_fail_count`
  - `influencers.cooldown_until`
  - 及相关索引：`idx_last_auto_dm_at` / `idx_do_not_contact` / `idx_cooldown_until`
- 执行方式：
  - 后台运维中心一键迁移（`/admin.php/ops_center` -> 运行迁移）
  - 或命令行：
    - Windows: `php database\run_migration_auto_dm_hotfix.php`
    - Linux: `php database/run_migration_auto_dm_hotfix.php`

## 23. 2026-04-12 仪表盘页面（Stitch 对齐）重构

### 23.1 改动范围
- 页面模板：`view/admin/index/index.html`
- 多语言：`public/static/i18n/i18n.js`
- 文档：`docs/requirements.md`、`requirements.md`

### 23.2 页面结构（按 Stitch 仪表盘分区重写）
- 顶部区改为「系统指挥中心」样式，包含同步频率、节点状态、时间范围按钮、报表导出按钮。
- KPI 区分为两行：
  - 第一行 4 张核心卡（视频总数、已下载、下载率重点卡、待下载）。
  - 第二行 4 张运营卡（平台数、设备数、今日上传、今日下载，含环比/7日均值信息）。
- 增加 3 张业务模块卡（寻款索引、达人名录、达人链）。
- 保留并重排数据面板：趋势图、平台分布、异常趋势、实时预警、商品库存、容量统计。
- 所有展示文本统一走 `AppI18n.t`，新增 Dashboard 相关 key（zh/en/vi）。

### 23.3 低耦合实现说明
- 未新增后端接口，继续复用现有统计 API：
  - `/admin.php/stats/trends`
  - `/admin.php/stats/platformDistribution`
  - `/admin.php/stats/downloadErrorTrends`
  - `/admin.php/stats/downloadErrorTop`
  - `/admin.php/stats/productDistribution`
  - `/admin.php/stats/storageUsage`
- 仅调整前端布局与渲染，不影响 `StatsService` 数据聚合逻辑。
- ECharts 渲染逻辑保持模块化函数拆分（`renderTrends/renderPlatformList/renderErrors/renderProduct`）。

### 23.4 使用说明
1. 进入后台首页 `/admin.php` 即可看到新版仪表盘。
2. 通过「7天/30天」切换趋势范围。
3. 点击「生成报表」触发浏览器打印导出。
4. 点击「查看全部通知」跳转下载异常列表页。

### 23.5 验证命令（Windows / Linux 通用）
- Windows：
  - `php -l app\controller\admin\Index.php`
  - `php -l app\controller\admin\Stats.php`
- Linux：
  - `php -l app/controller/admin/Index.php`
  - `php -l app/controller/admin/Stats.php`

## 24. 2026-04-12 利润中心（多店铺 + 多账户 + 多币种）

### 24.1 模块目标
- 入口：`/admin.php/profit_center`
- 基于店铺 + 账户 + 渠道 + 日期进行利润录入与汇总。
- 统一以 `CNY` 为记账基准，支持 `USD/VND/CNY` 自动换算。
- 支持 TikTok 多店铺场景：同日多账户、多渠道并行录入并汇总。

### 24.2 数据结构
- 新增表：
  - `growth_profit_stores`：店铺与默认参数（售价/成本/取消率[直播/视频/达人三套默认值]/平台扣点/达人佣金/时薪/时区）。
  - `growth_profit_accounts`：广告账户（归属店铺、渠道、广告币种、默认 GMV 币种）。
  - `growth_profit_daily_entries`：日录入（`tenant + date + store + account + channel` 唯一）。
  - `growth_fx_rates`：汇率缓存（含来源与回退标记）。
- 所有表均带 `tenant_id`，支持 SaaS 租户隔离。

### 24.3 计算口径
- 渠道：`live`（直播）/`video`（视频）/`influencer`（达人）。
- ROI：`gmv_cny / ad_spend_cny`
- 直播/视频保本 ROI：
  - `break_even_roi = (sale*(1-cancel)) / (sale*(1-cancel)*(1-platform) - cost)`
- 达人保本 ROI（含佣金修正）：
  - `break_even_roi = (sale*(1-cancel)) / (sale*(1-cancel)*(1-platform-commission) - cost)`
- 直播/视频录入校验：`ad_spend_cny > 0 && gmv_cny > 0 && order_count > 0`
- 参数币种锁定：售价/成本/时薪使用 `CNY`。

### 24.4 汇率策略
- 服务：`app/service/FxRateService.php`
- 优先按日期拉取并缓存；失败时回退最近可用汇率，状态标记 `fallback_latest`。
- 支持 provider：
  - `currency-api@jsdelivr`（优先）
  - `open.er-api`（兜底 latest）
  - `fxratesapi`（二级兜底 latest）
- 兼容策略：
  - 优先 Guzzle 请求；若 Windows 环境 cURL 证书链异常（SSL error 60），自动回退 `file_get_contents` 流式抓取，不阻断汇率同步。
- 前端对回退汇率做 warning 提示，不阻塞保存。

### 24.5 接口清单
- 查询：
  - `GET /admin.php/profit_center/summaryJson`
  - `GET /admin.php/profit_center/entryListJson`
  - `GET /admin.php/profit_center/fxRateListJson`
- 录入与管理：
  - `POST /admin.php/profit_center/entrySave`
  - `POST /admin.php/profit_center/entryBatchSave`
  - `POST /admin.php/profit_center/entryDelete`
  - `GET/POST /admin.php/profit_center/storeListJson|storeSave|storeDelete`
  - `GET/POST /admin.php/profit_center/accountListJson|accountSave|accountDelete`
- 汇率与导入导出：
  - `POST /admin.php/profit_center/fxSync`
  - `POST /admin.php/profit_center/importXlsx`
  - `GET /admin.php/profit_center/exportCsv`

### 24.6 Excel 导入兼容
- 支持三 sheet：
  - `直播GMV利润表` -> `live`
  - `视频GMV利润表` -> `video`
  - `达人GMV利润表` -> `influencer`
- 支持模板下载：`GET /admin.php/profit_center/templateXlsx`
- 模板下载兼容低版本 PhpSpreadsheet（使用 `Coordinate` 列坐标写入），避免线上/本地版本差异导致下载失败。
- 仅读取输入列，忽略公式结果列。
- Excel 日期序列按 `Asia/Bangkok(GMT+7)` 转 `YYYY-MM-DD`。
- 旧模板不含店铺/账户/币种时，导入弹窗先选择目标店铺和账户，币种可覆盖。

### 24.7 前端实现
- 页面：`view/admin/profit_center/index.html`
- 技术栈：AdminLTE + Vue3 + Element Plus（仅调用 JSON API，前后端分离）。
- 页面区块：
  - 顶部筛选（日期/店铺/账户/渠道）
  - KPI（净利润/广告费/GMV/订单/ROI）
  - 录入明细表（编辑/删除）
  - 批量录入弹窗（同日多店铺 + 多广告账户多行录入，逐行返回成功/失败）
  - 店铺管理（支持直播/视频/达人三套取消率默认值）、账户管理、汇率状态、Excel 导入弹窗

### 24.8 迁移与执行
- 新增迁移脚本：`database/run_migration_profit_center.php`（幂等）
- 已接入运维中心迁移列表：`app/service/OpsMaintenanceService.php`
- 执行命令：
  - Windows：`php database\\run_migration_profit_center.php`
  - Linux：`php database/run_migration_profit_center.php`

### 24.9 验证命令（Windows / Linux 通用）
- Windows：
  - `php -l app\\controller\\admin\\ProfitCenter.php`
  - `php -l route\\admin.php`
  - `php -l app\\service\\FxRateService.php`
  - `php -l app\\service\\ProfitCalculatorService.php`
  - `php -l database\\run_migration_profit_center.php`
  - `node --check public\\static\\i18n\\i18n.ops2.js`
- Linux：
  - `php -l app/controller/admin/ProfitCenter.php`
  - `php -l route/admin.php`
  - `php -l app/service/FxRateService.php`
  - `php -l app/service/ProfitCalculatorService.php`
  - `php -l database/run_migration_profit_center.php`
  - `node --check public/static/i18n/i18n.ops2.js`

### 24.10 2026-04-12 批量录入（多店铺 + 多广告账户）
- 新增后端接口：`POST /admin.php/profit_center/entryBatchSave`
  - 入参：`items[]`（每行至少包含 `entry_date/store_id/account_id/channel_type/ad_spend_amount/ad_spend_currency/gmv_amount/gmv_currency/order_count`，直播可传 `live_hours`）。
  - 行级处理：逐行复用 `upsertEntry`，单行失败不影响其他行。
  - 出参：`total/saved_count/failed_count/saved_items/failed_items`。
- 新增前端能力：
  - 顶部新增「批量录入」按钮。
  - 弹窗支持一次维护多行，按店铺过滤账户，并可自动带出账户渠道与默认币种。
  - 提交后显示批量结果；若部分失败，保留失败行原因便于修正后重提。
- 自测记录（Windows 本地）：
  - 已执行 `entryBatchSave` 成功场景：2 行保存成功（多店铺 + 多账户）。
  - 已执行 `entryBatchSave` 部分失败场景：1 行成功 + 1 行失败，接口返回行号与错误码，验证“失败不阻断”。

### 24.11 2026-04-12 全模块自动化测试与 i18n 全量校验
- 自动化测试执行（Windows）：
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`
  - `node scripts/check_i18n_keys.js --scope=all`
  - 模块 API 冒烟（登录后批量调用 22 个列表/汇总接口，覆盖平台、设备、用户、素材、寻款、CRM、增长中台、利润中心、运维状态）
- 修复项：
  - 路由匹配修复：`/admin.php/ops_center/status` 路由提前于 `ops_center` 注册，避免被页面路由吞掉导致返回 HTML。
  - i18n 缺失键修复：
    - 三语缺失键补齐：65 个（`zh/en/vi`）。
    - 仅越南语缺失键回填：80 个（优先取 `en`，其次 `zh`）。
  - 汇率与模板链路修复（本轮回归确认）：
    - 模板下载修复为 `Coordinate` 写列，避免 PhpSpreadsheet 版本差异报错。
    - 汇率抓取增加 Windows 证书链异常回退与二级 provider，`fxSync` 可稳定返回有效汇率。
- 回归结果：
  - `ops2_smoke.ps1`：`PASS (21 checks)`。
  - `check_i18n_keys --scope=all`：`passed`（`used_keys=946`，缺失 0）。
  - 模块 API 冒烟：`MODULE_PASS=22`，`MODULE_FAIL=0`。
  - 利润中心专项：模板下载 `200 + xlsx`，`fxSync` 返回 `USD/VND` 非零汇率。

### 24.12 2026-04-12 参数码直显治理（消息归一化 + 利润中心补齐翻译）
- 问题背景：
  - 部分页面会直接显示后端返回的 `msg` 参数码（如 `ok/save_failed/invalid_params`），导致用户看到参数而非文案。
  - 利润中心存在少量硬编码文案（如 `Ad CCY/GMV CCY/Y/N`）与汇率来源代码直显。
- 本轮修复：
  - 全局 i18n 新增消息归一化能力（`public/static/i18n/i18n.js`）：
    - 新增 `AppI18n.translateMaybeKey()` 与 `AppI18n.resolveApiMessage()`。
    - 支持将常见后端消息码自动映射到 i18n 文案（`ok/save_failed/invalid_params/not_found/...`）。
    - 对未翻译的“疑似 key”消息不再原样直显，回退到业务 fallback 文案。
  - 全局 Element Plus 消息拦截（`view/admin/common/layout.html`）：
    - 在 `ElementPlus.ElMessage` 层统一做消息归一化，避免各页面重复写转换逻辑。
    - 覆盖 `success/warning/info/error` 入口，兼容字符串与对象参数。
  - 利润中心翻译补齐（`view/admin/profit_center/index.html` + `public/static/i18n/i18n.ops2.js`）：
    - `Ad CCY/GMV CCY` 改为 i18n 键。
    - `Y/N` 改为 `common.yes/common.no`。
    - 汇率来源 `source` 改为可读文案（Manual/Currency API/ER API/FXRates API/Fallback/Unknown）。
    - 批量录入失败行 `message` 支持参数码自动翻译后展示。
- 新增 i18n 键（zh/en/vi）：
  - `common.yes`、`common.no`
  - `page.profitCenter.colAdCurrency`、`page.profitCenter.colGmvCurrency`
  - `page.profitCenter.fxSourceManual`、`page.profitCenter.fxSourceCurrencyApi`、`page.profitCenter.fxSourceErApi`、`page.profitCenter.fxSourceFxRatesApi`、`page.profitCenter.fxSourceFallbackLatest`、`page.profitCenter.fxSourceUnknown`
- 本轮验证（Windows）：
  - `node --check public/static/i18n/i18n.js`
  - `node --check public/static/i18n/i18n.ops2.js`
  - `node scripts/check_i18n_keys.js --scope=all`（`used_keys=956`，缺失 0）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）
  - 模块 API 冒烟（25 接口）：`MODULE_PASS=25`，`MODULE_FAIL=0`
  - 参数码翻译自测：`ok/save_failed/invalid_params/page.profitCenter.fxSourceFallbackLatest` 均返回翻译文案。

### 24.13 2026-04-12 User 页面键值未翻译修复
- 问题现象：
  - `用户管理` 页面仍出现 i18n key 直显（如 `page.user.addTitle`、`page.user.passwordReset` 等）。
- 根因分析：
  - 页面 `view/admin/user/index.html` 里重复加载了旧参数版本 `i18n.js`，导致布局中已加载并扩展好的 `i18n.ops2.js` 字典被覆盖。
  - 页面使用了 `page.user.createSuccess` / `page.user.passwordReset`，但字典缺失这两个 key。
- 修复内容：
  - 移除用户页内重复的 `i18n.js` 引用，统一使用布局层加载的 i18n 资源。
  - 用户页 `respMsg()` 接入 `AppI18n.resolveApiMessage()`，避免参数码直显。
  - 在 `public/static/i18n/i18n.ops2.js` 补齐：
    - `page.user.createSuccess`
    - `page.user.passwordReset`
  - 在 `public/static/i18n/i18n.js` 增加“重复加载保护”：
    - 若 `window.AppI18n._dict` 已存在，则在重新加载 `i18n.js` 时先合并旧字典，保留 `i18n.ops2` 扩展键，避免被覆盖。
- 涉及文件：
  - `view/admin/user/index.html`
  - `public/static/i18n/i18n.js`
  - `public/static/i18n/i18n.ops2.js`
- 本轮验证（Windows）：
  - `php -l view/admin/user/index.html`
  - `node --check public/static/i18n/i18n.js`
  - `node --check public/static/i18n/i18n.ops2.js`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）
  - 重复加载场景自测：按 `i18n.js -> i18n.ops2.js -> i18n.js` 顺序执行后，`page.user.addTitle/page.user.editTitle/page.user.createSuccess/page.user.passwordReset/common.cannotDisableSelf` 均可正确翻译。

### 24.14 2026-04-12 利润中心账户模型调整（单店单 GMV MAX）
- 背景：
  - TikTok GMV MAX 场景下，单店铺仅绑定一个广告账户，且直播/视频共用该账户。
- 规则调整：
  - 账户管理不再配置渠道（`channel_type` 不再作为业务输入）。
  - `growth_profit_accounts` 改为“每店仅 1 账户”约束：
    - 应用层：`accountSave` 创建时若店铺已有账户，自动转更新该账户。
    - 数据层：迁移脚本补充唯一索引 `uk_tenant_store_single (tenant_id, store_id)`；若历史重复数据存在则跳过并提示。
  - 日录入保持渠道维度（`live/video/influencer`）独立输入，用于利润公式分支。
  - `entrySave`/`entryBatchSave` 支持未传 `account_id` 时按店铺自动绑定主账户；若店铺无账户则返回 `store_account_required`。
- 前端行为：
  - 账户弹窗移除渠道选择，增加“单店单 GMV MAX，直播/视频共用”提示。
  - 单条录入中账户下拉改为按店铺自动绑定（只读）。
  - 批量录入按店铺自动带出账户，账户列仅展示名称，不再手动切换渠道来源。
- 本轮验证（Windows）：
  - `php -l app/controller/admin/ProfitCenter.php`
  - `php -l database/run_migration_profit_center.php`
  - `php -l view/admin/profit_center/index.html`
  - `node --check public/static/i18n/i18n.ops2.js`
  - `node scripts/check_i18n_keys.js --scope=all`（`used_keys=957`，缺失 0）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）
  - 利润中心专项接口自测：
    - 同店两次 `accountSave` 返回同一 `id`
    - `accountListJson` 同店数量为 1
    - `entrySave` 不传 `account_id` 可成功自动绑定到账户

### 24.15 2026-04-12 广告赔付字段（多币种 + 自动折算 + 并入利润）
- 背景：
  - 广告 ROI 低于目标阈值时会产生平台赔付，且赔付并非每天都有，需要按日记录并参与利润核算。
- 字段设计（`growth_profit_daily_entries`）：
  - `ad_compensation_amount`：赔付原币金额
  - `ad_compensation_currency`：赔付币种（支持 `CNY/USD/VND`）
  - `ad_compensation_cny`：赔付折算为 CNY 后金额
- 录入与默认规则：
  - 单条录入与批量录入均新增“广告赔付（金额 + 币种）”输入。
  - 默认赔付币种跟随广告账户币种（`account_currency`）。
  - 未填写时按 `0` 处理，不影响原有录入流程。
- 计算与汇总规则：
  - 赔付金额按录入日期汇率折算到 CNY（与广告费/GMV 同一汇率服务）。
  - 日净利润改为：`原公式净利润 + ad_compensation_cny`。
  - ROI 口径保持不变（仍为 `gmv_cny / ad_spend_cny`）。
  - 汇总接口 `summaryJson` 增加 `ad_compensation_cny` 聚合，并在 KPI 展示“总广告赔付(CNY)”。
- 导出与兼容：
  - `exportCsv` 新增赔付三列（原币金额/币种/CNY）。
  - 迁移脚本 `run_migration_profit_center.php` 幂等补齐上述三字段，兼容历史库。

### 24.16 2026-04-13 利润中心界面分区重构（导入 Excel / 店铺添加 / 新增录入）
- 目标：
  - 对齐 Stitch 利润中心交互思路，突出三个高频操作区：`导入 Excel`、`店铺添加`、`新增录入`。
  - 保持前后端分离与低耦合：只重构前端模板与文案，不改接口契约。
- 页面改造（`view/admin/profit_center/index.html`）：
  - 新增顶部 Hero 区和「核心操作区」三卡片，分别承载：
    - 模板下载 + 导入入口
    - 店铺/账户管理入口
    - 单条录入 + 批量录入入口
  - 筛选区重排为左右分区（筛选条件/报表动作），KPI 区统一卡片化样式。
  - 新增录入弹窗重构为四分区：
    - 基础信息
    - 利润参数
    - 金额与币种
    - 扩展指标
  - 店铺管理弹窗增加引导提示，并将表单分为「基础参数/费率参数」。
  - 导入弹窗增加引导提示与文件上传说明区，降低模板映射出错率。
- i18n 补齐（`public/static/i18n/i18n.ops2.js`）：
  - 新增利润中心页面键：
    - `page.profitCenter.heroDesc`
    - `page.profitCenter.workspaceTitle`
    - `page.profitCenter.workspaceDesc`
    - `page.profitCenter.panelImportDesc`
    - `page.profitCenter.panelStoreDesc`
    - `page.profitCenter.panelEntryDesc`
    - `page.profitCenter.importDialogTip`
    - `page.profitCenter.fileSelectHint`
    - `page.profitCenter.entrySectionBase`
    - `page.profitCenter.entrySectionParam`
    - `page.profitCenter.entrySectionAmount`
    - `page.profitCenter.entrySectionExtra`
    - `page.profitCenter.storeDialogTip`
    - `page.profitCenter.storeSectionBasic`
    - `page.profitCenter.storeSectionRates`
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node --check public/static/i18n/i18n.ops2.js`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 24.17 2026-04-13 利润中心弹窗居中与像素对齐
- 目标：
  - 将利润中心 6 个主弹窗统一为遮罩层居中 + 弹窗垂直居中，避免不同分辨率下出现偏移。
  - 保持桌面端与移动端一致的边距、标题栏、内容区、底部操作区视觉对齐。
- 页面实现（`view/admin/profit_center/index.html`）：
  - 新增统一弹窗样式：
    - `.pc-dialog-overlay`：遮罩层 `flex` 居中布局，统一内边距。
    - `.pc-dialog`：`max-width/max-height` 限制，保证小屏不溢出。
    - `.el-dialog__header/body/footer`：统一上下边界与间距，减少不同弹窗视觉跳动。
  - 6 个弹窗统一增加：
    - `class="pc-dialog"`
    - `modal-class="pc-dialog-overlay"`
    - `align-center`
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 24.18 2026-04-13 利润中心弹窗居中修正（Overlay 容器）
- 问题：
  - 已开启 `align-center` 后，部分环境下弹窗仍出现“视觉偏上”。
- 根因：
  - Element Plus 实际控制定位的是 `.el-overlay-dialog` 容器，仅设置遮罩层 class 不足以稳定垂直居中。
- 修复：
  - 在 `view/admin/profit_center/index.html` 增加：
    - `.pc-dialog-overlay .el-overlay-dialog { display:flex; align-items:center; justify-content:center; }`
    - `.pc-dialog-overlay .el-dialog { margin:0; top:0; }`
  - 保留原有 `class="pc-dialog"` 与 `align-center`，形成双保险，兼容不同浏览器/缩放比例。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 24.19 2026-04-13 利润中心视觉对齐（Stitch 风格 Token 同步）
- 背景：
  - 用户反馈页面与设计稿存在明显视觉差异（层级、间距、按钮、卡片风格）。
- 实施范围（仅前端样式，不改接口）：
  - 文件：`view/admin/profit_center/index.html`
  - 同步 Stitch 风格 Token：
    - 页面背景、卡片圆角、边框、阴影、文本层级、按钮主色梯度。
    - KPI、工作台卡片、筛选区、表格头部视觉统一。
    - 弹窗遮罩与主体阴影统一，保留居中双保险（`align-center` + `overlay dialog flex center`）。
  - 交互层级调整：
    - 顶部与“新增录入”卡片按钮由 `success/warning` 调整为主次按钮层级（主按钮 `primary`，次按钮默认态）。
- 兼容性说明：
  - 仅新增/覆盖页面内 scoped 样式，未改后端接口与数据结构。
  - Windows 开发与 Linux 部署无差异（纯模板/CSS 调整）。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 24.20 2026-04-13 利润中心稳定性补丁（模板下载/汇率回退/i18n 键值）
- 导入模板修复（`app/controller/admin/ProfitCenter.php`）：
  - 修复模板下载与导入链路中的编码异常，恢复三 sheet 模板输出与导入识别。
  - 模板新增“广告赔付金额”列（直播/视频/达人），导入时自动读取并参与利润计算。
  - 导入兼容策略改为“标题 + 列结构”双重识别，避免旧模板标题编码差异导致跳过。
- 汇率服务增强（`app/service/FxRateService.php`）：
  - 新增多层请求回退：`Guzzle(严格 TLS) -> Guzzle(宽松 TLS) -> Stream(严格 TLS) -> Stream(宽松 TLS)`。
  - 解决 Windows 本地证书链缺失场景下汇率获取失败问题，同时保留失败状态打标。
- i18n 键值补齐（`public/static/i18n/i18n.js` + `public/static/i18n/i18n.ops2.js`）：
  - 增加利润中心后端错误码映射，避免界面出现 `invalid_entry_date` / `store_account_required` 等原始参数键值。
  - 新增多语种错误文案：
    - `page.profitCenter.msg.batchTooLarge`
    - `page.profitCenter.msg.invalidItem`
    - `page.profitCenter.msg.invalidEntryDate`
    - `page.profitCenter.msg.invalidStoreOrAccount`
    - `page.profitCenter.msg.storeNotFound`
    - `page.profitCenter.msg.storeAccountRequired`
    - `page.profitCenter.msg.accountNotFound`
    - `page.profitCenter.msg.accountStoreMismatch`
    - `page.profitCenter.msg.invalidChannelType`
    - `page.profitCenter.msg.calcFailed`
    - `page.profitCenter.msg.xlsxOnly`
    - `page.profitCenter.msg.fileUnreadable`
    - `page.profitCenter.msg.importFailed`
    - `page.profitCenter.msg.exportFailed`
    - `page.profitCenter.msg.storeHasAccounts`
    - `page.profitCenter.msg.storeHasEntries`
    - `page.profitCenter.msg.accountHasEntries`
  - 前端缓存版本号升级：`view/admin/common/layout.html` 中 i18n 资源版本更新为 `20260413_i18nfix4`。
- 自动化测试补充：
  - 新增利润中心专项冒烟脚本：`scripts/profit_center_smoke.ps1`
  - 覆盖范围：登录、店铺/账户创建、单条录入、批量录入、汇总、汇率同步、模板下载、单店单账户约束、数据清理。
- 本轮验证（Windows）：
  - `php -l app/controller/admin/ProfitCenter.php`
  - `php -l app/service/FxRateService.php`
  - `node --check public/static/i18n/i18n.js`
  - `node --check public/static/i18n/i18n.ops2.js`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

## 15. 2026-04-13 Profit Center UI Sync (Stitch)

### 15.1 Scope
- Synced `view/admin/profit_center/index.html` to Stitch project `3897242182509863659` visual structure for:
  - Profit Center main screen (`2b3ab685fe244efb8fdfa5aa0ad012db`)
  - New Entry modal (`1b5ca6d49d6d499da79c3d7d5c5129d3`)
  - Import Excel modal (`fae2232fd8af4727bbe9b40bf5b57035`)
  - Store Manage modal region (`1a344bdc1e7e42eb94ed64db04624534`)

### 15.2 UI/UX updates
- Rebuilt layout with Stitch-like KPI cards, filter toolbar, action button cluster, and data table panel.
- Unified modal centering via overlay class `pc-modal-overlay` + `align-center` for all Profit Center dialogs.
- New Entry modal switched to two-column layout with right-side realtime preview (store/account/ROI/wage/cancel rate).
- Import modal switched to 3-step flow (template download, file pick/drop area, store/account/currency mapping).
- Store Manage modal switched to card-list + editor split layout with rate metrics preview.

### 15.3 Validation (Windows dev)
- `node scripts/check_i18n_keys.js --scope=all` => pass
- `php -l app/controller/admin/ProfitCenter.php` => pass
- `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1` => pass (21 checks)
- Profit Center endpoint smoke (local php -S):
  - `/admin.php/profit_center/storeListJson` pass
  - `/admin.php/profit_center/accountListJson` pass
  - `/admin.php/profit_center/summaryJson` pass
  - `/admin.php/profit_center/entryListJson` pass
  - `/admin.php/profit_center/fxRateListJson` pass
  - `/admin.php/profit_center/templateXlsx` pass (download length > 0)

### 15.4 Pixel-level visual alignment (2026-04-13)
- Fine-tuned to screenshot parity for spacing/size/iconography in Profit Center:
  - toolbar title/button density
  - KPI cards with top-right icons
  - filter area sizing and action row spacing
  - table column widths and row action button styles
  - batch dialog action column (delete/clone inline) and link-button style fix
  - entry dialog label wrapping fix and metric section readability
- Validation:
  - `node scripts/check_i18n_keys.js --scope=all`
  - inline JS parse check for `view/admin/profit_center/index.html`
  - Profit endpoint smoke: `/admin.php/profit_center/entryListJson`
- Follow-up hotfix: reverted oversized typography/button scale to normal desktop density (title/KPI/table/buttons/filter fields).

### 15.5 2026-04-13 利润中心 UI 深度重构（统一字号/按钮/弹窗栅格）
- 重构文件：
  - `view/admin/profit_center/index.html`
- 本轮关键调整：
  - 移除利润中心页面全部内联样式（`style=""` 归零），统一改为 class 驱动。
  - 按钮体系统一：
    - 大按钮 `40px`、中按钮 `32px`、小按钮 `24px`。
    - 全局高优操作保留实心主按钮，行内“编辑/删除”统一降噪为文字链接风格。
  - 字号体系收敛为财务后台密度：
    - 主要标题/卡片数字/区块标题控制在 `16px` 以内，正文 `14px`，次要信息 `12px`。
    - 修复“字体过大”问题，避免 KPI 与弹窗视觉失衡。
  - 弹窗布局规范化：
    - 头/体/尾分割线保留，表单 label 右对齐，非 inline 表单统一 `20px` gutter 与行间距。
    - 金额输入（数值 + 币种）统一横向组样式，控件等高对齐。
  - 表格与间距规范：
    - 所有表格单元格统一 `padding: 12px 16px`。
    - 页面关键 spacing 使用 4 的倍数（8/12/16/20）统一节奏。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `node` 内联脚本语法解析（`JS_PARSE_OK`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 15.6 2026-04-13 利润录入弹窗数字输入错位修复
- 问题现象：
  - 新增录入弹窗中数值字段（订单数/直播时长/金额与币种/利润参数）在部分分辨率下出现输入框挤压、错位、对齐不齐。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 新增录入弹窗左侧表单从“窄三列”调整为“稳定两列”（`span 8 -> span 12`），减少固定 `label-width` 对输入区的挤压。
  - 调整录入弹窗主布局比例：`1.4fr / 1fr` -> `1.8fr / 1fr`，提升主录入区可用宽度。
  - 统一补充输入控件宽度约束：
    - `pc-full` 作用于 `el-input-number/el-select/el-date-picker` 时强制 `width:100%`。
    - 表单内容区 `min-width:0`，避免 flex 场景下内容溢出导致视觉错位。
  - 将移动/窄屏下录入弹窗纵向堆叠断点放宽到 `1360px`，降低中等分辨率错位概率。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）

### 15.7 2026-04-13 利润中心可读性修复（字体/关键列/数字框）
- 问题现象：
  - 页面存在“字体过小”“数字选择框错位”“主表重要信息需横向滚动后才可见”问题。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 字体可读性回调（避免过小）：
    - 页面主标题、KPI 数值、表格标题、表头与次级文字整体上调到更可读密度（保持统一风格）。
  - 数字输入框对齐修复：
    - 统一 `el-input-number` 宽度策略与加减按钮区域宽度，避免数值区被压缩导致错位。
    - 金额录入“数字 + 币种”组合中，数字框最小宽度与币种下拉宽度统一，减少视觉跳动。
  - 主表关键信息前置：
    - 将 `订单数/ROI/净利润` 前移。
    - `日期/店铺/净利润` 固定在左侧，确保不横向滚动也能先看到关键经营指标。
    - 店铺/账户列开启 `show-overflow-tooltip`，在压缩列宽下保持可读且不破版。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 15.8 2026-04-13 利润中心视觉强化（大字号 + 彩色图标）
- 问题现象：
  - 顶部标题与 KPI 区视觉存在“字体偏小、图标无色、缺少设计层次”的反馈。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 标题与副标题增强：
    - 页面主标题提升至大标题层级（Manrope 粗体），副标题同步提高可读性。
  - KPI 模块视觉强化：
    - KPI 卡片内边距与最小高度提升，数值字号放大至高对比展示。
    - 图标由单一灰色改为分卡片配色（利润/广告费/成交额/订单/ROI 各自色板），提升信息识别速度。
  - 顶部动作按钮图标配色：
    - 工具按钮图标增加彩色底片风格，主按钮与深色按钮自动切换白色图标体系，提升主次层级。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）

### 15.9 2026-04-13 视觉回调（防过大）+ 批量录入数字框错位修复
- 问题现象：
  - 视觉强化后顶部与 KPI 字号偏大；
  - 批量录入弹窗中“订单数/直播时长”数字输入框在窄列下出现挤压显示异常。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 字号回调到中档：
    - 主标题、副标题、KPI 数值和 KPI 辅助文案整体下调一档，保持“有层级但不过大”。
  - 批量录入表格输入框修复：
    - 批量表格单元格横向内边距收敛，释放内容宽度。
    - `订单数` 列宽提升到 `152`，`直播时长` 列宽提升到 `160`，保证 `el-input-number` 完整显示。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）

### 15.10 2026-04-13 汇总区字体可读性修复（按店铺/按渠道）
- 问题现象：
  - 底部“按店铺汇总 / 按渠道汇总”卡片标题与表格字号偏小，阅读成本高。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 汇总卡片标题字体由小号提升到可读层级（18px）。
  - 两张汇总表从 `size=small` 调整为 `size=default`，并增加专用样式类 `pc-summary-table`：
    - 表头字号提升（15px）
    - 数据行字号提升（16px）
    - 单元格内边距增加，改善阅读节奏
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）

### 15.11 2026-04-13 标题直出修复 + KPI 降噪 + 批量录入稳定性
- 问题现象：
  - 页面标题出现模板表达式直出（`{{ AppI18n... }}`）；
  - KPI 图标色彩过于跳跃；
  - 批量录入“订单数/直播时长”列在部分分辨率下仍可能挤压。
- 修复范围：
  - 文件：`view/admin/profit_center/index.html`
- 修复内容：
  - 标题块改为稳定服务端文本，杜绝模板表达式在浏览器中原样显示。
  - KPI 图标从多彩混搭调整为统一主色系（蓝灰层级），保留盈亏数值红绿提示作为唯一强语义颜色。
  - 批量录入表格输入稳定性增强：
    - 输入框最小宽度约束补齐；
    - `订单数`/`直播时长`列宽扩大到 `176`；
    - 批量表格单元格 padding 收敛，释放列内可用宽度。
  - 视觉回调：标题/KPI 字号从“过大”回调到中间档位。
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`（`passed`）
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`（`SUMMARY => PASS`）

### 15.12 2026-04-13 Sidebar 视觉与交互一致性优化
- 需求目标：
  - 统一侧边菜单选中态、间距、字体与图标尺度，增强收起模式可用性与整体动效一致性。
- 修复范围：
  - 文件：`view/admin/common/layout.html`
- 修复内容：
  - 去除选中项左侧竖条，统一改为圆角背景高亮（8px）：
    - 基于品牌色透明层实现选中/悬停背景（`rgba(var(--primary-rgb), alpha)`）。
  - 菜单项间距与字形统一：
    - 顶级菜单左右内边距统一为 `12px`，图标与文字间距调整为 `16px`。
    - 菜单文字统一 `Manrope`、`14px`，图标统一 `18px`，保证视觉重心对齐。
  - Mini 收起模式增强：
    - 收起后图标强制水平居中。
    - 为菜单项自动生成 `data-tooltip/title`，悬停显示 tooltip，避免收起后不可识别。
  - 动画统一：
    - 菜单背景切换、折叠展开、侧栏宽度/主区域联动过渡统一为 `0.3s ease-in-out`。
- 本轮验证（Windows）：
  - `php -l view/admin/common/layout.html`

### 15.13 2026-04-13 Sidebar 像素级微调（第二轮）
- 修复范围：
  - 文件：`view/admin/common/layout.html`
- 微调内容：
  - 新增侧栏节奏变量（项高/间距/圆角/tooltip 色板），统一顶级与子级菜单的垂直节奏。
  - 顶级菜单与子菜单补齐 `min-height` 与 `box-sizing`，修复不同内容长度下点击区不一致问题。
  - 收起模式改为固定命中区尺寸（与菜单项高度一致），确保图标在水平与垂直方向均精确居中。
  - tooltip 改为“基础态隐藏 + hover 渐显”机制，增加淡入与轻位移动画，阴影与对比度按设计稿收敛。
  - 选中态背景保留品牌色透明高亮，并补充轻边框，降低高亮突兀感。
- 本轮验证（Windows）：
  - `php -l view/admin/common/layout.html`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 15.14 2026-04-13 全系统按钮颜色风格统一
- 目标：
  - 统一后台系统按钮主色、次级按钮、危险按钮的视觉风格，减少页面间色彩漂移。
- 修复范围：
  - `view/admin/common/layout.html`
  - `view/admin/index/index.html`
  - `view/admin/profit_center/index.html`
- 实现内容：
  - 在全局布局新增按钮色板变量（primary/default/danger 及 hover/active/shadow）。
  - Bootstrap 按钮统一：
    - `btn-primary` 统一为品牌蓝实色体系；
    - `btn-outline-secondary`/`btn-secondary` 统一为中性浅底风格；
    - `btn-danger` 统一为红色语义风格。
  - Element Plus 按钮统一：
    - 在 `body.dark-dashboard` 下统一 `--el-color-primary` 与 `--el-color-danger` 系列变量。
    - 覆盖 `el-button--primary/default/danger` 的背景、边框、hover、active 与阴影，使其与 Bootstrap 同源。
  - 模块级收敛：
    - 仪表盘 `dashboard-actions` 去除独立渐变主按钮，改为全局主色。
    - 利润中心去除多彩工具按钮图标色板和深色特例按钮，统一到品牌蓝主按钮体系；链接按钮同步主色。
- 本轮验证（Windows）：
  - `php -l view/admin/common/layout.html`
  - `php -l view/admin/index/index.html`
  - `php -l view/admin/profit_center/index.html`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`（`PASS (21 checks)`）

### 15.15 2026-04-13 前台按钮色板统一（用户端页面）
- 目标：
  - 将用户端页面按钮风格与后台统一到同一品牌色体系，减少页面间颜色割裂。
- 修复范围：
  - `view/index/index.html`
  - `view/index/platforms.html`
  - `view/index/influencer.html`
  - `view/index/search_by_image.html`
  - `view/index/style_catalog.html`
  - `view/index/download.html`
- 实现内容：
  - 统一主按钮为品牌蓝：`#1677ff`（hover `#0f6fe8` / active `#0c5fca`）。
  - 统一次按钮为浅蓝灰底 + 细边框风格，危险操作统一红色语义。
  - 清理用户端页面按钮内联色值，改为 class 驱动（例如重试按钮、购物车删除按钮）。
  - 替换搜索/拍照/下载等场景的分散色值（黑/绿/靛蓝）为统一色板，保持交互态（hover/active/阴影）一致。
- 兼容性说明：
  - 仅修改模板与 CSS，不改接口，不影响 Windows 开发与 Linux 部署行为。
- 本轮验证（Windows）：
  - `php -l view/index/index.html`
  - `php -l view/index/platforms.html`
  - `php -l view/index/influencer.html`
  - `php -l view/index/search_by_image.html`
  - `php -l view/index/style_catalog.html`
  - `php -l view/index/download.html`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

### 15.16 2026-04-13 利润中心批量录入体验升级（快捷批量）
- 目标：
  - 解决“批量录入营业额信息显示不全”的问题，提升多店铺/多账户高频录入效率。
- 修复范围：
  - `view/admin/profit_center/index.html`
  - `public/static/i18n/i18n.ops2.js`
- 交互改造：
  - 批量录入弹窗新增“双模式”：
    - `快捷录入`（默认）：顶部统一基础信息（日期/店铺/账户/渠道），下方卡片式多行录入金额与订单，减少横向滚动。
    - `完整录入`：保留原表格录入模式，用于逐行细项调整。
  - 快捷录入支持：
    - 一键“应用基础信息”同步到全部行；
    - 行级新增/复制/删除；
    - 保留广告花费、成交金额、广告赔付、订单数、直播时长录入能力。
- 逻辑与校验：
  - 新增 `batchPreset` 基础信息模型，并与 `batchRows` 联动同步。
  - 在快捷模式提交前强校验基础信息完整性；不完整时给出明确提示。
  - 新行在快捷模式下自动继承基础信息与账户默认币种（广告币种/GMV 币种）。
- i18n：
  - 新增并落地 `zh/en/vi` 文案键值：
    - `page.profitCenter.batchModeQuick`
    - `page.profitCenter.batchModeFull`
    - `page.profitCenter.batchBaseTitle`
    - `page.profitCenter.batchApplyBase`
    - `page.profitCenter.batchPresetInvalid`
    - `page.profitCenter.batchQuickHint`
- 本轮验证（Windows）：
  - `php -l view/admin/profit_center/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

### 15.17 2026-04-14 商品管理分类补齐（列表筛选 + 分类列）
- 目标：
  - 补齐 `/admin.php/product` 商品管理页对“分类”的可见与可筛选能力。
- 修复范围：
  - `view/admin/product/index.html`
- 实现内容：
  - 列表查询参数新增 `category` 传递，复用后端 `listJson` 已有分类过滤能力。
  - 前端接入 `listJson.data.category_options`，生成分类下拉选项。
  - 顶部筛选区新增“分类”下拉（可清空）。
  - 表格新增“分类”列，展示 `category_name`。
  - 重置筛选时同步清空分类条件。
- 兼容性说明：
  - 不改接口结构与数据库，仅前端接线，Windows 开发与 Linux 部署通用。
- 本轮验证（Windows）：
  - `php -l view/admin/product/index.html`
  - `node scripts/check_i18n_keys.js --scope=all`
  - `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

## 17. 2026-04-14 仪表盘下载趋势兼容修复

### 17.1 问题现象
- 仪表盘“近 N 天趋势”中的下载曲线在部分环境始终为 `0`，即使业务端有实际下载行为。

### 17.2 根因
- 旧实现仅依赖 `download_logs.downloaded_at` 作为下载趋势口径。
- 在部分部署中下载行为只更新 `videos.is_downloaded`（并触发 `updated_at`），未写入 `download_logs`，导致统计为空。

### 17.3 修复方案（后端服务层）
- 文件：`app/service/StatsService.php`
- 新增下载统计回退链路：
  1. `download_logs.downloaded_at`（优先）
  2. `videos.downloaded_at` + `is_downloaded=1`
  3. `videos.updated_at` + `is_downloaded=1`（兜底）
- 覆盖范围：
  - `overview()`：今日下载、昨日下载、近7天日均下载
  - `trends()`：下载趋势数组
- 接口保持兼容，新增可选调试字段：
  - `/admin.php/stats/overview` 返回 `download_metric_source`
  - `/admin.php/stats/trends` 返回 `download_source`

### 17.4 验证命令（Windows）
1. `php -l app/service/StatsService.php`
2. `php -l app/controller/admin/Stats.php`
3. 打开仪表盘接口检查趋势：
   - `GET /admin.php/stats/trends?days=30`
   - 确认 `downloaded` 不再因 `download_logs` 缺失而全为 0（有下载行为时）

### 17.5 兼容性
- 开发环境（Windows）与部署环境（Linux）均可用。
- 若未来统一落库 `download_logs`，系统会自动使用该主口径，无需改动前端。

## 18. 2026-04-14 商品管理页面白屏修复（/admin.php/product）

### 18.1 问题现象
- 进入 `商品管理` 页面出现白屏，页面无法交互。

### 18.2 根因
- `view/admin/product/index.html` 的内联脚本存在编码污染，导致字符串引号缺失，触发前端 JavaScript 语法错误并中断渲染。
- 同时存在一处模板标签损坏（`</span>` 丢失），加剧页面异常。

### 18.3 修复内容
- 修复删除确认/删除成功文案的字符串闭合，消除 JS 语法错误。
- 修复商品链接列空值分支的 `</span>` 闭合标签。
- 同步清理该页可见乱码文案（标题、列名、状态等），恢复正常中文显示。

### 18.4 验证（Windows）
1. `php -l view/admin/product/index.html`
2. 解析并检查页面内联 JS：`node --check runtime/tmp_product_inline.js`（临时文件方式）
3. `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

### 18.5 兼容性
- 仅修改前端模板，不改数据库与接口；Windows 开发与 Linux 部署兼容。

## 19. 2026-04-14 分类管理入口重构（商品管理可直接维护分类）

### 19.1 目标
- 解决“只能在达人语境看到分类，商品管理无法高效新增分类”的使用痛点。
- 将分类管理入口提升到“素材与商品”大模块，支持商品与达人分类统一管理。

### 19.2 变更范围
- `app/service/ModuleManagerService.php`
  - `category` 模块依赖调整为独立模块（不再绑定 `creator_crm`）。
  - 侧边栏分类入口从“达人运营”迁移到“素材与商品”组。
  - 分类菜单链接统一改为 `/admin.php/category`（不再固定 `type=influencer`）。
  - 分类角标统计改为全量分类数（`categories`）。
- `app/middleware/AdminAuthMiddleware.php`
  - `/category` 路由映射到 `category` 模块，确保菜单与权限模型一致。
- `view/admin/product/index.html`
  - 商品管理页顶部新增“分类管理”按钮，直达 `/admin.php/category?type=product`。
- `view/admin/category/index.html`
  - 分类页标题/面包屑根据 `type` 动态显示（商品/达人/全部）。
- `public/static/i18n/i18n.js`
  - 新增上述交互对应的 `zh/en/vi` 文案键值。

### 19.3 使用说明
1. 进入 `素材与商品` -> `分类配置`，可统一维护商品与达人分类。
2. 在 `商品管理` 页面点击 `分类管理`，可直接进入商品分类视图。
3. 分类页支持：
   - `/admin.php/category?type=product`：商品分类
   - `/admin.php/category?type=influencer`：达人分类
   - `/admin.php/category`：全部分类

### 19.4 验证（Windows 开发）
1. `php -l app/service/ModuleManagerService.php`
2. `php -l app/middleware/AdminAuthMiddleware.php`
3. `php -l view/admin/product/index.html`
4. `php -l view/admin/category/index.html`
5. `node scripts/check_i18n_keys.js --scope=all`
6. `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`

## 20. 2026-04-14 侧栏轻量化 + 主内容卡片式 Tab 子菜单

### 20.1 目标
- 保留后台顶层框架（顶部栏 + 侧栏主分组），将业务子菜单迁移为主内容区上方“卡片式 Tab”。
- 降低左侧菜单层级复杂度，减少切换时的大面积重绘感，提升页面切换流畅度。

### 20.2 设计参考
- Stitch 项目：`现代界面设计`（ID: `3897242182509863659`）
- 目标画面：`达人管理 - 卡片式 Tab 版`（Screen ID: `f7c89fd5abda4396a95db3538fb54dff`）

### 20.3 实现范围
- 文件：
  - `view/admin/common/layout.html`
  - `view/admin/product/index.html`
- 关键实现：
  - 新增主内容区顶部 Tab 容器：`#adminContentTabsWrap` / `#adminContentTabs`。
  - 新增卡片式 Tab 样式（含 active 态、badge、横向滚动与移动端缩放）。
  - 新增运行时脚本：
    - 从当前激活侧栏分组读取子菜单链接（含二级叶子菜单）；
    - 自动渲染为主内容区卡片 Tab；
    - 自动同步当前 active 状态；
    - 保留 i18n 与 Lucide 图标渲染；
    - 桌面端将侧栏子菜单收敛为“主分组入口”，点击分组跳转至该组首个子页面。

### 20.4 使用说明
1. 进入任意包含多个业务子菜单的模块页（如达人运营、增长中台、素材与商品）。
2. 主内容区标题下方会自动出现该模块的卡片式 Tab。
3. 点击 Tab 可在同模块页面间快速切换；侧栏保留主分组导航。

### 20.5 兼容性
- 仅前端布局层改造，不改接口，不改数据库。
- Windows 开发与 Linux 部署兼容。

## 21. 2026-04-14 高级图标化主侧栏（Stitch 设计落地）

### 21.1 目标
- 将后台主侧栏调整为 Stitch 设计稿中的 80px 深色图标化主侧栏，减少左侧占用空间。
- 桌面端侧栏只保留顶层模块图标；业务子菜单通过主内容区顶部横向 Tab 承接。
- 手机端保持可展开的完整抽屉菜单，保证小屏设备仍可直接访问全部功能。

### 21.2 设计参考
- Stitch 项目：`现代界面设计`（ID: `3897242182509863659`）
- 目标画面：`高级图标化主侧栏 - TikStar OPS 2.0`（Screen ID: `6e159098badf43e7beb4d3b876cca102`）
- 已下载设计资源：
  - `.codex/stitch/sidebar-design.html`
  - `.codex/stitch/sidebar-design.png`

### 21.3 实现范围
- 文件：`view/admin/common/layout.html`
- 关键实现：
  - 新增桌面端 80px icon rail 样式，品牌区改为方形 `T` 标识。
  - 顶层菜单图标使用 48px 点击区，active 态使用蓝色弱背景 + 左侧蓝色指示条。
  - 桌面端隐藏侧栏文字、角标、页脚与子菜单，保留 tooltip 辅助识别。
  - 顶层分组在桌面端点击时跳转到该分组第一个可用子页面，避免空点击。
  - 内容标题区自动生成当前分组的横向 Tab 子菜单，支持 active 状态、i18n 与 Lucide 图标。
  - 移动端保持顶部栏 + 侧栏抽屉模式，展开后显示完整菜单与子菜单。
  - 商品管理页补充手机端标题/操作按钮换行规则，避免中文标题被压缩成逐字竖排。

### 21.4 使用说明
1. 桌面端进入后台后，左侧显示图标化主侧栏；悬停图标可看到模块名称。
2. 点击顶层模块图标会进入该模块第一个页面。
3. 当前模块的子页面入口显示在页面标题下方的横向 Tab 中。
4. 手机端点击顶部菜单按钮打开侧栏，可直接查看完整文字菜单。

### 21.5 兼容性
- 仅修改前端布局层，不改接口、不改数据库。
- Windows 开发与 Linux 部署通用。

## 22. 2026-04-14 主界面 Tab 菜单可见性修复

### 22.1 问题现象
- `/admin.php/influencer` 等多个主界面没有显示顶部 Tab 菜单，图标侧栏收起子菜单后无法切换到同组其他页面。
- 影响页面包括隐藏全局 `.content-header` 的页面，例如达人名录、商品管理、寻款、终端、系统等页面。

### 22.2 根因
- 上一版 Tab 容器挂在 `.content-header` 内。
- 多个业务页面为了使用自定义标题区，会在页面级样式中设置 `.content-header { display: none; }`，导致 Tab 容器也被隐藏。
- 仅依赖服务端 active 标记时，部分页面也可能无法可靠反推当前所属分组。

### 22.3 修复内容
- 文件：`view/admin/common/layout.html`
- 将 `#adminContentTabsWrap` 从 `.content-header` 内提升到 `.content-header` 与 `.content` 之间的全局容器 `#adminContentTabsShell`。
- 新增 `is-visible` 控制类，只有当前分组存在子菜单时才显示 Tab 区。
- 增强前端匹配逻辑：
  - 优先使用 active 子菜单；
  - 其次按当前 URL 匹配侧栏子菜单链接；
  - 自动为匹配项补 active 状态；
  - 渲染 Tab 时按 URL 兜底设置当前 active Tab。
- 修复桌面图标栏品牌区宽度，强制 `brand-link` 与 `sidebar` 锁定为 80px，避免品牌标识覆盖内容区 Tab。

### 22.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. Playwright 批量访问以下页面，确认 `#adminContentTabsShell` 可见且存在 active Tab：
   - `/admin.php/influencer`
   - `/admin.php/outreach_workspace`
   - `/admin.php/product`
   - `/admin.php/product_search`
   - `/admin.php/industry_trend`
   - `/admin.php/platform`
   - `/admin.php/settings`
3. 截图验证：
   - `.codex/stitch/verify-tabs-influencer.png`

### 22.5 兼容性
- 仅修改全局布局模板，不改接口、不改数据库。
- Windows 开发与 Linux 部署通用。

## 23. 2026-04-14 精致胶囊 Tab 与侧栏 hover 提示

### 23.1 目标
- 根据 Stitch “精致 Tab 版”设计稿，将主内容顶部 Tab 从卡片式按钮调整为精致胶囊导航。
- 左侧图标化主侧栏在 hover 时显示文字提示，避免只看图标无法判断模块含义。

### 23.2 设计参考
- Stitch 项目：`现代界面设计`（ID: `3897242182509863659`）
- 目标画面：
  - `达人管理 - 精致 Tab 版`（Screen ID: `0a58aa8a091f4a5ea8e9a4727a6d9723`）
  - `利润中心 - 精致 Tab 版`（Screen ID: `7a8219286ed74e83b14ddfd8885a9338`）
- 已下载设计资源：
  - `.codex/stitch/tab-influencer-design.html`
  - `.codex/stitch/tab-influencer-design.png`
  - `.codex/stitch/tab-profit-design.html`
  - `.codex/stitch/tab-profit-design.png`

### 23.3 修复内容
- 文件：`view/admin/common/layout.html`
- Tab 菜单：
  - 外层改为浅灰半透明胶囊容器，圆角 `999px`，带轻边框与轻阴影。
  - active 项改为白底、品牌蓝文字、轻阴影。
  - 非 active 项改为透明底、灰色文字，hover 时轻白底。
  - 默认隐藏 Tab 内图标，保留纯文字胶囊结构，与 Stitch 设计稿一致。
- 侧栏提示：
  - 桌面端 80px 图标栏增加 `data-tooltip` 伪元素提示。
  - hover 时在图标右侧显示深色小浮层，含箭头、圆角、阴影。
  - 桌面端侧栏提升层级并允许横向溢出，避免 tooltip 被裁剪。

### 23.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. Playwright 验证：
   - `/admin.php/product_search`：Tab 为胶囊容器，active 为白底蓝字，Tab 图标隐藏。
   - hover 左侧图标：右侧显示模块文字提示。
   - `/admin.php/influencer`：Tab 数量与 active 状态正常。
3. 截图验证：
   - `.codex/stitch/verify-refined-tabs-product-search.png`
   - `.codex/stitch/verify-refined-tabs-influencer.png`
   - `.codex/stitch/verify-sidebar-tooltip.png`

### 23.5 兼容性
- 仅修改全局布局模板，不改接口、不改数据库。
- Windows 开发与 Linux 部署通用。

## 24. 2026-04-14 内容区标题与 Tab 视觉统一

### 24.1 问题现象
- 部分页面（如 `/admin.php/industry_trend`）使用全局 `.content-header` 显示标题，部分页面（如 `/admin.php/offline_order`、`/admin.php/product_search`）在内容卡片内自定义标题并隐藏全局标题。
- Tab 菜单独立显示在内容区外层，导致页面之间出现标题位置、字号、背景层级和间距不一致。

### 24.2 修复范围
- 文件：`view/admin/common/layout.html`

### 24.3 实现内容
- 将“当前模块标题 + 所属一级菜单 + 子 Tab 菜单”统一收进全局 `admin-workspace-shell` 工作区标题壳。
- Tab 可见时自动给 `body` 增加 `admin-shell-tabs-active`，并隐藏旧 `.content-header`，避免同一页面出现两个标题体系。
- 对已有 `admin-modern-card > admin-header-actions` 做兼容收敛：
  - 隐藏重复的卡片内面包屑；
  - 隐藏重复的卡片内标题文字；
  - 保留右侧业务操作按钮（如导出、导入、筛选等）。
- 统一内容区背景层级：
  - 页面背景改为稳定浅色线性背景；
  - `admin-page-container` 不再额外套灰底卡片；
  - `admin-modern-card` 统一白底、12px 圆角、轻边框与轻阴影。

### 24.4 使用说明
1. 进入任意存在子菜单 Tab 的后台页面。
2. 页面顶部统一显示所属一级菜单、小标题和胶囊 Tab。
3. 业务内容统一从白色内容卡片开始，页面内不再重复显示同一标题。

### 24.5 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. Playwright 对比验证：
   - `/admin.php/offline_order`
   - `/admin.php/industry_trend`
   - `/admin.php/product_search`
3. 确认三类页面的标题位置、Tab 位置、字号和内容卡片背景一致。

## 25. 2026-04-14 单行标题栏与仪表盘标题收敛

### 25.1 问题现象
- 仪表盘页面同时显示全局标题“仪表盘”和页面内部大标题“系统指挥中心”，标题层级显得突兀。
- 全局 Tab 区为上下结构，和 Stitch 设计稿中“左侧模块名 + 中间胶囊 Tab + 右侧操作”的单行顶部栏不一致。

### 25.2 修复范围
- 文件：`view/admin/common/layout.html`

### 25.3 实现内容
- 将 `admin-workspace-shell` 调整为单行网格结构：
  - 左侧：模块图标 + 模块标题；
  - 中间：胶囊 Tab 菜单，居中显示；
  - 右侧：承接原 `page_actions` 的操作区。
- 标题壳改为所有后台页面常驻：
  - 有子菜单时显示 Tab；
  - 无子菜单时只显示当前页面标题，并隐藏旧 `.content-header`。
- 仪表盘在无 Tab 状态下隐藏页面内部大标题，保留同步状态与操作按钮，避免双标题。

### 25.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. Playwright 验证：
   - `/admin.php`：只显示一个统一标题栏，仪表盘内部不再重复大标题；
   - `/admin.php/profit_center` 或同类页面：Tab 与设计稿一样在同一行居中；
   - 移动端标题栏不横向溢出。

## 26. 2026-04-14 单行标题栏语言与退出入口补齐

### 26.1 问题现象
- 桌面端采用图标化侧栏后，旧顶部栏被隐藏。
- 语言切换和退出登录原本分别在旧顶部栏和侧栏底部，导致新单行标题栏下桌面端找不到入口。

### 26.2 修复范围
- 文件：`view/admin/common/layout.html`

### 26.3 实现内容
- 在 `admin-workspace-actions` 右侧操作区增加常驻入口：
  - 当前登录用户名；
  - 语言切换：`ZH / EN / VI`；
  - 退出登录按钮。
- 新增独立按钮 ID：
  - `btnLangZhShell`
  - `btnLangEnShell`
  - `btnLangViShell`
  - `btnAdminLogoutShell`
- 复用原有 `AppI18n` 与退出登录逻辑，避免新增后端接口。
- 保留旧移动端顶栏和侧栏底部入口，保证手机抽屉菜单仍可操作。

### 26.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. `node scripts/check_i18n_keys.js --scope=all`
3. Playwright 验证标题栏右侧显示语言切换与退出按钮，点击语言按钮可切换 active 状态。

## 27. 2026-04-14 后台全局视觉体系统一

### 27.1 问题现象
- 各后台页面存在独立页面壳和局部卡片样式，例如 `.tp-ep-wrap`、`.tp-cat-wrap`、`.ext-wrap`、`.admin-cache-card`、`.db-card` 等，导致背景、卡片圆角、阴影和内边距不一致。
- Bootstrap 与 Element Plus 的按钮、表格、表单、弹窗样式分别被页面覆盖，视觉层级和颜色体系不统一。
- 部分页面标题、面包屑、提示文字字号和颜色差异明显，影响后台整体一致性。

### 27.2 修复范围
- 文件：`view/admin/common/layout.html`

### 27.3 实现内容
- 新增 OPS 2.0 全局视觉变量：
  - 页面背景、卡片白底、文字色、弱文字色、边框、阴影、圆角；
  - 主按钮、成功、警告、危险、信息按钮语义色。
- 统一常见页面壳：
  - `.admin-page-container`
  - `.tp-ep-wrap`
  - `.tp-cat-wrap`
  - `.tp-mt-wrap`
  - `.ext-wrap`
  - `.auto-dm-wrap`
  - `.dashboard-shell`
- 统一常见内容卡片和统计卡片：
  - `admin-modern-card`
  - `admin-cat-card`
  - `admin-cache-card`
  - `ext-card`
  - `auto-dm-card`
  - `db-card`
  - `stat-card`
  - `admin-inf-card`、`admin-product-card`、`admin-dev-card`、`admin-dist-card`、`admin-log-card`、`admin-mt-card`、`admin-plat-card`
  - `pc-panel`、`pc-kpi-card`、`pc-table-panel`、`pc-summary-card`
- 统一 Bootstrap 与 Element Plus 的按钮、输入框、选择框、表格、标签、分页、弹窗和遮罩层风格。
- 弹窗统一为白底、轻边框、轻阴影、8/12px 圆角体系，按钮使用同一语义色板。

### 27.4 使用说明
1. 新增后台页面时优先使用公共 `admin-page-container` + `admin-modern-card`。
2. 页面内不要再定义整页灰底、独立大圆角卡片、独立按钮色板或弹窗色板。
3. 业务组件需要语义按钮时使用标准类型：
   - Bootstrap：`btn-primary`、`btn-success`、`btn-warning`、`btn-danger`、`btn-secondary`
   - Element Plus：`type="primary|success|warning|danger|info"`

### 27.5 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. `node scripts/check_i18n_keys.js --scope=all`
3. `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`
4. Playwright 抽样验证：
   - `/admin.php`
   - `/admin.php/influencer`
   - `/admin.php/product_search`
   - `/admin.php/profit_center`
   - `/admin.php/cache`

## 28. 2026-04-14 表格链接与标签色彩降噪

### 28.1 问题现象
- 表格内链接、`link` 按钮、`primary plain` 按钮和默认 `el-tag` 都使用蓝色，达人名录等页面出现大面积蓝字。
- 蓝色过多导致主操作、联系人链接、状态标签和普通文本之间层级不清晰。

### 28.2 修复范围
- 文件：`view/admin/common/layout.html`

### 28.3 实现内容
- 保留主按钮和当前选中态使用蓝色。
- 表格内普通链接默认改为深灰，hover 时才使用蓝色。
- WhatsApp / Zalo 链接使用低饱和语义色区分，避免整列都是蓝色。
- `primary plain` 按钮默认改为白底深灰文字，hover 时再体现蓝色。
- 默认 `el-tag` 改为灰色标签；`success / warning / danger / info` 分别使用低饱和绿、琥珀、红、灰。

### 28.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. Playwright 或浏览器检查 `/admin.php/influencer`：
   - 表格正文不再大面积蓝字；
   - 主按钮仍保持蓝色；
   - 标签和链接有语义色但不过度抢眼。

## 29. 2026-04-14 后台可见性与仪表盘色彩修复

### 29.1 问题现象
- 仪表盘“下载率”高亮卡被全局卡片白底覆盖后，内部文字仍使用白色，导致看起来像空白卡片。
- `/admin.php/auto_dm` 自动私信页面存在 Vue slot 空参数解构问题，页面渲染失败。
- `/admin.php/ops_center` 嵌入 iframe 后，AdminLTE IFrame 插件读取空配置并抛出 `autoIframeMode` 错误。

### 29.2 修复范围
- `view/admin/common/layout.html`
- `view/admin/auto_dm/index.html`

### 29.3 实现内容
- 仪表盘高亮 KPI 卡改为白底浅灰渐变、左侧蓝色强调线，文字统一深色，避免白底白字。
- 全局正文、表格、表单、弹窗文字进一步统一为深灰体系；弱信息使用低对比灰色。
- 按钮高度、间距、表格操作按钮间距统一，移动端按钮触控高度不低于 36px。
- 自动私信表格 slot 改为 `rowOf(scope)` 安全读取，避免空 slot 参数导致整页报错。
- 公共布局写入 AdminLTE IFrame 默认配置，关闭自动 iframe 模式，避免运维中心 iframe 子页面报错。

### 29.4 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. `php -l view/admin/auto_dm/index.html`
3. `node scripts/check_i18n_keys.js --scope=all`
4. `powershell -ExecutionPolicy Bypass -File scripts/ops2_smoke.ps1`
5. Playwright 爬取侧栏页面，确认无空白页面和前端报错。

## 30. 2026-04-14 商品链接点击复制

### 30.1 需求说明
- `/admin.php/product` 商品列表中的“商品链接”需要支持点击复制，减少人工选中 URL 的操作。
- 复制行为不能影响原有打开商品链接的使用场景。

### 30.2 修复范围
- `view/admin/product/index.html`

### 30.3 实现内容
- 商品链接列改为“点击链接文本复制 URL”。
- 右侧保留“打开”按钮，用于在新窗口查看商品链接。
- 复制逻辑优先使用浏览器 Clipboard API，非安全上下文或旧浏览器回退到 textarea + `execCommand('copy')`。
- 链接文本使用深灰和低饱和 hover 色，保持后台表格色彩降噪后的统一风格。

### 30.4 验证（Windows）
1. `php -l view/admin/product/index.html`
2. `node scripts/check_i18n_keys.js --scope=all`
3. 浏览器访问 `/admin.php/product`，点击商品链接文本应提示“已复制”，点击“打开”应新窗口打开原 URL。

## 15. 2026-04-17 商机寻款直播分析（按店铺预置商品库）

### 15.1 目标
- 采用“两段式”流程：
- 先维护店铺商品库（款式编号 + 商品图片）。
- 每场直播仅导入指标表，系统从商品标题提取款号并回连店铺商品库。
- 支持历史汇总、跨店铺导入、爆款分层（大爆款/畅销款/小爆款）。

### 15.2 数据表（新增）
- `growth_store_product_catalog`：店铺商品主数据（`tenant_id, store_id, style_code, product_name, image_url, status`）。
- `growth_live_sessions`：直播场次主表（店铺、日期、场次名、文件信息、导入作业）。
- `growth_live_product_metrics`：直播商品事实表（原始商品、指标、提取款号、匹配状态）。
- `growth_live_style_agg`：款式聚合快照（店铺榜/全局榜，场次/7天/30天/全历史）。

### 15.3 迁移脚本
- Windows：`php database\run_migration_live_product_analysis.php`
- Linux：`php database/run_migration_live_product_analysis.php`

### 15.4 后台页面与接口
- 页面：`/admin.php/product_search/live`
- 店铺列表：`GET /admin.php/product_search/live/storesJson`
- 店铺管理列表：`GET /admin.php/product_search/live/store/listJson`
- 店铺新增/编辑：`POST /admin.php/product_search/live/store/save`
- 店铺删除：`POST /admin.php/product_search/live/store/delete`
- 店铺商品库查询：`GET /admin.php/product_search/live/catalog/listJson`
- 店铺商品库导入：`POST /admin.php/product_search/live/catalog/import`
- 直播场次导入：`POST /admin.php/product_search/live/import/create`
- 场次列表：`GET /admin.php/product_search/live/sessionsJson`
- 未匹配列表：`GET /admin.php/product_search/live/unmatchedJson`
- 未匹配绑定：`POST /admin.php/product_search/live/unmatched/bind`
- 爆款榜：`GET /admin.php/product_search/live/rankingsJson`
- 款式详情：`GET /admin.php/product_search/live/styles/{style_code}`
- 款式图片更新：`POST /admin.php/product_search/live/styles/{style_code}/image`

### 15.5 导入模板建议字段
1. 店铺商品库模板（至少包含款号）：
- 必填：`style_code`（或中文别名：款号/款式编号/货号）
- 可选：`product_name`、`image_url`

2. 直播指标模板（每场直播导入）：
- 建议：`product_id`、`product_name`、`gmv`、`impressions`、`clicks`、`add_to_cart_count`、`orders`
- 中文列头兼容：成交额/销售额、曝光、点击、加购、订单数、支付转化率、点击率等

### 15.6 匹配与分层口径
- 匹配规则：商品标题提取款号（默认支持 `字母-数字`，如 `A-21`），按 `store_id + style_code` 精确匹配商品库。
- 未匹配处理：进入未匹配列表，支持批量手动绑定并触发回算。
- 综合分：`GMV 40% + CTR 20% + 加购率 20% + 支付转化率 20%`。
- 分层规则：
- 大爆款：`>= P90`
- 畅销款：`P70 ~ P90`
- 小爆款：`P50 ~ P70`

### 15.7 使用步骤
1. 打开“直播选款分析”，先进入“店铺管理”新增/维护店铺。
2. 在页面顶部“选择店铺”切换到目标店铺。
3. 在“店铺商品库管理”导入该店铺商品库（款号与图片）。
4. 在“直播场次导入”按场次导入直播指标表。
5. 在“未匹配处理”绑定未命中商品。
6. 在“爆款榜”按窗口查看店铺榜或全局榜，进入款式详情查看趋势并维护图片。

### 15.8 行为说明与异常处理
- 同店铺同场次同商品重复导入为覆盖更新（不重复累计）。
- 重新导入同场次时，会删除本次文件中不存在的旧商品行，保证“覆盖”语义。
- 绑定未匹配后，会同步刷新场次匹配统计并重建聚合快照。
- 店铺删除前会校验关联数据（商品库、直播场次、利润中心记录）；存在关联时拒绝删除，避免历史口径被破坏。
- 店铺新增或编辑后，直播分析页会自动刷新店铺选择器并联动后续导入分析。
- 若上传失败，优先检查：文件格式、字段名、PHP 上传大小限制、Nginx/Apache 请求体限制。
- Windows 开发与 Linux 部署统一使用 UTF-8、正则提取与日期口径，避免路径分隔符差异导致行为不一致。

### 15.18 2026-04-18 路由修复（店铺列表加载失败）
- 问题现象：直播选款分析页“店铺管理”出现“加载店铺列表失败”，接口返回 HTML 而非 JSON。
- 根因：`config/route.php` 使用非完整匹配（`route_complete_match=false`），`route/admin.php` 中 `GET /product_search/live` 在子接口前声明，导致 `/product_search/live/store/listJson` 被提前命中页面路由。
- 修复措施：将 Route::get('product_search/live', ...) 调整到该路由组末尾，确保 store/listJson、storesJson、catalog/listJson 等接口优先匹配。
- 影响范围：仅调整匹配顺序，不改动业务逻辑与数据库结构。

### 15.19 回归验证（Windows）
1. `php -l route/admin.php`
2. `php -l app/controller/admin/ProductSearchLive.php`
3. `curl -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest" -H "Cookie: PHPSESSID=<session>" "http://127.0.0.1:8011/admin.php/product_search/live/store/listJson"`
- 期望结果：返回 `{"code":0,"data":{"items":[...]}}`，不再返回 HTML 页面内容。




## 20. 2026-04-18 直播商品库 Excel 导入兼容增强

- 修复 `LiveStyleAnalysisService` 在 xlsx 读取时调用 `Worksheet::getCellByColumnAndRow()` 导致的导入失败，改为坐标方式读取（Windows 开发与 Linux 部署均可用）。
- `ProductSearchLive` 商品库导入新增“商品库专用解析链路”：
  - 表头兼容：`编号/款号/货号/款式编号/产品编号/商品编号/商品编码`。
  - 图片兼容：`图片/商品图片/主图/图/图片路径/图片链接`。
  - 备注兼容：`爆款类型/备注/分类`，导入到 `growth_store_product_catalog.notes`。
- 复用“款式检索”Excel 解析能力，支持 Office 365 `DISPIMG`/嵌入图提取，自动保存到 `/uploads/products/` 并写入 `image_url`。
- 导入解析顺序：优先走款式检索 Excel 解析器；如遇异常自动回退通用解析器，保证任务可继续。
- 已用样例文件 `1、耳环产品编号.xlsx` 验证：可解析 154 行，能正确识别 `A-1/A-5/...` 编号并提取图片路径。

## 21. 2026-04-18 导入稳定性兜底（缺少 Torch/Python 依赖）

- 问题场景：`款式检索` 导入依赖 Python + Torch 提取图片向量，若环境缺少 `torch`，会出现 `embed JSON invalid / ModuleNotFoundError: No module named torch`，导致行处理失败。
- 修复：`ProductStyleEmbeddingService::embedFile()` 增加降级向量机制。
  - 当脚本缺失、子进程无输出、JSON 解析失败时，自动按图片内容生成稳定向量（默认 128 维）。
  - 默认开启：`product_search.embedding_fallback_enabled = true`（未配置时即开启）。
  - 维度可配：`product_search.embedding_fallback_dims`（16~1024）。
- 结果：导入链路不再因 Torch 缺失整批报错，先保证数据可入库；后续补齐 Python/Torch 后可恢复高质量特征。
- 兼容性补充：直播商品库导入中将 `str_contains` 改为 `strpos`，并将 `catalogImport` 的 `createJob` 纳入异常捕获，确保接口持续返回 JSON 错误体而非 HTML 崩溃页。

## 16. 2026-04-18 商品库大文件导入（413）修复

### 16.1 问题根因
- 线上与本地 Web 导入 `POST /admin.php/product_search/live/catalog/import` 出现 HTTP 413（Request Entity Too Large）。
- 该错误发生在 Web 服务器层（常见于 Nginx `client_max_body_size`），请求未到达业务控制器。

### 16.2 实施方案
- 新增分片导入接口：`POST /admin.php/product_search/live/catalog/importChunk`。
- 前端商品库导入改为 512KB 分片上传，逐片提交并显示进度。
- 后端分片落盘后按顺序合并，再复用原有 `DataImportService + LiveStyleAnalysisService` 导入流程，保证口径一致。
- 合并与导入完成后自动清理分片临时目录。

### 16.3 兼容与提示
- 旧接口 `catalog/import` 保留，兼容小文件与历史调用。
- 前端在请求返回 HTTP 413 时给出明确提示，避免只显示通用“操作失败”。

### 16.4 结果
- 即使 Web 层单请求体限制较低，也可通过分片上传完成 100MB+ 商品库文件导入。
- 导入成功后仍返回统一结构：`job_id / store_id / result(inserted,updated,skipped)`。

## 17. 2026-04-18 直播商品库管理补充（编辑/删除/批量删除）

### 17.1 后端接口新增
- `POST /admin.php/product_search/live/catalog/save`：新增或编辑商品库记录。
- `POST /admin.php/product_search/live/catalog/delete`：删除单条商品库记录。
- `POST /admin.php/product_search/live/catalog/batchDelete`：按勾选 ID 批量删除。

### 17.2 前端交互补充
- 商品库页签新增：`新增商品`、`批量删除`、行内`编辑/删除`。
- 表格新增多选列，支持批量操作。
- 新增商品编辑弹窗：款式编号、商品名、图片 URL、状态、备注。

### 17.3 校验规则
- 必须先选择店铺。
- 款式编号必填。
- 同租户同店铺下，款式编号唯一。

## 18. 2026-04-18 直播场次管理补充（编辑/删除/批量删除）

### 18.1 后端接口新增
- `POST /admin.php/product_search/live/session/save`：编辑场次日期/名称，并同步更新该场次指标行的日期与场次名。
- `POST /admin.php/product_search/live/session/delete`：删除单个场次，并级联删除 `growth_live_product_metrics` 对应明细。
- `POST /admin.php/product_search/live/session/batchDelete`：批量删除场次与对应指标明细。

### 18.2 数据一致性处理
- 场次编辑后自动重算 `total_rows/matched_rows/unmatched_rows`。
- 场次编辑或删除后自动触发快照重建（`rebuildSnapshotsForAnchor`），保证榜单与汇总口径一致。

### 18.3 前端交互补充
- 直播场次表新增：多选框、行内“编辑/删除”、顶部“批量删除场次”按钮。
- 新增“编辑直播场次”弹窗（日期、场次名称）。

## 31. 2026-04-18 侧边栏菜单名称恢复 + 爆款榜可读性优化

### 31.1 侧边栏（桌面端）
- 调整 `view/admin/common/layout.html`，桌面端侧栏从图标 rail 改为“图标 + 菜单名称”的固定宽度样式。
- 统一主菜单高亮为背景色块，移除左侧竖向蓝条，降低视觉噪音。
- 保留“仅主菜单”展示：桌面端继续隐藏子菜单，子功能仍通过内容区上方 Tab 切换。
- 增加桌面端状态纠偏：当存在 `sidebar-collapse` 残留类名时自动清理，避免菜单文字被折叠隐藏。

### 31.2 直播选款分析 - 爆款榜
- 调整 `view/admin/product_search/live.html` 与 `public/static/admin/live_style_analysis.js`：
  - 榜单筛选区改为分组容器（边框+浅底），筛选控件尺寸与间距统一。
  - KPI 卡片提升层级（统一高度、字号、留白），锚点信息改为独立文字规格。
  - 榜单表格增加横向容器、表头浅底，关键列固定在左侧（排名/款号/图片/分层），操作列固定右侧。
  - 数值列统一右对齐，`GMV` 使用数值格式化输出，减少阅读负担。
- 页面脚本版本号升级：`live_style_analysis.js?v=20260418_live_style_catalog_ops3`，避免缓存导致“看不到更新”。

### 31.3 验证（Windows）
1. `php -l view/admin/common/layout.html`
2. `node --check public/static/admin/live_style_analysis.js`
3. 浏览器验证：
- `/admin.php/product_search/live`：爆款榜筛选/卡片/表格布局正常，关键列固定后无需频繁横向拖动。
- 任意后台页面：侧边栏显示主菜单文字，选中态为背景高亮，无左侧竖线。

### 31.4 2026-04-18 爆款榜右侧留白修复
- 问题：`/admin.php/product_search/live` 爆款榜在窄屏下出现右侧空白。
- 原因：外层横向滚动容器与 `el-table` 固定列叠加，导致布局计算留白。
- 修复：
  - 移除榜单表的固定列（left/right fixed）。
  - 表格滚动改为 Element Plus 内部滚动，外层容器取消 `overflow-x:auto`。
- 影响：仅前端展示层，不改接口与数据口径。

### 31.5 2026-04-18 侧边栏高亮与宽度微调
- 根据最新 UI 反馈继续优化 `view/admin/common/layout.html`：
  - 侧边栏宽度从 `228px` 收窄到 `214px`。
  - 菜单 hover 态：文字和图标同步高亮（不再仅背景变化）。
  - 菜单选中态：文字与图标使用品牌高亮色，提升可辨识度。
- 验证：`php -l view/admin/common/layout.html`

### 31.6 2026-04-18 直播选款分析图片点击放大
- 页面：`/admin.php/product_search/live`
- 改动：`public/static/admin/live_style_analysis.js`
  - 商品库表、爆款榜表、详情抽屉中的图片均增加 Element Plus 预览参数：
    - `:preview-src-list="[scope.row.image_url]"`
    - `:preview-teleported="true"`
  - 支持点击图片打开大图预览。
- 改动：`view/admin/product_search/live.html`
  - 图片 hover 光标改为 `zoom-in`，增强可点击放大提示。
  - 脚本版本号更新为 `live_style_catalog_ops4`，避免缓存导致旧页面不生效。
- 验证：
  1. `node --check public/static/admin/live_style_analysis.js`
  2. `php -l view/admin/product_search/live.html`

### 31.7 2026-04-18 移动端侧栏子菜单可折叠修复
- 问题：手机端侧栏中一级菜单的子项全部常驻展开，无法收起。
- 根因：`view/admin/common/layout.html` 在 `@media (max-width: 992px)` 下将 `.admin-sidenav-subwrap.collapse` / `.admin-sidenav-sub2wrap.collapse` 强制为 `display:block !important`。
- 修复：
  - 移除移动端对 collapse 的强制展开样式，恢复 Bootstrap collapse 原生折叠行为。
  - 恢复移动端菜单箭头显示，便于识别“可展开/收起”。
- 验证：`php -l view/admin/common/layout.html`

## 2026-04-18 支付转化率解析修复（直播选款分析）

- 修复导入解析：无百分号且值为 `1` 时按 `1%` 处理，避免被误判为 `100%`。
- 修复计算口径：`pay_cvr` 优先按 `orders_count / clicks` 计算；仅在无点击数据时回退 `payment_rate`。
- 修复聚合口径：榜单快照 `growth_live_style_agg.pay_cvr` 改为 `SUM(orders_count) / SUM(clicks)`，不再沿用历史 `m.pay_cvr` 加权。
- 增加快照自愈：若检测到 `orders_sum < clicks_sum` 但 `pay_cvr≈100%` 的异常快照，查询时自动重建。
- 影响接口：`/admin.php/product_search/live/rankingsJson`、`/admin.php/product_search/live/unmatchedJson`。
- 开发验证：
  - `php -l app/service/LiveStyleAnalysisService.php`
  - `php -l app/controller/admin/ProductSearchLive.php`
## 2026-04-18 直播选款详情空数据修复

- 修复前端详情请求：在“全局榜单”下点击详情不再携带当前店铺 `store_id` 过滤，避免误查空。
- 修复后端款号匹配：`styleDetailJson` 对 `A109 / A-109 / A_109` 做统一匹配（去连接符后比对），兼容历史数据格式。
- 同步增强：`normalizeCatalogStyleCode` 统一大写并标准化连接符，减少款号格式漂移。
- 兼容路由参数注入差异：`styleDetailJson/styleImageUpdate` 优先读取 `style_code` 路由变量，并回退请求参数，避免款号丢失导致详情全空。
- 前端脚本版本升级为 `20260418_live_style_catalog_ops7`，确保浏览器拉取最新逻辑。
- 验证：
  - `php -l app/controller/admin/ProductSearchLive.php`
  - `node --check public/static/admin/live_style_analysis.js`
- 详情接口增强容错：款号改为候选集合匹配（`A-201/A201/A_201`），并在店铺过滤无结果时自动回退全店铺查询，避免因店铺维度异常导致详情全空。
- 前端详情请求与图片更新请求增加 `style_code` 参数冗余传递（除路径参数外），规避路由变量注入异常。
- 脚本版本升级为 `20260418_live_style_catalog_ops8`。
- 进一步增强款号归一化：统一处理 `A201/A-201/A_201` 及常见 Unicode 横杠字符（en/em dash 等），避免详情匹配漏数。
- 新增详情调试日志：`live_style_detail_debug` 记录 `tenant/store/style/candidate/product_count`，用于排查线上“详情空数据”。
- 前端脚本版本升级为 `20260418_live_style_catalog_ops9`。

## 2026-04-18 直播选款详情接口稳态修复（路由与错误兜底）

- 问题现象：点击“详情”偶发提示“加载款式详情失败”，且日志未稳定出现对应详情请求。
- 根因：在 `route_complete_match=false` 场景下，`/styles/{style_code}` 这种路径参数接口存在命中不稳定风险（包含款号连接符时更容易受路由匹配影响）。
- 修复方案：
  - 新增稳定接口（query 方式，不依赖 path 参数解析）：
    - `GET /admin.php/product_search/live/styleDetailJson`
    - `POST /admin.php/product_search/live/styleImageUpdate`
  - 前端 `public/static/admin/live_style_analysis.js` 详情与图片更新请求统一切换到以上稳定接口。
  - 保留原路径接口 `styles/{style_code}` 与 `styles/{style_code}/image` 作为兼容入口。
  - `styleDetailJson` 新增全方法异常兜底，统一返回 JSON 错误体，避免前端收到 HTML 异常页。
  - 详情调试日志改为 `JSON_PARTIAL_OUTPUT_ON_ERROR`，避免因个别非法字符导致日志 JSON 为空。
- 缓存策略：页面脚本版本升级为 `20260418_live_style_catalog_ops11`，并建议清理 `runtime/cache` 与 `runtime/temp` 后回归。
- 验证（Windows）：
  1. `php -l route/admin.php`
  2. `php -l app/controller/admin/ProductSearchLive.php`
  3. `node --check public/static/admin/live_style_analysis.js`

## 2026-04-18 直播选款详情币种显示与人民币换算

- 问题：款式详情弹窗仅显示原始 GMV 数值，未体现店铺已配置币种，也没有人民币折算；同时表格列固定宽导致大屏空白较多，排版可读性较差。
- 后端改造（`ProductSearchLive@styleDetailJson`）：
  - 增加店铺币种映射：从 `growth_profit_accounts.default_gmv_currency`（回退 `account_currency`）读取 `store_id -> gmv_currency`。
  - 无账号配置时，直播分析页店铺币种显示默认回退 `VND`（越南站场景），避免误按 CNY 直读。
  - 返回新增字段：
    - `currency.gmv_currency / gmv_currency_label / base_currency / fx_status`
    - `summary.gmv_cny_sum`
    - `trend[].gmv_currency / gmv_cny_sum / fx_status`
  - 汇率转换使用 `FxRateService`，按 `session_date` 折算 CNY，并在明细接口内部做币种+日期缓存，降低重复查汇率开销。
- 前端改造（`live_style_analysis.js` + `live.html`）：
  - 款式详情 KPI 改为 5 卡：场次覆盖、GMV原币、折合CNY、CTR、加购率。
  - 趋势表新增 `GMV原币` 与 `GMV(CNY)` 双列展示，金额格式化为千分位 + 币种后缀。
  - 店铺选择项和店铺管理列表显示金额币种（如 `VND/USD/CNY`）。
  - 弹窗工具栏改为栅格布局，减少控件拥挤；表格列改为 `min-width`，避免大屏左挤右空。
  - 页面脚本版本升级：`live_style_catalog_ops12`。
- 验证（Windows）：
  1. `php -l app/controller/admin/ProductSearchLive.php`
  2. `php -l view/admin/product_search/live.html`
  3. `node --check public/static/admin/live_style_analysis.js`

## 2026-04-18 店铺默认 GMV 币种 + 菜单重构（决策版落地）

### 1) 店铺级默认 GMV 币种

- 新增增量迁移脚本：`database/run_migration_profit_store_currency.php`
  - 为 `growth_profit_stores` 增加字段：`default_gmv_currency CHAR(3) NOT NULL DEFAULT 'VND'`
  - 历史数据回填规则：优先使用店铺现有账号币种（`growth_profit_accounts.default_gmv_currency`，回退 `account_currency`），无则 `VND`
  - 同时规范脏值（空值、非 3 位币种）为 `VND`
- 运维自动迁移清单增加该脚本：`app/service/OpsMaintenanceService.php`

### 2) 后端接口字段扩展（向后兼容）

- `GET /admin.php/profit_center/storeListJson`
  - 返回新增：`default_gmv_currency`
- `POST /admin.php/profit_center/storeSave`
  - 入参支持：`default_gmv_currency`
  - 保存店铺后自动同步该店铺账号：`growth_profit_accounts.default_gmv_currency`
- `GET /admin.php/product_search/live/store/listJson`
  - 返回新增：`default_gmv_currency`（保留 `gmv_currency` 兼容旧前端）
- `POST /admin.php/product_search/live/store/save`
  - 入参支持：`default_gmv_currency`
  - 保存店铺后自动同步该店铺账号默认 GMV 币种
- 账号保存逻辑补充：
  - `ProfitCenter::accountSave` 在未显式传入 `default_gmv_currency` 时，默认继承店铺币种

### 3) 前端改造

- 利润中心 `view/admin/profit_center/index.html`
  - 店铺管理弹窗新增「店铺默认GMV币种」下拉
  - 保存时随店铺一起提交，保存后店铺/账号列表联动刷新
- 直播选款分析 `public/static/admin/live_style_analysis.js`
  - 店铺管理弹窗新增「GMV币种」下拉
  - 店铺列表显示字段改为店铺默认币种
  - 保存店铺时提交 `default_gmv_currency`

### 4) 菜单重构（仅重排，不改路由）

- `app/service/ModuleManagerService.php` 按业务流程调整为：
  - `概览`：仪表盘
  - `选品经营`：款式检索、直播选款分析、线下预定、利润中心
  - `增长分析`：行业趋势、竞品分析、广告情报、数据导入
  - `达人运营`：达人名录、外联工作台、自动私信、移动设备、寄样管理、话术模板、达人链路
  - `素材商品`：视频素材、批量上传、商品管理、分类配置
  - `系统终端`：平台、设备、系统设置、运维中心、用户管理、模块管理（后四项仍受 super_admin 控制）

### 5) i18n 三语同步

- 更新文件：
  - `public/static/i18n/i18n.js`
  - `public/static/i18n/i18n.ops2.js`
- 新增/调整菜单 key（并保留旧 key 文案兼容）：
  - `admin.menu.groupSelectionOps(_Menu)`
  - `admin.menu.groupGrowthAnalysis(_Menu)`
  - `admin.menu.groupMaterialProduct(_Menu)`
  - `admin.menu.groupSystemTerminal(_Menu)`
- 新增字段文案 key：
  - `page.profitCenter.storeDefaultGmvCurrency`
- 前端缓存版本已更新：
  - `view/admin/common/layout.html` i18n 版本参数：`20260418_menu_gmv_1`
  - `view/admin/product_search/live.html` 脚本版本：`20260418_live_style_catalog_ops13`

### 6) 执行顺序与使用说明（Windows 开发 / Linux 部署通用）

1. 先执行迁移（Windows）：
   - `php database\\run_migration_profit_store_currency.php`
2. Linux 部署执行：
   - `php database/run_migration_profit_store_currency.php`
3. 清理缓存后回归（建议）：
   - 删除 `runtime/cache/*`、`runtime/temp/*`
4. 进入后台：
   - 利润中心 -> 店铺管理：可直接设置店铺默认 GMV 币种
   - 商机寻款 -> 直播选款分析 -> 店铺管理：可设置/修改店铺默认 GMV 币种
5. 验证口径：
   - 店铺保存后，账号默认 GMV 币种应自动同步
   - 菜单按五组重排且切换 `zh/en/vi` 不出现 `admin.menu.*` 直出

## 2026-04-18 店铺加载与菜单显示补强（稳定性修复）

- 问题：直播选款分析页偶发“加载店铺失败/加载店铺列表失败”，并出现 `admin.menu.liveStyleAnalysis` key 直出。
- 后端加固（`app/controller/admin/ProductSearchLive.php`）：
  - `storesJson`、`storeListJson` 增加全链路异常兜底，异常时返回空列表并记录日志（`live_stores_json_failed` / `live_store_list_json_failed`），避免前端收到 HTML 异常页。
  - `loadStoreGmvCurrencyMap` 增加 `growth_profit_accounts.store_id` 字段存在性检查；账号币种查询增加 try/catch，表结构差异时自动回退，不阻断店铺列表。
  - 修复币种中文标签乱码（美元/越南盾/混合币种/人民币）。
- 前端修复：
  - `public/static/admin/live_style_analysis.js` 店铺选择器与详情图片维护店铺下拉优先显示 `default_gmv_currency`。
  - `view/admin/common/layout.html` 修正 `admin.menu.liveStyleAnalysis` 兜底文案，避免出现乱码。
  - `view/admin/product_search/live.html` 修复页面标题与面包屑乱码；脚本版本升级为 `20260418_live_style_catalog_ops14`。
- 验证（Windows）：
  1. `php -l app/controller/admin/ProductSearchLive.php`
  2. `node --check public/static/admin/live_style_analysis.js`
  3. 打开 `商机寻款 -> 直播选款分析`，确认店铺列表可加载、店铺管理可打开，菜单不再显示 i18n key。

## 2026-04-18 店铺加载稳态 + 菜单翻译兜底（补丁）

- 现场排查：
  - 本地库存在 `growth_profit_accounts.default_gmv_currency`，但缺少 `growth_profit_stores.default_gmv_currency`。
  - 已执行迁移：`php database\\run_migration_profit_store_currency.php`，字段补齐并按账号币种回填。
- 前端稳态（`public/static/admin/live_style_analysis.js`）：
  - `parseJson` 增加 BOM 清理与空文本保护，避免响应前缀 BOM 导致 JSON 解析失败。
  - 店铺加载新增一次性报错节流：初始化阶段不再弹出两条重复错误（“加载店铺失败 / 加载店铺列表失败”）。
  - 页面初始化改为静默首轮加载店铺数据，减少误报和噪音提示。
- 菜单翻译兜底（`view/admin/common/layout.html`）：
  - content tabs 增强 key 识别与翻译逻辑：即使缺少 `data-i18n` 或文本直接是 key，也会尝试自动翻译。
  - `admin.menu.liveStyleAnalysis` 保留硬兜底文案，避免显示 key 原文。
- i18n 补齐：
  - `public/static/i18n/i18n.js` 新增 `page.profitCenter.storeDefaultGmvCurrency` 的 `zh/en/vi` 文案，和 `i18n.ops2.js` 保持一致。
- 缓存版本升级：
  - `view/admin/common/layout.html`：`i18n.js/i18n.ops2.js?v=20260418_menu_gmv_2`
  - `view/admin/product_search/live.html`：`live_style_analysis.js?v=20260418_live_style_catalog_ops15`
- 验证（Windows）：
  1. `php -l view/admin/common/layout.html`
  2. `node --check public/static/admin/live_style_analysis.js`
  3. `node --check public/static/i18n/i18n.js`
  4. 打开 `商机寻款 -> 直播选款分析`，确认：
     - 初始化不再连续弹两条店铺加载失败；
     - 顶部 shortcut 不再出现 `admin.menu.liveStyleAnalysis`；
     - 店铺管理弹窗可编辑并保存 GMV 币种。

## 2026-04-18 侧边菜单可读性优化（文字提亮）

- 问题：深色侧边栏中一级菜单文字/图标对比度偏低，阅读不清晰。
- 调整文件：`view/admin/common/layout.html`
  - `--dash-sidebar-text` 从 `#8da0bc` 提亮为 `#b4c5e1`。
  - 桌面端主菜单颜色由硬编码 `#94a3b8` 改为 `var(--dash-sidebar-text)`，与主题变量统一。
- 影响范围：
  - 仅侧边栏主菜单默认态可读性提升；
  - hover、active 状态逻辑与配色保持不变。
- 验证（Windows）：
  1. `php -l view/admin/common/layout.html`
  2. 刷新后台页面，确认一级菜单文字更清晰、选中态仍可区分。

## 2026-04-18 侧边菜单可读性优化（二次提亮）

- 反馈问题：一级菜单未选中分组（如“选品经营/增长分析”）仍偏暗，不易识别。
- 调整文件：`view/admin/common/layout.html`
  - `--dash-sidebar-text` 从 `#b4c5e1` 再提亮到 `#c9d8ef`。
  - `--dash-sidebar-hover-text` 从 `#cfddff` 调整到 `#e2ebff`，保持 hover 层级清晰。
- 影响范围：
  - 仅深色侧栏一级菜单文字/图标颜色增强；
  - active 样式与交互逻辑不变。
- 验证（Windows）：
  1. `php -l view/admin/common/layout.html`
  2. 刷新后台页面，确认未选中一级菜单文字更亮，hover/active 仍可区分。

## 2026-04-18 侧边菜单可读性优化（三次修复：覆盖规则）

- 反馈问题：提亮变量后，一级菜单分组标题仍偏暗。
- 根因：`view/admin/common/layout.html` 中全局链接规则
  - `a:not(.btn)...` 未排除 `admin-sidenav-group-head` / `admin-sidenav-subgroup-head`
  - 该规则在样式后段把侧栏分组链接重置为 `#334155`，覆盖了侧栏变量色。
- 修复：
  - 在全局链接规则与 hover 规则中，新增排除：
    - `.admin-sidenav-group-head`
    - `.admin-sidenav-subgroup-head`
- 结果：
  - 侧栏一级分组（如“选品经营/增长分析/达人运营/素材商品”）恢复使用 `--dash-sidebar-text` / hover 变量色；
  - 其他正文链接配色不变。
- 验证（Windows）：
  1. `php -l view/admin/common/layout.html`
  2. 刷新后台页面，确认一级菜单分组标题不再发暗。

## 2026-04-18 一步到位无兼容层改造（全量路由清理 + 同日硬切）

### 1) 路由标准化（不保留旧入口）

- 文件：`route/admin.php`
- 改造规则：
  - 删除全部 `*Json` 路径别名，仅保留标准路径（如 `.../list`、`.../summary`、`.../sourceList`）。
  - 直播选款仅保留 query 风格详情接口：
    - `GET /admin.php/product_search/live/styleDetail`
    - `POST /admin.php/product_search/live/styleImageUpdate`
  - 移除旧路径式详情兼容：
    - `/admin.php/product_search/live/styles/{style_code}`
    - `/admin.php/product_search/live/styles/{style_code}/image`
- 结果：每个能力只保留一个 URL，旧 URL 直接不可用（404/路由未命中）。

### 2) 前端与客户端调用硬切新路由

- 改造范围：
  - `view/admin/*` 各模块页面内 fetch URL 全量改为标准路由（不再使用 `listJson/summaryJson/...`）。
  - `public/static/admin/live_style_analysis.js` 全量切换为：
    - `/product_search/live/store/list`
    - `/product_search/live/catalog/list`
    - `/product_search/live/sessions`
    - `/product_search/live/unmatched`
    - `/product_search/live/rankings`
    - `/product_search/live/styleDetail`
  - Android 客户端：
    - `android_app/.../SessionApiClient.java` 改为 `/mobile_task/list`、`/mobile_device/list`。
- 缓存强刷：
  - `view/admin/product_search/live.html` 脚本版本升级为 `20260418_live_style_catalog_ops16_hardcut`。

### 3) 店铺币种域逻辑收敛

- 新增服务：`app/service/StoreCurrencyService.php`
  - 统一 `default_gmv_currency` 规范化（默认 `VND`、按系统支持币种校验）。
  - 统一店铺币种映射加载（店铺默认币种 -> 账号币种回退）。
  - 统一店铺保存后账号默认币种同步。
- 控制器改造：
  - `ProfitCenter` 与 `ProductSearchLive` 均改为调用 `StoreCurrencyService`，不再各自维护一套币种校验/同步实现。

### 4) 租户能力去重

- 新增服务：`app/service/TenantScopeService.php`
  - 统一 `tenant_id` 识别、注入与 query 过滤。
- 收敛改造：
  - `app/BaseController.php` 租户方法统一委托到 `TenantScopeService`。
  - `DataImportService`、`DataImportDispatchService`、`MessageOutreachService`、`AutoDmService` 去除各自重复 tenant 过滤实现，统一复用。

### 5) 迁移与运维治理增强

- 文件：`app/service/OpsMaintenanceService.php`
- 改造内容：
  - 迁移脚本清单改为“自动发现 + 显式优先顺序”。
  - 补齐遗漏优先脚本：
    - `run_migration_live_product_analysis.php`
    - `run_migration_auto_dm_hotfix.php`
  - `status()` 增加完整性指标：
    - `checksum_current`
    - `checksum_history`
    - `checksum_match`
    - `integrity_missing_file_count`
    - `integrity_checksum_mismatch_count`

### 6) 发布与验证（Windows 开发 / Linux 部署）

1. 语法与脚本检查（Windows）：
   - `php -l route/admin.php`
   - `php -l app/service/TenantScopeService.php`
   - `php -l app/service/StoreCurrencyService.php`
   - `php -l app/controller/admin/ProductSearchLive.php`
   - `php -l app/controller/admin/ProfitCenter.php`
   - `php -l app/service/OpsMaintenanceService.php`
   - `php -l app/service/DataImportService.php`
   - `php -l app/service/DataImportDispatchService.php`
   - `php -l app/service/MessageOutreachService.php`
   - `php -l app/service/AutoDmService.php`
   - `node --check public/static/admin/live_style_analysis.js`
2. 同日硬切发布：
   - 后端和前端同版本上线（无灰度、无旧路由兼容）。
3. 发布后立即清缓存：
   - `runtime/cache/*`
   - `runtime/temp/*`

## 2026-04-19 多租户计划补齐（已完成）

### 1) 功能补齐
- 新增页面：`view/admin/tenant/index.html`
  - 提供 5 个子区域：租户、套餐、订阅、管理员、审计。
  - 页面仅通过 JSON API 调用后端（前后端分离，不走模板内耦合查询）。
- 新增脚本：`public/static/admin/tenant_center.js`
  - 完成以下接口编排：
    - `GET /admin.php/tenant/list`
    - `POST /admin.php/tenant/save`
    - `POST /admin.php/tenant/status`
    - `POST /admin.php/tenant/switch`
    - `GET /admin.php/tenant/package/list`
    - `POST /admin.php/tenant/package/save`
    - `POST /admin.php/tenant/subscription/save`
    - `GET /admin.php/tenant/subscription/modules`
    - `GET /admin.php/tenant/admin/list`
    - `POST /admin.php/tenant/admin/save`
    - `GET /admin.php/tenant/audit/list`
- 多语言补齐（避免页面显示键值或参数串）：
  - 更新 `public/static/i18n/i18n.js`
  - 更新 `public/static/i18n/i18n.ops2.js`
  - 增加租户菜单键与租户中心页面键（zh/en/vi）。
- 后端返回码映射补齐：
  - 在 `BACKEND_MSG_KEY_MAP` 增加租户/套餐/订阅/管理员相关错误码映射，前端提示可读化。

### 2) 稳定性修复
- 修复 `database/run_migration_tenant_saas_suite.php` 的 UTF-8 BOM 问题。
  - 现象：`strict_types declaration must be the very first statement`。
  - 处理：转为 UTF-8 无 BOM，迁移脚本可正常 `php -l`。

### 3) 自动化测试与联调结果
- 语法检查（Windows）：
  - `php -l app/controller/admin/Tenant.php` ✅
  - `php -l app/service/TenantScopeService.php` ✅
  - `php -l app/service/StoreCurrencyService.php` ✅
  - `php -l app/controller/admin/ProductSearchLive.php` ✅
  - `php -l app/controller/admin/ProfitCenter.php` ✅
  - `php -l app/service/OpsMaintenanceService.php` ✅
  - `php -l app/service/DataImportService.php` ✅
  - `php -l app/service/DataImportDispatchService.php` ✅
  - `php -l app/service/MessageOutreachService.php` ✅
  - `php -l app/service/AutoDmService.php` ✅
  - `php -l route/admin.php` ✅
  - `php -l database/run_migration_tenant_saas_suite.php` ✅
- 前端脚本检查：
  - `node --check public/static/admin/tenant_center.js` ✅
  - `node --check public/static/admin/live_style_analysis.js` ✅
- i18n 检查：
  - `node scripts/check_i18n_keys.js --scope=all` ✅
- HTTP 冒烟联调（本地内置服务器 + 登录态）：
  - `/admin.php/tenant/list` 返回 JSON ✅
  - `/admin.php/tenant/package/list` 返回 JSON ✅
  - `/admin.php/tenant/admin/list` 返回 JSON ✅
  - `/admin.php/tenant?tab=tenants` 页面渲染 `tenantCenterApp` 成功 ✅

### 4) 迁移前置说明
- 若出现以下返回码：
  - `tenant_package_tables_missing`
  - `tenant_subscription_tables_missing`
- 说明数据库尚未执行租户 SaaS 套件迁移，请先执行：
  - Windows：`php database\run_migration_tenant_saas_suite.php`
  - Linux：`php database/run_migration_tenant_saas_suite.php`
## 2026-04-19 后台稳定性优化（防空白页优先）

### 1) 全局链路：trace_id + JSON 错误增强
- 新增 `app/service/TraceIdService.php`：为每次请求生成/复用 `trace_id`。
- 新增 `app/middleware/TraceIdJsonMiddleware.php`：
  - 所有响应追加 `X-Trace-Id` Header。
  - JSON 响应自动补齐 `trace_id`。
  - 非 0 `code` 且缺失 `error_key` 时做最佳努力推断。
- `app/BaseController.php` 的 `apiJsonOk/apiJsonErr` 已统一返回 `trace_id`。

### 2) 权限与导航兜底
- `app/middleware/AdminAuthMiddleware.php`：
  - 模块禁用场景，非 JSON 请求改为返回标准无权限卡片页，而不是重定向造成“看起来空白”。
  - JSON 识别路径补充 `ops_frontend`。
- 新增无权限页面：`view/admin/common/no_access.html`。

### 3) 前端统一启动与请求层
- `view/admin/common/layout.html` 新增全局对象：
  - `window.AdminApi`：统一 `requestJson/get/post`，支持 `error_key` 翻译、`trace_id` 透传、会话过期跳转登录。
  - `window.AdminPageBootstrap.init(...)`：依赖检测、挂载异常捕获、`data-section` 可见性兜底、异常健康上报。

### 4) 前端健康上报
- 新增接口：`POST /admin.php/ops_frontend/health/save`
  - 控制器：`app/controller/admin/OpsFrontend.php`
  - 路由：`route/admin.php`
  - 字段：`page/module/event/trace_id/detail`（并记录 tenant/admin/ip/ua）。
- 新增迁移：`database/run_migration_ops_frontend_health.php`
  - 新表：`ops_frontend_health_logs`。
- 运维迁移清单已接入：`app/service/OpsMaintenanceService.php`。

### 5) 第一批页面接入（租户中心/利润中心/商品管理）
- 页面均新增 `data-section`，并接入 `AdminPageBootstrap`。
- 页面列表加载失败新增“内联错误 + 重试按钮”，不再只依赖 toast。
- 核心查询链路优先接入 `AdminApi`（租户中心全链路、利润中心核心查询、商品管理列表/删除）。

### 6) 静态检查与 CI
- 新增脚本：`scripts/check_admin_template_stability.js`
  - 校验第一批核心页面是否包含 `data-section`。
  - 校验 DOM 模板中是否存在 `el-*` 自闭合标签（忽略 `<script>` 内模板字符串）。
- CI 更新：`.github/workflows/i18n-check.yml`
  - 在 i18n 检查后执行 `node scripts/check_admin_template_stability.js`。

### 7) 本地验证命令（Windows）
1. `php -l app/service/TraceIdService.php`
2. `php -l app/middleware/TraceIdJsonMiddleware.php`
3. `php -l app/controller/admin/OpsFrontend.php`
4. `php -l app/middleware/AdminAuthMiddleware.php`
5. `php -l app/BaseController.php`
6. `php -l route/admin.php`
7. `node --check public/static/admin/tenant_center.js`
8. `node --check scripts/check_admin_template_stability.js`
9. `node scripts/check_i18n_keys.js --scope=all`
10. `node scripts/check_admin_template_stability.js`

### 8) 迁移命令
- Windows：`php database\\run_migration_ops_frontend_health.php`
- Linux：`php database/run_migration_ops_frontend_health.php`
## 16. 后台稳定性防空白页（2026-04-19）

### 16.1 目标
- 后台页面在依赖失败、接口失败、权限不足、Tab/Section 状态异常时，不再出现“标题有但功能区空白”。
- 输出可见错误态与可追踪 `trace_id`，便于快速定位线上问题。

### 16.2 已落地能力
- 新增前端统一启动器：`window.AdminPageBootstrap.init(...)`。
  - 依赖检查（Vue/ElementPlus 等）
  - Mount 失败兜底
  - `data-section` 可见性检测与默认区块回退
- 新增前端统一请求层：`window.AdminApi`。
  - 统一 GET/POST JSON 请求
  - `error_key` + `trace_id` 统一处理
  - 会话过期自动跳转登录
  - 异常健康上报（仅异常事件上报）
- 后端统一可观测字段：
  - `app/BaseController.php` 的 `apiJsonOk/apiJsonErr` 返回 `trace_id`
  - `app/middleware/TraceIdJsonMiddleware.php` 补齐 `trace_id/error_key`（兜底）
- 权限禁用模块时返回“无权限页面”而不是弱反馈空白。

### 16.3 健康上报
- 新增接口：`POST /admin.php/ops_frontend/health/save`
- 字段：`page/module/event/trace_id/detail`
- 数据表：`ops_frontend_health_logs`
- 迁移脚本：`database/run_migration_ops_frontend_health.php`（幂等）

### 16.4 首批接入模块
- `租户中心`：`view/admin/tenant/index.html` + `public/static/admin/tenant_center.js`
- `利润中心`：`view/admin/profit_center/index.html`
- `商品管理`：`view/admin/product/index.html`

### 16.5 静态检查与命令
- 模板稳定性检查：`node scripts/check_admin_template_stability.js`
  - 检查项：
    - 首批页面必须包含 `data-section`
    - DOM 模板禁止 `el-*` 自闭合写法
- i18n 检查：`node scripts/check_i18n_keys.js --scope=all`

### 16.6 Windows / Linux 执行
1. 执行迁移
   - Windows: `php database\run_migration_ops_frontend_health.php`
   - Linux: `php database/run_migration_ops_frontend_health.php`
2. 语法检查
   - `php -l app/BaseController.php`
   - `php -l app/middleware/TraceIdJsonMiddleware.php`
   - `php -l app/controller/admin/OpsFrontend.php`
   - `php -l app/middleware/AdminAuthMiddleware.php`
3. 前端检查
   - `node scripts/check_admin_template_stability.js`
   - `node scripts/check_i18n_keys.js --scope=all`
## Stability Checker Update (2026-04-19)
- `scripts/check_admin_template_stability.js` now validates:
  - required `data-section` markers
  - required `data-module` marker
  - required `AdminPageBootstrap.init(...)` wiring (tenant page allows external `tenant_center.js` bootstrap)
  - disallow self-closing `el-*` tags in DOM template
## 17. 稳定性自动化冒烟（2026-04-19）

- 新增脚本：`scripts/admin_stability_smoke.ps1`
- 目标：覆盖“防空白页优先”关键链路（登录、核心页面可见性、JSON trace_id/error_key、权限注入、静态检查）。

### 覆盖项
1. 登录流程（`/admin.php/auth/login`）
2. 核心页面可见性
   - `租户中心` `/admin.php/tenant`
   - `利润中心` `/admin.php/profit_center`
   - `商品管理` `/admin.php/product`
3. API 结构
   - `trace_id` 存在
   - 异常响应 `error_key` 存在
4. 权限注入
   - 临时禁用 `profit_center` 模块并验证受限响应
5. 静态校验联动
   - `node scripts/check_admin_template_stability.js`
   - `node scripts/check_i18n_keys.js --scope=all`

### 运行命令（Windows）
- `powershell -ExecutionPolicy Bypass -File scripts/admin_stability_smoke.ps1`

### 说明
- 脚本会创建临时租户与临时管理员，测试结束自动清理。
- 若环境中健康上报保存失败（返回 `save_failed`），脚本会记录提示并继续执行其余校验。

## 18. TikTok 浏览器插件自动回传 V1（2026-04-19）

### 18.1 目标范围
- 浏览器：Chrome / Edge（Manifest V3）。
- 流程：一键抓取 -> 预览确认 -> 批量回传利润中心。
- V1 字段：广告费、GMV、订单数（含日期、店铺/广告户引用、渠道、币种）。
- 数据来源：TikTok Ads / TikTok Shop 页面。

### 18.2 后端新增能力（利润中心）
- Token 与桥接接口：
  - `GET /admin.php/profit_center/plugin/bootstrap`
  - `POST /admin.php/profit_center/plugin/tokenCreate`
  - `POST /admin.php/profit_center/plugin/tokenRevoke`
  - `POST /admin.php/profit_center/plugin/ingestBatch`
  - `GET /admin.php/profit_center/plugin/ingestLogs`
  - `POST /admin.php/profit_center/plugin/mappingSave`
  - `POST /admin.php/profit_center/plugin/mappingDelete`
- 安全：
  - 插件使用 Bearer Token，不依赖后台登录态 Cookie。
  - Token scope 固定 `profit_ingest`，支持过期和吊销。
- 落库与审计：
  - 新增插件 Token、回传日志、店铺别名映射、广告户别名映射四张表。
  - 回传结果含 `trace_id/error_key`，便于排错。

### 18.3 插件端目录
- `tools/browser_plugin/profit_center_capture/`
  - `manifest.json`
  - `background.js`
  - `content.js`
  - `shared/parser.js`
  - `popup.html`
  - `popup.js`
  - `popup.css`
  - `README.md`
  - `test/fixtures/*.html`

### 18.4 回传冲突策略
- 唯一键：`tenant + entry_date + store + account + channel`。
- 同键重复回传：覆盖同字段并触发利润重算，不做重复累计。

### 18.5 迁移与使用命令（Windows/Linux 通用）
1. 执行迁移：
   - `php database/run_migration_profit_plugin.php`
2. 语法检查：
   - `php -l app/service/ProfitPluginTokenService.php`
   - `php -l app/controller/admin/ProfitCenter.php`
   - `php -l app/middleware/AdminAuthMiddleware.php`
3. 插件解析测试：
   - `node scripts/profit_plugin_parser_test.js`

### 18.6 验证结果（本轮）
- `php -l`：通过（插件相关后端文件）。
- `node scripts/profit_plugin_parser_test.js`：通过（3 组页面快照）。
- `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`：通过。

## 19. TikTok Profit Plugin Aggregation Update (2026-04-19)

### 19.1 Scope
- Browser plugin now supports campaign-level aggregation on TikTok Ads pages.
- Channel mapping rule:
  - `Product GMV Max` -> `video`
  - `LIVE GMV Max` -> `live`

### 19.2 Data Rules
- For Ads pages, plugin aggregates multiple campaign rows by channel and sends totals.
- Aggregated metrics: `ad_spend_amount`, `gmv_amount`, `order_count`, `total_roi`.
- Currency rule on Ads pages: `gmv_currency` must equal `ad_spend_currency`.
- Plugin sends `raw_metrics_json` with `capture_mode`, `campaign_count`, and `total_roi`.

### 19.3 Backend Ingest
- `preparePluginRowPayload()` now accepts and persists plugin `raw_metrics_json` / `raw_metrics` when provided.
- If source page is Ads (`ads.tiktok.com`), backend enforces `gmv_currency = ad_spend_currency`.

### 19.4 Validation
- Parser test: `node scripts/profit_plugin_parser_test.js`.
- Syntax checks:
  - `node --check tools/browser_plugin/profit_center_capture/shared/parser.js`
  - `node --check tools/browser_plugin/profit_center_capture/popup.js`
  - `php -l app/controller/admin/ProfitCenter.php`
- Smoke test: `powershell -ExecutionPolicy Bypass -File scripts/profit_center_smoke.ps1`.

### 19.5 Parser Precision Fix (2026-04-19)
- Plugin date extraction now prioritizes the top date-range control and uses the range start date as `entry_date`.
- Channel selection now follows active tab first:
  - `Product GMV Max` => `video`
  - `LIVE GMV Max` => `live`
- Orders extraction strengthened:
  - supports `SKU orders` header in table/role-grid aggregation
  - adds `sku orders` keyword fallback in text parsing.

### 19.6 Profit Plugin SKU Orders Backend Compatibility (2026-04-19)
- Profit plugin ingest now treats the following fields as order quantity aliases and writes them into `order_count`:
  - `order_count`
  - `sku_orders`
  - `sku_order_count`
  - `orders_count`
- If those fields are absent, backend will also try `raw_metrics_json.total_orders` / `order_count` / `sku_orders`.
- Browser plugin preview table header is updated to `SKU orders` to align with TikTok Ads terminology.
- Store name extraction now supports avatar image alt text, e.g. `.p-avatar-image img[alt="Banano VN"]` -> `store_ref=Banano VN`.
- Popup UX improved: row-level duplicate action, stronger table readability, and batch-date expansion for one-time multi-date submit.
- Batch-date supports:
  - newline/comma list: `2026-04-18, 2026-04-19`
  - range syntax: `2026-04-01~2026-04-07`
- Tab-safe zero rule:
  - when `LIVE/Product` tab is selected but tab-level rows are unavailable/no-data, plugin returns zero metrics instead of using overview totals.

## 2026-04-20 Plugin Update: TikTok Creative Three-Stage Analyzer (Local Only)

### Scope
- Module: `tools/browser_plugin/profit_center_capture`
- Upgraded local creative workflow to three-stage diagnosis + actionable optimization suggestions.
- No backend schema/API change in this phase.

### Behaviors
- Parse GMV Max creative rows and extract `video_id`, title, account, status, and visible metrics.
- Generate structured diagnosis for each creative:
  - `hook_score`, `retention_score`, `conversion_score`
  - `material_type` (`bad|potential|scale`)
  - `problem_position` (`front_3s|middle|conversion_tail|multi_stage`)
  - `continue_delivery` (`yes|no`), `core_conclusion`, `actions[]`, `confidence`
- Page rendering rule:
  - only render tags on rows that contain a Boost button
  - tag style: `优秀款 / 观察中 / 垃圾素材 / 忽略` (el-tag style)
- Popup panel:
  - displays full diagnosis and allows manual override
  - supports one-click export of exclude candidate `video_id` list
- Persist decisions in browser local storage key: `profit_plugin_creative_opt_v1`.

### Rule Set (Balanced Mode, VN GMV Max baseline)
- Hook stage: weighted by `Product ad click rate + 2s view rate`.
- Retention stage: weighted by `6s + 25% + 50% + 75% view rate`.
- Conversion stage: weighted by `Ad conversion rate + ROI + Cost per order + SKU orders`.
- Type mapping:
  - `bad`: conversion stage low and learning threshold reached
  - `potential`: at least one stage strong but not closed-loop
  - `scale`: conversion high and hook/retention not below mid
- Missing metrics:
  - per-stage weighted fallback over available metrics
  - if insufficient metrics, reduce confidence and avoid aggressive downgrade.

### Message Protocol (plugin internal)
- `profit_plugin_creative_scan`
- `profit_plugin_creative_apply_labels`
- `profit_plugin_creative_export_excludes`

### Notes
- Works in Windows development and Linux deployment (extension-side logic only).
- Recommended column set: `ROI`, `SKU orders`, `Ad conversion rate`, `Product ad click rate`, `2s/6s/25/50/75% view rate`.
- This phase does not perform automated click actions on TikTok UI; it only provides diagnosis, tagging, and copyable IDs.

## 2026-04-21 Live Catalog Import: Remote Image URL Cloud Upload

### Scope
- Module: `/admin.php/product_search/live` catalog import flow.
- Applies to store product catalog import when image column value is `http/https` URL.

### Behavior
- During catalog import, if image value is remote URL:
  - fetch image to runtime temp file
  - upload directly to cloud storage (Qiniu)
  - delete temp file immediately
  - persist cloud URL into `growth_store_product_catalog.image_url`
- If cloud upload fails, keep original remote URL as fallback (import does not block).

### Storage Impact
- No persistent write to `public/uploads` for URL image import in this flow.
- Only short-lived runtime temp file is used and removed after upload.

### Compatibility
- Windows development and Linux deployment are both supported.
- If Qiniu is disabled, system keeps original URL and continues import.

## 2026-04-21 Live Ranking Fallback Style Support (#01 / #66)

### Scope
- Module: `/admin.php/product_search/live` session import + ranking aggregation.

### Behavior
- Style extraction now supports hash style IDs in product title:
  - `#01`, `#02`, `#45`, `#66` etc.
- Ranking aggregation now includes unmatched rows when extracted style exists:
  - style key fallback: `catalog_style_code` first, otherwise `extracted_style_code`
  - so rows without catalog binding can still appear in hot ranking.
- If style has no image, ranking keeps `image_url` empty (frontend shows no image).

### Matching Examples
- Existing: `A-139`, `A139`, `D142`, `C-129`
- Added fallback: `#01`, `#66`, `#123`

## 2026-04-21 直播爆款榜空白兜底修复

### 修复内容
- 后端 `LiveStyleAnalysisService::getRankings` 增加“空结果回退锚点”逻辑：当前筛选锚点无数据时，自动回退到最近有数据的锚点日期重查。
- 前端 `live_style_analysis.js` 增加切换到“爆款榜”标签自动刷新，避免跨标签操作后榜单不刷新。
- 页面脚本版本号已更新，避免浏览器缓存旧JS导致看不到修复。

### 使用说明
1. 进入 `/admin.php/product_search/live`，切到“爆款榜”会自动刷新。
2. 若手动选的锚点日期无数据，系统会自动回退到最近有数据日期并展示榜单。
3. 若仍显示异常，先 `Ctrl+F5` 强制刷新再重试。

## 2026-04-21 未匹配款号入榜修复（#01/#66）

### 变更
- `LiveStyleAnalysisService::ensureSnapshot` 增加未匹配 hash 款号快照修复检测：当窗口内存在 `#数字`（含纯数字写法）但榜单快照没有 `#` 款时，自动触发重算。
- `normalizeAggStyleCode` 增强：兼容全角 `＃`、空格分隔、纯数字写法（如 `66`）并归一到 `#66`。

### 效果
- 未匹配列表中的 `#01/#66/...` 会自动进入爆款榜聚合（图片可为空）。
- 无需手动删库重跑，首次查询榜单会自动修复旧快照。

## 2026-04-21 直播榜单详情与场次数修复

### 修复内容
- `styleDetail` 查询口径调整：不再只查已匹配数据，新增未匹配回落条件（`is_matched=0 AND extracted_style_code in candidates`），支持 `#xx` 款式查看详情趋势。
- 榜单聚合 `session_count` 口径调整为按 `session_date + session_name` 去重，避免仅按 `session_id` 导致历史快照场次统计异常。
- 增加快照修复检测：若窗口内实际存在多场次，但快照场次数仍全 <=1，将自动触发重算。

### 使用说明
1. 在“爆款榜”点击某个 `#xx` 款式的“详情”，现在会返回真实数据（无图片也可看指标）。
2. 若历史快照场次数异常，重新点击“查询榜单”会自动触发修复并重算。

## 2026-04-21 Auto DM Desktop Agent Support (Zalo/WhatsApp)

### Scope
- Module: `/admin.php/auto_dm` + agent endpoints.
- Goal: Auto DM tasks can be executed by both phone devices and desktop devices.

### Backend Changes
- `mobile_devices.platform` now supports `desktop` in admin save flow.
- Added desktop API aliases:
  - `POST /admin.php/desktop_agent/pull_auto`
  - `POST /admin.php/desktop_agent/report_auto`
- Existing endpoints remain valid:
  - `POST /admin.php/mobile_agent/pull_auto`
  - `POST /admin.php/mobile_agent/report_auto`

### Dispatch Rules
- Auto DM campaign can carry optional `execute_client` in `target_filter_json`:
  - `mobile`, `desktop`, `both` (default `both`).
- Dispatcher behavior:
  - if task is not executable on current device client, task is released back to `pending` (not blocked).
  - avoids incorrect blocking when mixed mobile/desktop agents pull in parallel.

### UI / i18n
- Mobile device management page adds `Desktop` option in platform selector.
- i18n keys added:
  - `page.mobileDevice.platformDesktop` for `zh/en/vi`.

### Windows Dev / Linux Deploy
- No OS-specific path dependency added.
- Same API contract works on Windows development and Linux deployment.

## 2026-04-21 Desktop Agent Program (Auto Send on PC)

### Scope
- New runtime tool: `tools/desktop_agent/agent.py`
- Purpose: run Auto DM task execution directly on desktop (Windows/Linux).

### Capabilities
- Pull task from `POST /admin.php/desktop_agent/pull_auto`.
- Auto open chat page by channel (`wa` / `zalo`).
- Auto input rendered text and optionally press Enter to send.
- Report result to `POST /admin.php/desktop_agent/report_auto`.
- Persist browser login/profile via `runtime/desktop_agent/browser_profile`.

### Runtime Config
- Required env:
  - `DESKTOP_AGENT_ADMIN_BASE`
  - `DESKTOP_AGENT_TOKEN`
  - `DESKTOP_AGENT_DEVICE_CODE`
- Optional env:
  - `DESKTOP_AGENT_BROWSER_CHANNEL` (`msedge` default)
  - `DESKTOP_AGENT_HEADLESS`
  - `DESKTOP_AGENT_SEND_ENTER`
  - `DESKTOP_AGENT_TASK_TYPES`
  - `DESKTOP_AGENT_DRY_RUN`

### Files
- `tools/desktop_agent/agent.py`
- `tools/desktop_agent/requirements.txt`
- `tools/desktop_agent/README.md`

### Usage Notes
- First run requires login on WhatsApp Web / Zalo Web in persistent browser profile.
- If Zalo input selector changes, task reports failed with clear error for retry.

## 2026-04-21 Desktop Agent GUI Launcher (Windows)

### Scope
- New launcher UI: `tools/desktop_agent/desktop_agent_gui.py`
- One-click run script: `tools/desktop_agent/run_gui.bat`
- One-click packaging script: `tools/desktop_agent/build_windows_exe.ps1`

### User Flow
- Open GUI launcher.
- Fill `admin_base`, `token`, `device_code` once and save.
- Click `Start agent` to run desktop auto-DM without command-line env setup.
- Log file is persisted in `tools/desktop_agent/runtime_gui/desktop_agent.log`.

### Deploy Notes
- Windows dev and Linux deploy remain compatible (agent core unchanged).
- GUI launcher is optional wrapper; backend API remains unchanged.

## 13. 达秘协同（CSV先落地，2026-04-21）

### 13.1 目标
- 不重复建设达人主数据系统，达秘作为主数据来源。
- 我方系统聚焦运营执行与结果闭环（Auto DM + 回传 + ROI看板）。

### 13.2 数据模型
- `influencers` 新增字段：
  - `profile_url`
  - `data_source`
  - `source_system`
  - `source_influencer_id`
  - `source_sync_at`
  - `source_hash`
  - `last_crawled_at`
  - `source_batch_id`
- 新增审计表：`influencer_source_import_batches`
  - 记录批次号、文件名、映射、总数、成功/更新/失败等。

### 13.3 新增接口
- `POST /admin.php/influencer/source/importPreview`
  - 上传 CSV/Excel，返回字段映射、差异预览与失败行。
- `POST /admin.php/influencer/source/importCommit`
  - 执行同步（同 `tiktok_id` 更新，不重复新增）。
- `GET /admin.php/influencer/source/importBatches`
  - 返回导入批次及触达/回复/转化统计。

### 13.4 页面能力
- 达人页新增“达秘导入向导”：
  - 上传 -> 字段映射 -> 预览差异 -> 执行同步。
- 达人页新增“达秘来源 ROI 批次看板”：
  - 达人数、触达任务、回复数、转化数、触达率、回复率、转化率。
- 达人列表新增“来源”列：`达秘` / `手工`。

### 13.5 Auto DM 联动
- 创建活动时支持 `source_system` 过滤：
  - `dami`（达秘导入）
  - `manual`（手工/旧数据）
- 仅筛选出有可用联系方式的达人参与发送链路。

### 13.6 迁移与运行
- Windows:
  - `php database\\run_migration_influencer_source_dami.php`
- Linux:
  - `php database/run_migration_influencer_source_dami.php`

### 13.7 说明
- 本地运营字段不被导入覆盖（状态、标签、备注、外联结果等）。
- 仍以 `tiktok_id` 作为业务主键；若缺失则可用 `source_influencer_id` 暂存映射。
- 本期为 CSV/Excel 适配器，已预留后续 API 适配器扩展位。

## 2026-04-21 Desktop Agent Launcher 可用性增强（已完成）
- 文件：`tools/desktop_agent/desktop_agent_gui.py`
- 目标：解决“点击启用无反应 / requests 缺失 / 启动失败不可见”。

### 本次增强
1. 启动器改为中文界面，增加实时状态与健康度显示。
2. 增加“一键诊断”按钮（Python 可用性 + 后台地址可达性）。
3. 增加“安装/修复依赖”按钮（自动准备 venv、安装依赖、安装 Chromium）。
4. 增加实时日志面板，启动失败会明确弹窗提示。
5. 启动逻辑统一走 `.venv` Python，避免系统 Python 缺少依赖导致失败。

### Agent 兼容性修复
- 文件：`tools/desktop_agent/agent.py`
- 变更：移除对 `requests` 的硬依赖，改为内置 `urllib` 发起 HTTP 请求。
- 效果：即使未安装 requests，也可正常拉任务/回报任务。

### 启动脚本修复
- 文件：`tools/desktop_agent/run_gui.bat`
- 变更：依赖检测从 `requests+playwright` 调整为仅检测 `playwright`，减少误报。

### 使用说明（Windows）
1. 双击 `tools/desktop_agent/run_gui.bat`。
2. 填写后台地址、代理令牌、设备编码。
3. 点“安装/修复依赖”（首次建议执行一次）。
4. 点“启动代理”，首次登录 Zalo/WhatsApp Web 后保持程序后台运行。
- Hotfix（2026-04-22）：`tools/desktop_agent/agent.py` 增加后台路由自动兼容，支持 `/admin.php/xxx` 与 `admin.php?s=xxx` 等候选地址自动重试，修复本地环境 `No input file specified` 导致桌面代理 404 的问题。
## 2026-04-24 GMV Max 动态投放助手（店铺历史基准）

- 浏览器插件 `tools/browser_plugin/profit_center_capture` 新增“GMV Max 动态投放助手”面板。
- 插件可将当前 TikTok GMV Max 素材页数据同步到后端，按 `tenant_id + store_id + campaign_id + metric_date + video_id` 覆盖更新。
- 新增迁移脚本：`php database/run_migration_gmv_max_creative_insights.php`。
- 新增数据表：
  - `gmv_max_creative_daily`：素材每日指标。
  - `gmv_max_store_baselines`：店铺 7/14/30 天及全历史基准。
  - `gmv_max_recommendation_snapshots`：每日投放建议快照。
- 新增接口：
  - `POST /admin.php/gmv_max/creative/sync`
  - `GET /admin.php/gmv_max/creative/baseline`
  - `GET /admin.php/gmv_max/creative/recommendation`
  - `GET /admin.php/gmv_max/creative/history`
  - `GET /admin.php/gmv_max/creative/ranking`
- 推荐逻辑：
  - 历史样本不少于 30 条时使用店铺历史基准。
  - 历史样本不足时使用越南 GMV Max 通用基准，并返回 `baseline_mode=regional_default`。
  - 输出账户阶段、主问题、动作级别、今日该做、今日不要做、预算建议、ROI 建议、素材新增方向、放量视频 ID、排除视频 ID。
- 使用说明：
  1. 在插件中连接利润中心 Token。
  2. 打开 TikTok GMV Max 素材列表页并勾选核心指标列。
  3. 在“GMV Max 动态投放助手”选择店铺，选填目标 ROI 和预算。
  4. 点击“同步到后端并生成建议”。
  5. 根据后端建议执行放量、优化或排除。

## 2026-04-24 GMV Max 投放助手同步与SOP修复

### 范围
- 模块：`tools/browser_plugin/profit_center_capture`
- 目标：解决投放助手提示“没有可同步的素材”，并补齐“从0到放量”完整投放操作提示。

### 行为变更
- 素材行识别不再只依赖 Boost 按钮，插件会同时扫描 TikTok 可见表格行。
- 当 TikTok 页面未渲染真实 `Video ID` 时，插件会基于行内容生成 `pseudo_xxx` 稳定伪 ID，用于后端历史沉淀，避免整页被过滤。
- 同步前会强制重新扫描当前页，避免缓存的空素材列表导致误报。
- 投放助手默认展示“GMV Max 从0到放量 SOP”，包含冷启动准备、首测设置、素材判断、放量动作、止损动作、账户养护和每日节奏。

### 使用说明
1. 打开 TikTok GMV Max 素材列表页。
2. 建议显示 `Creative / Cost / SKU orders / ROI / Product ad click rate / Ad conversion rate / 视频播放率` 等核心列。
3. 在插件选择店铺，点击“同步到后端并生成建议”。
4. 若页面缺少真实 Video ID，后端会先保存 `pseudo_xxx` 行；后续显示真实 ID 后再按真实 ID 继续沉淀；早期伪 ID 样本会保留用于当日诊断。
