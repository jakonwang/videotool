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



