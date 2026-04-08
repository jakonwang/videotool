package com.videotool.console;

import android.app.AlertDialog;
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
import android.view.LayoutInflater;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
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

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

import de.hdodenhof.circleimageview.CircleImageView;

public class ModuleConsoleActivity extends AppCompatActivity
{
    private static final String[] LANG_CODES = new String[]{"zh", "en", "vi"};
    private static final String[] TASK_TYPE_VALUES = new String[]{"", "comment_warmup", "tiktok_dm", "zalo_im", "wa_im"};
    private static final String[] TASK_STATUS_VALUES = new String[]{"", "0", "1", "2", "3", "4", "5", "6"};

    private AppPrefs prefs;
    private SessionApiClient apiClient;

    private TextView textPortalTitle;
    private TextView textUserInfo;
    private TextView textMetricContacted;
    private TextView textMetricPendingReply;
    private TextView textMetricSampled;
    private TextView textEmptyHint;
    private TextView textTaskPageInfo;
    private Spinner spinnerLanguage;
    private Spinner spinnerTaskType;
    private Spinner spinnerTaskStatus;
    private EditText inputTaskKeyword;
    private Button btnTaskPrevPage;
    private Button btnTaskNextPage;
    private LinearLayout taskCardContainer;
    private LinearLayout moduleBoardContainer;

