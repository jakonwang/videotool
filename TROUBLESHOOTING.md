# 502错误排查指南

## 常见原因和解决方法

### 1. PHP版本问题
确保PHP版本 >= 7.2.5
```bash
php -v
```

### 2. PHP-FPM未运行（Nginx环境）
检查PHP-FPM服务是否运行：
```bash
# Windows (phpstudy)
# 在phpstudy控制面板中启动PHP-FPM

# Linux
systemctl status php-fpm
```

### 3. 检查错误日志
查看PHP错误日志：
- Windows: phpstudy日志目录
- Linux: /var/log/php-fpm/error.log

### 4. 测试PHP环境
访问：`http://your-domain/public/test.php`
如果这个页面能正常显示，说明PHP环境正常。

### 5. 检查文件权限
确保以下目录可写：
- `runtime/`
- `public/uploads/`

### 6. 检查数据库连接
确保数据库配置正确，数据库已创建。

### 7. 清除缓存
删除 `runtime/` 目录下的所有文件（保留目录）。

### 8. 检查Web服务器配置

#### Apache
确保 `.htaccess` 文件存在且正确。

#### Nginx
确保配置了正确的PHP处理：
```nginx
location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 快速诊断步骤

1. 访问 `http://your-domain/public/test.php`
   - 如果显示PHP信息，说明PHP正常
   - 如果502，检查PHP-FPM

2. 检查 `runtime/` 目录
   - 确保目录存在且可写
   - 查看是否有错误日志文件

3. 检查数据库连接
   - 确保数据库服务运行
   - 确保配置正确

4. 查看Web服务器错误日志
   - Apache: error.log
   - Nginx: error.log

## 路由控制器不存在错误

### 错误信息
```
控制器不存在:app\controller\Admin
```

### 原因
在Windows系统上开发时，文件系统不区分大小写，但在Linux系统上部署时，文件系统是区分大小写的。如果路由配置中使用 `admin.Index/index` 这种格式，ThinkPHP框架可能会将 `admin` 解析为 `Admin`（首字母大写），导致找不到控制器。

### 解决方法
路由配置应使用完整的命名空间路径，格式为：`app\controller\模块名\控制器名@方法名`

**示例：**
```php
// 错误写法（可能导致大小写问题）
Route::get('/', 'admin.Index/index');

// 正确写法（使用完整命名空间）
Route::get('/', 'app\controller\admin\Index@index');
```

### 已修复的文件
- `route/admin.php` - 后台路由配置
- `route/api.php` - API路由配置
- `route/app.php` - 前台路由配置

所有路由配置已统一使用完整命名空间格式，确保Windows和Linux系统都能正常工作。

## URL路径重复问题

### 错误现象
访问后台时URL出现重复，例如：`/admin.php/admin/admin.php/admin`

### 原因
1. 路由配置中使用了 `Route::group('admin', ...)` 添加了 `admin` 前缀
2. 视图文件中的链接也包含了 `admin.php/admin/...`
3. 入口文件 `admin.php` 已经作为后台入口，不需要再加 `admin` 前缀

### 解决方法
1. **修改路由配置**：去掉路由组的 `admin` 前缀，因为入口文件已经是 `admin.php`
2. **修改入口文件**：将默认路径从 `/admin/` 改为 `/`
3. **修改视图链接**：将所有 `admin.php/admin/...` 改为 `admin.php/...`

### 已修复的文件
- `route/admin.php` - 去掉路由组的 `admin` 前缀
- `public/admin.php` - 修改默认路径为 `/`
- `view/admin/common/layout.html` - 更新所有链接
- 所有视图文件中的链接已统一修改

现在访问后台的URL格式为：
- 首页：`/admin.php` 或 `/admin.php/`
- 平台管理：`/admin.php/platform`
- 设备管理：`/admin.php/device`
- 视频管理：`/admin.php/video`

**重要提示：** 所有URL链接必须使用以 `/` 开头的绝对路径，避免相对路径导致的重复问题。

## 路由参数格式问题

### 错误信息
```
控制器不存在:app\controller\Platform
```

### 原因
路由配置中使用的是路径参数格式（如 `edit/:id`），但视图文件中的链接使用的是查询参数格式（如 `edit?id=2`），导致路由无法匹配，框架尝试按控制器/方法的方式解析URL，从而找不到控制器。

### 解决方法
将视图文件中的链接从查询参数格式改为路径参数格式：

**错误格式：**
```html
<a href="/admin.php/platform/edit?id=2">编辑</a>
```

**正确格式：**
```html
<a href="/admin.php/platform/edit/2">编辑</a>
```

### 已修复的文件
- `view/admin/platform/index.html` - 编辑链接
- `view/admin/device/index.html` - 编辑链接
- `view/admin/video/index.html` - 编辑链接

### 路由配置说明
路由配置使用路径参数格式：
```php
Route::get('edit/:id', 'app\controller\admin\Platform@edit');
```

对应的URL格式应该是：`/admin.php/platform/edit/2`，而不是 `/admin.php/platform/edit?id=2`
