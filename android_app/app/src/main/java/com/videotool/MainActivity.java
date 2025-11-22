package com.videotool;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.DownloadManager;
import android.app.ProgressDialog;
import android.content.BroadcastReceiver;
import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.graphics.Bitmap;
import android.media.MediaScannerConnection;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.provider.MediaStore;
import android.view.MenuItem;
import android.view.View;
import android.webkit.DownloadListener;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.OkHttpClient;
import okhttp3.Protocol;
import okhttp3.Request;
import okhttp3.Response;

public class MainActivity extends AppCompatActivity {
    
    // 修改为你的服务器地址
    private static final String BASE_URL = "https://videotool.banono-us.com";
    private static final String DOWNLOAD_REFERER = BASE_URL + "/";
    private static final String DOWNLOAD_UA = "Mozilla/5.0 (Linux; Android) AppleWebKit/537.36 (KHTML, like Gecko) VideotoolApp";
    
    private RecyclerView recyclerView;
    private PlatformAdapter adapter;
    private List<Platform> platformList;
    private ProgressDialog progressDialog;
    private WebView webView;
    private View platformListView;
    private DownloadManager downloadManager;
    private long downloadId;
    private final Map<Long, DownloadTaskMeta> downloadTaskMap = new ConcurrentHashMap<>();
    private static final String[] CDN_HOST_KEYWORDS = {
            "storage.banono-us.com",
            "qiniucdn",
            "qiniudn",
            "qnssl",
            "qiniu"
    };
    
    // 权限请求码
    private static final int PERMISSION_REQUEST_CODE = 1001;
    
