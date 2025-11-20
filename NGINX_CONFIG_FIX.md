# Nginx 配置修复指南

## 问题
访问根目录 `/` 时显示后台管理页面，而不是前台视频页面。

## 原因
Nginx 配置中可能没有正确设置默认入口文件，或者 `admin.php` 在 `index.php` 之前。

## 解决方法

### 方法一：修改 Nginx 配置（推荐）

在 Nginx 配置文件中，确保 `index` 指令正确设置：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/videotool/public;
    
    # ⚠️ 重要：明确指定 index.php 为默认入口文件
    index index.php index.html;
    
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

### 方法二：phpstudy 用户

1. 打开 phpstudy 控制面板
2. 点击"网站" → 找到你的站点 → "管理" → "配置文件"
3. 在配置文件中找到 `index` 指令，确保是：
   ```nginx
   index index.php index.html;
   ```
4. 保存并重启 Nginx

### 方法三：临时解决方案

如果无法修改 Nginx 配置，可以：
1. 直接访问 `http://your-domain.com/index.php?platform=tiktok`
2. 或者创建一个重定向文件

## 验证

访问以下URL测试：
- `http://your-domain.com/` - 应该显示前台视频页面
- `http://your-domain.com/?platform=tiktok` - 应该显示 TikTok 平台的视频
- `http://your-domain.com/admin.php` - 应该显示后台管理页面

## 注意事项

- 确保 `index.php` 在 `index` 指令的第一位
- 如果使用 Apache，`public/.htaccess` 文件已经设置了 `DirectoryIndex index.php`
- 修改配置后需要重启 Nginx

