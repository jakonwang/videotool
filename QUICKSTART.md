# 快速开始指南

## 5分钟快速部署

### 前提条件
- PHP >= 7.2.5
- MySQL >= 5.7
- Composer
- Web服务器（Apache/Nginx）

### 步骤1: 解压项目

```bash
unzip videotool-v1.0.0.zip
cd videotool
```

### 步骤2: 安装依赖

```bash
composer install
# 或使用项目自带的
php composer.phar install
```

### 步骤3: 配置数据库

编辑 `config/database.php`：

```php
'hostname' => '127.0.0.1',
'database' => 'videotool',
'username' => 'root',
'password' => 'your_password',
```

### 步骤4: 导入数据库

```bash
mysql -u root -p videotool < database/schema.sql
```

或使用phpMyAdmin导入 `database/schema.sql`

### 步骤5: 设置权限

**Linux/Mac:**
```bash
chmod -R 777 public/uploads runtime
```

**Windows:** 通常不需要设置

### 步骤6: 配置Web服务器

**Apache:** 确保启用 `mod_rewrite`，`.htaccess` 已配置

**Nginx:** 参考 `INSTALL.md` 中的Nginx配置

### 步骤7: 访问系统

- 前台: `http://your-domain.com/`
- 后台: `http://your-domain.com/admin.php`

## 初始化配置

### 1. 添加平台

访问后台 -> 平台管理 -> 添加平台

示例：
- 名称: TikTok
- 代码: tiktok
- 图标: 📱

### 2. 上传视频

访问后台 -> 视频管理 -> 批量上传

选择平台和设备，上传视频文件

### 3. 访问前台

手机访问: `http://your-domain.com/?platform=tiktok`

## 常见问题快速解决

**上传失败？**
- 检查 `public/uploads` 权限
- 检查PHP `upload_max_filesize` 配置

**404错误？**
- 检查Web服务器重写规则
- 确保 `.htaccess` 文件存在（Apache）

**数据库连接失败？**
- 检查 `config/database.php` 配置
- 确保数据库已创建

**详细说明请查看 `INSTALL.md`**
