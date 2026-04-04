# 多平台视频管理系统

## 项目简介

一个基于 ThinkPHP 6.0 开发的多平台视频管理系统，支持 TikTok、虾皮等多个平台，可以批量上传、管理视频，并根据设备IP自动分配未下载的视频。

## 核心功能

### 1. 多平台管理
- ✅ 支持多个平台（TikTok、虾皮等）
- ✅ 平台信息管理（名称、代码、图标）
- ✅ 平台状态控制

### 2. 设备管理
- ✅ 自动识别设备IP地址
- ✅ 设备与平台关联
- ✅ 设备信息管理
- ✅ 支持多设备同时使用

### 3. 视频管理（核心功能）

#### 批量上传
- ✅ 拖拽上传多个视频文件
- ✅ 支持批量设置标题
- ✅ 支持批量上传封面图
- ✅ 实时预览视频
- ✅ 上传进度显示

#### 批量编辑
- ✅ 批量修改视频标题
- ✅ 批量修改封面URL
- ✅ 批量修改视频URL
- ✅ 支持选择多个视频进行批量操作

#### 单个编辑
- ✅ 编辑视频信息
- ✅ 替换封面图
- ✅ 替换视频文件
- ✅ 修改标题

#### 列表管理
- ✅ 视频列表展示
- ✅ 按平台筛选
- ✅ 按设备筛选
- ✅ 按下载状态筛选
- ✅ 分页显示
- ✅ 批量删除

### 4. 前台展示（手机端）
- ✅ 根据IP自动识别设备
- ✅ 根据平台显示对应视频
- ✅ 显示未下载的视频
- ✅ 视频标题、封面、视频播放
- ✅ 一键复制标题到剪贴板
- ✅ 下载封面图到云手机
- ✅ 下载视频到云手机
- ✅ 下载后自动显示下一个视频

### 5. API接口
- ✅ 获取视频接口（根据IP和平台）
- ✅ 代理下载接口（支持流式传输，解决跨域和大文件下载问题）
- ✅ 自动创建设备记录

### 6. 商品与达人分发（全局下载状态）
- ✅ 后台「商品」维护商品名称与可选商品页外链
- ✅ 视频在列表/编辑/批量上传时可绑定「所属商品」
- ✅ 「达人链」为商品生成达人链接；达人打开 `index.php/d/{token}` 随机获得该商品下一条**未下载**视频
- ✅ 视频下载后 `is_downloaded` 全局为已下载，任意达人链接均不会再随机到该条

### 7. 系统设置与默认封面
- ✅ 后台「设置」：存储方式（**本地** 仅写服务器 / **七牛云** 走 CDN）；七牛密钥、Bucket、域名、区域可在本页填写，**非空项覆盖** `config/qiniu.php`（留空则仍用配置文件）
- ✅ 默认封面：无封面时 API/前台使用配置的地址，未配置则用内置 `public/static/default-cover.svg`
- ✅ 站点名称等键值存于 `system_settings` 表，可扩展

## 技术栈

- **后端框架**: ThinkPHP 6.0
- **前端UI**: AdminLTE 3 + Bootstrap 5
- **数据库**: MySQL 5.7+
- **前端JS**: jQuery 3.6
- **文件上传**: 本地存储 + 七牛云CDN（可选）

## 系统架构