    // 待下载的文件信息
    private String pendingDownloadUrl;
    private String pendingFileName;
    private String pendingFallbackUrl;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        
        // 启用返回按钮
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle(getString(R.string.app_name));
        }
        
        platformListView = findViewById(R.id.platform_list_view);
        recyclerView = findViewById(R.id.recycler_view);
        webView = findViewById(R.id.web_view);
        
        // 初始化平台列表
        platformList = new ArrayList<>();
        adapter = new PlatformAdapter(platformList, new PlatformAdapter.OnPlatformClickListener() {
            @Override
            public void onPlatformClick(Platform platform) {
                openVideoPage(platform);
            }
        });
        
        recyclerView.setLayoutManager(new GridLayoutManager(this, 2));
        recyclerView.setAdapter(adapter);
        
        // 配置 WebView
        setupWebView();
        
        // 加载平台列表
        loadPlatforms();
        
        // 检查并请求存储权限（Android 6.0+）
        checkStoragePermission();
    }
    
    /**
     * 检查存储权限
     */
    private void checkStoragePermission() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
            // Android 9及以下需要WRITE_EXTERNAL_STORAGE权限
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE,
                                Manifest.permission.READ_EXTERNAL_STORAGE},
                        PERMISSION_REQUEST_CODE);
            }
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // Android 13+需要细化权限
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_VIDEO)
                    != PackageManager.PERMISSION_GRANTED ||
                ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.READ_MEDIA_VIDEO,
                                Manifest.permission.READ_MEDIA_IMAGES},
                        PERMISSION_REQUEST_CODE);
            }
        }
        // Android 10-12不需要运行时权限（使用MediaStore API）
    }
    
    /**
     * 权限请求结果处理
     */
    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQUEST_CODE) {
            boolean allGranted = true;
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    allGranted = false;
                    break;
                }
            }
            if (allGranted) {
                // 权限已授予，如果有待下载的文件，继续下载
                if (pendingDownloadUrl != null && pendingFileName != null) {
                    String primary = pendingDownloadUrl;
                    String fallback = pendingFallbackUrl;
                    String name = pendingFileName;
                    pendingDownloadUrl = null;
                    pendingFallbackUrl = null;
                    pendingFileName = null;
                    downloadWithFallback(primary, fallback, name);
                }
            } else {
                Toast.makeText(this, "需要存储权限才能保存到相册", Toast.LENGTH_LONG).show();
            }
        }
    }
    
    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setUseWideViewPort(true);
        webSettings.setBuiltInZoomControls(false);
        webSettings.setDisplayZoomControls(false);
        webSettings.setSupportZoom(false);
        webSettings.setDefaultTextEncodingName("utf-8");
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);
        
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                // 如果是下载链接，使用系统浏览器打开
                if (url.endsWith(".mp4") || url.endsWith(".jpg") || url.endsWith(".png")) {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    startActivity(intent);
                    return true;
                }
                return false;
            }
            
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                if (progressDialog != null && !progressDialog.isShowing()) {
                    progressDialog.show();
                }
            }
            
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                if (progressDialog != null && progressDialog.isShowing()) {
                    progressDialog.dismiss();
                }
            }
        });
        
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                super.onProgressChanged(view, newProgress);
            }
        });
        
        // 添加下载监听器
        downloadManager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
        webView.setDownloadListener(new DownloadListener() {
            @Override
            public void onDownloadStart(String url, String userAgent, String contentDisposition,
                                       String mimeType, long contentLength) {
                // 拦截下载请求，使用DownloadManager下载
                downloadFile(url, contentDisposition, mimeType);
            }
        });
        
        // 添加JavaScript接口，用于前端调用原生下载功能
        webView.addJavascriptInterface(new WebAppInterface(), "Android");
    }
    
    /**
     * JavaScript接口，前端可以通过 window.Android.downloadFile() 调用原生下载
     */
    public class WebAppInterface {
        @JavascriptInterface
        public void downloadFile(String url, String fileName) {
            runOnUiThread(() -> {
                downloadWithFallback(url, null, fileName);
            });
        }
        
        @JavascriptInterface
        public void downloadFileWithFallback(String primaryUrl, String fallbackUrl, String fileName) {
            runOnUiThread(() -> {
                downloadWithFallback(primaryUrl, fallbackUrl, fileName);
            });
        }
        
        @JavascriptInterface
        public void showToast(String message) {
            runOnUiThread(() -> {
                Toast.makeText(MainActivity.this, message, Toast.LENGTH_SHORT).show();
            });
        }
    }
    
    /**
     * 下载文件（兼容旧调用）
     */
    private void downloadFileWithName(String url, String fileName) {
        downloadWithFallback(url, null, fileName);
    }
    
    /**
     * 下载文件，支持主链 + 备用链
     */
    private void downloadWithFallback(String primaryUrl, String fallbackUrl, String fileName) {
        if (primaryUrl == null || primaryUrl.isEmpty()) {
            Toast.makeText(this, "无效的下载地址", Toast.LENGTH_LONG).show();
            return;
        }
        
        String normalizedFileName = ensureFileNameHasExtension(fileName, primaryUrl);
        
        // 权限检查
        boolean requiresLegacyPermission = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q;
        boolean requiresMediaPermission = Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU;
        if (requiresLegacyPermission) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE)
                    != PackageManager.PERMISSION_GRANTED) {
                pendingDownloadUrl = primaryUrl;
                pendingFallbackUrl = fallbackUrl;
                pendingFileName = normalizedFileName;
                checkStoragePermission();
                return;
            }
        } else if (requiresMediaPermission) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_VIDEO)
                    != PackageManager.PERMISSION_GRANTED ||
                ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES)
                    != PackageManager.PERMISSION_GRANTED) {
                pendingDownloadUrl = primaryUrl;
                pendingFallbackUrl = fallbackUrl;
                pendingFileName = normalizedFileName;
                checkStoragePermission();
                return;
            }
        }
        
        final String finalPrimaryUrl = primaryUrl;
        final String finalFallbackUrl = fallbackUrl;
        final String finalFileName = normalizedFileName;
        
        String primaryMime = guessMimeType(normalizedFileName, null);
        boolean isPrimaryVideo = primaryMime != null && primaryMime.toLowerCase(Locale.US).contains("video");
        boolean isPrimaryImage = primaryMime != null && primaryMime.toLowerCase(Locale.US).contains("image");

        if (isCdnUrl(finalPrimaryUrl)) {
            if (downloadViaSystemManager(finalPrimaryUrl, normalizedFileName, primaryMime, isPrimaryVideo, isPrimaryImage, finalFallbackUrl)) {
                return;
            }
        }

        new Thread(() -> {
            boolean success = attemptDownload(finalPrimaryUrl, finalFileName, true);
            if (!success && finalFallbackUrl != null && !finalFallbackUrl.isEmpty() && !finalFallbackUrl.equals(finalPrimaryUrl)) {
                runOnUiThread(() -> Toast.makeText(MainActivity.this, "主链接下载失败，尝试备用链接", Toast.LENGTH_SHORT).show());
                boolean fallbackCdn = isCdnUrl(finalFallbackUrl);
                if (fallbackCdn && downloadViaSystemManager(finalFallbackUrl, finalFileName, primaryMime, isPrimaryVideo, isPrimaryImage, null)) {
                    return;
                }
                attemptDownload(finalFallbackUrl, finalFileName, false);
            }
        }).start();
    }
    
    /**
     * 执行具体的下载并保存到相册
     */
    private boolean attemptDownload(String url, String fileName, boolean showStartToast) {
        try {
            OkHttpClient client = new OkHttpClient.Builder()
                    .connectTimeout(20, TimeUnit.SECONDS)
                    .readTimeout(5, TimeUnit.MINUTES)
                    .writeTimeout(5, TimeUnit.MINUTES)
                    .retryOnConnectionFailure(true)
                    .protocols(Collections.singletonList(Protocol.HTTP_1_1))
                    .build();
            Request request = new Request.Builder()
                    .url(url)
                    .header("Connection", "close")
                    .header("Referer", DOWNLOAD_REFERER)
                    .header("User-Agent", DOWNLOAD_UA)
                    .build();
            
            Response response = client.newCall(request).execute();
            if (!response.isSuccessful() || response.body() == null) {
                final int httpCode = response.code();
                runOnUiThread(() -> Toast.makeText(MainActivity.this, "下载失败: HTTP " + httpCode, Toast.LENGTH_LONG).show());
                response.close();
                return false;
            }
            
            if (showStartToast) {
                runOnUiThread(() -> Toast.makeText(MainActivity.this, "开始下载: " + fileName, Toast.LENGTH_SHORT).show());
            }
            
            String headerMime = response.header("Content-Type");
            String mimeType = guessMimeType(fileName, headerMime);
            boolean isVideo = mimeType != null && mimeType.toLowerCase(Locale.US).contains("video");
            boolean isImage = mimeType != null && mimeType.toLowerCase(Locale.US).contains("image");
            
            try (InputStream inputStream = response.body().byteStream()) {
                Uri savedUri;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    savedUri = saveToMediaStore(fileName, mimeType, isVideo, isImage, inputStream);
                } else {
                    savedUri = saveToLegacyStorage(fileName, mimeType, isVideo, isImage, inputStream);
                }
                
                if (savedUri != null) {
                    final Uri finalUri = savedUri;
                    final boolean finalIsVideo = isVideo;
                    final boolean finalIsImage = isImage;
                    final String finalMime = mimeType;
                    runOnUiThread(() -> {
                        if (finalIsVideo) {
                            Toast.makeText(MainActivity.this, "视频已保存到相册", Toast.LENGTH_SHORT).show();
                        } else if (finalIsImage) {
                            Toast.makeText(MainActivity.this, "图片已保存到相册", Toast.LENGTH_SHORT).show();
                        } else {
                            Toast.makeText(MainActivity.this, "文件已保存", Toast.LENGTH_SHORT).show();
                        }
                    });
                    
                    MediaScannerConnection.scanFile(this,
                            new String[]{finalUri.toString()},
                            new String[]{finalMime},
                            (path, uri) -> {});
                    sendBroadcast(new Intent(Intent.ACTION_MEDIA_SCANNER_SCAN_FILE, finalUri));
                    response.close();
                    return true;
                } else {
                    runOnUiThread(() -> Toast.makeText(MainActivity.this, "保存失败", Toast.LENGTH_LONG).show());
                }
            } finally {
                response.close();
            }
        } catch (Exception e) {
            final String errorMsg = e.getMessage();
            runOnUiThread(() -> Toast.makeText(MainActivity.this, "下载失败: " + errorMsg, Toast.LENGTH_LONG).show());
            e.printStackTrace();
        }
        return false;
    }
    
    /**
     * 确保文件名包含扩展名
     */
    private String ensureFileNameHasExtension(String fileName, String referenceUrl) {
        String name = (fileName == null || fileName.trim().isEmpty())
                ? "videotool_" + System.currentTimeMillis()
                : fileName.trim();
        String lower = name.toLowerCase(Locale.US);
        if (lower.endsWith(".mp4") || lower.endsWith(".mov")) {
            return name;
        }
        if (lower.endsWith(".jpg") || lower.endsWith(".jpeg") || lower.endsWith(".png")) {
            return name;
        }
        
        String reference = referenceUrl != null ? referenceUrl.toLowerCase(Locale.US) : "";
        if (reference.contains(".mp4") || reference.contains("type=video") || reference.contains("video")) {
            return name + ".mp4";
        }
        if (reference.contains(".png")) {
            return name + ".png";
        }
        return name + ".jpg";
    }
    
    /**
     * 根据文件名或Header猜测MimeType
     */
    private String guessMimeType(String fileName, String headerMime) {
        if (headerMime != null && !headerMime.isEmpty()) {
            return headerMime;
        }
        String lower = fileName.toLowerCase(Locale.US);
        if (lower.endsWith(".mp4") || lower.endsWith(".mov")) {
            return "video/mp4";
        }
        if (lower.endsWith(".png")) {
            return "image/png";
        }
        if (lower.endsWith(".jpg") || lower.endsWith(".jpeg")) {
            return "image/jpeg";
        }
        return "application/octet-stream";
    }

    private static class DownloadTaskMeta {
        String originalUrl;
        String fallbackUrl;
        String fileName;
        boolean isVideo;
        boolean isImage;
    }

    private String translateDmReason(int reason) {
        switch (reason) {
            case DownloadManager.ERROR_DEVICE_NOT_FOUND:
                return "存储设备不可用";
            case DownloadManager.ERROR_FILE_ALREADY_EXISTS:
                return "文件已存在";
            case DownloadManager.ERROR_FILE_ERROR:
                return "文件读写错误";
            case DownloadManager.ERROR_HTTP_DATA_ERROR:
                return "网络数据错误";
            case DownloadManager.ERROR_INSUFFICIENT_SPACE:
                return "存储空间不足";
            case DownloadManager.ERROR_TOO_MANY_REDIRECTS:
                return "重定向过多";
            case DownloadManager.ERROR_UNHANDLED_HTTP_CODE:
                return "HTTP响应异常";
            case DownloadManager.ERROR_CANNOT_RESUME:
                return "无法恢复下载";
            case DownloadManager.ERROR_UNKNOWN:
            default:
                return "未知错误";
        }
    }

    private boolean isCdnUrl(String url) {
        if (url == null || url.isEmpty()) {
            return false;
        }
        try {
            Uri uri = Uri.parse(url);
            String host = uri.getHost();
            if (host == null) {
                return false;
            }
            for (String keyword : CDN_HOST_KEYWORDS) {
                if (host.contains(keyword)) {
                    return true;
                }
            }
        } catch (Exception ignored) {
        }
        return false;
    }

    private boolean downloadViaSystemManager(String url, String fileName, String mimeType, boolean isVideo, boolean isImage, String fallbackUrl) {
        try {
            Uri uri = Uri.parse(url);
            DownloadManager.Request request = new DownloadManager.Request(uri);
            request.setAllowedNetworkTypes(DownloadManager.Request.NETWORK_WIFI | DownloadManager.Request.NETWORK_MOBILE);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setVisibleInDownloadsUi(true);
            request.setTitle(fileName);
            request.setDescription("社媒素材库正在下载");
            request.setMimeType(mimeType != null ? mimeType : "*/*");
            request.addRequestHeader("Connection", "close");
            request.addRequestHeader("Referer", DOWNLOAD_REFERER);
            request.addRequestHeader("User-Agent", DOWNLOAD_UA);
            request.addRequestHeader("Accept", "*/*");

            String targetDir = isVideo ? Environment.DIRECTORY_MOVIES : Environment.DIRECTORY_PICTURES;
            String subDir = "VideoTool";
            request.setDestinationInExternalPublicDir(targetDir, subDir + "/" + fileName);

            downloadId = downloadManager.enqueue(request);

            DownloadTaskMeta meta = new DownloadTaskMeta();
            meta.originalUrl = url;
            meta.fallbackUrl = fallbackUrl;
            meta.fileName = fileName;
            meta.isVideo = isVideo;
            meta.isImage = isImage;
            downloadTaskMap.put(downloadId, meta);

            registerDownloadReceiver();
            runOnUiThread(() -> Toast.makeText(MainActivity.this, "已交由系统下载管理器处理", Toast.LENGTH_SHORT).show());
            return true;
        } catch (Exception e) {
            e.printStackTrace();
            runOnUiThread(() -> Toast.makeText(MainActivity.this, "系统下载失败，尝试备用方案", Toast.LENGTH_SHORT).show());
            return false;
        }
    }
    
    /**
     * 使用MediaStore API保存到相册（Android 10+）
     */
    @SuppressLint("InlinedApi")
    private Uri saveToMediaStore(String fileName, String mimeType, boolean isVideo, boolean isImage, InputStream inputStream) throws IOException {
        ContentResolver resolver = getContentResolver();
        ContentValues contentValues = new ContentValues();
        
        contentValues.put(MediaStore.MediaColumns.DISPLAY_NAME, fileName);
        contentValues.put(MediaStore.MediaColumns.MIME_TYPE, mimeType);
        contentValues.put(MediaStore.MediaColumns.RELATIVE_PATH, isVideo 
                ? Environment.DIRECTORY_MOVIES + "/VideoTool" 
                : Environment.DIRECTORY_PICTURES + "/VideoTool");
        
        Uri collectionUri;
        if (isVideo) {
            collectionUri = MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY);
        } else {
            collectionUri = MediaStore.Images.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY);
        }
        
        Uri uri = resolver.insert(collectionUri, contentValues);
        if (uri != null) {
            OutputStream outputStream = resolver.openOutputStream(uri);
            if (outputStream != null) {
                byte[] buffer = new byte[8192];
                int bytesRead;
                while ((bytesRead = inputStream.read(buffer)) != -1) {
                    outputStream.write(buffer, 0, bytesRead);
                }
                outputStream.close();
                return uri;
            }
        }
        return null;
    }
    
    /**
     * 使用传统方式保存到相册（Android 9及以下）
     */
    private Uri saveToLegacyStorage(String fileName, String mimeType, boolean isVideo, boolean isImage, InputStream inputStream) throws IOException {
        File dir;
        if (isVideo) {
            dir = new File(Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_MOVIES), "VideoTool");
        } else {
            dir = new File(Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_PICTURES), "VideoTool");
        }
        
        if (!dir.exists()) {
            dir.mkdirs();
        }
        
        File file = new File(dir, fileName);
        FileOutputStream outputStream = new FileOutputStream(file);
        
        byte[] buffer = new byte[8192];
        int bytesRead;
        while ((bytesRead = inputStream.read(buffer)) != -1) {
            outputStream.write(buffer, 0, bytesRead);
        }
        outputStream.close();
        
        return Uri.fromFile(file);
    }
    
    /**
     * 下载文件（WebView下载监听器调用）
     * 也保存到相册
     */
    private void downloadFile(String url, String contentDisposition, String mimeType) {
        // 提取文件名
        String fileName = URLUtil.guessFileName(url, contentDisposition, mimeType);
        if (fileName == null || fileName.isEmpty()) {
            fileName = "videotool_" + System.currentTimeMillis();
            if (mimeType != null) {
                if (mimeType.contains("video")) {
                    fileName += ".mp4";
                } else if (mimeType.contains("image")) {
                    fileName += ".jpg";
                }
            }
        }
        
        // 使用统一的下载方法
        downloadFileWithName(url, fileName);
    }
    
    /**
     * 注册下载完成广播接收器
     * 注意：使用MediaStore API保存到相册时，不需要此方法
     * 保留此方法以防将来需要使用DownloadManager
     */
    private void registerDownloadReceiver() {
        BroadcastReceiver receiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {
                String action = intent.getAction();
                if (DownloadManager.ACTION_DOWNLOAD_COMPLETE.equals(action)) {
                    long id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1);
                    if (id == downloadId) {
                        DownloadManager.Query query = new DownloadManager.Query();
                        query.setFilterById(id);
                        Cursor cursor = downloadManager.query(query);
                        
                        DownloadTaskMeta meta = downloadTaskMap.remove(downloadId);
                        if (cursor.moveToFirst()) {
                            int statusIndex = cursor.getColumnIndex(DownloadManager.COLUMN_STATUS);
                            int status = cursor.getInt(statusIndex);
                            
                            if (status == DownloadManager.STATUS_SUCCESSFUL) {
                                String localUri = cursor.getString(cursor.getColumnIndex(DownloadManager.COLUMN_LOCAL_URI));
                                runOnUiThread(() -> {
                                    Toast.makeText(MainActivity.this, "系统下载完成", Toast.LENGTH_SHORT).show();
                                });
                                if (localUri != null) {
                                    MediaScannerConnection.scanFile(MainActivity.this,
                                            new String[]{Uri.parse(localUri).getPath()},
                                            null,
                                            null);
                                }
                            } else if (status == DownloadManager.STATUS_FAILED) {
                                int reasonIndex = cursor.getColumnIndex(DownloadManager.COLUMN_REASON);
                                final int reason = reasonIndex >= 0 ? cursor.getInt(reasonIndex) : -1;
                                runOnUiThread(() -> Toast.makeText(MainActivity.this,
                                        "系统下载失败: " + translateDmReason(reason), Toast.LENGTH_SHORT).show());
                                if (meta != null) {
                                    final String nextUrl = meta.fallbackUrl != null && !meta.fallbackUrl.isEmpty()
                                            ? meta.fallbackUrl : meta.originalUrl;
                                    if (nextUrl != null && !nextUrl.isEmpty()) {
                                        new Thread(() -> attemptDownload(nextUrl, meta.fileName, false)).start();
                                    }
                                }
                            }
                        }
                        cursor.close();
                        MainActivity.this.unregisterReceiver(this);
                    }
                }
            }
        };
        
        registerReceiver(receiver, new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE));
    }
    
    private void loadPlatforms() {
        showProgress("加载平台列表...");
        
        OkHttpClient client = new OkHttpClient();
        Request request = new Request.Builder()
                .url(BASE_URL + "/api/platforms")
                .build();
        
        client.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(Call call, IOException e) {
                runOnUiThread(() -> {
                    hideProgress();
                    Toast.makeText(MainActivity.this, "加载失败: " + e.getMessage(), Toast.LENGTH_SHORT).show();
                });
            }
            
            @Override
            public void onResponse(Call call, Response response) throws IOException {
                if (response.isSuccessful()) {
                    String jsonData = response.body().string();
                    try {
                        JSONObject jsonObject = new JSONObject(jsonData);
                        if (jsonObject.getInt("code") == 0) {
                            JSONArray dataArray = jsonObject.getJSONArray("data");
                            platformList.clear();
                            for (int i = 0; i < dataArray.length(); i++) {
                                JSONObject platformObj = dataArray.getJSONObject(i);
                                Platform platform = new Platform();
                                platform.setId(platformObj.getInt("id"));
                                platform.setName(platformObj.getString("name"));
                                platform.setCode(platformObj.getString("code"));
                                if (platformObj.has("icon")) {
                                    platform.setIcon(platformObj.getString("icon"));
                                }
                                platformList.add(platform);
                            }
                            runOnUiThread(() -> {
                                hideProgress();
                                adapter.notifyDataSetChanged();
                            });
                        } else {
                            String errorMsg = jsonObject.optString("msg", "加载失败");
                            runOnUiThread(() -> {
                                hideProgress();
                                Toast.makeText(MainActivity.this, errorMsg, Toast.LENGTH_SHORT).show();
                            });
                        }
                    } catch (JSONException e) {
                        final String errorMsg = "解析数据失败: " + e.getMessage();
                        runOnUiThread(() -> {
                            hideProgress();
                            Toast.makeText(MainActivity.this, errorMsg, Toast.LENGTH_SHORT).show();
                        });
                    }
                } else {
                    runOnUiThread(() -> {
                        hideProgress();
                        Toast.makeText(MainActivity.this, "服务器错误: " + response.code(), Toast.LENGTH_SHORT).show();
                    });
                }
            }
        });
    }
    
    private void openVideoPage(Platform platform) {
        String videoUrl = BASE_URL + "/?platform=" + platform.getCode();
        webView.loadUrl(videoUrl);
        platformListView.setVisibility(View.GONE);
        webView.setVisibility(View.VISIBLE);
        
        // 更新标题
        if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle(platform.getName());
        }
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            if (webView.getVisibility() == View.VISIBLE && webView.canGoBack()) {
                webView.goBack();
            } else if (webView.getVisibility() == View.VISIBLE) {
                webView.setVisibility(View.GONE);
                platformListView.setVisibility(View.VISIBLE);
                if (getSupportActionBar() != null) {
                    getSupportActionBar().setTitle(getString(R.string.app_name));
                }
            } else {
                finish();
            }
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    @Override
    public void onBackPressed() {
        if (webView.getVisibility() == View.VISIBLE && webView.canGoBack()) {
            webView.goBack();
        } else if (webView.getVisibility() == View.VISIBLE) {
            webView.setVisibility(View.GONE);
            platformListView.setVisibility(View.VISIBLE);
            if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle(getString(R.string.app_name));
            }
        } else {
            super.onBackPressed();
        }
    }
    
    private void showProgress(String message) {
        if (progressDialog == null) {
            progressDialog = new ProgressDialog(this);
            progressDialog.setCancelable(false);
        }
        progressDialog.setMessage(message);
        progressDialog.show();
    }
    
    private void hideProgress() {
        if (progressDialog != null && progressDialog.isShowing()) {
            progressDialog.dismiss();
        }
    }
}

