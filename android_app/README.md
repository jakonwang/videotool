# TikStar OPS Android Mobile Agent

`android_app` 是 TikStar OPS 的移动执行端，负责把后台创建的任务在 Android 手机上高效落地执行。

## 1. V1 能力范围
- 登录：复用后台登录接口 `POST /admin.php/auth/login`，不新增账号体系。
- 分端：按后台角色自动分端。
  - 商家端：`super_admin`、`operator`
  - 达人端：`viewer`
- 多语言：`zh/en/vi`（自动识别 + 手动切换 + 本地持久化）。
- 模块首页：动态读取 `GET /admin.php/mobile_console/bootstrap` 返回的菜单渲染。
- 执行边界：固定为 **自动填充 + 人工发送**。

## 2. 主要页面与模块
- `MainActivity`：
  - 启动路由（已登录 -> 模块首页；未登录 -> 登录页）。
- `com.videotool.console.LoginActivity`：
  - 登录页，支持语言切换。
- `com.videotool.console.ModuleConsoleActivity`：
  - 分端首页，按模块动态显示功能入口。
  - Creator CRM 快捷操作：
    - 创建评论预热任务（`comment_warmup`）
    - 创建私信任务（`tiktok_dm`）
    - 查看待处理任务
    - 查看设备状态
- `com.videotool.console.WebModuleActivity`：
  - 内嵌 WebView 打开后台模块页面，复用会话 Cookie。
- `com.videotool.AgentControlActivity`：
  - 移动执行中心（保留原有 Agent 任务执行能力）。

## 3. 网络接口（V1）
- `GET /admin.php/mobile_console/bootstrap`
- `POST /admin.php/auth/login`
- `POST /admin.php/auth/logout`
- `POST /admin.php/mobile_task/create_batch`
- `GET /admin.php/mobile_task/list`
- `GET /admin.php/mobile_device/list`
- `POST /admin.php/mobile_agent/pull`
- `POST /admin.php/mobile_agent/report`

## 4. 本地开发（Windows）

### 4.1 先决条件
- JDK 17（推荐 Temurin 17）
- Android SDK + ADB

### 4.2 编译
```powershell
cd android_app
$env:JAVA_HOME='C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot'
$env:Path="$env:JAVA_HOME\bin;$env:Path"
.\gradlew.bat :app:assembleDebug
```

### 4.3 安装与启动
```powershell
adb devices
adb install -r app\build\outputs\apk\debug\app-debug.apk
adb shell am start -W -n com.videotool/.MainActivity
```

## 5. Linux 部署说明
- Android 客户端与 Linux 无直接编译耦合，仅依赖后台 HTTP 接口。
- 后台部署在 Linux 时，确保：
  - `/admin.php/auth/login`
  - `/admin.php/mobile_console/bootstrap`
  - `/admin.php/mobile_task/*`
  - `/admin.php/mobile_device/*`
  - `/admin.php/mobile_agent/*`
  可用且带会话/权限策略一致。

## 6. 注意事项
- 不做无人值守自动发送（合规与封控风险控制）。
- `mobile_agent/pull|report` 走设备 token 鉴权，需先配置 `mobile_devices`。
- 若 App 无法编译，优先检查 `JAVA_HOME` 与 `adb` 连通性。

## 7. UI 风格（2026-04-08）
- 登录页、模块首页、执行中心统一为卡片化样式。
- 统一了视觉规范：渐变背景、圆角卡片、分级按钮、统一输入框样式。
- 模块菜单动态渲染项改为卡片行，`Open` 操作按钮可读性更高。
- 语言切换下拉框使用定制样式（`zh/en/vi`）。
