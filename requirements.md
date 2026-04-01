# 功能需求与实现说明

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

### 后台侧栏（分组 + 精简名称）
| 分组 | 子菜单 | 含义 |
|------|--------|------|
| （顶栏） | 仪表盘 | 首页 |
| 素材 | 视频 / 上传 / 商品 / 达人链 | 列表、批量上传、商品、达人分发链接 |
| 终端 | 平台 / 设备 | 平台与终端设备 |
| 系统 | 系统设置 / 用户 / 发卡 / 版本 / 缓存 / 异常 | 参数、管理员、桌面授权码、桌面安装包发布、缓存、下载错误监控 |

- 侧栏分组使用 **Bootstrap collapse**（与页面 SSR 同步 `show`，避免 AdminLTE Treeview 初始化把当前分组收拢）；样式为自定义「玻璃拟态 + 霓虹描边」分组头与子项指示点，与 AdminLTE 默认 `nav-treeview` 视觉脱钩。

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
- `GET /admin.php/stats/overview`：KPI 总览（含今日上传/今日下载）
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

### 多语言切换（后台：中文/英文；达人页：越南语/英文）（2026-04）
#### 后台管理（Admin）
- 支持语言：中文（`zh`）/英文（`en`）
- URL 参数：`?lang=zh` 或 `?lang=en`
- 本地记忆：`localStorage(app_lang)`
- 语言优先级：`?lang=` > `localStorage(app_lang)` > `navigator.language` > 默认中文
- 代码位置：`public/static/i18n/i18n.js`

#### 达人取片页（Influencer）
- 支持语言：越南语（`vi`，默认）/英文（`en`）
- 与后台切换**完全独立**（不读取/不写入 `app_lang`，不使用 `?lang=`）
- URL 参数：`?ilang=vi` 或 `?ilang=en`
- 本地记忆：`localStorage(influencer_lang)`
- 语言优先级：`?ilang=` > `localStorage(influencer_lang)` > `navigator.language` > 默认越南语
- 代码位置：`public/static/i18n/influencer_i18n.js`，页面：`view/index/influencer.html`

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

### 说明
- 这些页面会隐藏 layout 自带的 `content-header`，改用页面内部的统一标题区，避免出现两套标题/操作区导致不一致。

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
- 多语言键：`public/static/i18n/i18n.js` 中 `admin.menu.clientLicense` / `admin.menu.clientVersion`。

### Windows 测试建议
1. 执行迁移后登录后台，在「发卡」批量生成若干条，在「版本」发布一条并填下载链接或上传附件。
2. 浏览器访问 `http://你的站点/index.php/download` 查看公开页。
3. 使用 Postman 或 curl 调用 `api/client/verifyLicense` 与 `api/client/checkUpdate`。