```
videotool/
├── app/                          # 应用目录
│   ├── controller/
│   │   ├── admin/                # 后台控制器
│   │   │   ├── Index.php        # 后台首页
│   │   │   ├── Platform.php     # 平台管理
│   │   │   ├── Device.php       # 设备管理
│   │   │   ├── Product.php      # 商品
│   │   │   ├── Distribute.php   # 分发链接
│   │   │   ├── Settings.php     # 系统设置
│   │   │   └── Video.php        # 视频管理
│   │   ├── api/                 # API控制器
│   │   │   └── Video.php        # 视频API
│   │   └── index/               # 前台控制器
│   │       ├── Index.php        # 前台首页
│   │       └── Influencer.php   # 达人取片页
│   ├── model/                    # 模型
│   │   ├── Platform.php         # 平台模型
│   │   ├── Device.php           # 设备模型
│   │   ├── Product.php          # 商品
│   │   ├── ProductLink.php      # 分发链接
│   │   ├── SystemSetting.php    # 系统设置表模型
│   │   └── Video.php            # 视频模型
│   └── middleware/               # 中间件
├── config/                       # 配置文件
│   ├── database.php             # 数据库配置
│   └── filesystem.php           # 文件系统配置
├── public/                       # 入口文件
│   ├── index.php                # 前台入口
│   ├── admin.php                # 后台入口
│   ├── static/                  # 静态资源（如默认封面 default-cover.svg）
│   └── uploads/                 # 上传目录
│       ├── videos/              # 视频文件
│       └── covers/              # 封面文件
├── route/                        # 路由
│   ├── admin.php                # 后台路由
│   └── api.php                  # API路由
├── view/                         # 视图
│   ├── admin/                   # 后台视图
│   │   ├── index/               # 首页
│   │   ├── platform/            # 平台管理
│   │   ├── device/              # 设备管理
│   │   ├── product/             # 商品
│   │   ├── distribute/          # 分发
│   │   ├── settings/          # 设置
│   │   └── video/               # 视频管理
│   └── index/                   # 前台视图
├── database/                     # 数据库
│   └── migrations/              # 迁移文件
└── README.md                     # 说明文档
```

## 数据库设计

### platforms 平台表
- id: 主键
- name: 平台名称
- code: 平台代码（唯一）
- icon: 平台图标
- status: 状态（1启用/0禁用）
- created_at/updated_at: 时间戳

### devices 设备表
- id: 主键
- platform_id: 平台ID
- ip_address: IP地址
- device_name: 设备名称
- status: 状态
- created_at/updated_at: 时间戳

### products 商品表
- id: 主键
- name: 商品名称
- goods_url: 商品页外链（可选）
- status: 状态（1启用/0禁用）
- sort_order: 排序

### product_links 达人分发链接表
- id: 主键
- product_id: 商品ID
- token: 令牌（唯一）
- label: 备注（可选）
- status: 状态（1启用/0禁用）

### videos 视频表
- id: 主键
- platform_id: 平台ID
- device_id: 设备ID（可空；达人素材绑定商品时可不选设备）
- product_id: 所属商品ID（可空，绑定后参与达人随机取片）
- title: 视频标题
- cover_url: 封面URL
- video_url: 视频URL
- is_downloaded: 是否已下载（0未下载/1已下载）
- sort_order: 排序
- created_at/updated_at: 时间戳

### system_settings 系统设置表
- id: 主键
- skey: 键名（唯一），如 `storage`、`default_cover_url`、`site_name`
- svalue: 值（文本）
- created_at/updated_at: 时间戳

### download_logs 下载记录表
- id: 主键
- video_id: 视频ID
- download_type: 下载类型（cover/video）
- downloaded_at: 下载时间

## 快速开始

### 5分钟快速部署

1. **解压项目文件**
2. **安装依赖**: `composer install`（要求 **PHP ≥ 8.1**；后台「寻款」**Excel 嵌入图导入**依赖 `phpoffice/phpspreadsheet` 5.x；**拍照寻款（阿里云图搜）**依赖 `alibabacloud/imagesearch-20201214`；需 `ext-zip`、`ext-xml`、`ext-gd` 等，安装时 Composer 会提示缺失扩展）
3. **配置数据库**: 编辑 `config/database.php`
4. **导入数据库**: `mysql -u root -p videotool < database/schema.sql`  
   - 已有库升级商品/达人链：**推荐**在项目根目录执行  
     `php database/run_migration_product_distribution.php`（可重复执行，已存在的列/索引/外键会自动跳过）  
     亦可手动在 MySQL 中执行 `database/migrations/20260330_product_distribution.sql`（若某步已执行过可能报错，需自行注释重复语句）
5. **设置权限**: `chmod -R 777 public/uploads runtime`
6. **访问系统**: 
   - 前台: `http://your-domain.com/`
   - 后台: `http://your-domain.com/admin.php`

**详细安装说明请查看 [INSTALL.md](INSTALL.md)**

**快速开始指南请查看 [QUICKSTART.md](QUICKSTART.md)**

**部署打包说明请查看 [DEPLOY.md](DEPLOY.md)**

## 使用说明

### 后台管理

1. **设置**（建议优先配置）
   - 存储方式：仅本地 或 七牛云；七牛参数可在「设置」页维护，或与 `config/qiniu.php` / 环境变量组合（数据库非空优先）
   - 默认封面：无封面时的图片 URL（可留空用内置图）

