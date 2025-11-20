@echo off
chcp 65001 >nul
echo ========================================
echo   视频管理 Android 应用编译脚本
echo ========================================
echo.

REM 检查 gradle-wrapper.jar 是否存在
if not exist "gradle\wrapper\gradle-wrapper.jar" (
    echo [错误] 未找到 gradle-wrapper.jar 文件
    echo.
    echo 请先运行以下命令下载：
    echo   powershell -ExecutionPolicy Bypass -File 下载GradleWrapper.ps1
    echo.
    echo 或者使用 Android Studio 打开项目（推荐）
    echo.
    pause
    exit /b 1
)

echo [信息] 开始编译 APK...
echo.

REM 编译 Debug APK
call gradlew.bat assembleDebug

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo   编译成功！
    echo ========================================
    echo.
    echo APK 文件位置：
    echo   app\build\outputs\apk\debug\app-debug.apk
    echo.
) else (
    echo.
    echo ========================================
    echo   编译失败！
    echo ========================================
    echo.
    echo 请检查错误信息，或使用 Android Studio 编译
    echo.
)

pause

