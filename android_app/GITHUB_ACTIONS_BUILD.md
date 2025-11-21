# GitHub Actions 自动编译 APK

## 概述

本项目已配置 GitHub Actions，可以直接在 GitHub 上自动编译 Android APK，无需本地安装 Android SDK。

## 自动触发

### 方式一：自动编译（推荐）

当你将代码推送到 GitHub 的 `main` 分支时，GitHub Actions 会自动编译 APK：

1. **提交代码到 GitHub**
   ```bash
   git add .
   git commit -m "更新代码"
   git push origin main
   ```

2. **查看编译进度**
   - 访问 GitHub 仓库页面
   - 点击 `Actions` 标签
   - 查看最新的工作流运行状态

3. **下载 APK**
   - 编译完成后，点击工作流运行
   - 在 `Artifacts` 部分下载 `app-debug.apk`

### 方式二：手动触发

如果需要手动触发编译（不推送代码）：

1. 访问 GitHub 仓库
2. 点击 `Actions` 标签
3. 在左侧选择 `Build Android APK` 工作流
4. 点击 `Run workflow` 按钮
5. 选择分支（通常是 `main`）
6. 点击绿色的 `Run workflow` 按钮
7. 等待编译完成并下载 APK

## 工作流配置

工作流文件位置：`.github/workflows/build-apk.yml`

### 触发条件

- ✅ **自动触发**：当 `android_app` 目录下的文件有变化并推送到 `main` 分支
- ✅ **手动触发**：在 GitHub Actions 页面手动运行
- ✅ **Pull Request**：创建 PR 时也会自动编译

### 编译步骤

1. 检出代码
2. 设置 JDK 17
3. 安装 Python 和 Pillow（用于生成图标）
4. 设置 Android SDK
5. 创建应用图标
6. 编译 APK（Debug 版本）
7. 上传 APK 作为 Artifact

## 下载 APK

编译完成后：

1. 进入 GitHub Actions 页面
2. 点击最新完成的工作流运行
3. 向下滚动到 `Artifacts` 部分
4. 点击 `app-debug` 下载 APK 文件
5. 将 APK 传输到 Android 设备并安装

## APK 保存时间

- APK 文件会保留 **30 天**
- 超过 30 天后需要重新编译

## 修改服务器地址

如果修改了服务器地址（`BASE_URL`），需要：

1. 编辑 `android_app/app/src/main/java/com/videotool/MainActivity.java`
2. 修改 `BASE_URL` 变量：
   ```java
   private static final String BASE_URL = "https://your-domain.com";
   ```
3. 提交并推送到 GitHub
4. 等待自动编译完成
5. 下载新的 APK

## 常见问题

### 1. 编译失败？

**可能原因：**
- 代码语法错误
- Gradle 配置问题
- 依赖下载失败

**解决方法：**
- 查看 Actions 页面的错误日志
- 检查代码是否有语法错误
- 重新触发编译

### 2. 找不到 APK？

- 确保编译已完成（显示绿色勾号）
- 滚动到页面底部查看 `Artifacts` 部分
- 如果超过 30 天，需要重新编译

### 3. APK 无法安装？

- 确保 Android 设备已启用"未知来源"安装
- 检查 APK 文件是否完整下载
- 卸载旧版本后再安装新版本

### 4. 如何查看编译日志？

1. 进入 GitHub Actions 页面
2. 点击工作流运行
3. 点击 `build` 任务
4. 查看各步骤的详细日志

## 优化建议

1. **只编译变化的部分**：工作流已配置为只有 `android_app` 目录变化时才编译
2. **使用缓存**：GitHub Actions 会自动缓存 Gradle 依赖，加快编译速度
3. **手动触发**：不需要等推送，可以随时手动触发编译

## 下一步

编译完成后：
1. 下载 APK 文件
2. 传输到 Android 设备
3. 安装并测试
4. 如有问题，修改代码后重新推送触发编译

## 注意事项

- APK 是 Debug 版本，适合测试使用
- 如需发布版本，需要配置签名密钥
- 首次编译可能需要 5-10 分钟（下载依赖）
- 后续编译通常 3-5 分钟完成

