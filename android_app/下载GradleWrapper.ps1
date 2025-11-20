# PowerShell 脚本：下载 Gradle Wrapper JAR 文件

Write-Host "正在下载 Gradle Wrapper JAR 文件..." -ForegroundColor Green

# 创建目录
$wrapperDir = "gradle\wrapper"
if (-not (Test-Path $wrapperDir)) {
    New-Item -ItemType Directory -Force -Path $wrapperDir | Out-Null
    Write-Host "已创建目录: $wrapperDir" -ForegroundColor Yellow
}

# 下载文件
$jarUrl = "https://raw.githubusercontent.com/gradle/gradle/v8.0.0/gradle/wrapper/gradle-wrapper.jar"
$jarPath = "$wrapperDir\gradle-wrapper.jar"

try {
    Write-Host "正在从 $jarUrl 下载..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $jarUrl -OutFile $jarPath -UseBasicParsing
    Write-Host "下载成功！文件已保存到: $jarPath" -ForegroundColor Green
    Write-Host ""
    Write-Host "现在可以运行编译命令了：" -ForegroundColor Cyan
    Write-Host "  gradlew.bat assembleDebug" -ForegroundColor White
} catch {
    Write-Host "下载失败: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "请手动下载：" -ForegroundColor Yellow
    Write-Host "1. 访问: $jarUrl" -ForegroundColor White
    Write-Host "2. 保存到: $jarPath" -ForegroundColor White
    exit 1
}

