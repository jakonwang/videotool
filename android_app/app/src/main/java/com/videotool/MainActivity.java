package com.videotool;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.ProgressDialog;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.MenuItem;
import android.view.View;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.videotool.download.NativeDownloader;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.OkHttpClient;
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
    private NativeDownloader nativeDownloader;
    
    // 权限请求码
    private static final int PERMISSION_REQUEST_CODE = 1001;
    private static final int NOTIFICATION_PERMISSION_REQUEST = 2001;
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
        nativeDownloader = new NativeDownloader(this);
        
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
        checkNotificationPermission();

        // 创建通知渠道（Android 8.0+）
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    "download_channel",
                    "下载通知",
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("显示文件下载进度");
            NotificationManager notificationManager = getSystemService(NotificationManager.class);
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
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

    private void checkNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.POST_NOTIFICATIONS},
                        NOTIFICATION_PERMISSION_REQUEST);
            }
        }
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
        } else if (requestCode == NOTIFICATION_PERMISSION_REQUEST) {
            boolean granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
            if (!granted) {
                Toast.makeText(this, "未授予通知权限，下载进度将无法显示", Toast.LENGTH_LONG).show();
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
        
        // 添加下载监听器，统一走自研下载器
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
            downloadFile(url, contentDisposition, mimeType);
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
            android.util.Log.d("WebAppInterface", "downloadFile 被JS调用 - url: " + url + ", fileName: " + fileName);
            runOnUiThread(() -> {
                downloadWithFallback(url, null, fileName);
            });
        }
        
        @JavascriptInterface
        public void downloadFileWithFallback(String primaryUrl, String fallbackUrl, String fileName) {
            android.util.Log.d("WebAppInterface", "downloadFileWithFallback 被JS调用 - primaryUrl: " + primaryUrl + ", fallbackUrl: " + fallbackUrl + ", fileName: " + fileName);
            runOnUiThread(() -> {
                // 显示提示，确保用户知道下载已启动
                Toast.makeText(MainActivity.this, "正在准备下载: " + fileName, Toast.LENGTH_SHORT).show();
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
        android.util.Log.d("Download", "downloadWithFallback 被调用 - primaryUrl: " + primaryUrl + ", fileName: " + fileName);
        
        if (primaryUrl == null || primaryUrl.isEmpty()) {
            runOnUiThread(() -> Toast.makeText(this, "无效的下载地址", Toast.LENGTH_LONG).show());
            android.util.Log.e("Download", "下载失败：URL为空");
            return;
        }
        
        String normalizedFileName = ensureFileNameHasExtension(fileName, primaryUrl);
        android.util.Log.d("Download", "规范化文件名: " + normalizedFileName);
        
        // 权限检查
        boolean requiresLegacyPermission = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q;
        boolean requiresMediaPermission = Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU;
        if (requiresLegacyPermission) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE)
                    != PackageManager.PERMISSION_GRANTED) {
                android.util.Log.d("Download", "需要存储权限（旧版本），等待授权");
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
                android.util.Log.d("Download", "需要媒体权限，等待授权");
                pendingDownloadUrl = primaryUrl;
                pendingFallbackUrl = fallbackUrl;
                pendingFileName = normalizedFileName;
                checkStoragePermission();
                return;
            }
        }
        
        if (nativeDownloader == null) {
            nativeDownloader = new NativeDownloader(this);
        }
        
        android.util.Log.d("Download", "开始下载任务 - 文件名: " + normalizedFileName + ", URL: " + primaryUrl);
        
        nativeDownloader.enqueueDownload(normalizedFileName, primaryUrl, fallbackUrl, (success, msg) -> {
            android.util.Log.d("Download", "下载回调 - success: " + success + ", msg: " + msg);
            String toastMsg = msg != null ? msg : (success ? "下载完成" : "下载失败");
            runOnUiThread(() -> Toast.makeText(MainActivity.this, toastMsg, Toast.LENGTH_SHORT).show());
        });
        
        android.util.Log.d("Download", "下载任务已提交到队列");
    }
    
    /**
     * 确保文件名包含扩展名
     */
    private String ensureFileNameHasExtension(String fileName, String referenceUrl) {
        String name = (fileName == null || fileName.trim().isEmpty())
                ? "videotool_" + System.currentTimeMillis()
                : fileName.trim();
        
        android.util.Log.d("Download", "原始文件名: " + name);
        
        // 先进行URL解码（必须在检查扩展名之前）
        try {
            // 检查是否包含URL编码字符（%）
            if (name.contains("%")) {
                String decoded = java.net.URLDecoder.decode(name, "UTF-8");
                android.util.Log.d("Download", "文件名URL解码: " + name + " -> " + decoded);
                name = decoded;
                android.util.Log.d("Download", "解码后文件名: " + name);
            } else {
                android.util.Log.d("Download", "文件名无需解码（不包含%）");
            }
        } catch (IllegalArgumentException e) {
            android.util.Log.w("Download", "文件名URL解码失败（非法字符）: " + e.getMessage());
            // 如果解码失败（可能是已经解码过的），使用原始文件名
        } catch (Exception e) {
            android.util.Log.w("Download", "文件名URL解码异常: " + e.getMessage() + ", 使用原始文件名");
            // 解码失败时使用原始文件名
        }
        
        String lower = name.toLowerCase(Locale.US);
        if (lower.endsWith(".mp4") || lower.endsWith(".mov")) {
            android.util.Log.d("Download", "文件名已包含视频扩展名，直接返回: " + name);
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

