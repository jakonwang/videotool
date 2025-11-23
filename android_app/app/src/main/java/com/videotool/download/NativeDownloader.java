package com.videotool.download;

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Environment;
import android.util.Log;

/**
 * 简化版下载器 - 只使用Android DownloadManager
 * 这是Android系统级的标准下载方式，所有主流APP都在使用
 * 
 * 优势：
 * 1. 系统级下载，自动断点续传
 * 2. 后台下载，应用退出后仍可继续
 * 3. 省电优化，系统统一管理
 * 4. 自动处理网络切换、重定向
 * 5. 无需复杂逻辑，稳定可靠
 */
public class NativeDownloader {
    public interface DownloadListener {
        void onComplete(boolean success, String message);
    }

    private final Context context;

    public NativeDownloader(Context context) {
        this.context = context.getApplicationContext();
    }

    /**
     * 下载文件 - 统一使用DownloadManager（最简单可靠的方式）
     * 
     * @param fileName 文件名
     * @param url 下载URL（可以是CDN链接或代理链接，DownloadManager会自动处理重定向）
     * @param listener 下载结果回调
     */
    public void download(String fileName, String url, DownloadListener listener) {
        try {
            DownloadManager downloadManager = (DownloadManager) context.getSystemService(Context.DOWNLOAD_SERVICE);
            if (downloadManager == null) {
                Log.e("NativeDownloader", "DownloadManager服务不可用");
                if (listener != null) {
                    listener.onComplete(false, "下载服务不可用");
                }
                return;
            }

            Log.d("NativeDownloader", "开始下载: " + fileName + " - " + url);

            // 创建下载请求
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            
            // 判断文件类型
            boolean isVideo = fileName.toLowerCase().endsWith(".mp4") || 
                             fileName.toLowerCase().endsWith(".mov") ||
                             fileName.toLowerCase().endsWith(".avi") ||
                             fileName.toLowerCase().endsWith(".mkv") ||
                             fileName.toLowerCase().endsWith(".flv");
            
            // 设置下载目录
            if (isVideo) {
                request.setDestinationInExternalPublicDir(
                    Environment.DIRECTORY_MOVIES, 
                    "VideoTool/" + fileName
                );
            } else {
                request.setDestinationInExternalPublicDir(
                    Environment.DIRECTORY_PICTURES, 
                    "VideoTool/" + fileName
                );
            }
            
            // 基本设置
            request.setTitle(fileName);
            request.setDescription("VideoTool下载");
            
            // 通知设置 - 下载完成后显示通知
            request.setNotificationVisibility(
                DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED
            );
            
            // 允许移动网络和漫游下载
            request.setAllowedOverMetered(true);
            request.setAllowedOverRoaming(true);
            
            // 设置MIME类型（帮助系统识别文件类型）
            if (isVideo) {
                request.setMimeType("video/mp4");
            } else {
                request.setMimeType("image/jpeg");
            }
            
            // 添加请求头（如果需要防盗链验证）
            request.addRequestHeader("Referer", "https://videotool.banono-us.com/");
            request.addRequestHeader("User-Agent", "Mozilla/5.0 (Linux; Android) VideoToolApp");
            
            // 提交下载请求
            long downloadId = downloadManager.enqueue(request);
            
            Log.i("NativeDownloader", "下载已提交到DownloadManager - ID: " + downloadId + " - URL: " + url);
            
            // 立即返回成功（DownloadManager会在后台处理）
            // DownloadManager会自动处理：
            // 1. 302重定向到CDN
            // 2. 断点续传
            // 3. 网络切换
            // 4. 下载进度和通知
            if (listener != null) {
                listener.onComplete(true, "下载已开始，请在通知栏查看进度");
            }
            
        } catch (Exception e) {
            Log.e("NativeDownloader", "下载失败: " + e.getMessage(), e);
            if (listener != null) {
                listener.onComplete(false, "下载失败: " + e.getMessage());
            }
        }
    }

    /**
     * 下载文件（支持主备链接）
     * 优先使用主链接，如果主链接为空则使用备用链接
     * 
     * @param fileName 文件名
     * @param primaryUrl 主链接（优先使用）
     * @param fallbackUrl 备用链接（主链接为空时使用）
     * @param listener 下载结果回调
     */
    public void downloadWithFallback(String fileName, String primaryUrl, String fallbackUrl, DownloadListener listener) {
        // 优先使用主链接
        if (primaryUrl != null && !primaryUrl.isEmpty()) {
            Log.d("NativeDownloader", "使用主链接下载: " + primaryUrl);
            download(fileName, primaryUrl, listener);
        } else if (fallbackUrl != null && !fallbackUrl.isEmpty()) {
            Log.d("NativeDownloader", "主链接为空，使用备用链接: " + fallbackUrl);
            download(fileName, fallbackUrl, listener);
        } else {
            Log.e("NativeDownloader", "所有下载链接都为空");
            if (listener != null) {
                listener.onComplete(false, "下载地址无效");
            }
        }
    }
}
