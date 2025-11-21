# 多平台视频管理系统 - 安装部署指南

## 版本信息
- **版本**: v1.0.0
- **发布日期**: 2025-11-20
- **PHP版本要求**: >= 7.2.5
- **MySQL版本要求**: >= 5.7
- **框架**: ThinkPHP 6.1

## 一、环境要求

### 必需环境
- **PHP**: >= 7.2.5
- **MySQL**: >= 5.7（推荐 5.7+ 或 8.0+）
- **Web服务器**: Apache 2.4+ 或 Nginx 1.18+
- **Composer**: 用于安装PHP依赖

### PHP扩展要求
- `pdo_mysql` - MySQL数据库支持
- `mbstring` - 多字节字符串支持
- `fileinfo` - 文件信息检测
- `gd` 或 `imagick` - 图片处理（可选）
- `curl` - HTTP请求支持（可选）

### 检查PHP环境
```bash
php -v
php -m | grep pdo_mysql
php -m | grep mbstring
```

## 二、安装步骤

### 步骤1: 上传项目文件

将项目文件上传到服务器，建议放在以下目录：
- **Linux**: `/var/www/videotool` 或 `/home/www/videotool`
- **Windows**: `D:\phpstudy_pro\WWW\videotool` 或 `C:\www\videotool`

### 步骤2: 安装PHP依赖

进入项目根目录，执行：

```bash
# 如果已安装 Composer
composer install

# 如果没有 Composer，使用项目自带的 composer.phar
php composer.phar install
```

**注意**: 如果下载依赖较慢，可以使用国内镜像：
```bash
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

### 步骤3: 配置数据库

#### 3.1 创建数据库

登录MySQL，创建数据库：
```sql
CREATE DATABASE `videotool` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3.2 修改数据库配置

编辑 `config/database.php` 文件，修改以下配置：

```php
return [
    // 数据库类型
    'type'            => 'mysql',
    // 服务器地址
    'hostname'        => '127.0.0.1',  // 修改为你的数据库地址
    // 数据库名
    'database'        => 'videotool',   // 修改为你的数据库名
    // 用户名
    'username'        => 'root',        // 修改为你的数据库用户名
    // 密码
    'password'        => 'your_password', // 修改为你的数据库密码
    // 端口
    'hostport'        => '3306',        // 修改为你的数据库端口
    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',  // 重要：支持emoji
    ],
    // ... 其他配置保持不变
];
```

### 步骤4: 导入数据库

#### 方法一：使用phpMyAdmin导入（推荐）

1. 打开phpMyAdmin
2. 选择数据库 `videotool`
3. 点击"导入"标签
4. 选择 `database/schema.sql` 文件
5. 确保"字符集"选择为 `utf8mb4`
6. 点击"执行"

#### 方法二：使用命令行导入

```bash
mysql -u root -p videotool < database/schema.sql
```

**如果遇到字符集错误（emoji无法插入）：**

1. 如果数据库已存在，先执行 `database/fix_charset.sql` 修复字符集：
```bash
mysql -u root -p videotool < database/fix_charset.sql
```

2. 或者删除现有数据库后重新导入：
```sql
DROP DATABASE IF EXISTS videotool;
CREATE DATABASE `videotool` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 步骤5: 设置目录权限

#### Linux/Mac系统

```bash
# 设置上传目录权限
chmod -R 777 public/uploads
chmod -R 777 runtime

# 如果使用Apache，确保www-data用户有权限
chown -R www-data:www-data public/uploads runtime
```

#### Windows系统

Windows系统通常不需要设置权限，但需要确保：
- `public/uploads/` 目录存在且可写
- `runtime/` 目录存在且可写

如果遇到权限问题，右键文件夹 -> 属性 -> 安全 -> 编辑，给 `Users` 或 `IIS_IUSRS` 添加"完全控制"权限。

### 步骤6: 配置Web服务器

#### Apache配置

1. **启用mod_rewrite模块**

编辑 `httpd.conf`，确保以下行没有被注释：
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

2. **配置虚拟主机**

编辑 `httpd-vhosts.conf` 或主配置文件：

```apache
<VirtualHost *:80>
    ServerName videotool.local
    DocumentRoot "D:/phpstudy_pro/WWW/videotool/public"
    
    <Directory "D:/phpstudy_pro/WWW/videotool/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. **确保.htaccess文件存在**