2. **平台管理**
   - 添加平台（TikTok、虾皮等）
   - 设置平台代码和图标

3. **设备管理**
   - 系统会自动根据IP创建设备
   - 也可以手动添加设备

4. **商品**（可选，用于达人分发）
   - 添加商品名称与商品页链接（可选）
   - 在「达人链」中为商品生成链接，将 `index.php/d/令牌` 发给达人

5. **视频管理**
   - **批量上传**: 
     - 选择平台；若选「所属商品」则无需设备，否则需选择设备
     - 拖拽或选择多个视频文件
     - 为每个视频设置标题和封面
     - 点击上传
   - **批量编辑**:
     - 在列表中选择多个视频
     - 点击批量编辑
     - 统一修改标题、封面URL、视频URL
   - **单个编辑**:
     - 点击编辑按钮
     - 修改视频信息
     - 上传新的封面或视频

### 达人取片（分发链接）

1. 在后台「达人链」复制某商品的达人链接（`…/index.php/d/令牌`）
2. 手机浏览器打开；随机展示该商品下一条未下载视频（需视频已绑定该商品）
3. 下载视频后全局标记已下载，该条不再出现

### 前台使用（IP 设备流）

1. 手机访问系统URL
2. 系统自动识别IP和平台
3. 显示该设备未下载的视频
4. 可以：
   - 复制标题到TikTok发布
   - 下载封面图
   - 下载视频
5. 下载视频后自动显示下一个

## API接口文档

### 达人随机取片（按分发 token）
```
GET /api/video/influencerRandom?token=分发令牌
```
- 返回该商品下随机一条 `is_downloaded=0` 且 `product_id` 匹配的视频；下载后仍走 `POST /api/video/markDownloaded` 全局核销。

### 获取视频
```
GET /api/video/getVideo?platform=tiktok
Response:
{
    "code": 0,
    "data": {
        "id": 1,
        "title": "视频标题",
        "cover_url": "/uploads/covers/xxx.jpg",
        "video_url": "/uploads/videos/xxx.mp4"
    }
}
```

### 代理下载文件
```
GET /api/video/download?video_id=1&type=video

参数:
- video_id: 视频ID（必填）
- type: 下载类型，cover（封面）或 video（视频），默认为video
- format: 返回格式，json（返回JSON）或空（直接下载），默认空
- app: APP标识，1表示APP请求（等同于format=json），默认0

说明:
- 浏览器访问：直接返回文件流或302重定向到CDN，浏览器自动下载
- APP访问：使用 format=json 参数，返回JSON格式的下载URL
- 支持本地文件和七牛云等远程文件的流式传输下载
- 解决跨域下载问题，支持大文件下载
- 支持断点续传（Range请求）

响应（浏览器，format为空）:
直接返回文件流，Content-Type根据文件类型自动设置

响应（APP，format=json）:
{
    "code": 0,
    "msg": "获取成功",
    "data": {
        "video_id": 1,
        "type": "video",
        "file_url": "https://storage.banono-us.com/videos/xxx.mp4",
        "download_url": "https://your-domain.com/api/video/download?video_id=1&type=video",
        "file_name": "视频标题.mp4",
        "file_size": null
    }
}
```

#### APP使用示例

**获取下载URL（JSON格式）:**
```
GET /api/video/download?video_id=1&type=video&format=json
或
GET /api/video/download?video_id=1&type=video&app=1
```

**返回JSON后，APP可以使用以下两种方式下载:**
1. 使用原始文件URL（file_url）- 如果APP可以直接访问CDN
2. 使用代理下载URL（download_url）- 如果CDN有跨域限制，推荐使用此URL

**Android示例（使用OkHttp）:**
```java
// 先获取下载URL
String url = "https://your-domain.com/api/video/download?video_id=1&type=video&format=json";
Response response = client.newCall(new Request.Builder().url(url).build()).execute();
JSONObject json = new JSONObject(response.body().string());
String downloadUrl = json.getJSONObject("data").getString("download_url");

// 然后下载文件
Request request = new Request.Builder().url(downloadUrl).build();
Response downloadResponse = client.newCall(request).execute();
// 保存文件...
```

