# 快速生成 PWA 图标

## 问题
PWA Builder 提示图标文件不存在或无法访问。

## 解决方案

### 方法一：使用 PHP 自动生成（推荐）

**访问以下地址，会自动生成所有图标文件：**

```
https://videotool.banono-us.com/create-icons.php
```

这会自动生成：
- ✅ `icon-192.png` (192x192 像素)
- ✅ `icon-512.png` (512x512 像素)  
- ✅ `favicon.ico` (32x32 像素)

### 方法二：手动生成

1. 访问：`https://videotool.banono-us.com/generate-icons.html`
2. 点击下载按钮生成图标
3. 将文件上传到服务器的 `public` 目录

## 图标要求

PWA Builder 需要以下图标：
- **icon-192.png**: 192x192 像素 PNG 图片
- **icon-512.png**: 512x512 像素 PNG 图片

这些图标必须：
- ✅ 可以通过 HTTP/HTTPS 访问
- ✅ 返回正确的 Content-Type: image/png
- ✅ 文件确实存在（不能是 404）

## 验证图标

生成图标后，访问以下地址验证：
- https://videotool.banono-us.com/icon-192.png
- https://videotool.banono-us.com/icon-512.png

如果显示图片，说明图标已正确生成。

## 完成后

图标生成后，重新在 PWA Builder 中测试：
1. 访问：https://www.pwabuilder.com/
2. 输入：`https://videotool.banono-us.com/platforms.html`
3. 点击 "Start"
4. 现在应该可以正常识别图标了！

