# 功能需求与实现说明

> 本文档为 **UTF-8** 编码。根目录 `requirements.md` 与本文内容一致；若某工具写入根目录该文件名时中文变为问号，请以 **`docs/requirements.md`** 为准或用 `Copy-Item docs/requirements.md requirements.md` 同步。

## TikStar OPS 系统模块（2026-04）

### 分类 + 达人 CRM + 话术增强（2026-04-06）

- 新增后台模块 **分类管理**：`/admin.php/category`，支持 `product` / `influencer` 两类分类的 CRUD（名称、类型、排序、状态），接口：`listJson`、`options`、`save`、`delete`。
- 商品表单 `view/admin/product/form.html` 改为分类下拉选择（`category_id` + `category_name` 双写入），选项来源 `GET /admin.php/category/options?type=product`。
- 达人名录 `view/admin/influencer/index.html` 升级为 CRM：  
  - `status` 扩展为 `0待联系/1已发私信/2已回复/3待寄样/4已寄样/5合作中/6黑名单`；  
  - 增加快捷状态切换；  
  - 支持 `category_id` 选择、标签（`tags_json`）筛选、`last_contacted_at` 排序；  
  - 编辑弹窗新增寄样字段（`sample_tracking_no`、`sample_status`）；  
  - 新增联系历史弹窗（数据源 `GET /admin.php/influencer/outreachHistory`）。
- 话术渲染增强：`MessageOutreachService` 新增变量 `{{current_time_period}}`、`{{random_emoji}}`；模板支持 `lang(zh/en/vi)` + `template_key` 多语言分组，渲染时按达人 `region` 自动选语言版本。
- 新增外联历史表 `outreach_logs`：每次渲染 `POST /admin.php/message_template/render` 记录模板、语言、商品与渲染正文，并自动更新 `influencers.last_contacted_at`。
- 数据迁移脚本：`php database/run_migration_category_crm_outreach.php`（Windows 用反斜杠路径，Linux 用正斜杠路径）。

### 定位

- **TikStar OPS** 作为运营中台，整合：**寻款**（商品/款式图搜索索引）、**达人运营**（TikTok `@handle` 名录、联系信息导入与更新、达人分发链、联系话术）、**素材库**（视频与商品归类、批量上传）、**终端**（平台/设备）与**系统**（设置、用户、桌面端发卡/版本、缓存与异常）。

### 侧栏信息架构（与 `view/admin/common/layout.html` 一致）

| 模块 | 分组文案（i18n 键） | 子菜单 / 入口 | 说明 |
|------|---------------------|---------------|------|
| 概览 | `admin.menu.overview` | 仪表盘 | 统计与快捷入口 |
| 寻款 | `admin.menu.groupSearch` | `admin.menu.styleSearch` → `/product_search` | 图片搜款式、CSV/Excel 异步导入索引 |
| 达人 | `admin.menu.groupCreator` + `admin.menu.groupCreatorMenu`（折叠） | `admin.menu.influencerList` → `/influencer`；`admin.menu.distribute` → `/distribute`；`admin.menu.messageTemplates` → `/message_template` | TikTok 名录、达人链、话术模板 |
| 素材 | `admin.menu.material` + `admin.menu.materialMenu`（折叠） | 视频、上传、商品 | 内容与商品维度的素材管理 |
| 终端 | `admin.menu.terminal` | 平台、设备 | 取片设备与平台 |
| 系统 | `admin.menu.system` | 设置、用户、发卡、版本、缓存、异常 | |

### 面包屑约定

- 寻款页：`page.styleSearch.breadcrumb`（如「寻款 / 索引」）。
- 达人名录 / 达人链 / 话术：`page.influencer.breadcrumb`、`page.distribute.breadcrumb`、`page.messageTemplate.breadcrumb`（统一在「达人」域下）。

---

## 品牌与达人 CRM（2026-04）

### 系统名称

- 后台侧栏与默认页面标题展示为 **TikStar OPS**（原「社媒素材库」文案已替换）。

### 达人名录（tiktok_id = TikTok 用户名 @handle）