**iOS示例（使用AFNetworking）:**
```swift
// 先获取下载URL
let url = "https://your-domain.com/api/video/download?video_id=1&type=video&format=json"
AF.request(url).responseJSON { response in
    if let json = response.value as? [String: Any],
       let data = json["data"] as? [String: Any],
       let downloadUrl = data["download_url"] as? String {
        // 然后下载文件
        let destination: DownloadRequest.Destination = { _, _ in
            let documentsPath = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask)[0]
            let fileURL = documentsPath.appendingPathComponent("video.mp4")
            return (fileURL, [.removePreviousFile, .createIntermediateDirectories])
        }
        AF.download(downloadUrl, to: destination).response { downloadResponse in
            // 处理下载结果...
        }
    }
}
```

## 功能扩展

### 云手机下载集成
在 `app/controller/api/Video.php` 的 `download()` 方法中，需要集成云手机平台的下载API。

示例：
```php
// 调用云手机API下载文件
$cloudPhoneAPI = new CloudPhoneAPI();
$cloudPhoneAPI->download($url, $deviceId);
```

### 七牛云存储支持
系统已集成七牛云对象存储服务，支持将视频和封面自动上传到七牛云CDN。

**配置说明**：
1. **方式 A**：后台「设置」→ 七牛云：填写 Access Key、Secret Key、Bucket、访问域名、区域、**额外 CDN 域名**（密钥留空表示不修改已有后台保存值；Bucket/域名/区域/额外 CDN 留空表示使用配置文件；额外 CDN 非空时整表替换 `cdn_domains`）
2. **方式 B**：编辑 `.env` 或 `config/qiniu.php`（与方式 A 合并，数据库中非空项优先）
3. 配置中 `enabled = true`（或保持默认）且存储方式选「七牛云」时走 CDN
4. 上传文件时会按当前合并后的配置同步到七牛云

**详细配置请查看 [七牛云存储配置说明.md](七牛云存储配置说明.md)**

**功能特点**：
- ✅ 双存储策略（本地备份 + 七牛云CDN）
- ✅ 自动上传同步
- ✅ 智能回退机制
- ✅ 无缝切换
- ✅ 支持批量上传、单个编辑、分片上传

### OSS存储支持（可选）
如需使用其他OSS服务，可以扩展 `app/service/QiniuService.php` 或实现类似的服务类。

## 开发计划

- [x] 基础框架搭建
- [x] 数据库设计
- [x] 模型创建
- [x] 后台管理功能
- [x] 批量上传功能
- [x] 批量编辑功能
- [x] 前台展示功能
- [x] API接口
- [x] 代理下载接口（支持流式传输，解决跨域和大文件下载问题）
- [ ] 云手机API集成
- [x] 七牛云存储支持
- [ ] 用户登录系统（可选）
- [ ] 数据统计报表
- [ ] 视频自动生成封面

## 注意事项

1. **文件上传大小限制**
   - 修改 `php.ini`: `upload_max_filesize` 和 `post_max_size`
   - 修改 `config/filesystem.php` 中的文件大小限制

2. **IP识别**
   - 如果使用代理，需要修改 `getClientIP()` 方法
   - 确保能正确获取真实IP

3. **安全性**
   - 生产环境建议添加登录验证
   - 文件上传需要验证文件类型
   - 防止SQL注入（ThinkPHP已处理）
   - 防止XSS攻击

4. **性能优化**
   - 大文件上传建议使用分片上传
   - 视频文件建议使用CDN
   - 数据库添加索引优化查询

## 常见问题

**Q: 上传文件失败？**
A: 检查 `public/uploads` 目录权限，确保可写。

**Q: 无法识别IP？**
A: 检查服务器配置，确保能获取真实IP。

**Q: 视频无法播放？**
A: 检查视频格式，建议使用MP4格式。

**Q: 下载文件很慢或下载失败？**
A: 系统已实现代理下载功能，支持流式传输。如果下载缓慢：
1. 检查服务器网络连接
2. 如果是七牛云文件，检查CDN配置
3. 检查PHP的curl扩展是否启用
4. 查看服务器日志排查错误

## 技术支持

如有问题，请查看文档或提交Issue。

## 更新日志

### v1.0.0 (2024-01-XX)
- ✅ 初始版本发布
- ✅ 多平台支持
- ✅ 批量上传功能
- ✅ 批量编辑功能
- ✅ 前台展示功能

