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
- ✅ 下载接口（封面/视频）
- ✅ 自动创建设备记录

## 技术栈

- **后端框架**: ThinkPHP 6.0
- **前端UI**: AdminLTE 3 + Bootstrap 5
- **数据库**: MySQL 5.7+
- **前端JS**: jQuery 3.6
- **文件上传**: 本地存储（可扩展OSS）

## 系统架构

```
videotool/
├── app/                          # 应用目录
│   ├── controller/
│   │   ├── admin/                # 后台控制器
│   │   │   ├── Index.php        # 后台首页
│   │   │   ├── Platform.php     # 平台管理
│   │   │   ├── Device.php       # 设备管理
│   │   │   └── Video.php        # 视频管理
│   │   ├── api/                 # API控制器
│   │   │   └── Video.php        # 视频API
│   │   └── index/               # 前台控制器
│   │       └── Index.php        # 前台首页
│   ├── model/                    # 模型
│   │   ├── Platform.php         # 平台模型
│   │   ├── Device.php           # 设备模型
│   │   └── Video.php            # 视频模型
│   └── middleware/               # 中间件
├── config/                       # 配置文件
│   ├── database.php             # 数据库配置
│   └── filesystem.php           # 文件系统配置
├── public/                       # 入口文件
│   ├── index.php                # 前台入口
│   ├── admin.php                # 后台入口
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

### videos 视频表
- id: 主键
- platform_id: 平台ID
- device_id: 设备ID
- title: 视频标题
- cover_url: 封面URL
- video_url: 视频URL
- is_downloaded: 是否已下载（0未下载/1已下载）
- sort_order: 排序
- created_at/updated_at: 时间戳

### download_logs 下载记录表
- id: 主键
- video_id: 视频ID
- download_type: 下载类型（cover/video）
- downloaded_at: 下载时间

## 快速开始

### 5分钟快速部署

1. **解压项目文件**
2. **安装依赖**: `composer install`
3. **配置数据库**: 编辑 `config/database.php`
4. **导入数据库**: `mysql -u root -p videotool < database/schema.sql`
5. **设置权限**: `chmod -R 777 public/uploads runtime`
6. **访问系统**: 
   - 前台: `http://your-domain.com/`
   - 后台: `http://your-domain.com/admin.php`

**详细安装说明请查看 [INSTALL.md](INSTALL.md)**

**快速开始指南请查看 [QUICKSTART.md](QUICKSTART.md)**

**部署打包说明请查看 [DEPLOY.md](DEPLOY.md)**

## 使用说明

### 后台管理

1. **平台管理**
   - 添加平台（TikTok、虾皮等）
   - 设置平台代码和图标

2. **设备管理**
   - 系统会自动根据IP创建设备
   - 也可以手动添加设备

3. **视频管理**
   - **批量上传**: 
     - 选择平台和设备
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

### 前台使用

1. 手机访问系统URL
2. 系统自动识别IP和平台
3. 显示该设备未下载的视频
4. 可以：
   - 复制标题到TikTok发布
   - 下载封面图
   - 下载视频
5. 下载视频后自动显示下一个

## API接口文档

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

### 下载文件
```
POST /api/video/download
Content-Type: application/json
Body:
{
    "video_id": 1,
    "type": "video"  // cover 或 video
}
Response:
{
    "code": 0,
    "msg": "下载成功",
    "url": "/uploads/videos/xxx.mp4"
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

### OSS存储支持
修改 `config/filesystem.php`，配置OSS存储：
```php
'disks' => [
    'oss' => [
        'type' => 'oss',
        'access_id' => 'your-access-id',
        'access_key' => 'your-access-key',
        'bucket' => 'your-bucket',
        'endpoint' => 'your-endpoint',
    ],
],
```

## 开发计划

- [x] 基础框架搭建
- [x] 数据库设计
- [x] 模型创建
- [x] 后台管理功能
- [x] 批量上传功能
- [x] 批量编辑功能
- [x] 前台展示功能
- [x] API接口
- [ ] 云手机API集成
- [ ] OSS存储支持
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

## 技术支持

如有问题，请查看文档或提交Issue。

## 更新日志

### v1.0.0 (2024-01-XX)
- ✅ 初始版本发布
- ✅ 多平台支持
- ✅ 批量上传功能
- ✅ 批量编辑功能
- ✅ 前台展示功能

