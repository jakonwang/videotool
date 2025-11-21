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
import java.util.List;
import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;

public class MainActivity extends AppCompatActivity {
    
    // 修改为你的服务器地址
    private static final String BASE_URL = "https://videotool.banono-us.com";
    
    private RecyclerView recyclerView;
    private PlatformAdapter adapter;
    private List<Platform> platformList;
    private ProgressDialog progressDialog;
    private WebView webView;
    private View platformListView;
    private DownloadManager downloadManager;
    private long downloadId;
    
    // 权限请求码
    private static final int PERMISSION_REQUEST_CODE = 1001;
    
    // 待下载的文件信息
    private String pendingDownloadUrl;
    private String pendingFileName;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        
        // 启用返回按钮
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("视频管理");
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
                    downloadFileWithName(pendingDownloadUrl, pendingFileName);
                    pendingDownloadUrl = null;
                    pendingFileName = null;
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
                downloadFileWithName(url, fileName);
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
     * 下载文件（带文件名，用于JavaScript接口调用）
     * 保存到相册：图片保存到Pictures，视频保存到Movies
     */
    private void downloadFileWithName(String url, String fileName) {
        // 检查权限
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE)
                    != PackageManager.PERMISSION_GRANTED) {
                // 保存待下载信息，等待权限授予
                pendingDownloadUrl = url;
                pendingFileName = fileName;
                checkStoragePermission();
                return;
            }
        }
        
        new Thread(() -> {
            try {
                // 从URL判断MIME类型
                String mimeType = null;
                boolean isVideo = false;
                boolean isImage = false;
                
                if (url.contains(".mp4") || url.contains("video") || url.contains("/api/video/download?type=video")) {
                    mimeType = "video/mp4";
                    isVideo = true;
                } else if (url.contains(".jpg") || url.contains(".jpeg") || url.contains("image") || url.contains("/api/video/download?type=cover")) {
                    mimeType = "image/jpeg";
                    isImage = true;
                } else if (url.contains(".png")) {
                    mimeType = "image/png";
                    isImage = true;
                }
                
                // 确保文件名有扩展名
                if (fileName != null && !fileName.isEmpty()) {
                    if (!fileName.contains(".")) {
                        if (mimeType != null) {
                            if (mimeType.contains("video")) {
                                fileName += ".mp4";
                            } else if (mimeType.contains("image")) {
                                fileName += ".jpg";
                            }
                        }
                    }
                } else {
                    fileName = "videotool_" + System.currentTimeMillis();
                    if (mimeType != null) {
                        if (mimeType.contains("video")) {
                            fileName += ".mp4";
                        } else if (mimeType.contains("image")) {
                            fileName += ".jpg";
                        }
                    }
                }
                
                runOnUiThread(() -> {
                    Toast.makeText(this, "开始下载: " + fileName, Toast.LENGTH_SHORT).show();
                });
                
                // 使用OkHttp下载文件
                OkHttpClient client = new OkHttpClient();
                Request request = new Request.Builder()
                        .url(url)
                        .build();
                
                Response response = client.newCall(request).execute();
                if (!response.isSuccessful()) {
                    runOnUiThread(() -> {
                        Toast.makeText(this, "下载失败: HTTP " + response.code(), Toast.LENGTH_LONG).show();
                    });
                    return;
                }
                
                InputStream inputStream = response.body().byteStream();
                
                // 保存到相册
                Uri uri = null;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    // Android 10+ 使用MediaStore API
                    uri = saveToMediaStore(fileName, mimeType, isVideo, isImage, inputStream);
                } else {
                    // Android 9及以下使用传统方式
                    uri = saveToLegacyStorage(fileName, mimeType, isVideo, isImage, inputStream);
                }
                
                inputStream.close();
                
                if (uri != null) {
                    // 通知媒体库更新
                    MediaScannerConnection.scanFile(this,
                            new String[]{uri.toString()},
                            new String[]{mimeType},
                            (path, uri2) -> {
                                runOnUiThread(() -> {
                                    if (isVideo) {
                                        Toast.makeText(this, "视频已保存到相册", Toast.LENGTH_SHORT).show();
                                    } else {
                                        Toast.makeText(this, "图片已保存到相册", Toast.LENGTH_SHORT).show();
                                    }
                                });
                            });
                    
                    // 通知相册刷新
                    sendBroadcast(new Intent(Intent.ACTION_MEDIA_SCANNER_SCAN_FILE, uri));
                } else {
                    runOnUiThread(() -> {
                        Toast.makeText(this, "保存失败", Toast.LENGTH_LONG).show();
                    });
                }
                
            } catch (Exception e) {
                runOnUiThread(() -> {
                    Toast.makeText(this, "下载失败: " + e.getMessage(), Toast.LENGTH_LONG).show();
                });
                e.printStackTrace();
            }
        }).start();
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
        try {
            // 提取文件名
            String fileName = URLUtil.guessFileName(url, contentDisposition, mimeType);
            if (fileName == null || fileName.isEmpty()) {
                fileName = "download_" + System.currentTimeMillis();
                if (mimeType != null) {
                    if (mimeType.contains("video")) {
                        fileName += ".mp4";
                    } else if (mimeType.contains("image")) {
                        fileName += ".jpg";
                    }
                }
            }
            
            // 创建下载请求
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            
            // 设置下载目录
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);
            
            // 设置下载通知
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setTitle("正在下载: " + fileName);
            request.setDescription("文件正在下载中...");
            
            // 允许在移动网络和WiFi下下载
            request.setAllowedNetworkTypes(DownloadManager.Request.NETWORK_WIFI | DownloadManager.Request.NETWORK_MOBILE);
            request.setAllowedOverRoaming(true);
            
            // 设置MIME类型
            if (mimeType != null && !mimeType.isEmpty()) {
                request.setMimeType(mimeType);
            }
            
            // 开始下载
            downloadId = downloadManager.enqueue(request);
            
            // 注册下载完成监听
            registerDownloadReceiver();
            
            Toast.makeText(this, "开始下载: " + fileName, Toast.LENGTH_SHORT).show();
            
        } catch (Exception e) {
            Toast.makeText(this, "下载失败: " + e.getMessage(), Toast.LENGTH_LONG).show();
            e.printStackTrace();
        }
    }
    
    /**
     * 注册下载完成广播接收器
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
                        
                        if (cursor.moveToFirst()) {
                            int statusIndex = cursor.getColumnIndex(DownloadManager.COLUMN_STATUS);
                            int status = cursor.getInt(statusIndex);
                            
                            if (status == DownloadManager.STATUS_SUCCESSFUL) {
                                String fileName = cursor.getString(cursor.getColumnIndex(DownloadManager.COLUMN_LOCAL_FILENAME));
                                runOnUiThread(() -> {
                                    Toast.makeText(MainActivity.this, "下载完成: " + fileName, Toast.LENGTH_SHORT).show();
                                });
                            } else if (status == DownloadManager.STATUS_FAILED) {
                                runOnUiThread(() -> {
                                    Toast.makeText(MainActivity.this, "下载失败", Toast.LENGTH_SHORT).show();
                                });
                            }
                        }
                        cursor.close();
                        unregisterReceiver(this);
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
                    getSupportActionBar().setTitle("视频管理");
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
                getSupportActionBar().setTitle("视频管理");
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

