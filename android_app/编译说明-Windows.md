# Windows 系统编译说明

## 方法一：使用 Android Studio（推荐，最简单）

1. **下载并安装 Android Studio**
   - 访问：https://developer.android.com/studio
   - 下载并安装最新版本

2. **打开项目**
   - 启动 Android Studio
   - 选择 `Open an Existing Project`
   - 选择 `android_app` 文件夹

3. **等待 Gradle 同步**
   - Android Studio 会自动下载 Gradle 和依赖
   - 等待同步完成（右下角显示 "Gradle sync finished"）

4. **编译 APK**
   - 点击菜单 `Build` -> `Build Bundle(s) / APK(s)` -> `Build APK(s)`
   - 等待编译完成

5. **找到 APK 文件**
   - 编译完成后，点击通知栏的 `locate` 链接
   - 或者手动打开：`android_app\app\build\outputs\apk\debug\app-debug.apk`

## 方法二：使用命令行（需要先下载 Gradle Wrapper）

### 步骤 1：下载 Gradle Wrapper JAR 文件

由于 `gradle-wrapper.jar` 是二进制文件，需要手动下载：

1. **访问 Gradle 官网下载**
   - 访问：https://raw.githubusercontent.com/gradle/gradle/v8.0.0/gradle/wrapper/gradle-wrapper.jar
   - 下载文件并保存到：`android_app\gradle\wrapper\gradle-wrapper.jar`

2. **或者使用 PowerShell 下载**（在 `android_app` 目录下执行）：
   ```powershell
   # 创建目录
   New-Item -ItemType Directory -Force -Path "gradle\wrapper"
   
   # 下载 gradle-wrapper.jar
   Invoke-WebRequest -Uri "https://raw.githubusercontent.com/gradle/gradle/v8.0.0/gradle/wrapper/gradle-wrapper.jar" -OutFile "gradle\wrapper\gradle-wrapper.jar"
   ```

### 步骤 2：编译 APK

在 `android_app` 目录下，使用 PowerShell 或 CMD 执行：

```cmd
gradlew.bat assembleDebug
```

**注意**：在 Windows 上使用 `gradlew.bat`，而不是 `./gradlew`

### 步骤 3：找到 APK 文件

编译完成后，APK 文件位置：
```
android_app\app\build\outputs\apk\debug\app-debug.apk
```

## 方法三：使用在线编译服务

如果不想安装 Android Studio，可以使用在线编译服务：

### GitHub Actions（需要 GitHub 账号）

1. 将项目推送到 GitHub
2. 创建 `.github/workflows/build.yml` 工作流文件
3. 自动编译并生成 APK

### AppCenter（微软提供）

1. 注册账号：https://appcenter.ms
2. 上传项目代码
3. 自动编译 APK

## 常见问题

### Q: 提示 "gradlew.bat: No such file or directory"
A: 确保你在 `android_app` 目录下执行命令，并且已经下载了 `gradle-wrapper.jar` 文件。

### Q: 提示 "JAVA_HOME is not set"
A: 需要安装 JDK 并设置环境变量：
1. 下载安装 JDK 8 或更高版本
2. 设置环境变量 `JAVA_HOME` 指向 JDK 安装目录
3. 将 `%JAVA_HOME%\bin` 添加到 PATH 环境变量

### Q: Gradle 下载很慢
A: 可以配置国内镜像源，编辑 `gradle.properties` 文件，添加：
```properties
systemProp.http.proxyHost=127.0.0.1
systemProp.http.proxyPort=10809
systemProp.https.proxyHost=127.0.0.1
systemProp.https.proxyPort=10809
```

### Q: 编译失败
A: 
1. 确保网络连接正常
2. 尝试清理项目：`gradlew.bat clean`
3. 删除 `.gradle` 文件夹后重新编译

## 推荐方案

**强烈推荐使用 Android Studio**，因为：
- ✅ 自动处理所有依赖
- ✅ 自动下载 Gradle
- ✅ 图形界面，操作简单
- ✅ 可以实时查看编译错误
- ✅ 支持调试和测试