- 数据库表 `influencers`：`tiktok_id` 为**规范化**用户名——小写、前缀 `@`，与 TikTok **@handle** 对应，全局唯一；另有昵称、头像 URL、粉丝数、`contact_info`（TEXT，JSON 文本）、地区、`status`（0 待联系 / 1 合作中 / 2 黑名单）。
- 后台路径 **达人 → 名录**（`/admin.php/influencer`）：分页列表、关键词与状态筛选、**导入更新**（异步）、**示例 CSV 下载**、**全量导出 CSV**（`GET /admin.php/influencer/exportCsv`，UTF-8 BOM，列名 `contact` 与导入兼容）、行内 **编辑/删除**（删除前自动将相关达人链的 `influencer_id` 置空）。
- 支持 **.csv / .txt / .xlsx / .xls / .xlsm**：首行可识别表头（含 `tiktok_id`、`handle`、`用户名` 等）；无法识别时默认**第一列为 TikTok 用户名**。
- 导入任务表 `influencer_import_tasks`；前端创建任务后轮询 `POST /admin.php/influencer/importTaskTick`。
- **升级数据库**（Windows / Linux 均可）：项目根目录执行  
  `php database/run_migration_influencers_crm.php`  
  （依赖 `config/database.php`；已存在结构会跳过）。**全新建库**：`database/schema.sql` 已包含 `influencers`、`influencer_import_tasks` 与 `product_links.influencer_id`。

### 达人链可选关联达人

- `product_links.influencer_id` 可空；生成页输入框支持 **输入关键字调用** `GET /influencer/search` 下拉点选；须与名录中已有 `tiktok_id` 一致；列表接口返回 `influencer` 对象便于展示。

### 达人外联：话术模板与一键联系（2026-04）

- **数据表** `message_templates`：模板名称、正文 `body`（占位符）、`sort_order`、`status`。
- **商品扩展**（`products`）：`thumb_url`（列表缩略图）、`tiktok_shop_url`（TikTok Shop/橱窗链接），与 `goods_url` 并存；话术渲染时可用 `{{tiktok_shop_url}}` 等变量。
- **迁移**（已有库）：  
  `php database/run_migration_outreach.php`（Windows：`php database\run_migration_outreach.php`）  
  或手动执行 `database/migrations/20260415_outreach_product_thumb.sql`（注意重复执行时的列已存在错误）。  
  新装以 `database/schema.sql` 为准。
- **服务** `app/service/MessageOutreachService.php`：解析达人 `contact_info` 中的 WhatsApp/Zalo，`buildRenderVars` 填充 `{{tiktok_id}}`、`{{nickname}}`、`{{region}}`、`{{whatsapp}}`、`{{zalo}}`、`{{product_name}}`、`{{goods_url}}`、`{{tiktok_shop_url}}`、`{{distribute_link}}`；`distribute_link` 优先匹配「该达人 + 该商品」的启用达人链，否则退回该商品任意启用链。
- **后台路由**（`admin.php`，需登录）：  
  | 方法 | 路径 | 说明 |
  |------|------|------|
  | GET | `/message_template` | 话术管理页（Vue 渲染） |
  | GET | `/message_template/list` | 列表 JSON |
  | POST | `/message_template/save` | 保存模板 |
  | POST | `/message_template/delete` | 删除模板 |
  | POST | `/message_template/render` | 渲染话术（`template_id`、`influencer_id`、可选 `product_id`），返回 `text`、`wa_url`、`zalo_url` |
- **名录页** `view/admin/influencer/index.html`：行操作 **话术** 打开弹窗，选择模板与可选商品，预览正文，支持复制、WhatsApp 预填、Zalo 打开。若尚无启用模板，会提示先至侧栏 **达人 → 话术** 新建（`page.influencer.noTemplates`）；关联商品下拉占位 `page.influencer.productOptionalPh`；无 WhatsApp 号码时 `page.influencer.noWhatsapp`。
- **商品** `POST /admin.php/product/uploadThumb`：上传缩略图至 `public/uploads/product_thumbs/{Ymd}/`，表单见 `view/admin/product/form.html`。

### 后台 JSON 接口（入口 `admin.php`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/influencer/list` | 达人分页列表 |
| GET | `/influencer/search` | 下拉搜索，`q` |
| POST | `/influencer/importCsv` | 上传文件，返回 `task_id` |
| GET | `/influencer/importTaskStatus` | 任务快照 |
| POST | `/influencer/importTaskTick` | 推进一步 |
| GET | `/influencer/sampleCsv` | 下载导入示例 CSV |
| GET | `/influencer/exportCsv` | 全量导出达人 CSV（UTF-8 BOM） |
| POST | `/influencer/update` | JSON 编辑达人（不可改 `tiktok_id`） |
| POST | `/influencer/delete` | JSON `{"id":n}` 删除达人 |

### 多语言

