# 502错误排查指南

## 开启页面详细报错（便于复制给开发者）

ThinkPHP 是否显示**完整异常与堆栈**由环境变量 **`APP_DEBUG`** 控制（见框架 `think\App::debugModeInit()`）。项目根目录若**没有** `.env`，默认相当于关闭调试，页面可能只显示「页面错误」类提示。

**Windows / 本机调试：**

1. 在项目根目录（与 `composer.json` 同级）放置 **`.env`**，内容至少包含一行：  
   `APP_DEBUG = true`  
2. 可参考仓库中的 **`.env.example`**：`Copy-Item .env.example .env`（PowerShell）。  
3. 修改后**刷新页面**；线上 Linux 部署请改为 **`APP_DEBUG = false`**，避免泄露路径与 SQL。

**说明：** `.env` 已在 `.gitignore` 中，勿把含密钥的 `.env` 提交到 Git。

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

## 后台列表页出现 `Unexpected token '<'`

### 错误现象

后台分页列表页在打开或翻页时，浏览器控制台出现：

```text
Unexpected token '<', "<!DOCTYPE "... is not valid JSON
```

### 根因

前端把列表接口当 JSON 解析，但服务端实际返回了 HTML 页面。常见原因有两种：

1. 列表请求地址写成了未注册的路由，比如前端请求 `/admin.php/product/listJson`，而路由里只注册了 `/admin.php/product/list`
2. 未登录或路由异常，服务端返回了登录页 / 404 页面 / PHP 错误页

### 现在项目的兼容规则

后台列表接口已兼容两种地址：

- `/list`
- `/listJson`

也就是说下面两种写法都应返回 JSON：

```text
/admin.php/product/list?page=1&page_size=10
/admin.php/product/listJson?page=1&page_size=10
```

### 服务器快速验证

在 Linux 服务器上执行：

```bash
cd /www/wwwroot/你的项目目录
php think route:list | grep list
```

然后确认对应模块同时存在 `/list` 或 `/listJson` 路由。

如果刚 `git pull` 完，务必继续执行：

```bash
rm -rf runtime/temp/* runtime/cache/*
systemctl restart php-fpm || systemctl restart php-fpm.service || true
```

### 浏览器侧排查

打开开发者工具 `Network`，点开失败请求，重点看：

- `Request URL`
- `Status Code`
- `Response`

如果 `Response` 里是 `<!DOCTYPE html>`，说明返回的不是 JSON，而是页面内容，需要继续检查路由或登录态。

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
