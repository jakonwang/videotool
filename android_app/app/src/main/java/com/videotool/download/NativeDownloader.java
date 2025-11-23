package com.videotool.download;

import android.app.NotificationManager;
import android.content.ContentValues;
import android.content.Context;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.media.MediaScannerConnection;
import android.widget.Toast;
import androidx.core.app.NotificationCompat;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.io.RandomAccessFile;
import java.util.Locale;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicLong;
import okhttp3.OkHttpClient;
import okhttp3.Protocol;
import okhttp3.Request;
import okhttp3.Response;

public class NativeDownloader {
    public interface DownloadListener {
        void onComplete(boolean success, String message);
    }

    private static final int THREAD_COUNT = 4;
    private static final int CONNECT_TIMEOUT = 15;
    private static final int READ_TIMEOUT = 120;
    private static final int WRITE_TIMEOUT = 120;
    private static final String CHANNEL_ID = "download_channel";

    private final Context context;
    private final OkHttpClient httpClient;
    private final ExecutorService executorService;

    public NativeDownloader(Context context) {
        this.context = context.getApplicationContext();
        this.httpClient = new OkHttpClient.Builder()
                .connectTimeout(CONNECT_TIMEOUT, java.util.concurrent.TimeUnit.SECONDS)
                .readTimeout(READ_TIMEOUT, java.util.concurrent.TimeUnit.SECONDS)
                .writeTimeout(WRITE_TIMEOUT, java.util.concurrent.TimeUnit.SECONDS)
                .retryOnConnectionFailure(true)
                .protocols(java.util.Collections.singletonList(Protocol.HTTP_1_1))
                .build();
        this.executorService = Executors.newFixedThreadPool(THREAD_COUNT);
    }

    public void enqueueDownload(String fileName, String primaryUrl, String fallbackUrl, DownloadListener listener) {
        new Thread(() -> {
            boolean success = downloadWithUrl(fileName, primaryUrl, listener);
            if (!success && fallbackUrl != null && !fallbackUrl.isEmpty() && !fallbackUrl.equals(primaryUrl)) {
                downloadWithUrl(fileName, fallbackUrl, listener);
            }
        }).start();
    }

    private boolean downloadWithUrl(String fileName, String url, DownloadListener listener) {
        NotificationManager nm = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        int notificationId = (int) (System.currentTimeMillis() / 1000);
        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.stat_sys_download)
                .setContentTitle(fileName)
                .setContentText("准备下载...")
                .setOnlyAlertOnce(true)
                .setOngoing(true)
                .setPriority(NotificationCompat.PRIORITY_LOW);
        if (nm != null) nm.notify(notificationId, builder.build());

