package com.videotool.console;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.TextUtils;
import android.view.Gravity;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import com.videotool.AgentControlActivity;
import com.videotool.R;
import com.videotool.automation.CommentAutomationBridge;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.Locale;

public class ModuleConsoleActivity extends AppCompatActivity
{
    private static final String[] LANG_CODES = new String[]{"zh", "en", "vi"};

    private AppPrefs prefs;
    private SessionApiClient apiClient;

    private TextView textPortalTitle;
    private TextView textUserInfo;
    private TextView textMetricContacted;
    private TextView textMetricPendingReply;
    private TextView textMetricSampled;
    private TextView textEmptyHint;
    private Spinner spinnerLanguage;
    private LinearLayout taskCardContainer;

    private boolean languageInitialized = false;
    private boolean creatorModuleEnabled = false;
    private String currentPortal = "merchant";

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
        setContentView(R.layout.activity_module_console);
        if (getSupportActionBar() != null) {
            getSupportActionBar().hide();
        }

        prefs = new AppPrefs(this);
        apiClient = new SessionApiClient(this);
        if (!prefs.isLoggedIn() || prefs.getAdminBase().isEmpty()) {
            goLogin();
            return;
        }

        bindViews();
        bindLanguageSwitch();
        bindButtons();
        renderUserHeader();
        loadBootstrap();
    }

    @Override
    protected void onResume()
    {
        super.onResume();
        renderUserHeader();
        if (creatorModuleEnabled) {
            loadTaskDashboard();
        }
    }

    private void bindViews()
    {
        textPortalTitle = findViewById(R.id.text_portal_title);
        textUserInfo = findViewById(R.id.text_user_info);
        textMetricContacted = findViewById(R.id.text_metric_contacted);
        textMetricPendingReply = findViewById(R.id.text_metric_pending_reply);
        textMetricSampled = findViewById(R.id.text_metric_sampled);
        textEmptyHint = findViewById(R.id.text_empty_hint);
        spinnerLanguage = findViewById(R.id.spinner_language_console);
        taskCardContainer = findViewById(R.id.task_card_container);
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
                    LocaleHelper.switchLanguage(ModuleConsoleActivity.this, selected);
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

    private void bindButtons()
    {
        Button btnLogout = findViewById(R.id.btn_logout);
        Button btnExecutionCenter = findViewById(R.id.btn_open_execution_center);
        Button btnRefresh = findViewById(R.id.btn_refresh_tasks);

        btnLogout.setOnClickListener(v -> doLogout());
        btnExecutionCenter.setOnClickListener(v -> startActivity(new Intent(this, AgentControlActivity.class)));
        btnRefresh.setOnClickListener(v -> loadTaskDashboard());
    }

    private void renderUserHeader()
    {
        String role = prefs.getRole();
        currentPortal = prefs.getPortal();
        textPortalTitle.setText("influencer".equals(currentPortal)
                ? getString(R.string.portal_influencer_title)
                : getString(R.string.portal_merchant_title));
        textUserInfo.setText(getString(
                R.string.console_user_info,
                prefs.getUsername(),
                role,
                String.valueOf(prefs.getTenantId())
        ));
    }

    private void loadBootstrap()
    {
        textEmptyHint.setVisibility(View.GONE);
        taskCardContainer.removeAllViews();

        String lang = prefs.getLanguage();
        if (lang.isEmpty()) {
            lang = LocaleHelper.detectSystemLanguage();
        }
        apiClient.bootstrap(prefs.getAdminBase(), lang, new SessionApiClient.JsonCallback()
        {
            @Override
            public void onSuccess(final JSONObject data)
            {
                runOnUiThread(() -> applyBootstrapData(data));
            }

            @Override
            public void onUnauthorized()
            {
                runOnUiThread(ModuleConsoleActivity.this::handleUnauthorized);
            }

            @Override
            public void onError(final String errorMessage)
            {
                runOnUiThread(() -> showEmpty(getString(R.string.console_load_failed) + ": " + errorMessage));
            }
        });
    }

    private void applyBootstrapData(JSONObject data)
    {
        JSONObject user = data.optJSONObject("user");
        if (user != null) {
            prefs.saveSession(
                    user.optInt("id", prefs.getUserId()),
                    user.optString("username", prefs.getUsername()),
                    user.optString("role", prefs.getRole()),
                    user.optInt("tenant_id", prefs.getTenantId())
            );
        }
        String portal = data.optString("portal", prefs.getPortal());
        currentPortal = TextUtils.isEmpty(portal) ? prefs.getPortal() : portal;
        renderUserHeader();

        creatorModuleEnabled = hasEnabledModule(data.optJSONArray("enabled_modules"), "creator_crm");
        if (!creatorModuleEnabled) {
            showEmpty(getString(R.string.console_creator_module_disabled));
            setSummary(0, 0, 0);
            return;
        }
        loadTaskDashboard();
    }

    private boolean hasEnabledModule(JSONArray modules, String name)
    {
        if (modules == null || TextUtils.isEmpty(name)) {
            return false;
        }
        for (int i = 0; i < modules.length(); i++) {
            JSONObject row = modules.optJSONObject(i);
            if (row == null) {
                continue;
            }
            if (!name.equalsIgnoreCase(row.optString("name", ""))) {
                continue;
            }
            return row.optInt("is_enabled", 0) == 1;
        }
        return false;
    }

    private void loadTaskDashboard()
    {
        if (!creatorModuleEnabled) {
            return;
        }
        textEmptyHint.setVisibility(View.GONE);
        taskCardContainer.removeAllViews();
        apiClient.listDashboardTasks(prefs.getAdminBase(), 60, new SessionApiClient.JsonCallback()
        {
            @Override
            public void onSuccess(JSONObject data)
            {
                runOnUiThread(() -> applyTaskData(data));
            }

            @Override
            public void onUnauthorized()
            {
                runOnUiThread(ModuleConsoleActivity.this::handleUnauthorized);
            }

            @Override
            public void onError(String errorMessage)
            {
                runOnUiThread(() -> showEmpty(getString(R.string.console_action_failed) + ": " + errorMessage));
            }
        });
    }

    private void applyTaskData(JSONObject data)
    {
        JSONObject summary = data.optJSONObject("summary");
        int contacted = summary == null ? 0 : summary.optInt("today_contacted", 0);
        int waiting = summary == null ? 0 : summary.optInt("pending_reply", 0);
        int sampled = summary == null ? 0 : summary.optInt("sample_shipped", 0);
        setSummary(contacted, waiting, sampled);

        JSONArray items = data.optJSONArray("items");
        renderTaskCards(items);
    }

    private void setSummary(int contacted, int waiting, int sampled)
    {
        textMetricContacted.setText(String.valueOf(Math.max(0, contacted)));
        textMetricPendingReply.setText(String.valueOf(Math.max(0, waiting)));
        textMetricSampled.setText(String.valueOf(Math.max(0, sampled)));
    }

    private void renderTaskCards(JSONArray items)
    {
        taskCardContainer.removeAllViews();
        if (items == null || items.length() == 0) {
            showEmpty(getString(R.string.console_no_data));
            return;
        }

        int rendered = 0;
        for (int i = 0; i < items.length(); i++) {
            JSONObject row = items.optJSONObject(i);
            if (row == null) {
                continue;
            }
            View card = buildTaskCard(row);
            taskCardContainer.addView(card);
            rendered++;
        }
        if (rendered <= 0) {
            showEmpty(getString(R.string.console_no_data));
        }
    }

    private View buildTaskCard(JSONObject row)
    {
        String handle = sanitizeHandle(row.optString("tiktok_id", ""));
        String nickname = row.optString("nickname", "");
        String avatarUrl = row.optString("avatar_url", "").trim();
        String displayName = nickname.trim().isEmpty() ? ("@" + handle) : nickname;
        float gpm = (float) row.optDouble("quality_score", 0.0);
        int taskStatus = row.optInt("task_status", 0);

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setBackgroundResource(R.drawable.bg_task_card);
        card.setPadding(dp(12), dp(12), dp(12), dp(12));
        card.setElevation(dp(2));
        LinearLayout.LayoutParams cardLp = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
        );
        cardLp.bottomMargin = dp(10);
        card.setLayoutParams(cardLp);

        LinearLayout top = new LinearLayout(this);
        top.setOrientation(LinearLayout.HORIZONTAL);
        top.setGravity(Gravity.CENTER_VERTICAL);
        card.addView(top);

        TextView avatar = new TextView(this);
        avatar.setText(avatarLetter(handle, nickname));
        avatar.setTextColor(Color.WHITE);
        avatar.setTypeface(Typeface.DEFAULT_BOLD);
        avatar.setGravity(Gravity.CENTER);
        avatar.setTextSize(14f);
        GradientDrawable avatarBg = new GradientDrawable();
        avatarBg.setShape(GradientDrawable.OVAL);
        avatarBg.setColor(avatarColor(handle, avatarUrl));
        avatar.setBackground(avatarBg);
        LinearLayout.LayoutParams avatarLp = new LinearLayout.LayoutParams(dp(38), dp(38));
        avatarLp.rightMargin = dp(10);
        avatar.setLayoutParams(avatarLp);
        top.addView(avatar);

        LinearLayout center = new LinearLayout(this);
        center.setOrientation(LinearLayout.VERTICAL);
        LinearLayout.LayoutParams centerLp = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f);
        center.setLayoutParams(centerLp);
        top.addView(center);

        TextView name = new TextView(this);
        name.setText("@" + handle);
        name.setTextColor(ContextCompat.getColor(this, R.color.text_primary));
        name.setTypeface(Typeface.DEFAULT_BOLD);
        name.setTextSize(15f);
        center.addView(name);

        TextView sub = new TextView(this);
        sub.setText(getString(
                R.string.console_card_sub,
                displayName.trim().isEmpty() ? getString(R.string.console_no_nickname) : displayName,
                formatGpm(gpm)
        ));
        sub.setTextColor(ContextCompat.getColor(this, R.color.text_secondary));
        sub.setTextSize(12f);
        center.addView(sub);

        TextView badge = new TextView(this);
        badge.setText(taskStatusText(taskStatus));
        badge.setTextSize(11f);
        badge.setTypeface(Typeface.DEFAULT_BOLD);
        badge.setPadding(dp(8), dp(4), dp(8), dp(4));
        badge.setGravity(Gravity.CENTER);
        styleStatusBadge(badge, taskStatus);
        top.addView(badge);

        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);
        actions.setLayoutParams(new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
        ));
        actions.setPadding(0, dp(10), 0, 0);
        card.addView(actions);

        Button btnZalo = actionButton(getString(R.string.console_btn_add_zalo), R.drawable.bg_button_success, R.color.btn_success_text);
        Button btnComment = actionButton(getString(R.string.console_btn_go_comment), R.drawable.bg_button_primary, android.R.color.white);
        Button btnDm = actionButton(getString(R.string.console_btn_send_dm), R.drawable.bg_button_neutral, R.color.btn_neutral_text);

        LinearLayout.LayoutParams btnLpA = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f);
        btnLpA.rightMargin = dp(6);
        btnZalo.setLayoutParams(btnLpA);

        LinearLayout.LayoutParams btnLpB = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f);
        btnLpB.rightMargin = dp(6);
        btnComment.setLayoutParams(btnLpB);

        LinearLayout.LayoutParams btnLpC = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f);
        btnDm.setLayoutParams(btnLpC);

        actions.addView(btnZalo);
        actions.addView(btnComment);
        actions.addView(btnDm);

        btnZalo.setOnClickListener(v -> handleContactAction(row));
        btnComment.setOnClickListener(v -> handleCommentAction(row));
        btnDm.setOnClickListener(v -> handleDmAction(row, false));

        return card;
    }

    private int avatarColor(String handle, String avatarUrl)
    {
        if (!TextUtils.isEmpty(avatarUrl)) {
            return Color.parseColor("#20A56A");
        }
        int hash = Math.abs((handle == null ? "" : handle).hashCode());
        int[] palette = new int[]{
                Color.parseColor("#4F7EF5"),
                Color.parseColor("#6B5CF3"),
                Color.parseColor("#16A34A"),
                Color.parseColor("#E67E22"),
                Color.parseColor("#C0392B")
        };
        return palette[hash % palette.length];
    }

    private Button actionButton(String text, int bgRes, int textColorRes)
    {
        Button btn = new Button(this);
        btn.setText(text);
        btn.setAllCaps(false);
        btn.setTextSize(12f);
        btn.setTypeface(Typeface.DEFAULT_BOLD);
        btn.setBackgroundResource(bgRes);
        btn.setMinHeight(dp(36));
        btn.setPadding(dp(8), dp(6), dp(8), dp(6));
        btn.setTextColor(ContextCompat.getColor(this, textColorRes));
        return btn;
    }

    private void handleCommentAction(JSONObject row)
    {
        String text = getCommentWarmupText(row);
        String uid = sanitizeHandle(row.optString("tiktok_id", ""));
        if (uid.isEmpty()) {
            toast(getString(R.string.console_action_failed));
            return;
        }
        copyText(text);
        openUrl(
                "snssdk1128://user/profile/" + uid,
                "https://www.tiktok.com/@" + uid
        );
        int taskId = row.optInt("id", 0);
        apiClient.updateTaskStatus(prefs.getAdminBase(), taskId, "comment_prepared", "prepared", text, emptyCallback());

        if (!ensureOverlayPermission()) {
            return;
        }
        if (!CommentAutomationBridge.isAccessibilityEnabled(this)) {
            toast(getString(R.string.console_accessibility_required));
            CommentAutomationBridge.openAccessibilitySettings(this);
            return;
        }
        CommentAutomationBridge.savePending(this, taskId, prefs.getAdminBase(), text);
        CommentAutomationBridge.startFloatingBubble(this);
        toast(getString(R.string.console_comment_ready));
    }

    private void handleContactAction(JSONObject row)
    {
        JSONObject channels = extractChannels(row);
        String zalo = channels.optString("zalo", "").trim();
        String wa = normalizePhone(channels.optString("whatsapp", ""));
        String region = row.optString("region", "").trim().toUpperCase(Locale.ROOT);
        String text = preferredText(row);

        if (!zalo.isEmpty()) {
            copyText(text);
            openUrl("zalo://conversation?uid=" + zalo, "https://zalo.me/" + zalo);
            apiClient.updateTaskStatus(prefs.getAdminBase(), row.optInt("id", 0), "im_prepared", "prepared", text, emptyCallback());
            return;
        }
        if (!wa.isEmpty() && !isVietnamRegion(region)) {
            copyText(text);
            openUrl("whatsapp://send?phone=" + wa, "https://wa.me/" + wa);
            apiClient.updateTaskStatus(prefs.getAdminBase(), row.optInt("id", 0), "im_prepared", "prepared", text, emptyCallback());
            return;
        }

        toast(getString(R.string.console_no_external_contact));
        handleDmAction(row, true);
    }

    private void handleDmAction(JSONObject row, boolean fallbackMode)
    {
        String uid = sanitizeHandle(row.optString("tiktok_id", ""));
        if (uid.isEmpty()) {
            toast(getString(R.string.console_action_failed));
            return;
        }
        String text = preferredText(row);
        copyText(text);
        openUrl("snssdk1128://user/profile/" + uid, "https://www.tiktok.com/@" + uid);
        apiClient.updateTaskStatus(prefs.getAdminBase(), row.optInt("id", 0), "dm_prepared", "prepared", text, emptyCallback());
        if (fallbackMode) {
            toast(getString(R.string.console_switched_dm_mode));
        }
    }

    private SessionApiClient.JsonCallback emptyCallback()
    {
        return new SessionApiClient.JsonCallback()
        {
            @Override
            public void onSuccess(JSONObject data)
            {
            }

            @Override
            public void onUnauthorized()
            {
            }

            @Override
            public void onError(String errorMessage)
            {
            }
        };
    }

    private boolean ensureOverlayPermission()
    {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            return true;
        }
        if (Settings.canDrawOverlays(this)) {
            return true;
        }
        toast(getString(R.string.console_overlay_required));
        Intent intent = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                Uri.parse("package:" + getPackageName()));
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        startActivity(intent);
        return false;
    }

    private JSONObject extractChannels(JSONObject row)
    {
        JSONObject payload = row.optJSONObject("payload");
        if (payload == null) {
            return new JSONObject();
        }
        JSONObject channels = payload.optJSONObject("channels");
        return channels == null ? new JSONObject() : channels;
    }

    private String preferredText(JSONObject row)
    {
        String rendered = row.optString("rendered_text", "").trim();
        if (!rendered.isEmpty()) {
            return rendered;
        }
        return getCommentWarmupText(row);
    }

    private String getCommentWarmupText(JSONObject row)
    {
        JSONObject payload = row.optJSONObject("payload");
        if (payload != null) {
            String comment = payload.optString("comment_text", "").trim();
            if (!comment.isEmpty()) {
                return comment;
            }
        }
        String nickname = row.optString("nickname", "").trim();
        if (nickname.isEmpty()) {
            nickname = "friend";
        }
        return getString(R.string.console_default_comment, nickname);
    }

    private void copyText(String text)
    {
        if (TextUtils.isEmpty(text)) {
            return;
        }
        ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        if (clipboard == null) {
            return;
        }
        clipboard.setPrimaryClip(ClipData.newPlainText("reach_text", text));
    }

    private void openUrl(String primary, String fallback)
    {
        if (!TextUtils.isEmpty(primary)) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(primary));
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(intent);
                return;
            } catch (Exception ignore) {
            }
        }
        if (!TextUtils.isEmpty(fallback)) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(fallback));
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(intent);
            } catch (Exception e) {
                toast(getString(R.string.console_action_failed));
            }
        }
    }

    private String sanitizeHandle(String value)
    {
        String handle = value == null ? "" : value.trim();
        if (handle.startsWith("@")) {
            handle = handle.substring(1);
        }
        return handle;
    }

    private String avatarLetter(String handle, String nickname)
    {
        String source = handle.trim().isEmpty() ? nickname.trim() : handle.trim();
        if (source.isEmpty()) {
            return "K";
        }
        return source.substring(0, 1).toUpperCase(Locale.ROOT);
    }

    private String formatGpm(float gpm)
    {
        if (gpm <= 0f) {
            return "0.0";
        }
        return String.format(Locale.US, "%.1f", gpm);
    }

    private String taskStatusText(int status)
    {
        switch (status) {
            case 1:
                return getString(R.string.console_status_assigned);
            case 2:
                return getString(R.string.console_status_prepared);
            case 3:
                return getString(R.string.console_status_done);
            case 4:
                return getString(R.string.console_status_failed);
            case 5:
                return getString(R.string.console_status_skipped);
            case 6:
                return getString(R.string.console_status_canceled);
            default:
                return getString(R.string.console_status_pending);
        }
    }

    private void styleStatusBadge(TextView view, int status)
    {
        int bg;
        int fg = Color.WHITE;
        switch (status) {
            case 1:
                bg = Color.parseColor("#4F7EF5");
                break;
            case 2:
                bg = Color.parseColor("#21B57D");
                break;
            case 3:
                bg = Color.parseColor("#2FB170");
                break;
            case 4:
                bg = Color.parseColor("#E04D59");
                break;
            case 5:
                bg = Color.parseColor("#7A869A");
                break;
            case 6:
                bg = Color.parseColor("#4B5563");
                break;
            default:
                bg = Color.parseColor("#8B95A7");
                break;
        }
        GradientDrawable drawable = new GradientDrawable();
        drawable.setCornerRadius(dp(12));
        drawable.setColor(bg);
        view.setBackground(drawable);
        view.setTextColor(fg);
    }

    private boolean isVietnamRegion(String region)
    {
        if (TextUtils.isEmpty(region)) {
            return false;
        }
        return "VN".equalsIgnoreCase(region) || region.startsWith("VI");
    }

    private String normalizePhone(String raw)
    {
        if (raw == null) {
            return "";
        }
        return raw.replaceAll("[^0-9]", "");
    }

    private void showEmpty(String message)
    {
        textEmptyHint.setVisibility(View.VISIBLE);
        textEmptyHint.setText(message);
    }

    private void doLogout()
    {
        apiClient.logout(prefs.getAdminBase(), new SessionApiClient.JsonCallback()
        {
            @Override
            public void onSuccess(JSONObject data)
            {
                runOnUiThread(ModuleConsoleActivity.this::clearAndGoLogin);
            }

            @Override
            public void onUnauthorized()
            {
                runOnUiThread(ModuleConsoleActivity.this::clearAndGoLogin);
            }

            @Override
            public void onError(String errorMessage)
            {
                runOnUiThread(ModuleConsoleActivity.this::clearAndGoLogin);
            }
        });
    }

    private void handleUnauthorized()
    {
        toast(getString(R.string.console_session_expired));
        clearAndGoLogin();
    }

    private void clearAndGoLogin()
    {
        HttpClientProvider.clearCookies(this);
        prefs.clearSession();
        goLogin();
    }

    private void goLogin()
    {
        Intent intent = new Intent(this, LoginActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        finish();
    }

    private int dp(int value)
    {
        float density = getResources().getDisplayMetrics().density;
        return Math.round(value * density);
    }

    private void toast(String msg)
    {
        Toast.makeText(this, msg, Toast.LENGTH_SHORT).show();
    }
}
