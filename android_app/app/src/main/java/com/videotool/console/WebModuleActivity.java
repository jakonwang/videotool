package com.videotool.console;

import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.appcompat.app.ActionBar;
import androidx.appcompat.app.AppCompatActivity;

import com.videotool.R;

public class WebModuleActivity extends AppCompatActivity
{
    public static final String EXTRA_TITLE = "extra_title";
    public static final String EXTRA_HREF = "extra_href";
    public static final String EXTRA_ADMIN_BASE = "extra_admin_base";

    private WebView webView;
    private ProgressBar loadingBar;

    @Override
    protected void attachBaseContext(android.content.Context newBase)
    {
        super.attachBaseContext(LocaleHelper.wrap(newBase));
    }

    @Override
    protected void onCreate(Bundle savedInstanceState)
    {
        super.onCreate(savedInstanceState);
        LocaleHelper.applySavedLocale(this);
        setContentView(R.layout.activity_web_module);

        webView = findViewById(R.id.web_module_view);
        loadingBar = findViewById(R.id.web_loading);

        String title = getIntent().getStringExtra(EXTRA_TITLE);
        String href = getIntent().getStringExtra(EXTRA_HREF);
        String adminBase = getIntent().getStringExtra(EXTRA_ADMIN_BASE);
        if (TextUtils.isEmpty(adminBase)) {
            adminBase = new AppPrefs(this).getAdminBase();
        }
        if (TextUtils.isEmpty(title)) {
            title = getString(R.string.web_title_default);
        }

        ActionBar actionBar = getSupportActionBar();
        if (actionBar != null) {
            actionBar.setTitle(title);
            actionBar.setDisplayHomeAsUpEnabled(true);
        } else {
            setTitle(title);
        }

        String targetUrl = buildTargetUrl(adminBase, href);
        if (TextUtils.isEmpty(targetUrl)) {
            toast(getString(R.string.web_load_failed));
            finish();
            return;
        }

        configureWebView();

        PersistentCookieJar cookieJar = HttpClientProvider.cookieJar(this);
        String origin = AppPrefs.baseOrigin(adminBase);
        if (!TextUtils.isEmpty(origin)) {
            cookieJar.syncToWebView(origin);
        }
        cookieJar.syncToWebView(targetUrl);

        webView.loadUrl(targetUrl);
    }

    @Override
    public boolean onSupportNavigateUp()
    {
        onBackPressed();
        return true;
    }

    @Override
    public void onBackPressed()
    {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
            return;
        }
        super.onBackPressed();
    }

    @Override
    protected void onDestroy()
    {
        if (webView != null) {
            webView.stopLoading();
            webView.destroy();
            webView = null;
        }
        super.onDestroy();
    }

    private void configureWebView()
    {
        CookieManager manager = CookieManager.getInstance();
        manager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            manager.setAcceptThirdPartyCookies(webView, true);
        }

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);

        webView.setWebViewClient(new WebViewClient()
        {
            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon)
            {
                super.onPageStarted(view, url, favicon);
                setLoading(true);
            }

            @Override
            public void onPageFinished(WebView view, String url)
            {
                super.onPageFinished(view, url);
                setLoading(false);
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url)
            {
                return handleUrl(url);
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request)
            {
                return handleUrl(request == null ? "" : request.getUrl().toString());
            }
        });

        webView.setWebChromeClient(new WebChromeClient()
        {
            @Override
            public void onProgressChanged(WebView view, int newProgress)
            {
                loadingBar.setProgress(newProgress);
                setLoading(newProgress < 100);
                super.onProgressChanged(view, newProgress);
            }
        });
    }

    private boolean handleUrl(String url)
    {
        if (TextUtils.isEmpty(url)) {
            return true;
        }
        String lower = url.toLowerCase();
        if (lower.startsWith("http://") || lower.startsWith("https://")) {
            return false;
        }
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
            startActivity(intent);
        } catch (Exception e) {
            toast(getString(R.string.web_open_external_failed));
        }
        return true;
    }

    private String buildTargetUrl(String adminBase, String href)
    {
        String path = href == null ? "" : href.trim();
        if (path.isEmpty()) {
            return "";
        }
        if (path.startsWith("http://") || path.startsWith("https://")) {
            return path;
        }

        String origin = AppPrefs.baseOrigin(adminBase);
        if (origin.isEmpty()) {
            return "";
        }

        if (path.startsWith("/")) {
            return origin + path;
        }
        return origin + "/" + path;
    }

    private void setLoading(boolean loading)
    {
        loadingBar.setVisibility(loading ? View.VISIBLE : View.GONE);
    }

    private void toast(String message)
    {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}