        try {
            long fileSize = fetchContentLength(url);
            File tempFile = File.createTempFile("native_dl_", ".part", context.getCacheDir());
            
            // 如果无法获取文件大小，使用单线程流式下载
            if (fileSize <= 0) {
                android.util.Log.w("NativeDownloader", "无法获取文件大小，使用流式下载: " + url);
                return downloadStreaming(url, tempFile, fileName, nm, notificationId, builder, listener);
            }
            
            // 获取到文件大小时，使用多线程分片下载
            RandomAccessFile raf = new RandomAccessFile(tempFile, "rw");
            raf.setLength(fileSize);
            raf.close();

            AtomicLong downloadedBytes = new AtomicLong(0);
            AtomicBoolean hasError = new AtomicBoolean(false);
            CountDownLatch latch = new CountDownLatch(THREAD_COUNT);
            long chunkSize = (long) Math.ceil(fileSize * 1.0 / THREAD_COUNT);

            for (int i = 0; i < THREAD_COUNT; i++) {
                long start = i * chunkSize;
                long end = Math.min(fileSize - 1, (i + 1) * chunkSize - 1);
                executorService.execute(new SegmentTask(url, tempFile, start, end, downloadedBytes, fileSize, latch, hasError, nm, notificationId, builder));
            }

            latch.await();

            if (hasError.get()) {
                tempFile.delete();
                if (listener != null) listener.onComplete(false, "下载失败，已自动重试备用链接");
                if (nm != null) nm.cancel(notificationId);
                return false;
            }

            if (saveToGallery(tempFile, fileName)) {
                if (listener != null) listener.onComplete(true, "下载完成");
                if (nm != null) {
                    builder.setContentText("下载完成")
                            .setOngoing(false)
                            .setSmallIcon(android.R.drawable.stat_sys_download_done)
                            .setProgress(0, 0, false);
                    nm.notify(notificationId, builder.build());
                    new Thread(() -> {
                        try { Thread.sleep(3000); } catch (InterruptedException ignored) {}
                        nm.cancel(notificationId);
                    }).start();
                }
                tempFile.delete();
                return true;
            } else {
                tempFile.delete();
                if (listener != null) listener.onComplete(false, "保存失败");
                if (nm != null) nm.cancel(notificationId);
                return false;
            }
        } catch (Exception e) {
            if (listener != null) listener.onComplete(false, e.getMessage());
            if (nm != null) nm.cancel(notificationId);
            return false;
        }
    }

    private long fetchContentLength(String url) throws IOException {
        Request req = new Request.Builder()
                .url(url)
                .head()
                .header("Referer", "https://videotool.banono-us.com/")
                .header("User-Agent", "Mozilla/5.0 (Linux; Android) VideotoolApp")
                .build();
        try (Response response = httpClient.newCall(req).execute()) {
            if (!response.isSuccessful()) {
                android.util.Log.w("NativeDownloader", "HEAD请求失败: HTTP " + response.code() + " - " + url);
                // 不抛出异常，返回-1以触发流式下载
                return -1;
            }
            long contentLength = response.body() != null ? response.body().contentLength() : -1;
            if (contentLength <= 0) {
                android.util.Log.w("NativeDownloader", "HEAD请求成功但无Content-Length: " + url);
            } else {
                android.util.Log.d("NativeDownloader", "获取到文件大小: " + contentLength + " bytes - " + url);
            }
            return contentLength;
        } catch (Exception e) {
            android.util.Log.w("NativeDownloader", "HEAD请求异常: " + e.getMessage() + " - " + url);
            // 异常时返回-1，触发流式下载
            return -1;
        }
    }

    private boolean saveToGallery(File tempFile, String fileName) {
        try {
            String mime = guessMimeType(fileName);
            boolean isVideo = mime.contains("video");
            Uri uri;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ContentValues values = new ContentValues();
                values.put(MediaStore.MediaColumns.DISPLAY_NAME, fileName);
                values.put(MediaStore.MediaColumns.MIME_TYPE, mime);
                values.put(MediaStore.MediaColumns.RELATIVE_PATH, isVideo ? "Movies/VideoTool" : "Pictures/VideoTool");
                Uri external = isVideo ? MediaStore.Video.Media.EXTERNAL_CONTENT_URI : MediaStore.Images.Media.EXTERNAL_CONTENT_URI;
                uri = context.getContentResolver().insert(external, values);
                if (uri == null) throw new FileNotFoundException("Cannot create media entry");
                try (InputStream in = new java.io.FileInputStream(tempFile);
                     java.io.OutputStream out = context.getContentResolver().openOutputStream(uri)) {
                    if (out == null) throw new FileNotFoundException("输出流为空");
                    byte[] buf = new byte[8192];
                    int len;
                    while ((len = in.read(buf)) != -1) {
                        out.write(buf, 0, len);
                    }
                }
            } else {
                File downloads = isVideo
                        ? new File(android.os.Environment.getExternalStoragePublicDirectory(android.os.Environment.DIRECTORY_MOVIES), "VideoTool")
                        : new File(android.os.Environment.getExternalStoragePublicDirectory(android.os.Environment.DIRECTORY_PICTURES), "VideoTool");
                if (!downloads.exists()) downloads.mkdirs();
                File dest = new File(downloads, fileName);
                try (InputStream in = new java.io.FileInputStream(tempFile);
                     FileOutputStream out = new FileOutputStream(dest)) {
                    byte[] buf = new byte[8192];
                    int len;
                    while ((len = in.read(buf)) != -1) {
                        out.write(buf, 0, len);
                    }
                }
                uri = Uri.fromFile(dest);
            }

            android.media.MediaScannerConnection.scanFile(context,
                    new String[]{uri.toString()},
                    new String[]{mime},
                    (path, scannedUri) -> {});
            return true;
        } catch (Exception e) {
            Toast.makeText(context, "保存失败: " + e.getMessage(), Toast.LENGTH_LONG).show();
            return false;
        }
    }

    private String guessMimeType(String fileName) {
        String lower = fileName.toLowerCase(Locale.US);
        if (lower.endsWith(".mp4")) return "video/mp4";
        if (lower.endsWith(".mov")) return "video/quicktime";
        if (lower.endsWith(".jpg") || lower.endsWith(".jpeg")) return "image/jpeg";
        if (lower.endsWith(".png")) return "image/png";
        return "application/octet-stream";
    }

    private class SegmentTask implements Runnable {
        private final String url;
        private final File tempFile;
        private final long start;
        private final long end;
        private final AtomicLong downloaded;
        private final long totalSize;
        private final CountDownLatch latch;
        private final AtomicBoolean hasError;
        private final NotificationManager nm;
        private final int notificationId;
        private final NotificationCompat.Builder builder;

        SegmentTask(String url, File tempFile, long start, long end,
                    AtomicLong downloaded, long totalSize, CountDownLatch latch,
                    AtomicBoolean hasError, NotificationManager nm, int notificationId,
                    NotificationCompat.Builder builder) {
            this.url = url;
            this.tempFile = tempFile;
            this.start = start;
            this.end = end;
            this.downloaded = downloaded;
            this.totalSize = totalSize;
            this.latch = latch;
            this.hasError = hasError;
            this.nm = nm;
            this.notificationId = notificationId;
            this.builder = builder;
        }

        @Override
        public void run() {
            if (hasError.get()) {
                latch.countDown();
                return;
            }
            Request request = new Request.Builder()
                    .url(url)
                    .header("Referer", "https://videotool.banono-us.com/")
                    .header("User-Agent", "Mozilla/5.0 (Linux; Android) VideotoolApp")
                    .header("Accept-Encoding", "identity")
                    .header("Range", "bytes=" + start + "-" + end)
                    .build();
            try (Response response = httpClient.newCall(request).execute()) {
                if (!response.isSuccessful() || response.body() == null) {
                    hasError.set(true);
                    latch.countDown();
                    return;
                }
                InputStream in = response.body().byteStream();
                RandomAccessFile raf = new RandomAccessFile(tempFile, "rw");
                raf.seek(start);
                byte[] buffer = new byte[8192];
                long totalRead = 0;
                long lastUpdate = 0;
                int len;
                while ((len = in.read(buffer)) != -1) {
                    raf.write(buffer, 0, len);
                    totalRead += len;
                    long downloadedNow = downloaded.addAndGet(len);
                    long now = System.currentTimeMillis();
                    if (nm != null && now - lastUpdate > 500) {
                        int progress = (int) (downloadedNow * 100 / totalSize);
                        builder.setProgress(100, progress, false)
                                .setContentText("下载中... " + progress + "%");
                        nm.notify(notificationId, builder.build());
                        lastUpdate = now;
                    }
                }
                raf.close();
                in.close();
            } catch (IOException e) {
                hasError.set(true);
            } finally {
                latch.countDown();
            }
        }
    }
    
    /**
     * 流式下载（当无法获取文件大小时使用）
     */
    private boolean downloadStreaming(String url, File tempFile, String fileName,
                                      NotificationManager nm, int notificationId,
                                      NotificationCompat.Builder builder, DownloadListener listener) {
        try {
            Request request = new Request.Builder()
                    .url(url)
                    .header("Referer", "https://videotool.banono-us.com/")
                    .header("User-Agent", "Mozilla/5.0 (Linux; Android) VideotoolApp")
                    .header("Accept-Encoding", "identity")
                    .build();
            
            try (Response response = httpClient.newCall(request).execute()) {
                if (!response.isSuccessful() || response.body() == null) {
                    android.util.Log.e("NativeDownloader", "流式下载失败: HTTP " + (response.code()));
                    if (listener != null) listener.onComplete(false, "HTTP " + response.code());
                    if (nm != null) nm.cancel(notificationId);
                    return false;
                }
                
                InputStream in = response.body().byteStream();
                java.io.FileOutputStream out = new java.io.FileOutputStream(tempFile);
                
                byte[] buffer = new byte[8192];
                long totalRead = 0;
                long lastUpdate = 0;
                int len;
                
                builder.setContentText("正在下载...")
                        .setProgress(0, 0, true); // 不确定进度
                if (nm != null) nm.notify(notificationId, builder.build());
                
                while ((len = in.read(buffer)) != -1) {
                    out.write(buffer, 0, len);
                    totalRead += len;
                    
                    long now = System.currentTimeMillis();
                    if (nm != null && now - lastUpdate > 1000) { // 每秒更新一次
                        builder.setContentText("正在下载... " + (totalRead / 1024 / 1024) + " MB");
                        nm.notify(notificationId, builder.build());
                        lastUpdate = now;
                    }
                }
                
                out.close();
                in.close();
                
                if (tempFile.length() == 0) {
                    android.util.Log.e("NativeDownloader", "流式下载失败: 文件为空");
                    tempFile.delete();
                    if (listener != null) listener.onComplete(false, "下载失败：文件为空");
                    if (nm != null) nm.cancel(notificationId);
                    return false;
                }
                
                // 保存到相册
                if (saveToGallery(tempFile, fileName)) {
                    if (listener != null) listener.onComplete(true, "下载完成");
                    if (nm != null) {
                        builder.setContentText("下载完成")
                                .setOngoing(false)
                                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                                .setProgress(0, 0, false);
                        nm.notify(notificationId, builder.build());
                        new Thread(() -> {
                            try { Thread.sleep(3000); } catch (InterruptedException ignored) {}
                            nm.cancel(notificationId);
                        }).start();
                    }
                    tempFile.delete();
                    return true;
                } else {
                    tempFile.delete();
                    if (listener != null) listener.onComplete(false, "保存失败");
                    if (nm != null) nm.cancel(notificationId);
                    return false;
                }
            }
        } catch (Exception e) {
            android.util.Log.e("NativeDownloader", "流式下载异常: " + e.getMessage(), e);
            if (tempFile.exists()) {
                tempFile.delete();
            }
            if (listener != null) listener.onComplete(false, e.getMessage());
            if (nm != null) nm.cancel(notificationId);
            return false;
        }
    }
}

