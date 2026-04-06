# 功能需求与实现说明

## TikStar OPS 系统模块（2026-04）

### 定位
- **TikStar OPS** 作为运营中台，整合：**寻款**（商品/款式图搜索索引）、**达人运营**（TikTok `@handle` 名录、联系信息导入与更新、达人分发链）、**素材库**（视频与商品归类、批量上传）、**终端**（平台/设备）与**系统**（设置、用户、桌面端发卡/版本、缓存与异常）。

### 侧栏信息架构（与 `view/admin/common/layout.html` 一致）
| 模块 | 分组文案（i18n 键） | 子菜单 / 入口 | 说明 |
|------|---------------------|---------------|------|
| 概览 | `admin.menu.overview` | 仪表盘 | 统计与快捷入口 |
| 寻款 | `admin.menu.groupSearch` | `admin.menu.styleSearch` → `/product_search` | 图片搜款式、CSV/Excel 异步导入索引 |
| 达人 | `admin.menu.groupCreator` + `admin.menu.groupCreatorMenu`（折叠） | `admin.menu.influencerList` → `/influencer`；`admin.menu.distribute` → `/distribute` | TikTok 名录（唯一 `tiktok_id`、联系信息 Upsert）、达人链生成与关联达人 |
| 素材 | `admin.menu.material` + `admin.menu.materialMenu`（折叠） | 视频、上传、商品 | 内容与商品维度的素材管理 |
| 终端 | `admin.menu.terminal` | 平台、设备 | 取片设备与平台 |
| 系统 | `admin.menu.system` | 设置、用户、发卡、版本、缓存、异常 | |

### 面包屑约定
- 寻款页：`page.styleSearch.breadcrumb`（如「寻款 / 索引」）。
- 达人名录 / 达人链：`page.influencer.breadcrumb`、`page.distribute.breadcrumb`（统一在「达人」域下）。

---

## 品牌与达人 CRM（2026-04）

### 系统名称
- 后台侧栏与默认页面标题展示为 **TikStar OPS**（原「社媒素材库」文案已替换）。

### 达人名录（tiktok_id = TikTok 用户名 @handle）
- 数据库表 `influencers`：`tiktok_id` 存**规范化**用户名——小写、前导 `@`，与 TikTok **@handle** 对应，全局唯一；另有昵称、头像 URL、粉丝数、`contact_info`（TEXT 存 JSON 文本）、地区、`status`（0 待联系 / 1 合作中 / 2 黑名单）。
- 后台路径：**达人 → 名录**（`/admin.php/influencer`）：分页列表、关键词与状态筛选、**导入更新**（异步）、**示例 CSV 下载**、**全量导出 CSV**（`GET /admin.php/influencer/exportCsv`，UTF-8 BOM，列含 `contact` 与导入兼容）、行内**编辑/删除**（删除前自动将相关达人链的 `influencer_id` 置空）。
- 支持 **.csv / .txt / .xlsx / .xls / .xlsm**：首行可识别表头（含 `tiktok_id`、`handle`、`用户名` 等）；无法识别时默认**第一列为 TikTok 用户名**。
- 导入任务表 `influencer_import_tasks`；前端创建任务后轮询 `POST /admin.php/influencer/importTaskTick`。
- **升级数据库**（Windows / Linux 均可）：项目根目录执行  
  `php database/run_migration_influencers_crm.php`  
  （依赖 `config/database.php`；已存在结构会跳过）。**全新建库**时 `database/schema.sql` 已包含 `influencers`、`influencer_import_tasks` 及 `product_links.influencer_id`。

### 达人链可选关联达人
- `product_links.influencer_id` 可空；生成页输入框支持 **输入关键字调用** `GET /influencer/search` 下拉点选；须与名录中已有 `tiktok_id` 一致；列表接口返回 `influencer` 对象便于展示。