- **调试报错页**：根目录 **`.env`** 中 `APP_DEBUG = true` 时 ThinkPHP 在浏览器输出**完整异常与堆栈**（便于复制）；生产环境须 `false`。模板见 **`.env.example`**；细则见 **`TROUBLESHOOTING.md`**「开启页面详细报错」。
- 运维速查：**`README.md`** 中有「多语言（i18n）与脚本缓存版本」总述。
- `public/static/i18n/i18n.js` 增加 **中文 / English / Tiếng Việt**（`?lang=vi`）。修改脚本后需**全站**提高所有引用 `i18n.js` 的 **`?v=`**（当前示例：**`20260406_category1`**），包括 `layout.html`、登录页、寻款/达人链等独立页，以及各 Vue 独立页；达人前台另 bump `influencer_i18n.js` 的 `?v=`。

### 寻款索引 i18n（2026-04）

- `view/admin/product_search/index.html`：提示框、筛选、表格列、导入进度弹窗、编辑弹窗及脚本内 `ElMessage` / `ElMessageBox` 文案已走 `AppI18n.t`（`page.styleSearch.*` 与 `common.*`）；`tt` 支持占位符 `{var}`。

### 达人名录 i18n（2026-04）

- `view/admin/influencer/index.html`：导入轮询、会话过期、复制成功/失败、保存/删除成功提示与删除确认框均走 i18n；导入进度与结束摘要复用寻款一致键。

### 达人链页 i18n（2026-04）

- `view/admin/distribute/index.html`：页眉与表格列、启停/删除/复制等提示与确认框均走 `page.distribute.*` / `common.*`；勿在模板中误用 `$\{tt(...)\}` 导致文案不随 Vue 渲染。

### 登录页 i18n（2026-04）

- `view/admin/auth/login.html`：`i18n.js` 版本与全站对齐；登录失败兜底文案使用 `auth.loginFailed`。

### 仪表盘业务 KPI（2026-04）

- 首页 SSR 与 `GET /admin.php/stats/overview` 同源（`StatsService::overview()`），含寻款索引、达人名录、达人链等；表不存在或未迁移时对应项为 `0`。

---

## 商品与达人分发（2026-03）

### 需求摘要

- 视频可按**商品**归类；后台维护商品（名称、可选商品页 URL、缩略图、TikTok 商品链）。
- **分发链接**对应一个商品；达人打开链接后，从该商品已绑定视频中**随机**取一条**未下载**（`is_downloaded=0`）的素材。
- **全局核销**：任意达人下载某条视频并标记后，该视频对所有人不再参与随机（与现有 IP 设备流共享 `videos.is_downloaded`）。

### 数据表

- `products`、`product_links`、`videos.product_id`（可空）、`videos.device_id`（可空）。

### API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/video/influencerRandom?token=` | 达人页拉取随机未下载视频 |
| POST | `/api/video/markDownloaded` | 下载后核销 |
| GET | `/api/video/download` | 代理下载 |

### 系统设置与默认封面

- `system_settings`、七牛合并 `QiniuService::getMergedQiniuConfig()`、读写 **`app\service\SystemConfigService`**（勿与 `think\Model::set()` 冲突）。

---

## 仪表盘统计（2026-03）

- `GET /admin.php/stats/overview`、`trends`、`platformDistribution`、`downloadErrorTrends`、`downloadErrorTop`、`productDistribution`、`storageUsage`。
- 实现：`app/service/StatsService.php`、`app/controller/admin/Stats.php`、首页 `view/admin/index/index.html`。仪表盘 **所有 KPI 数字**（含已下载/未下载、下载率、平台/设备、今日上传/下载与环比、寻款/达人/达人链等）均在 **`Index::dashboardScalars()`** 中从 `StatsService::overview()` 转为扁平标量（`d_*`、`video_total`、`asof_display`）再传入视图；模板**禁止**再使用 `{$stats.xxx|default=...}`，以免 ThinkPHP 编译出含 `$stats['downloaded']` 等片段时在个别环境报 **unexpected identifier "videos" / "downloaded"** 等。
- 升级：`php database/run_migration_product_distribution.php` 或 `database/migrations/20260330_product_distribution.sql`。

---

## 后台：视频 / 商品 / 达人链 / 平台 / 设备 / 缓存 / 下载异常（Vue3 + Element Plus）

