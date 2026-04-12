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
