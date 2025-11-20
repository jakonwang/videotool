package com.videotool;

import android.annotation.SuppressLint;
import android.app.ProgressDialog;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.view.MenuItem;
import android.view.View;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.io.IOException;
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