`public/.htaccess` 文件应该包含：
```apache
<IfModule mod_rewrite.c>
  Options +FollowSymlinks -Multiviews
  RewriteEngine On

  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ index.php?/$1 [QSA,PT,L]
</IfModule>
```

#### Nginx配置

编辑Nginx配置文件（通常在 `/etc/nginx/sites-available/` 或 `/usr/local/nginx/conf/`）：

```nginx
server {
    listen 80;
    server_name videotool.local;  # 修改为你的域名
    root /var/www/videotool/public;  # 修改为你的项目路径
    index index.php index.html;

    # 日志
    access_log /var/log/nginx/videotool_access.log;
    error_log /var/log/nginx/videotool_error.log;

    # 上传文件大小限制
    client_max_body_size 100M;

    # 前台入口
    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }

    # 后台入口
    location ~ ^/admin\.php {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index admin.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # 修改为你的PHP-FPM地址
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问敏感目录
    location ~* (runtime|application)/ {
        return 403;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|mp4)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

**重要**: 修改配置后，重启Nginx：
```bash
nginx -t  # 测试配置
nginx -s reload  # 重新加载配置
# 或
systemctl restart nginx
```

### 步骤7: 配置PHP

#### 修改php.ini配置

编辑 `php.ini` 文件，确保以下配置：

```ini
; 文件上传大小限制
upload_max_filesize = 100M
post_max_size = 100M

; 内存限制
memory_limit = 256M

; 执行时间限制
max_execution_time = 300
max_input_time = 300

; 时区设置
date.timezone = Asia/Shanghai

; 错误报告（生产环境建议关闭）
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

**修改后重启PHP-FPM或Apache**：
```bash
# PHP-FPM
systemctl restart php-fpm

# Apache
systemctl restart httpd
# 或
service apache2 restart
```

### 步骤8: 访问系统

#### 前台访问
```
http://your-domain.com/
或
http://your-ip/
```

#### 后台访问
```
http://your-domain.com/admin.php
或
http://your-ip/admin.php
```

## 三、初始化配置

### 1. 添加平台

1. 访问后台：`http://your-domain.com/admin.php`
2. 点击左侧菜单"平台管理"
3. 点击"添加平台"
4. 填写平台信息：
   - **平台名称**: TikTok（或其他平台名称）
   - **平台代码**: tiktok（小写，用于URL参数）
   - **平台图标**: 可以输入emoji图标，如 📱
   - **状态**: 启用
5. 点击"保存"

### 2. 添加设备

设备可以通过以下方式添加：

**方式一：自动创建**
- 当手机访问前台时，系统会自动根据IP地址创建设备记录

**方式二：手动添加**
1. 访问后台 -> 设备管理
2. 点击"添加设备"
3. 选择平台
4. 填写IP地址（可选，系统会自动获取）
5. 填写设备名称（可选）
6. 点击"保存"

### 3. 上传视频

1. 访问后台 -> 视频管理
2. 点击"批量上传"
3. 选择平台和设备
4. 拖拽或选择视频文件
5. 为每个视频设置标题和封面（可选）
6. 点击"开始上传"

## 四、使用说明

### 后台管理

#### 平台管理
- 添加、编辑、删除平台
- 设置平台代码（用于前台URL参数）
- 设置平台图标（支持emoji）

#### 设备管理
- 查看所有设备
- 手动添加设备
- 编辑设备信息
- 系统会自动根据IP创建设备

