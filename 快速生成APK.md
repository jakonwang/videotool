# 快速生成 APK 文件

## 🚀 最快方法：使用 PWA Builder（5分钟）

### 步骤：

1. **确保平台选择页面已部署**
   - 访问：https://videotool.banono-us.com/platforms
   - 确认可以正常显示平台列表

2. **使用 PWA Builder 生成 APK**
   - 打开：https://www.pwabuilder.com/
   - 输入：`https://videotool.banono-us.com/platforms`
   - 点击 "Start"
   - 等待分析完成
   - 点击 "Build My PWA" -> "Android"
   - 点击 "Generate Package"
   - 等待1-2分钟
   - 下载 APK 文件

---

## 🤖 自动化方法：GitHub Actions（一次配置）

如果你的项目在 GitHub 上：

1. **推送代码**
   ```bash
   git add .
   git commit -m "Add platform selection page"
   git push
   ```

2. **等待编译**
   - 访问：https://github.com/你的用户名/videotool/actions
   - 等待编译完成（约5-10分钟）

3. **下载 APK**
   - 点击编译完成的 workflow
   - 在 "Artifacts" 部分下载 `app-debug`

---

## 💻 本地编译：Android Studio

如果已安装 Android Studio：

```bash
# 1. 打开 Android Studio
# 2. 打开 android_app 文件夹
# 3. 等待 Gradle 同步
# 4. Build -> Build Bundle(s) / APK(s) -> Build APK(s)
# 5. APK 位置：app/build/outputs/apk/debug/app-debug.apk
```

---

## 📦 使用命令行（需要 JDK 和 Android SDK）

```bash
cd android_app

# Windows
gradlew.bat assembleDebug

# APK 位置
# app/build/outputs/apk/debug/app-debug.apk
```

---

## ⚡ 推荐方案

**立即获取 APK：** 使用方法一（PWA Builder），最快！

**长期使用：** 使用方法二（GitHub Actions），自动化编译！

