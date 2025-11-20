# Nginx 配置修复详细指南

## 问题
访问根目录 `/` 时显示后台管理页面，而不是前台视频页面。

## 原因
Nginx 的 `index` 指令可能包含了 `admin.php`，或者 `admin.php` 在 `index.php` 之前。

## 解决方法

### 方法一：修改 Nginx 配置文件（phpstudy）

1. **打开 phpstudy 控制面板**

2. **找到你的站点配置**
   - 点击"网站"标签
   - 找到你的站点（例如：`videotool.top`）
   - 点击"管理"按钮
   - 选择"配置文件"或"修改配置"

3. **修改 `index` 指令**
   
   在配置文件中找到 `server { }` 块，确保 `index` 指令是：
   ```nginx
   index index.php index.html;
   ```
   
   **重要：** 不要包含 `admin.php`，或者确保 `index.php` 在 `admin.php` 之前。

4. **完整的 server 块示例**
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /path/to/videotool/public;
       
       # ⚠️ 重要：index.php 必须在第一位
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

5. **保存并重启 Nginx**
   - 保存配置文件
   - 在 phpstudy 控制面板中重启 Nginx

### 方法二：直接编辑 Nginx 配置文件

如果知道 Nginx 配置文件位置：

1. **找到配置文件**
   - phpstudy 通常位于：`phpstudy安装目录/Extensions/Nginx/conf/vhosts/你的站点.conf`
   - 或者：`phpstudy安装目录/Extensions/Nginx/conf/nginx.conf`

2. **编辑配置文件**
   ```bash
   # 使用文本编辑器打开配置文件
   # 找到 server { } 块
   # 修改 index 指令
   ```

3. **重启 Nginx**
   ```bash
   # 在 phpstudy 控制面板中重启
   # 或使用命令行
   ```

### 方法三：临时解决方案

如果无法修改 Nginx 配置，可以：

1. **直接访问前台页面**
   - 前台：`http://your-domain/index.php?platform=tiktok`
   - 后台：`http://your-domain/admin.php`

2. **修改平台管理页面的链接**
   - 在后台平台管理页面，链接改为：`http://your-domain/index.php?platform=平台代码`

## 验证修复

修复后测试以下URL：

1. `http://your-domain/` 
   - ✅ 应该显示前台视频页面
   - ❌ 不应该显示后台管理页面

2. `http://your-domain/?platform=tiktok`
   - ✅ 应该显示 TikTok 平台的视频

3. `http://your-domain/admin.php`
   - ✅ 应该显示后台管理页面

## 常见问题

### Q: 修改后仍然显示后台页面？
A: 
1. 确保已重启 Nginx
2. 清除浏览器缓存
3. 检查是否有其他配置文件覆盖了设置
4. 检查 `public/.htaccess` 文件是否存在（Apache）

### Q: 如何确认当前使用的是哪个入口文件？
A: 访问 `http://your-domain/check_index.php` 查看诊断信息

### Q: 修改配置后出现 502 错误？
A: 
1. 检查 Nginx 配置语法是否正确
2. 检查 PHP-FPM 是否运行
3. 查看 Nginx 错误日志

## 配置文件位置参考

- **phpstudy Nginx 配置**：`phpstudy安装目录/Extensions/Nginx/conf/vhosts/`
- **phpstudy 主配置**：`phpstudy安装目录/Extensions/Nginx/conf/nginx.conf`
- **站点配置**：通过 phpstudy 控制面板 → 网站 → 管理 → 配置文件