#### 视频管理
- **批量上传**: 支持拖拽上传多个视频，可设置标题和封面
- **批量编辑**: 选择多个视频，统一修改标题
- **单个编辑**: 编辑单个视频信息，可替换封面和视频
- **批量删除**: 选择多个视频进行删除
- **筛选功能**: 按平台、设备、下载状态筛选

### 前台使用

1. **访问前台**
   - 手机浏览器访问：`http://your-domain.com/?platform=tiktok`
   - 系统会自动识别设备IP和平台

2. **功能说明**
   - **复制标题**: 点击"复制标题"按钮，标题会复制到剪贴板
   - **下载封面**: 点击"下载封面"按钮，封面图片会保存到手机
   - **下载视频**: 点击"下载视频"按钮，视频文件会保存到手机
   - **自动切换**: 下载视频后，自动显示下一个未下载的视频

## 五、常见问题

### Q1: 上传文件失败，提示413错误？

**原因**: 文件大小超过服务器限制

**解决方法**:
1. **PHP配置** (`php.ini`):
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   ```

2. **Nginx配置**:
   ```nginx
   client_max_body_size 100M;
   ```

3. **Apache配置** (`.htaccess`):
   ```apache
   php_value upload_max_filesize 100M
   php_value post_max_size 100M
   ```

### Q2: 数据库导入失败，提示字符集错误？

**原因**: 数据库字符集不是utf8mb4

**解决方法**:
1. 确保MySQL版本 >= 5.5.3
2. 创建数据库时指定字符集：
   ```sql
   CREATE DATABASE `videotool` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. 如果数据库已存在，执行 `database/fix_charset.sql`

### Q3: 路由404错误？

**原因**: Web服务器重写规则未配置

**解决方法**:
- **Apache**: 确保启用了 `mod_rewrite`，`.htaccess` 文件存在
- **Nginx**: 确保配置了 `rewrite` 规则（参考步骤6）

### Q4: 无法识别设备IP？

**原因**: 服务器在代理或负载均衡后面

**解决方法**:
编辑 `app/common.php` 中的 `get_client_ip()` 函数，根据实际情况修改IP获取逻辑。

### Q5: 视频无法播放？

**原因**: 视频格式不支持或路径错误

**解决方法**:
1. 确保视频格式为MP4（推荐H.264编码）
2. 检查视频文件路径是否正确
3. 检查文件权限

### Q6: 后台页面样式错乱？

**原因**: 静态资源加载失败

**解决方法**:
1. 检查 `public/` 目录权限
2. 检查Web服务器配置，确保静态文件可以正常访问
3. 清除浏览器缓存

## 六、安全建议

### 生产环境配置

1. **关闭错误显示**
   ```ini
   display_errors = Off
   ```

2. **设置强密码**
   - 数据库密码
   - 服务器密码

3. **限制后台访问**
   - 使用IP白名单
   - 或添加登录验证（需要开发）

4. **文件上传安全**
   - 系统已限制文件类型
   - 建议定期清理 `runtime/temp/` 目录

5. **定期备份**
   - 数据库备份
   - 上传文件备份

## 七、性能优化

### 1. 启用OPcache
```ini
opcache.enable=1
opcache.memory_consumption=128
```

### 2. 使用CDN
- 将视频文件上传到CDN
- 修改配置使用CDN地址

### 3. 数据库优化
- 为常用查询字段添加索引
- 定期清理过期数据

### 4. 缓存配置
- 启用Redis缓存（可选）
- 配置模板缓存

## 八、技术支持

如遇到问题，请检查：
1. `runtime/log/` 目录下的日志文件
2. Web服务器错误日志
3. PHP错误日志

## 九、更新日志

### v1.0.0 (2025-11-20)
- ✅ 初始版本发布
- ✅ 多平台支持
- ✅ 批量上传功能（支持分片上传）
- ✅ 批量编辑功能
- ✅ 单个编辑功能
- ✅ 前台展示功能
- ✅ 自动设备识别
- ✅ 下载功能

---

**祝您使用愉快！**