### 后台 JSON 接口（入口 `admin.php`）
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/influencer/list` | 达人分页列表 |
| GET | `/influencer/search` | 下拉搜索，`q` |
| POST | `/influencer/importCsv` | 上传文件，返回 `task_id` |
| GET | `/influencer/importTaskStatus` | 任务快照 |
| POST | `/influencer/importTaskTick` | 推进一行 |
| GET | `/influencer/sampleCsv` | 下载导入示例 CSV |
| GET | `/influencer/exportCsv` | 全量导出达人 CSV（UTF-8 BOM） |
| POST | `/influencer/update` | JSON 编辑达人（不可改 `tiktok_id`） |
| POST | `/influencer/delete` | JSON `{"id":n}` 删除达人 |

### 多语言
- 运维速查：**`README.md`** 中有「多语言（i18n）与脚本缓存版本」总述；本文件下列为业务键与页面细节。
- `public/static/i18n/i18n.js` 增加 **中文 / English / Tiếng Việt**（`?lang=vi`）；模块分组键名见 `admin.menu.overview`、`admin.menu.groupSearch`、`admin.menu.groupCreator`、`admin.menu.material` 等。修改脚本后需**全站**提高所有引用 `i18n.js` 的 `?v=`（当前示例：`20260414_full_sync`），包括 `layout.html`、登录页、寻款/达人链等独立页，以及各 Vue 独立页（缓存/用户/商品/视频等）；达人前台另见 `influencer_i18n.js` 的 `?v=`。

### 寻款索引页 i18n（2026-04）
- `view/admin/product_search/index.html`：提示框、筛选、表格列、导入进度弹窗、编辑弹窗及脚本内 `ElMessage` / `ElMessageBox` 文案已走 `AppI18n.t`（`page.styleSearch.*` 与 `common.*`）；`tt` 支持占位符 `{var}`。越南语 `vi` 已补常用键，其余仍回退中文。

### 达人名录页 i18n（2026-04）
- `view/admin/influencer/index.html`：导入轮询、会话过期、复制成功/失败、保存/删除成功提示与删除确认框（标题与确认/取消按钮）均走 i18n；导入进度与结束摘要复用 `page.styleSearch.tickLine`、`importSummary`、`taskPushFail` 等与寻款一致的键；`tt` 支持第二参数占位符。

### 达人链页 i18n（2026-04）
- `view/admin/distribute/index.html`：页眉与表格列、启停/删除/复制等提示与确认框均走 `page.distribute.*` / `common.*`；修复原模板中误用 JS 模板字符串 `${tt(...)}` 导致文案不随 Vue 渲染的问题，改为 `{{ tt('…') }}`；删除成功提示使用 `common.deleted`。

### 登录页 i18n（2026-04）
- `view/admin/auth/login.html`：`i18n.js` 版本与全站对齐；品牌脚注由 `auth.brandFootnote` 注入；语言切换增加 **Tiếng Việt**（`setLang('vi')`）；登录失败兜底文案使用 `auth.loginFailed`（不再误用 `auth.loginBtn`）。

### 仪表盘业务 KPI（2026-04）
- 首页 SSR 与 `GET /admin.php/stats/overview` 同源（`StatsService::overview()`），除平台/设备/视频等指标外，另含：**寻款索引**（`product_style_items` 且 `status=1`）、**达人名录**（`influencers`）、**达人链**（`product_links`）；表不存在或未迁移时对应项为 `0`。
- **今日数据总结**卡片在「今日上传 / 待处理 / 下载率」下增加一行 **业务概览**（`dashboard.ops.bizHead`），与三张业务 KPI 卡数字一致。

## 商品与达人分发（2026-03）

### 需求摘要
- 视频可按**商品**归类；后台维护商品（名称、可选商品页 URL）。
- **分发链接**对应一个商品；达人打开链接后，从该商品已绑定视频中**随机**取一条**未下载**（`is_downloaded=0`）的素材。
- **全局核销**：任意达人下载某条视频并标记后，该视频对所有人不再参与随机（与现有 IP 设备流共用 `videos.is_downloaded`）。

### 数据库
- `products`：商品。
- `product_links`：`token`、`product_id`、备注、启用状态。
- `videos.product_id`：可空；有值且与分发商品一致时才参与 `influencerRandom`。
- `videos.device_id`：可空；绑定商品用于达人链时，可不选设备（IP 取片仍按设备分配未下载视频）。

### 后台侧栏（分组 + 精简名称）— 已由「TikStar OPS 系统模块」一节替代，此处保留历史参考
| 分组 | 子菜单 | 含义 |
|------|--------|------|
| （顶栏） | 仪表盘 | 首页 |
| 寻款 | 寻款 | 图片搜款式索引（独立模块入口） |
| 达人 | 名录 / 达人链 | TikTok 达人名录与导入、达人分发链接 |
| 素材 | 视频 / 上传 / 商品 | 视频与商品维度的素材管理 |
| 终端 | 平台 / 设备 | 平台与终端设备 |
| 系统 | 系统设置 / 用户 / 发卡 / 版本 / 缓存 / 异常 | 参数、管理员、桌面授权码、桌面安装包发布、缓存、下载错误监控 |

- 侧栏分组使用 **Bootstrap collapse**（与页面 SSR 同步 `show`，避免 AdminLTE Treeview 初始化把当前分组收拢）；样式为自定义「玻璃拟态 + 霓虹描边」分组头与子项指示点，与 AdminLTE 默认 `nav-treeview` 视觉脱钩。
- **后台多语言**：文案在 `public/static/i18n/i18n.js` 中维护；侧栏等使用 `data-i18n="键名"`，由 `AppI18n.applyDom` 替换。**新增或修改翻译键后**，须同步提高**全站**所有 `i18n.js` 引用的 `?v=`（与 `layout.html` 保持一致，当前示例 `20260414_full_sync`），否则浏览器可能仍加载旧脚本，界面会显示键名（如 `admin.menu.styleSearch`）而非译文。

### 侧边栏 UI（2026-03）
- 暗黑侧栏参考 **Vercel / Tailwind UI**：分组标题更小更克制（uppercase + tracking-wider），可点击项与标题层级区分明显。
- 激活态：取消外部描边感，改为**柔和深灰底色** + 左侧 **2px 主色强调条**。
- 子菜单：补充 Lucide 细粒度图标，并收紧缩进与垂直间距，提升对齐与可读性。
- 底部信息区：卡片化布局，Label 小字号灰色左对齐，Value 白色高亮右对齐；命名统一（如“上传量”）。

- **达人链**：生成/列表/启停/删除链接；达人页：`{站点}/index.php/d/{token}`。
- **视频 / 上传 / 编辑**：可选「所属商品」。

### API
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/video/influencerRandom?token=` | 达人页拉取随机未下载视频 |
| POST | `/api/video/markDownloaded` | 下载后核销（与现网一致） |
| GET | `/api/video/download` | 代理下载（与现网一致） |

### 系统设置与默认封面
- 表 `system_settings`：`storage`=`local`|`qiniu`（控制是否走七牛上传）、`default_cover_url`（可选）、`site_name`（可选）。
- **七牛（可选键，非空则覆盖 `config/qiniu.php` / 环境变量）**：`qiniu_access_key`、`qiniu_secret_key`、`qiniu_bucket`、`qiniu_domain`、`qiniu_region`、`qiniu_cdn_domains`（额外 CDN 域名，文本：一行一个或逗号/分号分隔；**非空则整表替换**配置里的 `cdn_domains` 数组）。合并逻辑见 **`QiniuService::getMergedQiniuConfig()`**。
- 后台「设置」页：可填 Access/Secret/Bucket/域名/区域/额外 CDN；密钥类**留空表示不修改**；勾选「清空」可删除库中该项以恢复仅用配置文件。Bucket/域名/区域/额外 CDN **留空**表示不覆盖文件配置（`qiniu_cdn_domains` 留空时沿用 `config` 中的 `cdn_domains`）。
- 读写使用 **`app\service\SystemConfigService`**（`get` / `set`），**不**在 `Model` 子类上写静态 `set`，避免与 `think\Model::set()` 冲突。
- 无封面时：`VideoCoverService` 使用配置的默认地址或 `/static/default-cover.svg`。
- 迁移脚本会建表并写入默认键值；七牛键在首次保存时写入即可。

## 仪表盘统计（2026-03）

### 目标
- 让后台首页不止“总数”，增加**趋势**与**结构分布**，便于运营与排查问题。

### 接口（后台入口 `admin.php`）
- `GET /admin.php/stats/overview`：KPI 总览（含今日上传/今日下载，以及寻款索引/达人/达人链条数等业务字段，见 `StatsService::overview()`）
- `GET /admin.php/stats/trends?days=30`：近 N 天上传/下载趋势
- `GET /admin.php/stats/platformDistribution`：平台分布（已下载/未下载）
- `GET /admin.php/stats/downloadErrorTrends?days=7`：近 N 天下载异常趋势（解析 runtime 日志）
- `GET /admin.php/stats/downloadErrorTop?days=7&limit=8`：近 N 天异常 Top（错误短语）
- `GET /admin.php/stats/productDistribution?limit=12`：商品分布（已下载/未下载 TopN）
- `GET /admin.php/stats/storageUsage`：容量（`public/uploads` 与 `runtime/cache`）

### 代码位置
- 聚合查询：`app/service/StatsService.php`（只做 SQL 聚合，低耦合）
- 控制器：`app/controller/admin/Stats.php`（只读 JSON）
- 仪表盘页面：`view/admin/index/index.html`（ECharts 渲染）

### 部署注意
- **新装**：使用更新后的 `database/schema.sql`。
- **升级（已有库）**：在项目根目录执行  
  `php database/run_migration_product_distribution.php`  
  脚本会按 `config/database.php` 连接数据库，并安全跳过已存在的结构。  
  若不用 PHP，可改用手动执行 `database/migrations/20260330_product_distribution.sql`（重复执行可能需注释已做过的语句）。

### 测试环境（Windows）
- 浏览器访问：`http://本地域名/index.php/d/{token}`。
- 确认视频已绑定同一商品且未下载，再验证随机与下载后不再出现。

### 与 Linux 部署
- 路径与 Windows 一致，使用 `index.php` 形式 URL 可避免重写差异；若生产环境开启伪静态，可改为 `/d/{token}`（需 Web 服务器规则指向 `index.php`）。

