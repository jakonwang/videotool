package com.videotool.console;

import android.content.Intent;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.videotool.R;

public class LoginActivity extends AppCompatActivity
{
    private static final String[] LANG_CODES = new String[]{"zh", "en", "vi"};

    private EditText inputAdminBase;
    private EditText inputUsername;
    private EditText inputPassword;
    private Spinner spinnerLanguage;
    private Button btnLogin;
    private ProgressBar loading;

    private AppPrefs prefs;
    private SessionApiClient api;
    private boolean languageInitialized = false;

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
        setContentView(R.layout.activity_login);
        if (getSupportActionBar() != null) {
            getSupportActionBar().hide();
        }

        prefs = new AppPrefs(this);
        api = new SessionApiClient(this);
        bindViews();
        bindLanguageSwitch();
        bindActions();
        prefillForm();
    }

    private void bindViews()
    {
        inputAdminBase = findViewById(R.id.input_admin_base);
        inputUsername = findViewById(R.id.input_username);
        inputPassword = findViewById(R.id.input_password);
        spinnerLanguage = findViewById(R.id.spinner_language);
        btnLogin = findViewById(R.id.btn_login);
        loading = findViewById(R.id.login_loading);
    }

    private void bindLanguageSwitch()
    {
        String[] labels = new String[]{
                getString(R.string.lang_zh),
                getString(R.string.lang_en),
                getString(R.string.lang_vi)
        };
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, R.layout.item_spinner_selected, labels);
        adapter.setDropDownViewResource(R.layout.item_spinner_dropdown);
        spinnerLanguage.setAdapter(adapter);

        String current = prefs.getLanguage();
        if (current.isEmpty()) {
            current = LocaleHelper.detectSystemLanguage();
        }
        spinnerLanguage.setSelection(indexOfLang(current), false);
        languageInitialized = true;
        spinnerLanguage.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener()
        {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id)
            {
                if (!languageInitialized) {
                    return;
                }
                String selected = LANG_CODES[Math.max(0, Math.min(position, LANG_CODES.length - 1))];
                String currentLang = prefs.getLanguage();
                if (currentLang.isEmpty()) {
                    currentLang = LocaleHelper.detectSystemLanguage();
                }
                if (!selected.equals(currentLang)) {
                    LocaleHelper.switchLanguage(LoginActivity.this, selected);
                    recreate();
                }
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent)
            {
            }
        });
    }

    private int indexOfLang(String lang)
    {
        for (int i = 0; i < LANG_CODES.length; i++) {
            if (LANG_CODES[i].equalsIgnoreCase(lang)) {
                return i;
            }
        }
        return 0;
    }

    private void bindActions()
    {
        btnLogin.setOnClickListener(v -> doLogin());
    }

    private void prefillForm()
    {
        if (!prefs.getAdminBase().isEmpty()) {
            inputAdminBase.setText(prefs.getAdminBase());
        }
        if (!prefs.getUsername().isEmpty()) {
            inputUsername.setText(prefs.getUsername());
        }
    }

    private void doLogin()
    {
        final String base = AppPrefs.normalizeAdminBase(inputAdminBase.getText().toString());
        final String username = inputUsername.getText().toString().trim();
        // 密码保持原样，避免因 trim() 误删前后空格导致服务端校验失败
        final String password = inputPassword.getText().toString();

        if (TextUtils.isEmpty(base) || TextUtils.isEmpty(username) || TextUtils.isEmpty(password)) {
            toast(getString(R.string.login_error_required));
            return;
        }

        setLoading(true);
        api.login(base, username, password, new SessionApiClient.LoginCallback()
        {
            @Override
            public void onSuccess(int userId, String userName, String role, int tenantId)
            {
                runOnUiThread(() ->
                {
                    prefs.setAdminBase(base);
                    prefs.saveSession(userId, userName, role, tenantId);
                    HttpClientProvider.cookieJar(LoginActivity.this).syncToWebView(AppPrefs.baseOrigin(base));
                    setLoading(false);
                    Intent intent = new Intent(LoginActivity.this, ModuleConsoleActivity.class);
                    intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK);
                    startActivity(intent);
                    finish();
                });
            }

            @Override
            public void onError(final String errorMessage)
            {
                runOnUiThread(() ->
                {
                    setLoading(false);
                    toast(getString(R.string.login_error_prefix) + " " + errorMessage);
                });
            }
        });
    }

    private void setLoading(boolean loadingNow)
    {
        loading.setVisibility(loadingNow ? View.VISIBLE : View.GONE);
        btnLogin.setEnabled(!loadingNow);
    }

    private void toast(String message)
    {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}
