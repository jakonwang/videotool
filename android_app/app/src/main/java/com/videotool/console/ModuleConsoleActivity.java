package com.videotool.console;

import android.app.AlertDialog;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Rect;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.TextUtils;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.HapticFeedbackConstants;
import android.view.ViewGroup;
import android.view.animation.AlphaAnimation;
import android.view.animation.AnimationUtils;
import android.view.animation.LinearInterpolator;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.core.widget.NestedScrollView;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.videotool.AgentControlActivity;
import com.videotool.R;
import com.videotool.automation.CommentAutomationBridge;
import com.google.android.material.bottomnavigation.BottomNavigationView;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class ModuleConsoleActivity extends AppCompatActivity
{
    private static final String[] LANG_CODES = new String[]{"zh", "en", "vi"};
    private static final String[] TASK_TYPE_VALUES = new String[]{"", "comment_warmup", "tiktok_dm", "zalo_im", "wa_im"};
    private static final String[] TASK_STATUS_VALUES = new String[]{"", "0", "1", "2", "3", "4", "5", "6"};
    private static final int MODULE_GRID_SPAN_COUNT = 3;
    private static final int MODULE_GRID_MAX_ITEMS = 9;

    private AppPrefs prefs;
    private SessionApiClient apiClient;

    private TextView textPortalTitle;
    private TextView textUserInfo;
    private TextView textMetricContacted;
    private TextView textMetricPendingReply;
    private TextView textMetricActiveDevices;
    private TextView textEmptyHint;
    private TextView textTaskPageInfo;
    private TextView textProfileUser;
    private TextView textProfileAvatar;
    private TextView textProfilePortalBadge;
    private TextView textProfileRoleValue;
    private TextView textProfileTenantValue;
    private TextView textProfileEndpointValue;
    private Spinner spinnerLanguage;
    private Spinner spinnerTaskType;
    private Spinner spinnerTaskStatus;
    private EditText inputTaskKeyword;
    private Button btnTaskPrevPage;
    private Button btnTaskNextPage;
    private LinearLayout taskCardContainer;
    private RecyclerView moduleBoardRecycler;
    private LinearLayout moduleSectionContainer;
    private LinearLayout reminderContainer;
    private LinearLayout skeletonContainer;
    private NestedScrollView scrollWorkbench;
    private ScrollView scrollProfile;
    private BottomNavigationView bottomNav;
    private ModuleBoardAdapter moduleBoardAdapter;

    private boolean languageInitialized = false;
    private boolean suppressTaskFilterCallbacks = false;
    private boolean creatorModuleEnabled = false;
    private String currentPortal = "merchant";
    private JSONArray rawTaskItems = new JSONArray();
    private int currentTaskPage = 1;
    private int totalTaskPages = 1;
    private int currentTaskPageSize = 20;
    private int currentTaskTotal = 0;
    private int activeNavItemId = R.id.nav_workbench;
    private final List<ModuleSection> moduleSections = new ArrayList<>();
    private int selectedModuleSectionIndex = 0;

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
        applyConsoleSystemBars();
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
        bindMicroInteractions();
        bindBottomNavigation();
        renderUserHeader();
        loadBootstrap();
    }

    private void applyConsoleSystemBars()
    {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            getWindow().setStatusBarColor(color(R.color.brand_primary_dark));
            getWindow().setNavigationBarColor(color(R.color.card_surface));
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            View decorView = getWindow().getDecorView();
            int uiFlags = decorView.getSystemUiVisibility();
            uiFlags &= ~View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                uiFlags |= View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR;
            }
            decorView.setSystemUiVisibility(uiFlags);
        }
    }

    @Override
    protected void onResume()
    {
        super.onResume();
        renderUserHeader();
        switchTab(activeNavItemId);
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
        textMetricActiveDevices = findViewById(R.id.text_metric_active_devices);
        textEmptyHint = findViewById(R.id.text_empty_hint);
        textTaskPageInfo = findViewById(R.id.text_task_page_info);
        textProfileUser = findViewById(R.id.text_profile_user);
        textProfileAvatar = findViewById(R.id.text_profile_avatar);
        textProfilePortalBadge = findViewById(R.id.text_profile_portal_badge);
        textProfileRoleValue = findViewById(R.id.text_profile_role_value);
        textProfileTenantValue = findViewById(R.id.text_profile_tenant_value);
        textProfileEndpointValue = findViewById(R.id.text_profile_endpoint_value);
        spinnerLanguage = findViewById(R.id.spinner_language_console);
        spinnerTaskType = findViewById(R.id.spinner_task_type);
        spinnerTaskStatus = findViewById(R.id.spinner_task_status);
        inputTaskKeyword = findViewById(R.id.input_task_keyword);
        btnTaskPrevPage = findViewById(R.id.btn_task_page_prev);
        btnTaskNextPage = findViewById(R.id.btn_task_page_next);
        taskCardContainer = findViewById(R.id.task_card_container);
        moduleBoardRecycler = findViewById(R.id.module_board_recycler);
        moduleSectionContainer = findViewById(R.id.module_section_container);
        reminderContainer = findViewById(R.id.reminder_container);
        skeletonContainer = findViewById(R.id.skeleton_container);
        scrollWorkbench = findViewById(R.id.scroll_workbench);
        scrollProfile = findViewById(R.id.scroll_profile);
        bottomNav = findViewById(R.id.bottom_nav);
        setupModuleBoardRecycler();
    }

    private void setupModuleBoardRecycler()
    {
        if (moduleBoardRecycler == null) {
            return;
        }
        moduleBoardRecycler.setNestedScrollingEnabled(false);
        moduleBoardRecycler.setHasFixedSize(true);
        if (moduleBoardRecycler.getLayoutManager() == null) {
            moduleBoardRecycler.setLayoutManager(new GridLayoutManager(this, MODULE_GRID_SPAN_COUNT));
        }
        if (moduleBoardRecycler.getItemDecorationCount() == 0) {
            moduleBoardRecycler.addItemDecoration(new GridSpacingDecoration(MODULE_GRID_SPAN_COUNT, dp(8)));
        }
        if (moduleBoardAdapter == null) {
            moduleBoardAdapter = new ModuleBoardAdapter();
            moduleBoardRecycler.setAdapter(moduleBoardAdapter);
        }
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
        View btnLogout = findViewById(R.id.btn_logout);
        View btnExecutionCenter = findViewById(R.id.btn_open_execution_center);
        View btnProfileDevices = findViewById(R.id.btn_profile_devices);
        View btnRefresh = findViewById(R.id.btn_refresh_tasks);
        View btnSearch = findViewById(R.id.btn_search_task_keyword);
        View btnCreateCommentTask = findViewById(R.id.btn_create_comment_task);
        View btnCreateDmTask = findViewById(R.id.btn_create_dm_task);
        View btnResetFilters = findViewById(R.id.btn_reset_task_filters);

        if (btnLogout != null) {
            btnLogout.setOnClickListener(v -> doLogout());
        }
        if (btnExecutionCenter != null) {
            btnExecutionCenter.setOnClickListener(v -> startActivity(new Intent(this, AgentControlActivity.class)));
        }
        if (btnProfileDevices != null) {
            btnProfileDevices.setOnClickListener(v ->
                    openModule(getString(R.string.console_profile_manage_device), "/admin.php/device"));
        }
        if (btnRefresh != null) {
            btnRefresh.setOnClickListener(v -> reloadTaskDashboard(true));
        }
        if (btnSearch != null) {
            btnSearch.setOnClickListener(v -> reloadTaskDashboard(true));
        }
        if (btnCreateCommentTask != null) {
            btnCreateCommentTask.setOnClickListener(v -> createTaskBatch("comment_warmup"));
        }
        if (btnCreateDmTask != null) {
            btnCreateDmTask.setOnClickListener(v -> createTaskBatch("tiktok_dm"));
        }
        if (btnResetFilters != null) {
            btnResetFilters.setOnClickListener(v -> resetTaskFilters());
        }
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

    private void bindBottomNavigation()
    {
        if (bottomNav == null) {
            return;
        }
        bottomNav.setOnItemSelectedListener(item -> {
            int itemId = item.getItemId();
            if (itemId == R.id.nav_workbench) {
                switchTab(itemId);
                return true;
            }
            if (itemId == R.id.nav_profile) {
                switchTab(itemId);
                return true;
            }
            if (itemId == R.id.nav_search) {
                openModule(getString(R.string.console_nav_search), "/admin.php/product_search");
                return false;
            }
            if (itemId == R.id.nav_message) {
                openModule(getString(R.string.console_nav_message), "/admin.php/outreach_workspace");
                return false;
            }
            return false;
        });
        switchTab(R.id.nav_workbench);
    }

    private void bindMicroInteractions()
    {
        applyTouchFeedback(findViewById(R.id.btn_logout));
        applyTouchFeedback(findViewById(R.id.btn_open_execution_center));
        applyTouchFeedback(findViewById(R.id.btn_profile_devices));
        applyTouchFeedback(findViewById(R.id.btn_refresh_tasks));
        applyTouchFeedback(findViewById(R.id.btn_search_task_keyword));
        applyTouchFeedback(findViewById(R.id.btn_create_comment_task));
        applyTouchFeedback(findViewById(R.id.btn_create_dm_task));
        applyTouchFeedback(findViewById(R.id.btn_reset_task_filters));
        applyTouchFeedback(findViewById(R.id.btn_task_page_prev));
        applyTouchFeedback(findViewById(R.id.btn_task_page_next));
    }

    private void switchTab(int itemId)
    {
        activeNavItemId = itemId;
        if (scrollWorkbench != null) {
            scrollWorkbench.setVisibility(itemId == R.id.nav_workbench ? View.VISIBLE : View.GONE);
        }
        if (scrollProfile != null) {
            scrollProfile.setVisibility(itemId == R.id.nav_profile ? View.VISIBLE : View.GONE);
        }
    }

    private void renderUserHeader()
    {
        String username = prefs.getUsername().trim();
        String role = prefs.getRole();
        currentPortal = prefs.getPortal();
        String portalLabel = "influencer".equals(currentPortal)
                ? getString(R.string.portal_influencer_title)
                : getString(R.string.portal_merchant_title);
        textPortalTitle.setText("influencer".equals(currentPortal)
                ? getString(R.string.portal_influencer_title)
                : getString(R.string.portal_merchant_title));
        String userInfo = getString(
                R.string.console_user_info,
                username,
                role,
                String.valueOf(prefs.getTenantId())
        );
        textUserInfo.setText(userInfo);
        if (textProfileUser != null) {
            textProfileUser.setText(username.isEmpty() ? "-" : username);
        }
        if (textProfileAvatar != null) {
            String letter = username.isEmpty() ? "U" : username.substring(0, 1).toUpperCase(Locale.ROOT);
            textProfileAvatar.setText(letter);
        }
        if (textProfilePortalBadge != null) {
            textProfilePortalBadge.setText(portalLabel);
        }
        if (textProfileRoleValue != null) {
            textProfileRoleValue.setText(role.isEmpty() ? "-" : role);
        }
        if (textProfileTenantValue != null) {
            textProfileTenantValue.setText("T-" + prefs.getTenantId());
        }
        if (textProfileEndpointValue != null) {
            String endpoint = AppPrefs.baseOrigin(prefs.getAdminBase());
            if (endpoint.isEmpty()) {
                endpoint = getString(R.string.console_profile_endpoint_unknown);
            }
            textProfileEndpointValue.setText(endpoint);
        }
    }

    private void loadBootstrap()
    {
        textEmptyHint.setVisibility(View.GONE);
        taskCardContainer.removeAllViews();
        showTaskLoading(false);
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
        showTaskLoading(true);
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
                        runOnUiThread(() -> {
                            showTaskLoading(false);
                            showEmpty(getString(R.string.console_action_failed) + ": " + errorMessage);
                        });
                    }
                }
        );
    }

    private void applyTaskData(JSONObject data)
    {
        showTaskLoading(false);
        JSONObject summary = data.optJSONObject("summary");
        int creatorTotal = 0;
        int outreachToday = 0;
        int waitSampleCount = 0;
        if (summary != null) {
            creatorTotal = summary.optInt("influencer_total", 0);
            outreachToday = summary.optInt("today_contacted",
                    summary.optInt("reached_count", 0));
            waitSampleCount = summary.optInt("wait_sample_count",
                    summary.optInt("sample_shipped", 0));
        }
        if (creatorTotal <= 0) {
            creatorTotal = data.optInt("total", 0);
        }
        setSummary(creatorTotal, outreachToday, waitSampleCount);

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
        renderReminders(rawTaskItems);
    }

    private void setSummary(int creatorTotal, int outreachToday, int waitSampleCount)
    {
        textMetricContacted.setText(String.valueOf(Math.max(0, creatorTotal)));
        textMetricPendingReply.setText(String.valueOf(Math.max(0, outreachToday)));
        textMetricActiveDevices.setText(String.valueOf(Math.max(0, waitSampleCount)));
    }

    private void renderTaskCards(JSONArray items)
    {
        taskCardContainer.removeAllViews();
        if (items == null || items.length() == 0) {
            showEmpty(getString(R.string.console_no_data));
            return;
        }
        textEmptyHint.setVisibility(View.GONE);

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
            return;
        }
        taskCardContainer.setLayoutAnimation(AnimationUtils.loadLayoutAnimation(this, R.anim.layout_task_stagger_in));
        taskCardContainer.scheduleLayoutAnimation();
    }

    private void renderReminders(JSONArray items)
    {
        if (reminderContainer == null) {
            return;
        }
        reminderContainer.removeAllViews();
        List<JSONObject> urgent = new ArrayList<>();
        if (items != null) {
            for (int i = 0; i < items.length(); i++) {
                JSONObject row = items.optJSONObject(i);
                if (row == null) {
                    continue;
                }
                int influencerStatus = row.optInt("influencer_status", 0);
                if (influencerStatus == 2 || influencerStatus == 3) {
                    urgent.add(row);
                }
            }
        }
        if (urgent.isEmpty()) {
            TextView empty = new TextView(this);
            empty.setBackgroundResource(R.drawable.bg_card_alt);
            empty.setPadding(dp(12), dp(12), dp(12), dp(12));
            empty.setTextColor(color(R.color.text_secondary));
            empty.setTextSize(11f);
            empty.setText(getString(R.string.console_reminder_empty));
            empty.setElevation(dp(2));
            reminderContainer.addView(empty);
            return;
        }

        int max = Math.min(3, urgent.size());
        for (int i = 0; i < max; i++) {
            JSONObject row = urgent.get(i);
            TextView item = new TextView(this);
            item.setBackgroundResource(R.drawable.bg_card);
            item.setTextColor(color(R.color.text_primary));
            item.setTextSize(12f);
            item.setPadding(dp(12), dp(12), dp(12), dp(12));
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
            );
            if (i > 0) {
                lp.topMargin = dp(10);
            }
            item.setLayoutParams(lp);
            item.setElevation(dp(2));
            String handle = sanitizeHandle(row.optString("tiktok_id", ""));
            if (handle.isEmpty()) {
                handle = row.optString("nickname", "-");
            } else {
                handle = "@" + handle;
            }
            int influencerStatus = row.optInt("influencer_status", 0);
            String reminderType = influencerStatus == 2
                    ? getString(R.string.console_reminder_replied)
                    : getString(R.string.console_reminder_wait_sample);
            item.setText(getString(R.string.console_reminder_item_template, handle, reminderType));
            item.setOnClickListener(v -> openModule(
                    getString(R.string.console_nav_message),
                    "/admin.php/outreach_workspace"));
            reminderContainer.addView(item);
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

    private void showTaskLoading(boolean loading)
    {
        if (skeletonContainer == null || taskCardContainer == null) {
            return;
        }
        if (loading) {
            taskCardContainer.setVisibility(View.GONE);
            skeletonContainer.setVisibility(View.VISIBLE);
            startSkeletonPulse();
            return;
        }
        stopSkeletonPulse();
        skeletonContainer.setVisibility(View.GONE);
        taskCardContainer.setVisibility(View.VISIBLE);
    }

    private void startSkeletonPulse()
    {
        if (skeletonContainer == null) {
            return;
        }
        for (int i = 0; i < skeletonContainer.getChildCount(); i++) {
            View child = skeletonContainer.getChildAt(i);
            AlphaAnimation pulse = new AlphaAnimation(0.45f, 1f);
            pulse.setDuration(900);
            pulse.setRepeatMode(AlphaAnimation.REVERSE);
            pulse.setRepeatCount(AlphaAnimation.INFINITE);
            pulse.setStartOffset(i * 120L);
            pulse.setInterpolator(new LinearInterpolator());
            child.startAnimation(pulse);
        }
    }

    private void stopSkeletonPulse()
    {
        if (skeletonContainer == null) {
            return;
        }
        for (int i = 0; i < skeletonContainer.getChildCount(); i++) {
            skeletonContainer.getChildAt(i).clearAnimation();
        }
    }

    private View buildTaskCard(JSONObject row)
    {
        String handle = sanitizeHandle(row.optString("tiktok_id", ""));
        String nickname = row.optString("nickname", "").trim();
        float gpm = (float) row.optDouble("quality_score", 0.0);
        View card = LayoutInflater.from(this).inflate(R.layout.item_task_card, taskCardContainer, false);
        TextView textAvatarBox = card.findViewById(R.id.text_task_avatar_box);
        TextView textGpm = card.findViewById(R.id.text_task_gpm);
        TextView textMeta = card.findViewById(R.id.text_task_meta);
        TextView textHandle = card.findViewById(R.id.text_task_handle);
        TextView textBadge = card.findViewById(R.id.text_task_status_badge);
        View btnNextStep = card.findViewById(R.id.btn_task_next_step);
        View primaryArea = card.findViewById(R.id.layout_task_primary);
        View optionalActions = card.findViewById(R.id.layout_optional_actions);
        View actionZalo = card.findViewById(R.id.action_add_zalo);
        View actionNote = card.findViewById(R.id.action_write_note);
        View actionBlacklist = card.findViewById(R.id.action_blacklist);

        applyAvatarRect(textAvatarBox, handle, nickname);
        if (handle.isEmpty()) {
            textHandle.setText(nickname.isEmpty() ? "-" : nickname);
        } else {
            textHandle.setText("@" + handle);
        }
        String categoryName = row.optString("category_name", "").trim();
        if (categoryName.isEmpty()) {
            categoryName = getString(R.string.console_category_default);
        }
        String region = row.optString("region", "--");
        textGpm.setText(getString(R.string.console_gpm_format, formatGpm(gpm)));
        textMeta.setText(categoryName + " · " + region);

        applyCardStatusBadge(textBadge, row);
        textBadge.setOnClickListener(v -> showQuickStatusDialog(row));
        btnNextStep.setOnClickListener(v -> handleNextAction(row));
        btnNextStep.setOnLongClickListener(v -> {
            toggleOptionalActions(optionalActions);
            return true;
        });
        primaryArea.setOnClickListener(v -> showTaskDetailDialog(row));
        card.setOnLongClickListener(v -> {
            toggleOptionalActions(optionalActions);
            return true;
        });
        actionZalo.setOnClickListener(v -> handleContactAction(row));
        actionNote.setOnClickListener(v -> showQuickNoteDialog(row));
        actionBlacklist.setOnClickListener(v -> markInfluencerBlacklist(row));
        applyTouchFeedback(btnNextStep);
        applyTouchFeedback(textBadge);
        applyTouchFeedback(actionZalo);
        applyTouchFeedback(actionNote);
        applyTouchFeedback(actionBlacklist);
        return card;
    }

    private void toggleOptionalActions(View optionalActions)
    {
        if (optionalActions == null) {
            return;
        }
        boolean show = optionalActions.getVisibility() != View.VISIBLE;
        if (show) {
            optionalActions.setVisibility(View.VISIBLE);
            optionalActions.setAlpha(0f);
            optionalActions.setTranslationY(dp(12));
            optionalActions.animate().alpha(1f).translationY(0f).setDuration(180).start();
            return;
        }
        optionalActions.animate().alpha(0f).translationY(dp(12)).setDuration(140)
                .withEndAction(() -> {
                    optionalActions.setVisibility(View.GONE);
                    optionalActions.setAlpha(1f);
                    optionalActions.setTranslationY(0f);
                })
                .start();
    }

    private void applyAvatarRect(TextView avatarView, String handle, String nickname)
    {
        if (avatarView == null) {
            return;
        }
        int accent = avatarAccentColor(handle);
        GradientDrawable avatarBg = new GradientDrawable();
        avatarBg.setCornerRadius(dp(12));
        avatarBg.setColor(withAlpha(accent, 20));
        avatarView.setBackground(avatarBg);
        avatarView.setText(avatarLetter(handle, nickname));
        avatarView.setTextColor(accent);
    }

    private int avatarAccentColor(String handle)
    {
        return color(R.color.brand_primary);
    }

    private void applyCardStatusBadge(TextView textBadge, JSONObject row)
    {
        int influencerStatus = row.optInt("influencer_status", -1);
        int taskStatus = row.optInt("task_status", 0);
        String taskType = row.optString("task_type", "");

        String text = "";
        int color = color(R.color.btn_neutral_bg);
        int textColor = color(R.color.text_secondary);
        int actionColor = color(R.color.brand_primary);

        if ("comment_warmup".equalsIgnoreCase(taskType) && (taskStatus == 0 || taskStatus == 1)) {
            text = getString(R.string.console_badge_wait_comment);
            color = withAlpha(actionColor, 20);
            textColor = actionColor;
        } else if (influencerStatus == 4) {
            text = getString(R.string.console_badge_sampled);
            color = Color.parseColor("#EEF6FF");
        } else if (influencerStatus == 2) {
            text = getString(R.string.console_badge_replied);
            color = Color.parseColor("#EFF4FB");
        } else if (influencerStatus == 3) {
            text = getString(R.string.console_badge_wait_sample);
            color = Color.parseColor("#F3F5F9");
        } else if (influencerStatus == 6) {
            text = getString(R.string.console_badge_blacklist);
            color = Color.parseColor("#F5F1F4");
        } else {
            text = taskStatusText(taskStatus);
            color = Color.parseColor("#EEF2F7");
        }

        textBadge.setText(text);
        GradientDrawable badgeBg = new GradientDrawable();
        badgeBg.setCornerRadius(dp(999));
        badgeBg.setColor(color);
        textBadge.setBackground(badgeBg);
        textBadge.setTextColor(textColor);
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

    private void showQuickNoteDialog(JSONObject row)
    {
        final EditText noteInput = new EditText(this);
        noteInput.setHint(getString(R.string.console_note_hint));
        noteInput.setMinLines(3);
        new AlertDialog.Builder(this)
                .setTitle(R.string.console_btn_write_note)
                .setView(noteInput)
                .setPositiveButton(R.string.common_ok, (dialog, which) -> {
                    String note = noteInput.getText() == null ? "" : noteInput.getText().toString().trim();
                    if (!note.isEmpty()) {
                        toast(getString(R.string.console_note_saved));
                    }
                })
                .setNegativeButton(android.R.string.cancel, null)
                .show();
    }

    private void markInfluencerBlacklist(JSONObject row)
    {
        if (row == null) {
            return;
        }
        final int influencerId = row.optInt("influencer_id", 0);
        final int taskId = row.optInt("id", 0);
        if (influencerId <= 0) {
            toast(getString(R.string.console_action_failed));
            return;
        }
        new AlertDialog.Builder(this)
                .setTitle(R.string.console_btn_blacklist)
                .setMessage(R.string.console_blacklist_confirm)
                .setPositiveButton(R.string.common_ok, (dialog, which) -> apiClient.updateInfluencerStatus(
                        prefs.getAdminBase(),
                        influencerId,
                        6,
                        new SessionApiClient.JsonCallback()
                        {
                            @Override
                            public void onSuccess(JSONObject data)
                            {
                                if (taskId > 0) {
                                    apiClient.updateTaskStatus(
                                            prefs.getAdminBase(),
                                            taskId,
                                            "skip",
                                            "skip",
                                            preferredText(row),
                                            emptyCallback()
                                    );
                                }
                                runOnUiThread(() -> {
                                    toast(getString(R.string.console_blacklist_done));
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
                        }))
                .setNegativeButton(android.R.string.cancel, null)
                .show();
    }

    private void handleNextAction(JSONObject row)
    {
        if (row == null) {
            return;
        }
        String taskType = row.optString("task_type", "").trim().toLowerCase(Locale.ROOT);
        if ("comment_warmup".equals(taskType)) {
            handleCommentAction(row);
            return;
        }
        if ("tiktok_dm".equals(taskType)) {
            handleDmAction(row, false);
            return;
        }
        handleContactAction(row);
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
            openUrl("https://zalo.me/" + zalo, "zalo://conversation?uid=" + zalo);
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
        if (moduleBoardRecycler == null) {
            return;
        }
        setupModuleBoardRecycler();
        if (moduleBoardAdapter == null) {
            return;
        }
        moduleSections.clear();
        if (menus != null && menus.length() > 0) {
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
                String sectionTitle = resolveMenuText(section, getString(R.string.console_module_section_default));
                if (TextUtils.isEmpty(sectionTitle) || getString(R.string.console_module_section_default).equals(sectionTitle)) {
                    JSONObject firstItem = items.optJSONObject(0);
                    String fallbackFromItem = resolveMenuText(firstItem, sectionTitle);
                    if (!TextUtils.isEmpty(fallbackFromItem)
                            && !getString(R.string.console_module_section_default).equals(fallbackFromItem)) {
                        sectionTitle = fallbackFromItem;
                    }
                }
                moduleSections.add(new ModuleSection(sectionTitle, links));
            }
        }

        if (moduleSections.isEmpty()) {
            if (moduleSectionContainer != null) {
                moduleSectionContainer.removeAllViews();
            }
            moduleBoardRecycler.setVisibility(View.GONE);
            moduleBoardAdapter.submit(new ArrayList<>());
            return;
        }

        selectedModuleSectionIndex = Math.max(0, Math.min(selectedModuleSectionIndex, moduleSections.size() - 1));
        if (moduleSections.get(selectedModuleSectionIndex).links.size() <= 1) {
            for (int i = 0; i < moduleSections.size(); i++) {
                if (moduleSections.get(i).links.size() > 1) {
                    selectedModuleSectionIndex = i;
                    break;
                }
            }
        }
        renderModuleSectionTabs();
        renderSelectedModuleSection();
    }

    private void renderModuleSectionTabs()
    {
        if (moduleSectionContainer == null) {
            return;
        }
        moduleSectionContainer.removeAllViews();
        for (int i = 0; i < moduleSections.size(); i++) {
            ModuleSection section = moduleSections.get(i);
            TextView chip = new TextView(this);
            LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    dp(26)
            );
            if (i > 0) {
                params.setMarginStart(dp(6));
            }
            chip.setLayoutParams(params);
            chip.setPadding(dp(10), 0, dp(10), 0);
            chip.setGravity(android.view.Gravity.CENTER);
            chip.setText(section.title);
            boolean active = i == selectedModuleSectionIndex;
            chip.setTextSize(10f);
            chip.setTextColor(active ? color(R.color.brand_primary) : color(R.color.text_secondary));
            chip.setBackgroundResource(active
                    ? R.drawable.bg_module_section_chip_active
                    : R.drawable.bg_module_section_chip_inactive);
            final int targetIndex = i;
            chip.setOnClickListener(v -> {
                if (selectedModuleSectionIndex == targetIndex) {
                    return;
                }
                selectedModuleSectionIndex = targetIndex;
                renderModuleSectionTabs();
                renderSelectedModuleSection();
            });
            applyTouchFeedback(chip);
            moduleSectionContainer.addView(chip);
        }
    }

    private void renderSelectedModuleSection()
    {
        if (moduleBoardAdapter == null || moduleSections.isEmpty()) {
            return;
        }
        int index = Math.max(0, Math.min(selectedModuleSectionIndex, moduleSections.size() - 1));
        ModuleSection section = moduleSections.get(index);
        List<JSONObject> visibleItems = new ArrayList<>();
        for (int i = 0; i < section.links.size() && i < MODULE_GRID_MAX_ITEMS; i++) {
            visibleItems.add(section.links.get(i));
        }
        moduleBoardRecycler.setVisibility(visibleItems.isEmpty() ? View.GONE : View.VISIBLE);
        moduleBoardAdapter.submit(visibleItems);
    }

    private String resolveMenuText(JSONObject row, String fallback)
    {
        if (row == null) {
            return fallback;
        }
        String key = row.optString("section_i18n", "").trim();
        if (key.isEmpty()) {
            key = row.optString("text_i18n", "").trim();
        }
        String rowText = row.optString("text", "").trim();
        if (rowText.isEmpty()) {
            rowText = row.optString("section", "").trim();
        }
        return MenuTextResolver.resolve(
                this,
                key,
                rowText.isEmpty() ? fallback : rowText
        );
    }

    private void bindModuleTile(View tile, JSONObject item)
    {
        ImageView icon = tile.findViewById(R.id.img_module_icon);
        View iconPlate = tile.findViewById(R.id.layout_module_icon);
        TextView title = tile.findViewById(R.id.text_module_title);
        if (item == null) {
            return;
        }
        String href = item.optString("href", "").trim();
        String moduleTitle = resolveMenuText(item, getString(R.string.console_module_section_default));
        title.setText(moduleTitle);
        ModuleVisualStyle visualStyle = moduleVisualForHref(href);
        icon.setImageResource(visualStyle.iconRes);
        icon.setColorFilter(visualStyle.iconColor);
        GradientDrawable iconBg = new GradientDrawable();
        iconBg.setShape(GradientDrawable.OVAL);
        iconBg.setColor(withAlpha(visualStyle.iconColor, 13));
        iconPlate.setBackground(iconBg);
        if (!href.isEmpty()) {
            tile.setOnClickListener(v -> openModule(moduleTitle, href));
        } else {
            tile.setOnClickListener(null);
        }
        applyTouchFeedback(tile);
    }

    private ModuleVisualStyle moduleVisualForHref(String href)
    {
        String path = href == null ? "" : href.trim().toLowerCase(Locale.ROOT);
        int actionColor = color(R.color.brand_primary);
        int neutralColor = color(R.color.text_secondary);
        if (path.contains("product_search")) {
            return new ModuleVisualStyle(R.drawable.ic_module_search_line, actionColor);
        }
        if (path.contains("offline_order")) {
            return new ModuleVisualStyle(R.drawable.ic_module_clipboard_line, actionColor);
        }
        if (path.contains("influencer")) {
            return new ModuleVisualStyle(R.drawable.ic_module_user_line, actionColor);
        }
        if (path.contains("outreach_workspace")) {
            return new ModuleVisualStyle(R.drawable.ic_module_chat_line, actionColor);
        }
        if (path.contains("message_template")) {
            return new ModuleVisualStyle(R.drawable.ic_module_chat_line, actionColor);
        }
        if (path.contains("sample")) {
            return new ModuleVisualStyle(R.drawable.ic_module_clipboard_line, actionColor);
        }
        if (path.contains("category")) {
            return new ModuleVisualStyle(R.drawable.ic_module_settings_line, actionColor);
        }
        if (path.contains("industry_trend")) {
            return new ModuleVisualStyle(R.drawable.ic_module_search_line, actionColor);
        }
        if (path.contains("competitor_analysis")) {
            return new ModuleVisualStyle(R.drawable.ic_module_user_line, actionColor);
        }
        if (path.contains("ad_insight")) {
            return new ModuleVisualStyle(R.drawable.ic_module_media_line, actionColor);
        }
        if (path.contains("data_import")) {
            return new ModuleVisualStyle(R.drawable.ic_module_clipboard_line, actionColor);
        }
        if (path.contains("video")) {
            return new ModuleVisualStyle(R.drawable.ic_module_media_line, actionColor);
        }
        if (path.contains("product")) {
            return new ModuleVisualStyle(R.drawable.ic_module_media_line, actionColor);
        }
        if (path.contains("device")) {
            return new ModuleVisualStyle(R.drawable.ic_module_settings_line, neutralColor);
        }
        if (path.contains("ops_center")) {
            return new ModuleVisualStyle(R.drawable.ic_module_settings_line, neutralColor);
        }
        return new ModuleVisualStyle(R.drawable.ic_module_settings_line, neutralColor);
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

    private int withAlpha(int color, int alpha)
    {
        int clamp = Math.max(0, Math.min(255, alpha));
        return (color & 0x00FFFFFF) | (clamp << 24);
    }

    private int color(int resId)
    {
        return ContextCompat.getColor(this, resId);
    }

    private void applyTouchFeedback(View view)
    {
        if (view == null) {
            return;
        }
        view.setOnTouchListener((v, event) -> {
            int action = event.getActionMasked();
            if (action == MotionEvent.ACTION_DOWN) {
                v.animate().scaleX(0.97f).scaleY(0.97f).setDuration(80).start();
                v.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY);
            } else if (action == MotionEvent.ACTION_UP || action == MotionEvent.ACTION_CANCEL) {
                v.animate().scaleX(1f).scaleY(1f).setDuration(110).start();
            }
            return false;
        });
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

    private static final class ModuleSection
    {
        private final String title;
        private final List<JSONObject> links;

        private ModuleSection(String title, List<JSONObject> links)
        {
            this.title = title;
            this.links = links == null ? new ArrayList<>() : links;
        }
    }

    private final class ModuleBoardAdapter extends RecyclerView.Adapter<ModuleBoardViewHolder>
    {
        private final List<JSONObject> items = new ArrayList<>();

        void submit(List<JSONObject> nextItems)
        {
            items.clear();
            if (nextItems != null && !nextItems.isEmpty()) {
                items.addAll(nextItems);
            }
            notifyDataSetChanged();
        }

        @NonNull
        @Override
        public ModuleBoardViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType)
        {
            View view = LayoutInflater.from(parent.getContext())
                    .inflate(R.layout.item_module_tile, parent, false);
            return new ModuleBoardViewHolder(view);
        }

        @Override
        public void onBindViewHolder(@NonNull ModuleBoardViewHolder holder, int position)
        {
            if (position < 0 || position >= items.size()) {
                return;
            }
            bindModuleTile(holder.itemView, items.get(position));
        }

        @Override
        public int getItemCount()
        {
            return items.size();
        }
    }

    private static final class ModuleBoardViewHolder extends RecyclerView.ViewHolder
    {
        private ModuleBoardViewHolder(@NonNull View itemView)
        {
            super(itemView);
        }
    }

    private static final class GridSpacingDecoration extends RecyclerView.ItemDecoration
    {
        private final int spanCount;
        private final int spacing;

        private GridSpacingDecoration(int spanCount, int spacing)
        {
            this.spanCount = Math.max(1, spanCount);
            this.spacing = Math.max(0, spacing);
        }

        @Override
        public void getItemOffsets(@NonNull Rect outRect, @NonNull View view,
                                   @NonNull RecyclerView parent, @NonNull RecyclerView.State state)
        {
            int position = parent.getChildAdapterPosition(view);
            if (position == RecyclerView.NO_POSITION) {
                outRect.set(0, 0, 0, 0);
                return;
            }
            int column = position % spanCount;
            outRect.left = spacing - (column * spacing / spanCount);
            outRect.right = ((column + 1) * spacing) / spanCount;
            if (position < spanCount) {
                outRect.top = spacing;
            } else {
                outRect.top = 0;
            }
            outRect.bottom = spacing;
        }
    }

    private static final class ModuleVisualStyle
    {
        private final int iconRes;
        private final int iconColor;

        private ModuleVisualStyle(int iconRes, int iconColor)
        {
            this.iconRes = iconRes;
            this.iconColor = iconColor;
        }
    }

    private void toast(String msg)
    {
        Toast.makeText(this, msg, Toast.LENGTH_SHORT).show();
    }
}