## 后台：视频管理（Vue3 + Element Plus）（2026-03）

### 目标
- 保留现有后端接口与业务规则（筛选/分页/删除/批量删除/上传）。
- 前端升级为 **Vue3 + Element Plus** 的组件化界面：面包屑 + 筛选栏（多选）+ 数据表格 + 分页 + 上传弹窗。

### 新增接口（仅输出形式）
- `GET /admin.php/video/list`：返回视频列表 JSON（复用原 `Video@index` 的筛选规则与默认倒序逻辑；支持 `created_at` 排序）。

### 仍沿用的接口
- `POST /admin.php/video/delete/<id>`：删除
- `POST /admin.php/video/batchDelete`：批量删除
- `POST /admin.php/video/batchUpload`：上传（前端弹窗内拖拽选择文件后提交）

### 前端页面
- `view/admin/video/index.html`：通过 CDN 引入 Vue3/Element Plus，渲染表格与分页，并对接上述接口。
- 移动端：列表表格在窄屏会自动启用**横向滚动容器**；上传弹窗在手机上会变为接近全屏，便于点按与查看进度。

## 后台：商品管理（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「商品」列表页从 SSR + jQuery 重构为 **Vue3 + Element Plus**，保持前后端分离（页面只消费 JSON 接口）。
- UI 遵循统一后台页面骨架，并采用高密度现代风格：浅灰底 + 白卡片 + 紧凑表格 + 纯色 Primary。

### 新增接口
- `GET /admin.php/product/list`：返回商品列表 JSON（支持 `keyword`、`status`、`page`、`page_size`、`sort_prop`、`sort_order`）。

#### 参数
- `keyword`：名称模糊搜索
- `status`：`0` 禁用 / `1` 启用（可空表示全部）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）
- `sort_prop`：`sort_order|id|updated_at|created_at|status`
- `sort_order`：`asc|desc`

#### 返回示例
```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "商品A",
        "goods_url": "",
        "status": 1,
        "sort_order": 10,
        "total_videos": 100,
        "downloaded_videos": 60,
        "undownloaded_videos": 40,
        "updated_at": "2026-03-30 10:00:00",
        "created_at": "2026-03-30 09:00:00"
      }
    ],
    "total": 1,
    "page": 1,
    "page_size": 10
  }
}
```

### 仍沿用的接口
- `GET /admin.php/product`：商品管理页面（Vue 渲染）
- `GET /admin.php/product/add`：添加页
- `POST /admin.php/product/add`：提交添加
- `GET /admin.php/product/edit/<id>`：编辑页
- `POST /admin.php/product/edit/<id>`：提交编辑
- `POST /admin.php/product/delete/<id>`：删除

### 前端页面
- `view/admin/product/index.html`：通过 CDN 引入 Vue3/Element Plus，渲染筛选 + 表格 + 分页，并对接 `GET /admin.php/product/list`。

## 后台：达人链（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「达人链」列表页从 SSR + jQuery 重构为 **Vue3 + Element Plus**，保持前后端分离（页面只消费 JSON 接口）。
- UI 与「商品」页同一套高密度现代风格（浅灰底 + 白卡片 + 纯色 Primary + 表格 link 操作）。

### 新增接口
- `GET /admin.php/distribute/list`：返回达人链列表 JSON（支持 `product_id`、`page`、`page_size`）。

#### 参数
- `product_id`：商品 ID（0 或空表示全部）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）

#### 返回示例
```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "items": [
      {
        "id": 1,
        "product": { "id": 2, "name": "商品A" },
        "label": "备注",
        "token": "xxxxx",
        "status": 1,
        "created_at": "2026-03-30 10:00:00",
        "link": "http://example.com/index.php/d/xxxxx"
      }
    ],
    "total": 1,
    "page": 1,
    "page_size": 10
  }
}
```

### 仍沿用的接口
- `GET /admin.php/distribute`：达人链页面（Vue 渲染）
- `GET /admin.php/distribute/add`：生成页面
- `POST /admin.php/distribute/add`：提交生成
- `POST /admin.php/distribute/toggle/<id>`：启停
- `POST /admin.php/distribute/delete/<id>`：删除

### 前端页面
- `view/admin/distribute/index.html`：通过 CDN 引入 Vue3/Element Plus，渲染筛选 + 表格 + 分页，并对接 `GET /admin.php/distribute/list`。

## 后台：平台管理（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「平台」列表页从 SSR + jQuery 重构为 **Vue3 + Element Plus**，保持前后端分离（页面只消费 JSON 接口）。
- UI 与「商品」页同一套高密度现代风格，并将操作按钮统一为 link 文字按钮（避免表格右侧杂乱）。

### 新增接口
- `GET /admin.php/platform/list`：返回平台列表 JSON（支持 `keyword`、`status`、`page`、`page_size`、`sort_prop`、`sort_order`）。

#### 参数
- `keyword`：名称/代码模糊搜索
- `status`：`0` 禁用 / `1` 启用（可空）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）
- `sort_prop`：`id|name|code|status|created_at|updated_at`
- `sort_order`：`asc|desc`

### 前端页面
- `view/admin/platform/index.html`：通过 CDN 引入 Vue3/Element Plus，渲染筛选 + 表格 + 分页，并对接 `GET /admin.php/platform/list`。

## 后台：设备管理（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「设备」列表页从 SSR + jQuery 重构为 **Vue3 + Element Plus**，保持前后端分离（页面只消费 JSON 接口）。
- UI 与「平台/商品」页统一，高密度筛选栏（平台/状态/关键词同一行），表格操作 link 化。

### 新增接口
- `GET /admin.php/device/list`：返回设备列表 JSON（支持 `platform_id`、`keyword`、`status`、`page`、`page_size`、`sort_prop`、`sort_order`）。

#### 参数
- `platform_id`：平台 ID（0/空表示全部）
- `keyword`：设备名/IP 模糊搜索
- `status`：`0` 禁用 / `1` 启用（可空）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）
- `sort_prop`：`id|created_at|updated_at|device_name|ip_address|status`
- `sort_order`：`asc|desc`

### 前端页面
- `view/admin/device/index.html`：通过 CDN 引入 Vue3/Element Plus，渲染筛选 + 表格 + 分页，并对接 `GET /admin.php/device/list`。

## 后台：缓存（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「系统 / 缓存」页面从 SSR + jQuery 重构为 **Vue3 + Element Plus**，保持前后端分离（页面只消费 JSON 接口）。
- UI 采用高密度现代风格：浅灰底 + 白卡片；统计区改为白底卡片网格（禁止大色块/渐变）。

### 新增接口
- `GET /admin.php/cache/list`：返回缓存列表 JSON（支持 `keyword`、`page`、`page_size`），并同时返回 `stats` 与 `is_enabled` 供页面展示统计与提示。

#### 参数
- `keyword`：文件名/来源 URL 模糊搜索（可空）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）

### 仍沿用的接口
- `POST /admin.php/cache/clear`：清空缓存
- `POST /admin.php/cache/delete/<hash>`：删除单条缓存
- `GET /admin.php/cache/download/<hash>`：下载缓存文件

