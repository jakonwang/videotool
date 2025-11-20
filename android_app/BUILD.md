# Android APK 编译指南

## 快速开始

### 方法一：使用 Android Studio（最简单）

1. **下载并安装 Android Studio**
   - 访问：https://developer.android.com/studio
   - 下载并安装最新版本

2. **打开项目**
   - 启动 Android Studio
   - 选择 `Open an Existing Project`
   - 选择 `android_app` 文件夹

3. **配置服务器地址**
   - 打开 `app/src/main/java/com/videotool/MainActivity.java`
   - 找到第 20 行：`private static final String BASE_URL = "https://your-domain.com";`
   - 修改为你的实际服务器地址，例如：`https://videotool.banono-us.com`

4. **等待 Gradle 同步**
   - Android Studio 会自动下载依赖
   - 等待同步完成（右下角显示 "Gradle sync finished"）

5. **编译 APK**
   - 点击菜单 `Build` -> `Build Bundle(s) / APK(s)` -> `Build APK(s)`
   - 等待编译完成

6. **找到 APK 文件**
   - 编译完成后，点击通知栏的 `locate` 链接
   - 或者手动打开：`android_app/app/build/outputs/apk/debug/app-debug.apk`

### 方法二：使用命令行（需要安装 Android SDK）

1. **安装 Android SDK**
   - 下载 Android Studio 或 Android SDK Command Line Tools
   - 设置环境变量：
     ```bash
     export ANDROID_HOME=/path/to/android/sdk
     export PATH=$PATH:$ANDROID_HOME/tools:$ANDROID_HOME/platform-tools
     ```

2. **配置服务器地址**
   - 编辑 `app/src/main/java/com/videotool/MainActivity.java`
   - 修改 `BASE_URL` 为你的服务器地址

3. **编译 APK**
   ```bash
   cd android_app
   ./gradlew assembleDebug
   ```

4. **找到 APK 文件**
   - `app/build/outputs/apk/debug/app-debug.apk`

### 方法三：使用在线编译服务（无需安装）

如果不想安装 Android Studio，可以使用在线编译服务：

1. **GitHub Actions**（需要 GitHub 账号）
   - 将项目推送到 GitHub
   - 创建 `.github/workflows/build.yml` 工作流
   - 自动编译并生成 APK

2. **AppCenter**（微软提供）
   - 注册账号：https://appcenter.ms
   - 上传项目代码
   - 自动编译 APK

## 签名 APK（用于发布）

### 生成签名密钥

```bash
keytool -genkey -v -keystore videotool.keystore -alias videotool -keyalg RSA -keysize 2048 -validity 10000
```

### 签名 APK

```bash
jarsigner -verbose -sigalg SHA1withRSA -digestalg SHA1 -keystore videotool.keystore app-release-unsigned.apk videotool
```

### 对齐 APK（推荐）

```bash
zipalign -v 4 app-release-unsigned.apk videotool-release.apk
```

## 安装到设备

1. **启用开发者选项**
   - 设置 -> 关于手机 -> 连续点击"版本号"7次

2. **启用 USB 调试**
   - 设置 -> 开发者选项 -> 启用 USB 调试

3. **安装 APK**
   - 方法1：通过 USB 连接，使用 `adb install app-debug.apk`
   - 方法2：将 APK 传输到手机，点击安装

## 常见问题

### 1. Gradle 同步失败
- 检查网络连接
- 尝试使用 VPN 或代理
- 修改 `build.gradle` 使用国内镜像源

### 2. 编译错误
- 确保 Android SDK 已正确安装
- 检查 Java 版本（需要 JDK 8 或更高）
- 清理项目：`./gradlew clean`

### 3. APK 无法安装
- 确保已启用"未知来源"安装
- 检查 Android 版本兼容性（最低支持 Android 5.0）

### 4. 应用无法连接服务器
- 检查服务器地址是否正确
- 确保服务器支持 HTTPS（或配置网络安全策略）
- 检查网络权限是否已添加

## 修改应用信息

### 修改应用名称
编辑 `app/src/main/res/values/strings.xml`

### 修改应用图标
替换 `app/src/main/res/mipmap/ic_launcher.png`

### 修改包名
1. 修改 `app/build.gradle` 中的 `applicationId`
2. 修改 `AndroidManifest.xml` 中的 `package`
3. 移动 Java 文件到新的包路径

## 技术支持

如有问题，请查看：
- Android 官方文档：https://developer.android.com
- 项目 README.md 文件

