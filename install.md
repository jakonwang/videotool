# 安装指南

## 快速安装

### 1. 安装依赖
```bash
php composer.phar install
```

### 2. 配置数据库
编辑 `config/database.php`，修改数据库连接信息：
```php
'hostname' => '127.0.0.1',
'database' => 'videotool',
'username' => 'videotool',
'password' => 'heng,275113124',
```

### 3. 导入数据库

**方法一：使用phpMyAdmin导入（推荐）**
1. 打开phpMyAdmin
2. 选择数据库或创建新数据库 `videotool`
3. 点击"导入"标签
4. 选择 `database/schema.sql` 文件
5. 点击"执行"

**方法二：使用命令行导入**
```bash
mysql -u root -p videotool < database/schema.sql
```

**如果遇到字符集错误（emoji无法插入）：**
- 如果数据库已存在，先执行 `database/fix_charset.sql` 修复字符集
- 或者删除现有数据库后重新导入 `database/schema.sql`
- 确保MySQL版本 >= 5.5.3（支持utf8mb4）

### 4. 设置目录权限
确保以下目录可写：
- `public/uploads/` (上传文件目录)
- `runtime/` (运行时目录)

Windows下通常不需要设置权限，Linux/Mac下执行：
```bash
chmod -R 777 public/uploads runtime
```

### 5. 配置Web服务器

#### Apache
确保启用了 mod_rewrite 模块，`.htaccess` 文件已配置。

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/videotool/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. 访问系统

- **前台**: http://your-domain.com/
- **后台**: http://your-domain.com/admin.php

## 使用说明

### 初始化数据

1. 访问后台：`/admin.php`
2. 平台管理：添加平台（TikTok、虾皮等）
3. 设备管理：系统会自动根据IP创建设备，也可以手动添加
4. 批量上传：在视频管理中点击"批量上传"，选择平台和设备，上传视频文件

### 前台使用

1. 手机访问系统URL
2. 系统自动识别IP和平台（默认tiktok）
3. 显示该设备未下载的视频
4. 可以复制标题、下载封面、下载视频

## 常见问题

**Q: 上传文件失败？**
A: 检查 `public/uploads` 目录权限，确保可写。

**Q: 无法识别IP？**
A: 检查服务器配置，确保能获取真实IP。如果使用代理，需要修改 `app/common.php` 中的 `get_client_ip()` 函数。

**Q: 路由404错误？**
A: 确保Web服务器配置正确，Apache需要启用mod_rewrite，Nginx需要配置重写规则。

**Q: 导入数据库时提示字符集错误？**
A: 这是因为emoji字符需要utf8mb4字符集。解决方法：
1. 确保MySQL版本 >= 5.5.3
2. 如果数据库已存在，先执行 `database/fix_charset.sql` 修复字符集
3. 或者删除现有数据库后重新导入 `database/schema.sql`
4. 在phpMyAdmin中导入时，确保"字符集"选择为"utf8mb4"