### 前端页面
- `view/admin/cache/index.html`：通过 CDN 引入 Vue3/Element Plus（含 icons-vue），渲染标题操作区、警告提示、统计卡片、搜索与表格，并对接 `GET /admin.php/cache/list`。

## 后台：下载异常（Vue3 + Element Plus）（2026-03）

### 目标
- 将原「系统 / 异常」页面从 SSR + jQuery 重构为 **Vue3 + Element Plus**，风格与缓存页一致（白底统计卡片 + 紧凑表格 + 纯色按钮）。
- 支持按日期与关键词筛选，并提供刷新/清空入口。

### 新增接口
- `GET /admin.php/downloadLog/list`：返回下载异常列表 JSON（支持 `date`、`keyword`、`page`、`page_size`），并返回 `stats` 供统计卡片展示。

#### 参数
- `date`：`YYYYMMDD`（默认当天）
- `keyword`：错误信息关键词（可空）
- `page`：页码（默认 1）
- `page_size`：每页数量（默认 10，最大 100）

### 仍沿用的接口
- `POST /admin.php/downloadLog/clear`：清空异常（当前实现返回“功能开发中”，前端会提示）

### 前端页面
- `view/admin/download_log/index.html`：通过 CDN 引入 Vue3/Element Plus（含 icons-vue），渲染标题操作区、提示、统计卡片、筛选、表格与分页，并对接 `GET /admin.php/downloadLog/list`。

## 后台：用户登录与管理员管理（Session）（2026-04）

### 目标
- 为后台新增 **Session 登录**（Cookie + 服务端 session），访问后台页面与 JSON 接口前必须先登录。
- 新增「系统 / 用户」用于维护管理员账号（增删改查、启停、重置密码）。

### 数据库
- 新增表：`admin_users`
  - 字段：`username`（唯一）、`password_hash`、`status`、`last_login_at`、`last_login_ip`、时间戳

#### 新装
- 直接使用更新后的 `database/schema.sql`（包含默认管理员账号）。

#### 升级（已有库）
- Windows（PowerShell，项目根目录）：
  - `php database\\run_migration_admin_users.php`
- Linux：
  - `php database/run_migration_admin_users.php`

### 默认账号
- 默认会写入：`admin / admin123`（**请登录后立即修改密码**）。

### 路由与接口
#### 登录/退出
- `GET /admin.php/auth/login`：登录页面
- `POST /admin.php/auth/login`：登录（表单提交，返回 JSON，含 `redirect`）
- `POST /admin.php/auth/logout`：退出（JSON）
- `GET /admin.php/auth/logout`：退出（跳转到登录页，适合菜单链接/无 JS 场景）

#### 管理员账号
- `GET /admin.php/user`：用户管理页（Vue 渲染）
- `GET /admin.php/user/list`：列表 JSON（支持 `keyword/status/page/page_size`）
- `POST /admin.php/user/create`：创建（`username/password/status`）
- `POST /admin.php/user/update`：更新（`id/username/status`）
- `POST /admin.php/user/toggle`：启停（`id`）
- `POST /admin.php/user/resetPassword`：重置密码（`id/password`）
- `POST /admin.php/user/delete`：删除（`id`）

### 鉴权规则
- 中间件：`app/middleware/AdminAuthMiddleware.php`
  - 未登录访问后台页面：302 跳转 `/admin.php/auth/login?redirect=...`
  - 未登录访问 JSON 接口：返回 `{code:401,msg:'未登录'}`

### 注意事项
- 当前实现是 **后台 Session 登录**，适合 Web 管理后台；如需 APP/多端统一 Token/JWT，可在此基础上扩展。
- **Session 有效期**：`config/session.php` 中 `expire` 单位为**秒**（文件驱动）；历史默认 `1440` 仅约 **24 分钟**。现默认 **28800（8 小时）**，可在项目根 `.env` 设置 **`SESSION_EXPIRE=86400`**（24 小时）等覆盖。修改后需清浏览器 Cookie 或重新登录后生效。

### 多语言切换（后台：中文/英文；达人页：越南语/英文）（2026-04）
#### 后台管理（Admin）
- 支持语言：中文（`zh`）/英文（`en`）
- URL 参数：`?lang=zh` 或 `?lang=en`
- 本地记忆：`localStorage(app_lang)`
- 语言优先级：`?lang=` > `localStorage(app_lang)` > `navigator.language` > 默认中文
- 代码位置：`public/static/i18n/i18n.js`；模板中通过 `?v=` 防缓存，修改字典后需与全站统一提高版本号。

#### 达人取片页（Influencer）
- 支持语言：越南语（`vi`，默认）/英文（`en`）
- 与后台切换**完全独立**（不读取/不写入 `app_lang`，不使用 `?lang=`）
- URL 参数：`?ilang=vi` 或 `?ilang=en`
- 本地记忆：`localStorage(influencer_lang)`
- 语言优先级：`?ilang=` > `localStorage(influencer_lang)` > `navigator.language` > 默认越南语
- 代码位置：`public/static/i18n/influencer_i18n.js`，页面：`view/index/influencer.html`；修改字典后需提高该脚本 URL 的 `?v=`（与后台 `i18n.js` 同批次 bump，当前示例 `20260414_full_sync`）。

## 后台统一页面规范（与 /video 一致）（2026-03）

### 目标
- 后台所有页面统一为“现代 SaaS”排版：浅灰背景 + 白色内容卡片 + 统一标题区/筛选区/表格区/分页区。
- 不改后端逻辑，仅调整模板结构与样式，降低页面之间的视觉割裂。

### 全局样式位置
- `view/admin/common/layout.html`：新增一组可复用的页面骨架类（`admin-*`）。

### 页面骨架（推荐结构）
- 外层容器：`admin-page-container`（统一背景与外边距）
- 白卡片：`admin-modern-card`（统一圆角/内边距/阴影）
- 标题与操作区：`admin-header-actions`（左标题 + 右操作按钮）
- 筛选区：`admin-filter-section` + `admin-custom-form` + `admin-filter-buttons`（筛选控件同一行，窄屏横向滚动）
- 分页区：`admin-footer-pagination`（左统计信息 + 右分页组件）

### 已适配页面
- 列表页：平台（`view/admin/platform/index.html`）、设备（`view/admin/device/index.html`）、商品（`view/admin/product/index.html`）、达人链（`view/admin/distribute/index.html`）
- 系统页：设置（`view/admin/settings/index.html`）、缓存（`view/admin/cache/index.html`）、异常（`view/admin/download_log/index.html`）
- 桌面端：发卡（`view/admin/client_license/index.html`）、版本（`view/admin/client_version/index.html`）
- 寻款：`view/admin/product_search/index.html`；H5 `view/index/search_by_image.html`

### 说明
- 这些页面会隐藏 layout 自带的 `content-header`，改用页面内部的统一标题区，避免出现两套标题/操作区导致不一致。

## 图片搜款式「寻款」（2026-04；豆包视觉）