    private boolean languageInitialized = false;
    private boolean suppressTaskFilterCallbacks = false;
    private boolean creatorModuleEnabled = false;
    private String currentPortal = "merchant";
    private JSONArray rawTaskItems = new JSONArray();
    private int currentTaskPage = 1;
    private int totalTaskPages = 1;
    private int currentTaskPageSize = 20;
    private int currentTaskTotal = 0;

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
        bindTaskFilters();
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
            loadTaskDashboard(currentTaskPage);
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
        textTaskPageInfo = findViewById(R.id.text_task_page_info);
        spinnerLanguage = findViewById(R.id.spinner_language_console);
        spinnerTaskType = findViewById(R.id.spinner_task_type);
        spinnerTaskStatus = findViewById(R.id.spinner_task_status);
        inputTaskKeyword = findViewById(R.id.input_task_keyword);
        btnTaskPrevPage = findViewById(R.id.btn_task_page_prev);
        btnTaskNextPage = findViewById(R.id.btn_task_page_next);
        taskCardContainer = findViewById(R.id.task_card_container);
        moduleBoardContainer = findViewById(R.id.module_board_container);
    }

    private void bindTaskFilters()
    {
        String[] typeLabels = new String[]{
                getString(R.string.console_filter_type_all),
                getString(R.string.console_filter_type_comment),
                getString(R.string.console_filter_type_dm),
                getString(R.string.console_filter_type_zalo),
                getString(R.string.console_filter_type_wa)
        };
        ArrayAdapter<String> typeAdapter = new ArrayAdapter<>(this, R.layout.item_spinner_selected, typeLabels);
        typeAdapter.setDropDownViewResource(R.layout.item_spinner_dropdown);
        spinnerTaskType.setAdapter(typeAdapter);

        String[] statusLabels = new String[]{
                getString(R.string.console_filter_status_all),
                getString(R.string.console_status_pending),
                getString(R.string.console_status_assigned),
                getString(R.string.console_status_prepared),
                getString(R.string.console_status_done),
                getString(R.string.console_status_failed),
                getString(R.string.console_status_skipped),
                getString(R.string.console_status_canceled)
        };
        ArrayAdapter<String> statusAdapter = new ArrayAdapter<>(this, R.layout.item_spinner_selected, statusLabels);
        statusAdapter.setDropDownViewResource(R.layout.item_spinner_dropdown);
        spinnerTaskStatus.setAdapter(statusAdapter);

        AdapterView.OnItemSelectedListener listener = new AdapterView.OnItemSelectedListener()
        {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id)
            {
                if (suppressTaskFilterCallbacks) {
                    return;
                }
                reloadTaskDashboard(true);
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent)
            {
            }
        };
        spinnerTaskType.setOnItemSelectedListener(listener);
        spinnerTaskStatus.setOnItemSelectedListener(listener);
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
        Button btnSearch = findViewById(R.id.btn_search_task_keyword);
        Button btnCreateCommentTask = findViewById(R.id.btn_create_comment_task);
        Button btnCreateDmTask = findViewById(R.id.btn_create_dm_task);
        Button btnResetFilters = findViewById(R.id.btn_reset_task_filters);

        btnLogout.setOnClickListener(v -> doLogout());
        btnExecutionCenter.setOnClickListener(v -> startActivity(new Intent(this, AgentControlActivity.class)));
        btnRefresh.setOnClickListener(v -> reloadTaskDashboard(true));
        btnSearch.setOnClickListener(v -> reloadTaskDashboard(true));
        btnCreateCommentTask.setOnClickListener(v -> createTaskBatch("comment_warmup"));
        btnCreateDmTask.setOnClickListener(v -> createTaskBatch("tiktok_dm"));
        btnResetFilters.setOnClickListener(v -> resetTaskFilters());
        btnTaskPrevPage.setOnClickListener(v -> {
            if (currentTaskPage > 1) {
                loadTaskDashboard(currentTaskPage - 1);
            }
        });
        btnTaskNextPage.setOnClickListener(v -> {
            if (currentTaskPage < totalTaskPages) {
                loadTaskDashboard(currentTaskPage + 1);
            }
        });
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
        currentTaskPage = 1;
        totalTaskPages = 1;
        currentTaskTotal = 0;
        updateTaskPager();

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
        renderModuleBoard(data.optJSONArray("menus"));

        creatorModuleEnabled = hasEnabledModule(data.optJSONArray("enabled_modules"), "creator_crm");
        if (!creatorModuleEnabled) {
            showEmpty(getString(R.string.console_creator_module_disabled));
            setSummary(0, 0, 0);
            updateTaskPager();
            return;
        }
        reloadTaskDashboard(true);
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

    private void reloadTaskDashboard(boolean resetPage)
    {
        if (resetPage) {
            currentTaskPage = 1;
        }
        loadTaskDashboard(currentTaskPage);
    }

    private void loadTaskDashboard(int page)
    {
        if (!creatorModuleEnabled) {
            return;
        }
        currentTaskPage = Math.max(1, page);
        textEmptyHint.setVisibility(View.GONE);
        taskCardContainer.removeAllViews();
        updateTaskPager();
        apiClient.listDashboardTasks(
                prefs.getAdminBase(),
                currentTaskPage,
                currentTaskPageSize,
                valueOf(inputTaskKeyword),
                selectedTaskType(),
                selectedTaskStatus(),
                new SessionApiClient.JsonCallback()
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
                }
        );
    }

    private void applyTaskData(JSONObject data)
    {
        JSONObject summary = data.optJSONObject("summary");
        int totalTasks = summary == null ? 0 : summary.optInt("today_total_tasks", data.optInt("total", 0));
        int reachedCount = summary == null ? 0 : summary.optInt("reached_count", summary.optInt("today_contacted", 0));
        int repliedCount = summary == null ? 0 : summary.optInt("replied_count", 0);
        setSummary(totalTasks, reachedCount, repliedCount);

        rawTaskItems = data.optJSONArray("items");
        if (rawTaskItems == null) {
            rawTaskItems = new JSONArray();
        }
        currentTaskPage = Math.max(1, data.optInt("page", currentTaskPage));
        currentTaskPageSize = Math.max(10, data.optInt("page_size", currentTaskPageSize));
        currentTaskTotal = Math.max(0, data.optInt("total", rawTaskItems.length()));
        totalTaskPages = Math.max(1, (int) Math.ceil(currentTaskTotal / (double) Math.max(1, currentTaskPageSize)));
        updateTaskPager();
        renderTaskCards(rawTaskItems);
    }

    private void setSummary(int totalTasks, int reachedCount, int repliedCount)
    {
        textMetricContacted.setText(String.valueOf(Math.max(0, totalTasks)));
        textMetricPendingReply.setText(String.valueOf(Math.max(0, reachedCount)));
        textMetricSampled.setText(String.valueOf(Math.max(0, repliedCount)));
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

    private String selectedTaskType()
    {
        int typePos = spinnerTaskType == null ? 0 : spinnerTaskType.getSelectedItemPosition();
        return TASK_TYPE_VALUES[Math.max(0, Math.min(typePos, TASK_TYPE_VALUES.length - 1))];
    }

    private String selectedTaskStatus()
    {
        int statusPos = spinnerTaskStatus == null ? 0 : spinnerTaskStatus.getSelectedItemPosition();
        return TASK_STATUS_VALUES[Math.max(0, Math.min(statusPos, TASK_STATUS_VALUES.length - 1))];
    }

    private void updateTaskPager()
    {
        if (textTaskPageInfo != null) {
            textTaskPageInfo.setText(getString(
                    R.string.console_pagination_info,
                    String.valueOf(Math.max(1, currentTaskPage)),
                    String.valueOf(Math.max(1, totalTaskPages)),
                    String.valueOf(Math.max(0, currentTaskTotal))
            ));
        }
        if (btnTaskPrevPage != null) {
            btnTaskPrevPage.setEnabled(currentTaskPage > 1);
        }
        if (btnTaskNextPage != null) {
            btnTaskNextPage.setEnabled(currentTaskPage < totalTaskPages);
        }
    }

    private View buildTaskCard(JSONObject row)
    {
        String handle = sanitizeHandle(row.optString("tiktok_id", ""));
        String nickname = row.optString("nickname", "").trim();
        float gpm = (float) row.optDouble("quality_score", 0.0);
        View card = LayoutInflater.from(this).inflate(R.layout.item_task_card, taskCardContainer, false);
        CircleImageView imgAvatar = card.findViewById(R.id.img_task_avatar);
        TextView textAvatarLetter = card.findViewById(R.id.text_task_avatar_letter);
        TextView textGpm = card.findViewById(R.id.text_task_gpm);
        TextView textHandle = card.findViewById(R.id.text_task_handle);
        TextView textCategory = card.findViewById(R.id.text_task_category);
        TextView textBadge = card.findViewById(R.id.text_task_status_badge);
        View actionZalo = card.findViewById(R.id.action_add_zalo);
        View actionComment = card.findViewById(R.id.action_go_comment);
        View actionDm = card.findViewById(R.id.action_send_dm);

        int avatarBgColor = avatarColor(handle, row.optString("avatar_url", "").trim());
        imgAvatar.setImageDrawable(null);
        imgAvatar.setCircleBackgroundColor(avatarBgColor);
        textAvatarLetter.setText(avatarLetter(handle, nickname));
        textGpm.setText(getString(R.string.console_gpm_format, formatGpm(gpm)));
        textHandle.setText("@" + handle);

        String categoryName = row.optString("category_name", "").trim();
        if (categoryName.isEmpty()) {
            categoryName = getString(R.string.console_category_default);
        }
        if (!nickname.isEmpty()) {
            textCategory.setText(categoryName + " · " + nickname);
        } else {
            textCategory.setText(categoryName);
        }

        applyCardStatusBadge(textBadge, row);
        textBadge.setOnClickListener(v -> showQuickStatusDialog(row));
        actionZalo.setOnClickListener(v -> handleContactAction(row));
        actionComment.setOnClickListener(v -> handleCommentAction(row));
        actionDm.setOnClickListener(v -> handleDmAction(row, false));
        card.setOnLongClickListener(v -> {
            showTaskDetailDialog(row);
            return true;
        });

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

    private void applyCardStatusBadge(TextView textBadge, JSONObject row)
    {
        int influencerStatus = row.optInt("influencer_status", -1);
        int taskStatus = row.optInt("task_status", 0);
        String taskType = row.optString("task_type", "");

        String text = "";
        int color = Color.parseColor("#4F7EF5");

        if ("comment_warmup".equalsIgnoreCase(taskType) && (taskStatus == 0 || taskStatus == 1)) {
            text = getString(R.string.console_badge_wait_comment);
            color = Color.parseColor("#2563EB");
        } else if (influencerStatus == 4) {
            text = getString(R.string.console_badge_sampled);
            color = Color.parseColor("#16A34A");
        } else if (influencerStatus == 2) {
            text = getString(R.string.console_badge_replied);
            color = Color.parseColor("#0EA5E9");
        } else if (influencerStatus == 3) {
            text = getString(R.string.console_badge_wait_sample);
            color = Color.parseColor("#F59E0B");
        } else if (influencerStatus == 6) {
            text = getString(R.string.console_badge_blacklist);
            color = Color.parseColor("#DC2626");
        } else {
            text = taskStatusText(taskStatus);
            color = statusBadgeColor(taskStatus);
        }

        textBadge.setText(text);
        GradientDrawable badgeBg = new GradientDrawable();
        badgeBg.setCornerRadius(dp(12));
        badgeBg.setColor(color);
        textBadge.setBackground(badgeBg);
        textBadge.setTextColor(Color.WHITE);
    }

    private void showQuickStatusDialog(JSONObject row)
    {
        if (row == null) {
            return;
        }
        final int taskId = row.optInt("id", 0);
        if (taskId <= 0) {
            toast(getString(R.string.console_action_failed));
            return;
        }
        final String[] labels = new String[]{
                getString(R.string.console_quick_mark_done),
                getString(R.string.console_quick_mark_skip),
                getString(R.string.console_quick_mark_fail)
        };
        final String[] events = new String[]{"done", "skip", "fail"};
        new AlertDialog.Builder(this)
                .setTitle(getString(R.string.console_quick_status_title, String.valueOf(taskId)))
                .setItems(labels, (dialog, which) -> quickUpdateTaskStatus(row, events[which]))
                .setNegativeButton(android.R.string.cancel, null)
                .show();
    }

    private void quickUpdateTaskStatus(JSONObject row, String event)
    {
        if (row == null) {
            return;
        }
        int taskId = row.optInt("id", 0);
        if (taskId <= 0) {
            return;
        }
        String renderedText = preferredText(row);
        apiClient.updateTaskStatus(
                prefs.getAdminBase(),
                taskId,
                event,
                event,
                renderedText,
                new SessionApiClient.JsonCallback()
                {
                    @Override
                    public void onSuccess(JSONObject data)
                    {
                        runOnUiThread(() -> {
                            toast(getString(R.string.console_action_done));
                            loadTaskDashboard(currentTaskPage);
                        });
                    }

                    @Override
                    public void onUnauthorized()
                    {
                        runOnUiThread(ModuleConsoleActivity.this::handleUnauthorized);
                    }

                    @Override
                    public void onError(String errorMessage)
                    {
                        runOnUiThread(() -> toast(getString(R.string.console_action_failed) + ": " + errorMessage));
                    }
                }
        );
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
        toast(getString(R.string.console_comment_ready_mobile));
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

    private void createTaskBatch(String taskType)
    {
        apiClient.createBatch(prefs.getAdminBase(), taskType, 30, new SessionApiClient.JsonCallback()
        {
            @Override
            public void onSuccess(JSONObject data)
            {
                runOnUiThread(() -> {
                    String msg = getString(
                            R.string.console_create_task_result,
                            taskType,
                            String.valueOf(data.optInt("created", 0)),
                            String.valueOf(data.optInt("skipped_existing", 0)),
                            String.valueOf(data.optInt("blocked_24h", 0))
                    );
                    toast(msg);
                    reloadTaskDashboard(true);
                });
            }

            @Override
            public void onUnauthorized()
            {
                runOnUiThread(ModuleConsoleActivity.this::handleUnauthorized);
            }

            @Override
            public void onError(String errorMessage)
            {
                runOnUiThread(() -> toast(getString(R.string.console_action_failed) + ": " + errorMessage));
            }
        });
    }

    private void resetTaskFilters()
    {
        suppressTaskFilterCallbacks = true;
        if (spinnerTaskType != null) {
            spinnerTaskType.setSelection(0, false);
        }
        if (spinnerTaskStatus != null) {
            spinnerTaskStatus.setSelection(0, false);
        }
        if (inputTaskKeyword != null) {
            inputTaskKeyword.setText("");
            inputTaskKeyword.clearFocus();
        }
        suppressTaskFilterCallbacks = false;
        reloadTaskDashboard(true);
    }

    private void renderModuleBoard(JSONArray menus)
    {
        if (moduleBoardContainer == null) {
            return;
        }
        moduleBoardContainer.removeAllViews();
        if (menus == null || menus.length() == 0) {
            return;
        }
        for (int i = 0; i < menus.length(); i++) {
            JSONObject section = menus.optJSONObject(i);
            if (section == null) {
                continue;
            }
            JSONArray items = section.optJSONArray("items");
            if (items == null || items.length() == 0) {
                continue;
            }
            List<JSONObject> links = new ArrayList<>();
            collectLinkItems(items, links);
            if (links.isEmpty()) {
                continue;
            }

            TextView sectionTitle = new TextView(this);
            sectionTitle.setText(MenuTextResolver.resolve(
                    this,
                    section.optString("section_i18n", ""),
                    getString(R.string.console_module_section_default)
            ));
            sectionTitle.setTextColor(ContextCompat.getColor(this, R.color.text_secondary));
            sectionTitle.setTypeface(Typeface.DEFAULT_BOLD);
            sectionTitle.setTextSize(12f);
            LinearLayout.LayoutParams titleLp = new LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
            );
            titleLp.bottomMargin = dp(6);
            titleLp.topMargin = dp(2);
            sectionTitle.setLayoutParams(titleLp);
            moduleBoardContainer.addView(sectionTitle);

            LinearLayout groupBox = new LinearLayout(this);
            groupBox.setOrientation(LinearLayout.VERTICAL);
            groupBox.setBackgroundResource(R.drawable.bg_card);
            groupBox.setPadding(dp(10), dp(10), dp(10), dp(10));
            groupBox.setElevation(dp(1));
            LinearLayout.LayoutParams boxLp = new LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
            );
            boxLp.bottomMargin = dp(8);
            groupBox.setLayoutParams(boxLp);

            int count = 0;
            for (JSONObject link : links) {
                if (link == null) {
                    continue;
                }
                String href = link.optString("href", "").trim();
                if (href.isEmpty()) {
                    continue;
                }
                String text = MenuTextResolver.resolve(
                        this,
                        link.optString("text_i18n", ""),
                        href
                );
                String badge = link.optString("badge", "").trim();
                if (!badge.isEmpty() && !"0".equals(badge)) {
                    text = text + " (" + badge + ")";
                }
                Button btn = actionButton(text, R.drawable.bg_button_secondary, R.color.btn_secondary_text);
                btn.setGravity(Gravity.START | Gravity.CENTER_VERTICAL);
                LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                        LinearLayout.LayoutParams.MATCH_PARENT,
                        LinearLayout.LayoutParams.WRAP_CONTENT
                );
                if (count > 0) {
                    lp.topMargin = dp(6);
                }
                btn.setLayoutParams(lp);
                String finalHref = href;
                String finalText = text;
                btn.setOnClickListener(v -> openModule(finalText, finalHref));
                groupBox.addView(btn);
                count++;
            }
            if (count > 0) {
                moduleBoardContainer.addView(groupBox);
            }
        }
    }

    private void collectLinkItems(JSONArray items, List<JSONObject> links)
    {
        if (items == null || links == null) {
            return;
        }
        for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            if (item == null) {
                continue;
            }
            boolean hidden = item.optBoolean("hidden", false) || item.optInt("hidden", 0) == 1;
            if (hidden) {
                continue;
            }
            String kind = item.optString("kind", "").trim().toLowerCase(Locale.ROOT);
            if ("link".equals(kind)) {
                links.add(item);
                continue;
            }
            JSONArray children = item.optJSONArray("children");
            if (children != null) {
                collectLinkItems(children, links);
            }
        }
    }

    private void openModule(String title, String href)
    {
        if (TextUtils.isEmpty(href)) {
            toast(getString(R.string.web_load_failed));
            return;
        }
        Intent intent = new Intent(this, WebModuleActivity.class);
        intent.putExtra(WebModuleActivity.EXTRA_TITLE, title);
        intent.putExtra(WebModuleActivity.EXTRA_HREF, href);
        intent.putExtra(WebModuleActivity.EXTRA_ADMIN_BASE, prefs.getAdminBase());
        startActivity(intent);
    }

    private void showTaskDetailDialog(JSONObject row)
    {
        String handle = sanitizeHandle(row.optString("tiktok_id", ""));
        String message = getString(
                R.string.console_task_detail_template,
                handle,
                row.optString("task_type", ""),
                taskStatusText(row.optInt("task_status", 0)),
                row.optString("target_channel", "auto"),
                row.optString("device_name", row.optString("device_code", "-")),
                row.optString("last_commented_at", "-"),
                row.optString("last_contacted_at", "-"),
                row.optString("last_error_message", "-")
        );
        new AlertDialog.Builder(this)
                .setTitle(R.string.console_task_detail_title)
                .setMessage(message)
                .setPositiveButton(R.string.common_ok, null)
                .setNeutralButton(R.string.console_task_quick_action, (dialog, which) -> showQuickStatusDialog(row))
                .show();
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
            nickname = sanitizeHandle(row.optString("tiktok_id", ""));
        }
        return getString(R.string.console_preset_comment_vi, nickname);
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

    private int statusBadgeColor(int status)
    {
        switch (status) {
            case 1:
                return Color.parseColor("#4F7EF5");
            case 2:
                return Color.parseColor("#21B57D");
            case 3:
                return Color.parseColor("#2FB170");
            case 4:
                return Color.parseColor("#E04D59");
            case 5:
                return Color.parseColor("#7A869A");
            case 6:
                return Color.parseColor("#4B5563");
            default:
                return Color.parseColor("#8B95A7");
        }
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

    private String valueOf(EditText editText)
    {
        if (editText == null || editText.getText() == null) {
            return "";
        }
        return editText.getText().toString().trim();
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
