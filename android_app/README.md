# 社媒素材库·云境Pro Android 应用

这是“社媒素材库·云境Pro”的 WebView 套壳应用，用于展示平台分类与素材列表，避免浏览器广告干扰并提供更沉浸的下载体验。

## 功能特点

- 📱 平台分类列表展示
- 🎬 点击平台跳转到视频页面
- 🚫 无广告干扰（使用 WebView 而非浏览器）
- 📥 支持视频下载功能
- 🎨 简洁美观的界面

## 项目结构

```
android_app/
├── app/
│   ├── src/
│   │   └── main/
│   │       ├── java/com/videotool/
│   │       │   └── MainActivity.java
│   │       ├── res/
│   │       │   ├── layout/
│   │       │   │   └── activity_main.xml
│   │       │   ├── values/
│   │       │   │   └── strings.xml
│   │       │   └── mipmap/
│   │       │       └── ic_launcher.png
│   │       └── AndroidManifest.xml
│   └── build.gradle
├── build.gradle
├── settings.gradle
└── README.md
```

## 配置说明

### 1. 修改服务器地址

在 `MainActivity.java` 中修改 `BASE_URL` 变量为你的服务器地址：

```java
private static final String BASE_URL = "https://your-domain.com";
```

### 2. 编译 APK

#### 方法一：使用 Android Studio（推荐）

1. 安装 Android Studio
2. 打开项目文件夹 `android_app`
3. 等待 Gradle 同步完成
4. 点击 `Build` -> `Build Bundle(s) / APK(s)` -> `Build APK(s)`
5. APK 文件将生成在 `app/build/outputs/apk/debug/app-debug.apk`

#### 方法二：使用命令行

```bash
# 进入项目目录
cd android_app

# 编译 APK
./gradlew assembleDebug

# APK 文件位置
# app/build/outputs/apk/debug/app-debug.apk
```

### 3. 签名 APK（用于发布）

```bash
# 生成签名密钥（首次）
keytool -genkey -v -keystore videotool.keystore -alias videotool -keyalg RSA -keysize 2048 -validity 10000

# 签名 APK
jarsigner -verbose -sigalg SHA1withRSA -digestalg SHA1 -keystore videotool.keystore app-release-unsigned.apk videotool

# 对齐 APK（可选，但推荐）
zipalign -v 4 app-release-unsigned.apk videotool-release.apk
```

## 安装说明

1. 在 Android 设备上启用"未知来源"安装
2. 将 APK 文件传输到设备
3. 点击安装

## 使用说明

1. 打开应用，显示平台分类列表
2. 点击某个平台，跳转到该平台的视频页面
3. 在视频页面可以：
   - 观看视频
   - 下载视频
   - 下载封面
   - 复制标题

## 技术栈

- Android SDK
- WebView
- Java

## 注意事项

- 确保服务器支持 HTTPS（Android 9+ 默认要求）
- 如需支持 HTTP，需要在 AndroidManifest.xml 中配置网络安全策略
- 确保服务器已配置 CORS（如果需要）

## 更新日志

### v1.0.8
- 与服务端缓存/后台缓存管理功能同步，默认 APK 版本号升级至 1.0.8，便于渠道区分
- APP 下载链路沿用 v1.0.7 的断点续传、通知权限与本地缓存机制，如需灰度可直接通过版本号控制

### v1.0.7
- 新增临时缓存文件 + 断点续传 + 自动重试 3 次，彻底解决 `unexpected end of stream`
- 下载过程全程通知栏进度，Android 13+ 会请求通知权限确保有反馈
- 后端代理增加无限超时与 Content-Length 透传，兼容千牛云长链大文件

### v1.0.6
- 彻底弃用系统 DownloadManager，全量使用内置引擎下载，彻底解决系统下载空文件问题
- 新增下拉通知栏进度条，实时显示下载进度与状态
- 强化防盗链兼容，确保 CDN 资源 100% 可下载

### v1.0.5
- APP 下载统一附带合法 Referer 与 User-Agent，兼容启用了防盗链的七牛/千牛 CDN
- DownloadManager 与 OkHttp 两路下载均补齐 Header，确保直链和代理都能成功

### v1.0.4
- CDN 直链统一交由系统 DownloadManager 处理，失败后自动回退到应用内下载并提示错误原因
- 新增 DownloadManager 任务追踪与媒体库刷新，确保视频/图片在下载完成后立即显示在相册
- 扩展 CDN 域名识别范围，兼容千牛/七牛多个加速域

### v1.0.0
- 初始版本
- 支持平台分类展示
- 支持视频播放和下载