### 目标
- 从 **CSV / Excel** 导入「产品编号 + 参考图 + 可选爆款类型」：仍使用 **本地 Python**（MobileNetV2）生成向量写入 MySQL。若 **设置中勾选「导入时生成描述」**，则对每行参考图调用 **火山方舟豆包** 生成 **`ai_description`**，写入 **`product_style_items.ai_description`**，并同步到 **`products.ai_description`**（当存在同名商品时）。**仅豆包**，无 OpenAI / Google / 阿里云作为识图备选。
- **火山引擎方舟 · 豆包视觉**：与官方 **[快速入门](https://www.volcengine.com/docs/82379/1399008?lang=zh)** 一致：**`Authorization: Bearer <API Key>`**，请求体 **`model`** 填 **[模型列表](https://www.volcengine.com/docs/82379/1330310?lang=zh)** 中的 **Model ID**，或控制台 **推理接入点 ID**（`ep-`）。**推荐模型：Doubao-1.5-vision-pro-32k**（常见 API ID：`doubao-1-5-vision-pro-32k`，**以控制台完整 ID 为准**，可能带日期后缀；旧版视觉模型已下线时请改为此类 ID）。`config/services.php` 中 **`volc_ark.model_id`** 默认即为上述 ID（可用 **`VOLC_ARK_MODEL`** 覆盖）。**model 解析顺序**：`VOLC_ARK_MODEL` → 后台「模型 ID」→ **`ep-`（有则优先于配置默认）** → 配置默认。拍照寻款与导入均走 **`POST {base_url}/chat/completions`**；**`runtime/log`** 中 **`model`** 为实际传入值。
- **与官方 Python「多模态向量」示例的区别**：火山文档中 **`multimodal_embeddings`** 走的是 **向量** 接口，与本项目 **无关**。寻款需要 **对话/多模态理解**；若 **`model`** 填错或用了仅 Embedding 的资源，会 **HTTP 4xx**，见 **`ark_error`**。批量导入 **`describeForImport`** 为 **`describe_import_single`**（每行 **1 次**请求）。
- **H5 拍照寻款（全自动）**：**仅**在后台启用豆包时可用。后端 **`VolcArkVisionService::matchPhotoAutoWarehouse`**：将 **最近 N 条**（默认 **50**，`VOLC_ARK_AUTO_CATALOG` / `config/services.php` → **`auto_match_catalog_limit`**，且 **不超过** 后台 **`volc_ark_max_catalog`/`max_catalog_items`**）上架索引写入 **system**，行内合并 **`products.ai_description`**（同名上架商品）与 **`product_style_items`** 描述/爆款，并附 **参考图绝对 URL**（无则 `-`）；**user** 仅含实拍图与极简指令，模型 **只输出一行编号或 `NULL`**（禁止索要补充描述）。识别成功后 **`Search`** 直接查库组装 **`items`（通常 1 条）** 与商品详情闭环。失败时可选 **`hint`** + **关键词回退**（`ProductStyleKeywordSearchService`）。仍支持 **编号模糊查询**（`searchByCode`）。
- 导入行仍可 **入队阿里云** AddImage（与豆包独立；历史库若仍启用阿里云，队列表与同步逻辑保留，**后台设置页已移除阿里云表单项**，改配置需直接改库或 `system_settings`）。

### 数据库
| 表名 | 说明 |
|------|------|
| `product_style_items` | `product_code`（**全局唯一**）、`image_ref`、`hot_type`、**`ai_description`**（豆包生成）、`embedding`、`status` |
| `products` | 增加 **`ai_description`**（与寻款编号同名商品时由导入/编辑同步） |
| `product_style_is_queue` | 阿里云 AddImage 队列（可选） |

#### 升级（已有库）
- `php database\run_migration_product_style_search.php`（Windows）或 `php database/run_migration_product_style_search.php`（Linux）：若表尚不存在则创建（新脚本创建的表已含 `product_code` 唯一索引）。
- **早期已建表**（仅有普通索引 `idx_code`）时，为与「编号唯一」一致，请执行：  
  `php database\run_migration_product_style_unique_code.php`（Linux 路径写法 `database/run_migration_product_style_unique_code.php`）。  
  若库内已有重复 `product_code`，脚本会列出示例编号并中止，需先手工保留一条、删除或合并其余重复行后再执行。
- **阿里云队列表**：`php database\run_migration_product_style_is_queue.php`（Linux：`php database/run_migration_product_style_is_queue.php`）。
- **OpenAI 字段**：`php database\run_migration_openai_vision_columns.php`（Linux：`php database/run_migration_openai_vision_columns.php`）。

### Python 环境（服务器必装）
- 路径：`tools/product_style_search/`
- **PHP**：寻款 **Excel 导入** 依赖 `phpoffice/phpspreadsheet` **5.4+**；**豆包视觉** 使用 **`guzzlehttp/guzzle`** 调方舟 REST；若 composer 仍包含 **阿里云图搜 / Google** 相关包，仅在与历史导入/队列逻辑配合时需要。要求 **PHP ≥ 8.1**（需 `ext-zip`、`ext-xml`、`ext-gd` 等）。部署后务必执行 `composer install` / `composer update`。
- 依赖：在项目根执行 `pip install -r tools/product_style_search/requirements.txt`（`torch`、`torchvision`、`Pillow`）；建议用与 Web 将调用的同一解释器，例如 `py -3 -m pip install -r tools/product_style_search/requirements.txt`（Windows）或 `python3 -m pip ...`（Linux）。
- 配置：`config/product_search.php` 中 `python_bin`（由 `PRODUCT_SEARCH_PYTHON` 覆盖）、**`import_ai_usleep_microseconds`**（异步导入每行 AI 后的微秒级休眠，默认 **`0`（最快）**，可用环境变量 **`PRODUCT_STYLE_IMPORT_AI_USLEEP`** 覆盖；若遇方舟限流可设为 `100000`～`200000`）。**导入豆包描述**已改为**每行仅 1 次** `chat/completions`（合并原指纹+全量两步）。**未配置环境变量时**：Windows 在代码侧使用 `py -3`，Linux/macOS **默认即为 `python3`**。**Web 进程的 PATH 往往与 shell 不同**，若仍提示「环境未就绪」，请设置 `PRODUCT_SEARCH_PYTHON` 为解释器绝对路径（Linux 常见：`/usr/bin/python3`；Windows：`…\python.exe`）。
- 自检：用**与 PHP 相同身份**在命令行执行一次 `python embed_image.py 某张.jpg`（路径按你的配置），应输出一行 JSON 数组。`ProductStyleEmbeddingService` 会依次尝试 **`exec` → `proc_open` → `shell_exec`** 拉取子进程输出；若 `php.ini` 的 **`disable_functions` 把三者都禁用**，则无法从 PHP 调 Python，需在配置中**至少放行其一**（常见仅禁用 `exec` 时，`proc_open` 仍可用）。
- 说明：`tools/product_style_search/README.md`

### 配置
- **导入时是否生成描述**：后台 **设置 → 豆包视觉** 勾选「导入时生成描述」并**保存**；存储键名仍为 **`openai_describe_on_import`**（历史兼容），由 `VisionOpenAIConfig::get()['describe_on_import']` 读取。表单对「导入描述」「启用豆包」使用 **hidden=0 + checkbox=1**，避免只改其它配置保存时，因 HTML 不提交未勾选框而误写入 **关闭**。**方舟 API Key**：优先读 **Access Key** 框；若为空则使用 **Secret Key** 框（与 Access 二选一即可）。
- **`config/services.php`**：`volc_ark`（`access_key`/`VOLC_ACCESS_KEY`、`model_id`/`VOLC_ARK_MODEL`、`endpoint_id`/`VOLC_ENDPOINT_ID`、`base_url`/`VOLC_ARK_BASE_URL`、`max_catalog_items`、`auto_match_catalog_limit`/`VOLC_ARK_AUTO_CATALOG`（默认 **50**）、`auto_match_max_tokens`/`VOLC_ARK_AUTO_MATCH_MAX_TOKENS`（单行编号输出，默认 **64**）等）。后台 **`volc_ark_max_catalog`** 为索引侧上限；**全自动寻款**实际注入豆包的条数为 **`min(auto_match_catalog_limit, max_catalog_items)`**（代码见 `VolcArkVisionConfig`）。
- **Base URL**：须为 **`https://ark.cn-beijing.volces.com/api/v3`** 这类**根路径**（**不要**填到 `/chat/completions`）；代码会自动拼接 **`/chat/completions`**。若误填完整接口 URL，会变成双重路径，易出现 **HTTP 500 / InternalServiceError**。`VolcArkVisionConfig` 会自动去掉末尾的 **`/chat/completions`** 以容错。
- **`VOLC_ARK_VERIFY_SSL`**（可选）：Guzzle 访问方舟 HTTPS 是否校验证书，默认开启。Windows 本地若 **`runtime/log`** 出现 **`[volc_ark] 豆包网络异常`** 且含 **`curl_errno` 60**（SSL certificate problem），可在 `.env` 临时设 **`VOLC_ARK_VERIFY_SSL=false`** 验证是否证书链问题；**生产环境 Linux** 建议保持 `true`，并在 `php.ini` 配置 **`openssl.cafile`** 指向有效 CA 包（如 `cacert.pem`）。日志中已输出 **`curl_errno` / `curl_error` / `exception`** 便于对照。
- **连通性自测（推荐）**：项目根命令行执行 **`php scripts/volc_ark_ping.php`**（Windows：`php scripts\volc_ark_ping.php`）。脚本与后台共用配置，发**一条极简文本** `chat/completions`，输出 **`http`、`ark_error`、`body_snip`、`hint`**。若 **ping 成功** 而导入仍提示「未生成 AI 描述」，多为**识图请求**（大图、data URL）问题；若 **ping 也 HTTP 失败**，请根据 **`ark_error`** 检查 Key、**`ep-` 接入点是否为对话类**、`VOLC_ARK_BASE_URL` 地域是否与控制台一致。
- 环境变量示例：`VOLC_ACCESS_KEY`、**`VOLC_ARK_MODEL`**（请求体 model）、`VOLC_ENDPOINT_ID`（未设 model 时作 ep- 兜底）、`VOLC_ARK_BASE_URL`、`VOLC_ARK_VERIFY_SSL`；若仍需阿里云/Google 旧数据，可继续在库中保留 `aliyun_is_*`、`google_ps_*` 等键（**设置页已不提供编辑**）。

### 后台路由（`admin.php`，需登录）
- `GET /admin.php/product_search`：索引管理页（导入 CSV、列表、打开 H5）
- `GET /admin.php/product_search/list`：列表 JSON（`keyword`、`page`、`page_size`），并返回 `python_ok`、`python_diag`、**`vision_openai_enabled`**（恒为 `false`，兼容旧前端）、**`vision_describe_on_import`**、**`vision_any_provider_ready`**（**仅豆包**：与 `volc_ark_enabled` 一致）、**`vision_items_with_desc`**、**`aliyun_is_enabled`**、**`aliyun_is_pending`**、**`google_ps_enabled`**、**`volc_ark_enabled`**
- `POST /admin.php/product_search/importCsv`：`multipart` 字段 `file`；**`.csv` / `.txt` / `.xlsx` / `.xls` / `.xlsm`** 均为**异步任务**。上传成功后立即返回 **`data.mode=async`**、**`data.task_id`**。**AI 描述**：须在 **设置** 勾选「导入时生成描述」并 **启用豆包 + API Key + model**（模型 ID 或 ep-）；异步路径 **`describeForImport`** → **`describeImportSinglePassImage`**（`purpose=describe_import_single`）。**Excel** 依赖 **`phpoffice/phpspreadsheet`**。任务 **`total_rows` / 进度百分比** 按 **有效数据行** 计数（与单元格解析一致：编号、图片列文本、嵌入图**全空**的行不计入），不再用 `getHighestRow()` 把尾部空行算进分母。**`.xlsx`/`.xlsm`**：任务初始化阶段 **`ProductStyleXlsxDrawingRowMapBuilder::build`** 先批量提取嵌入图（**`getDrawingCollection()` + `getInCellDrawingCollection()`** 等）并按行落盘 **`public/uploads/products/{md5}.ext`**，行映射写入 **`runtime/style_import_tasks/drawing_map_{task_id}.json`**，随后 tick 中 **`readNextSubstantiveRowSingleLoad(..., publicRoot, rowMap)`** 按行读文本并关联路径（先落盘再扫行，减轻内存占用）。**异步 tick** 每次 HTTP 对当前工作簿 **完整加载一次** 再顺序扫行。**Excel 每有效数据行拆成两次 tick**：第一次读行并解析图片后写入 `runtime/style_import_tasks/pending_{task_id}.json` 并立即返回（日志可见「图片已解析」）；第二次再执行 **Python 向量 + 豆包 + 入库**。**导入任务不再**写入 Google 索引、**不再**入队阿里云图搜；`POST …/syncAliyunQueue` 返回提示已停用。**HTTP 413**：调 Nginx 与 PHP 上传上限。
- `GET /admin.php/product_search/importTaskStatus`：查询参数 **`task_id`**，只读任务进度与日志（不推进）。
- `POST /admin.php/product_search/importTaskTick`：JSON 或表单 **`task_id`**，**推进一行**（或完成表头解析/收尾），返回 `status`、`total_rows`、`processed_rows`、`percent`、`logs`（数组）、`done` 等。前端每 **2 秒**轮询一次直至 `done=true`。**PHP-FPM** 请将 **`request_terminate_timeout`** 设为 **`0`** 或明显大于单次 tick 最慢耗时（含 Python 与豆包），否则长任务可能在 tick 阶段被中断。
- `POST /admin.php/product_search/syncAliyunQueue`：**已停用**（固定返回错误说明；导入不再使用阿里云队列）。
- `POST /admin.php/product_search/delete/<id>`：删除一条索引
- `POST /admin.php/product_search/batchDelete`：批量删除；JSON body `{"ids":[1,2,3]}`（单次最多 500 条）
- `POST /admin.php/product_search/update/<id>`：编辑；`multipart`：`product_code`（必填）、`hot_type`、`image_ref`（修改链接/路径会**重算向量**）；可选文件字段 `image` 上传新图覆盖。若已启用豆包且**更换了参考图**，会尝试 **重新生成 `ai_description`** 并同步 `products`。
- `GET /admin.php/product_search/sampleCsv`：下载示例 CSV
- `GET /admin.php/product_search/exportCsv`：全量导出索引 CSV（UTF-8 BOM；列含 `id`、`product_code`、`image_ref`、（若库表有该列）`image_path`、`hot_type`、`ai_description`、`status`、时间戳；**不含** `embedding` 向量，避免文件过大）

### 开放 API（无需登录，供仓库手机端 H5）
- `POST /index.php/api/product_search/searchByImage`：由 **`app\controller\api\Search@searchByImage`** 处理；**仅豆包**：须在后台启用火山方舟并配置完整，否则返回错误提示。`multipart/form-data`，字段名 **`file`**；可选 **`hint`**。成功时 `data.engine`=`volc_ark` 时 **`data.auto_match`=true**、**`data.matched_code`** 与 **单条** `items`（含索引图、商品信息）；关键词回退时 `volc_ark_keyword`（`data.fallback`=true）。`items` 含 `product_code`、`similarity`、`match_reason`、`product` 等。
- `POST /index.php/api/search/searchByImage`：同上（别名路径）。
- `GET /index.php/api/product_search/searchByCode?q=`：编号 **LIKE** 模糊匹配（仍由 `ProductSearch` 提供）。

### H5 页面
- `GET /index.php/searchByImage`：拍照 / 选图 / 编号查询 / **可选补充说明**；以图寻款上传前 **中心正方形裁剪**（弱化背景）再 **压缩**（长边约 **1600**、体积目标约 **1MB** 以内）；加载态 **「AI 正在比对款式…」**；若 **`data.fallback`** 为真会显示关键词回退说明。H5 请求 API 使用 **`Request::baseFile()`** 动态拼接路径，**子目录部署**（如 `…/public/index.php/searchByImage`）时不再误用站点根 `/index.php/api/...`。**寻款清单**：除有 **AI 描述** 或 **爆款类型** 外，**仅有参考图**（`image_ref`）的上架款也会入清单（匹配弱于有描述，优于完全搜不到）。页面由 **`SearchByImage@index`** 注入 **`window.__H5_API_ENTRY__`**（`json_encode(baseFile())`）；Vue `setup()` 内定义 **`const API_ENTRY`** 读取该值，缺省为 **`/index.php`**，避免出现 **`API_ENTRY is not defined`**；若模板未走该控制器，头脚本也会回退 **`/index.php`**。

### CSV / Excel 列说明
- 首行表头需能识别 **产品编号**（含 **编号** 等同义）、**图片** 列（见 `ProductStyleImportService::mapHeader`）；可选 **爆款类型**。
- CSV 无数表头时按前两列为「编号、图片」解析；Excel 始终使用第一行为表头。
- **CSV**：图片列可为 `http(s)`、以 `/` 开头的站内路径、或 `data:image/...;base64,...`。
- **Excel**：将商品图**插入**到「图片」列对应行的单元格（浮动图，锚定在该格即可），无需填写 URL；亦可与 CSV 相同在单元格内填链接文本作为备选。**Microsoft 365 / Excel「将图片放置在单元格中」**：PhpSpreadsheet 将图读为单元格值（`BaseDrawing`），导入逻辑会 **读取单元格 `getValue()`** 并导出，并与 **`DrawingCollection`** 行级映射配合。映射策略为：优先「图片列」相交判定，若未命中再走「**仅按行锚点（忽略列）**」兜底，适配以嵌入图为主、列位置不规整的模板。**WPS/ET `DISPIMG` 单元格图片**（如 `=DISPIMG("ID_xxx",1)`、`=_xlfn.DISPIMG("ID_xxx",1)`）已支持：底层用 ZipArchive 读取 `xl/cellimages.xml` 与 `xl/_rels/cellimages.xml.rels`，先构建 **`ID => media` 映射表**，再按正则（`^=DISPIMG("...")` 及变体）提取 ID 取图，不再依赖 `DrawingCollection`。**ZIP/OOXML 兜底**（`ProductStyleXlsxZipEmbeddedImageService`）：若上述仍拿不到图，对 **`.xlsx`/`.xlsm`** 直接按 OOXML 解析 `xl/drawings/*.xml` 与 `xl/media/*`，按锚点匹配行列并解压图片到临时文件（**单工作表**且工作表未挂 `drawing` 关系时，会扫描全部 `drawing*.xml`）。 **zip://** 媒体在部分环境下 `fopen` 失败时会 **回退 `file_get_contents`** 读取；`data:image/...` 路径会优先本地解码，避免环境禁用 wrapper 时失败。针对锚点轻微偏移，Zip 兜底按行会做 **±1 行容错**。导入成功后嵌入图会**复制**到 `public/uploads/product_style/` 供列表展示，`image_ref` 存为站内路径（如 `/uploads/product_style/ps_xxx.jpg`）；**同时**将导入实拍图 **md5 命名**写入 **`public/uploads/products/`**，并在执行迁移 **`database/run_migration_product_style_image_path.php`** 后写入 **`product_style_items.image_path`**（供豆包等使用）。有编号但该行无可用图时，任务日志为 **`[第N行] 缺失图片对象`**，而非笼统「无法解析」。
- **CSV/TXT**：**不支持**单元格嵌入图；有编号但图片列为空时任务日志会提示改用 **xlsx** 或填链接/Base64。CSV 行入库时同样尝试将图写入 **`uploads/products/`** 并填充 **`image_path`**（若列已存在）。
- 从 Excel 另存 CSV 的说明见：`docs/耳环款式CSV说明.md`（文档面向饰品全品类，表名历史原因保留「耳环」）。

### 已适配页面
- 后台：`view/admin/product_search/index.html`（**编辑**编号/爆款/参考图；多选 + **批量删除**；「参考图」缩略图与预览）
- 前台 H5：`view/index/search_by_image.html`

### 注意
- **拍照寻款**使用豆包 **按次计费**；库内无 `ai_description` 时须先 **导入并开启描述生成**（或手工补写爆款/特征相关字段，或至少有 **参考图** 入索引）。
- 全自动寻款向豆包注入 **最近 N 条**索引（**默认 50**，受 **`VOLC_ARK_AUTO_CATALOG`** 与 **`volc_ark_max_catalog`** 共同约束）；若全库很大，候选仅为 **按 id 倒序的前 N 条**，可能漏匹配更早入库的款式，可 **提高两上限** 或 **用编号/关键词** 缩小范围。
- 向量（本地）仍为 **全表线性扫描**（适合万级以内）；**拍照流程不走本地余弦**，仅豆包视觉 + 可选关键词回退。
- 导入仍依赖 Python 抽特征时，请保证 `embed_image.py` 可用。
- **后台寻款批量导入**、手机拍照寻款：若 **HTTP 413**，先调 Nginx `client_max_body_size`，再调 PHP `upload_max_filesize` 与 `post_max_size`（三者均要覆盖最大单文件体积）。
- **HTTP 502**（Nginx Bad Gateway）常见于 **同步导入 Excel** 或 **未使用异步 CSV 时**的长请求：总时间超过 **Nginx `fastcgi_read_timeout`**、**PHP-FPM `request_terminate_timeout`**，或 **内存** 不足。**CSV 请优先走异步导入**（上传即返回 `task_id`）。**Excel** 仍建议：Nginx `fastcgi_read_timeout` / `fastcgi_send_timeout` 加大；`php.ini` 提高 `max_execution_time`、`memory_limit`（如 512M）；`www.conf` 中 `request_terminate_timeout = 600` 或 `0`；或 **拆表分批**。代码侧 `importCsv` / `tick` / Excel 入口会尝试 `set_time_limit(0)` 与 `memory_limit=512M`（受宿主策略限制）。

## 桌面端：发卡与版本、公开下载（2026-04）

### 目标
- 后台管理**桌面客户端授权码**（生成、启停、解绑机器、改到期时间与删除）。
- 后台管理**安装包版本**（发布/下线、强制更新标记、下载直链或本地上传）。
- 提供**无需登录**的客户端 API：校验授权、检查更新。
- 提供**无需登录**的公开下载页，展示最新版本与时间轴历史版本。

### 数据库
| 表名 | 说明 |
|------|------|
| `app_licenses` | `license_key`（唯一）、`machine_id`（可空）、`status`（1/0）、`expire_time`（可空=永久）、`created_at` |
| `app_versions` | `version`（唯一）、`release_notes`、`download_url`、`is_mandatory`、`status`（1发布/0下线）、`created_at` |

#### 新装
- 使用更新后的 `database/schema.sql`（已含上述两表）。

#### 升级（已有库）
- Windows（PowerShell，项目根目录）：
  - `php database\run_migration_client_app.php`
- Linux：
  - `php database/run_migration_client_app.php`
- 亦可手动执行 `database/migrations/20260401_client_app.sql`（注意重复执行时的表已存在错误）。

### 后台路由（`admin.php`，需登录）
#### 发卡 `ClientLicense`
- `GET /admin.php/client_license`：页面（Vue3 + Element Plus）
- `GET /admin.php/client_license/list`：列表 JSON（`keyword`、`status`、`page`、`page_size`）
- `POST /admin.php/client_license/add`：单条新增（`license_key` 可空自动生成；`expire_time` 可空；`status`）或批量（`batch_count`、`valid_days`，`valid_days=0` 表示永久）
- `POST /admin.php/client_license/update/<id>`：修改 `expire_time`、`status`
- `POST /admin.php/client_license/toggle/<id>`：启停
- `POST /admin.php/client_license/unbind/<id>`：清空 `machine_id`
- `POST /admin.php/client_license/delete/<id>`：删除

#### 版本 `ClientVersion`
- `GET /admin.php/client_version`：页面
- `GET /admin.php/client_version/list`：列表 JSON（`keyword`、`status`、`page`、`page_size`）
- `POST /admin.php/client_version/add`：发布（`version`、`release_notes`、`download_url`、`is_mandatory`、`status`）
- `POST /admin.php/client_version/update/<id>`：同上
- `POST /admin.php/client_version/toggle/<id>`：发布/下线切换
- `POST /admin.php/client_version/delete/<id>`：删除
- `POST /admin.php/client_version/uploadPackage`：`multipart/form-data` 字段 `file`，保存至 `public/uploads/client_releases/`，返回 `{ code:0, data:{ url } }`

### 开放 API（`index.php/api/...`，无需登录）
- `POST /index.php/api/client/verifyLicense`  
  参数：`license_key`、`machine_id`  
  逻辑：校验存在且启用、未过期；未绑定则写入 `machine_id`；已绑定则必须与传入一致。  
  成功：`{ code:0, msg:'ok', data:{ valid:true, expire_time } }`
- `GET` 或 `POST` `/index.php/api/client/checkUpdate`  
  参数：`current_version`（如 `1.0.0`）  
  逻辑：在 `status=1` 的记录中取**语义版本号大于**当前版本的最新一条（`version_compare`）。  
  无更新：`data.has_update=false`；有更新：返回 `version`、`release_notes`、`download_url`、`is_mandatory`。

### 公开下载页（SSR）
- `GET /index.php/download` 或 `GET /index.php/index/download`
- 控制器：`app\controller\index\Download@index`
- 模板：`view/index/download.html`（Tailwind CDN + 极简排版；数据服务端渲染）
- 仅展示 `app_versions.status=1`，按发布时间倒序；首条为「最新版本」，其余为时间轴「历史版本」。

### 前端说明
- 侧栏位于 **系统** 分组：`发卡`、`版本`（`view/admin/common/layout.html`）。
- 列表页使用统一骨架类：`admin-page-container`、`admin-modern-card`、`admin-header-actions`、`admin-filter-section`、`admin-footer-pagination`。
- **版本**列表：表格含 **「下载」** 列（新窗口打开该版本的 `download_url`；站内相对路径会自动补全域名）；右上角 **「公开下载页」** 打开 `/index.php/download`（给员工/用户看的落地页）。
- 多语言键：`public/static/i18n/i18n.js` 中 `admin.menu.clientLicense` / `admin.menu.clientVersion`。

### 故障排查（版本页提示「Unexpected token '<'」等 JSON 解析错误）
- 多为接口返回了 **HTML**（登录页、404、PHP 报错页），而前端按 JSON 解析。请确认：已登录后台；服务器已部署含 `client_version` 路由的代码；已执行迁移存在 `app_versions` 表。
- 前端已对发卡/版本接口请求携带 `Accept: application/json`，未登录时中间件返回 `{code:401}` 而非跳转 HTML；保存/上传异常时控制器尽量返回 JSON 错误信息。

### 版本安装包上传 HTTP 413（Content Too Large）
- **含义**：请求体在到达 PHP 前被 **Web 服务器**拒绝（常见默认仅 1MB～几十 MB），响应多为 HTML，故前端曾提示「非 JSON」。
- **处理**（Linux 部署示例，按实际环境调整数值）：
  - **Nginx**：在 `http` / `server` / `location` 中增加或调大，例如 `client_max_body_size 256m;`，重载 Nginx。
  - **PHP**：`php.ini` 中 `upload_max_filesize`、`post_max_size` 均须 **大于** 安装包体积（如 `256M`），修改后重启 PHP-FPM / Apache。
  - **Apache**：可用 `LimitRequestBody`（字节）放宽限制。
  - **Windows IIS**：在站点 `web.config` 中调整 `requestLimits` / `maxAllowedContentLength`（单位字节）。
- **替代方案**：大文件不必走本地上传，在「版本」表单中直接填写 **安装包直链**（对象存储/CDN URL）。

### Windows 测试建议
1. 执行迁移后登录后台，在「发卡」批量生成若干条，在「版本」发布一条并填下载链接或上传附件。
2. 浏览器访问 `http://你的站点/index.php/download` 查看公开页。
3. 使用 Postman 或 curl 调用 `api/client/verifyLicense` 与 `api/client/checkUpdate`。

### 第三方桌面客户端接入（发给外部队开发）
- 独立说明文档：`docs/client-desktop-api.md`（配置项、URL、参数、返回示例、`machine_id` 建议、curl 示例）。