- **视频** `GET /admin.php/video/list`；`view/admin/video/index.html`。注意：脚本区「下拉选项」注释**不可**与 `const PLATFORM_OPTIONS = []` 写在同一行（`//` 会吞掉整行，导致未定义）；分页区「反选」按钮须正确闭合 `</el-button>`。**内联脚本中** `ElMessage.success('...')` 等字符串须**成对闭合引号**，否则未闭合字符串会延续到后面模板里的 `Home / Library / Videos`，浏览器报 **unexpected identifier "videos"**。列表/筛选/表头/弹窗等文案统一 `page.video.*` 与 `tt()`，避免乱码引号破坏属性（如 `:placeholder`）。
- **商品** `GET /admin.php/product/list`（含 `thumb_url`、`tiktok_shop_url`、`category_name`）；`view/admin/product/index.html`（列表「商品链接」列仅展示 **TikTok 商品链接** `tiktok_shop_url`，操作区 **复制链接** 仅复制该字段；支持按分类筛选；行内 **编辑** 为弹窗，提交 `POST /admin.php/product/edit/{id}`，缩略图上传同 `POST /admin.php/product/uploadThumb`）；独立页 **添加商品** 仍为 `GET/POST /admin.php/product/add`，`view/admin/product/form.html`。列表表头、复制空内容、删除确认等均走 i18n（`page.product.colName`、`page.product.colCategory`、`page.product.copyLink`、`page.product.editTitle` 等及 `page.product.deleteConfirmMsg`）。
- **达人** `GET /admin.php/influencer/list` 增加 `category_name` 及分类聚合，`view/admin/influencer/index.html` 支持分类筛选与编辑分类；导入/导出 CSV 兼容 `category_name` 列。
- **分类字段迁移**：`php database/run_migration_product_influencer_category.php`（Windows：`php database\\run_migration_product_influencer_category.php`；Linux：`php database/run_migration_product_influencer_category.php`）。
- **达人链** `GET /admin.php/distribute/list`；`view/admin/distribute/index.html`。
- **平台** `GET /admin.php/platform/list`；`view/admin/platform/index.html`。
- **设备** `GET /admin.php/device/list`；`view/admin/device/index.html`。
- **缓存** `GET /admin.php/cache/list`；`view/admin/cache/index.html`。
- **下载异常** `GET /admin.php/downloadLog/list`；`POST /admin.php/downloadLog/clear`：从所选日期 runtime 日志中删除与列表规则一致的「下载/缓存相关异常」行，其余保留；`view/admin/download_log/index.html`。

---

## 后台：用户登录与管理员管理（Session）（2026-04）

- 表 `admin_users`；迁移 `php database/run_migration_admin_users.php`。
- 登录 `auth/login`、`auth/logout`；用户 CRUD `user/*`；中间件 `AdminAuthMiddleware.php`（未登录 JSON 返回 `401`）。
- 默认账号 `admin / admin123`（登录后请改密）；`config/session.php` 中 `expire` 单位为秒，可用 `.env` 的 `SESSION_EXPIRE` 覆盖。

### 多语言切换

- 后台：`?lang=`、`localStorage(app_lang)`、`public/static/i18n/i18n.js`。
- 达人取片页：`?ilang=`、`influencer_i18n.js`，与后台独立。

---

## 后台统一页面规范（2026-03）

- 骨架类：`admin-page-container`、`admin-modern-card`、`admin-header-actions`、`admin-filter-section`、`admin-footer-pagination`（见 `view/admin/common/layout.html`）。

---

## 图片搜款式「寻款」（2026-04；豆包视觉）

- CSV/Excel 导入、本地 Python 向量、豆包 `ai_description`；配置见 `config/services.php`、`config/product_search.php`。
- 详细路由、异步导入、开放 API、H5 等：**`tools/product_style_search/README.md`**、代码与 `route/admin.php`。
- CSV 列说明：`docs/耳环款式CSV说明.md`。
- 迁移脚本：`database/run_migration_product_style_search.php`（及唯一编号、图搜队列、image_path 等，见 `database/` 目录）。

---

## 桌面端：发卡与版本、公开下载（2026-04）

- 表 `app_licenses`、`app_versions`；迁移 `php database/run_migration_client_app.php`。
- 开放 API：`/index.php/api/client/verifyLicense`、`checkUpdate`；说明 **`docs/client-desktop-api.md`**；公开下载页 `index.php/download`。

---

## 根目录 `requirements.md` 乱码说明（维护必读）

1. 在本环境中，若直接用部分工具**重写**仓库根目录的 **`requirements.md`**，非 ASCII 字符可能被**误写成问号 `?`**，从而在编辑器里看到「全是乱码/问号」。
2. **规范做法**：以 **`docs/requirements.md`** 为**正本**（UTF-8 中文可正常保存）；同步到根目录请在项目根执行：`Copy-Item -Force docs\requirements.md requirements.md`（PowerShell），或 Linux：`cp docs/requirements.md requirements.md`。
3. 若 Git 历史里该文件曾被错误提交为问号，需用**正确 UTF-8 文件**覆盖后再提交。
