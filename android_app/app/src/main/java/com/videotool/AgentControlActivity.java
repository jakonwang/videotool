package com.videotool;

import android.content.BroadcastReceiver;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.TextUtils;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import com.videotool.agent.AgentConfig;
import com.videotool.agent.AgentPrefs;
import com.videotool.agent.AgentTask;
import com.videotool.agent.MobileAgentService;
import com.videotool.automation.CommentAutomationBridge;
import com.videotool.console.SessionApiClient;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;

public class AgentControlActivity extends AppCompatActivity {
    private EditText inputAdminBase;
    private EditText inputToken;
    private EditText inputDeviceCode;
    private EditText inputPollInterval;
    private CheckBox cbCommentWarmup;
    private CheckBox cbTiktokDm;
    private CheckBox cbZaloIm;
    private CheckBox cbWaIm;
    private TextView textStatus;
    private TextView textCurrentTask;
    private TextView textLog;

    private AgentPrefs prefs;
    private final StringBuilder logBuffer = new StringBuilder();

    private final BroadcastReceiver stateReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            if (intent == null) {
                return;
            }
            String status = intent.getStringExtra(MobileAgentService.EXTRA_STATUS);
            String logLine = intent.getStringExtra(MobileAgentService.EXTRA_LOG_LINE);
            String taskJson = intent.getStringExtra(MobileAgentService.EXTRA_TASK_JSON);
            applyRuntimeState(status, logLine, taskJson);
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        if (getSupportActionBar() != null) {
            getSupportActionBar().hide();
        }
        prefs = new AgentPrefs(this);

        bindViews();
        bindActions();
        restoreConfig();
        applyRuntimeState(prefs.getLastStatus(), prefs.getLastLog(), taskJsonFromPrefs());
        requestStateSync();
    }

    @Override
    protected void onStart() {
        super.onStart();
        IntentFilter filter = new IntentFilter(MobileAgentService.BROADCAST_STATE);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(stateReceiver, filter, RECEIVER_NOT_EXPORTED);
        } else {
            registerReceiver(stateReceiver, filter);
        }
        requestStateSync();
    }

    @Override
    protected void onStop() {
        super.onStop();
        try {
            unregisterReceiver(stateReceiver);
        } catch (Exception ignore) {
        }
    }

    private void bindViews() {
        inputAdminBase = findViewById(R.id.input_admin_base);
        inputToken = findViewById(R.id.input_token);
        inputDeviceCode = findViewById(R.id.input_device_code);
        inputPollInterval = findViewById(R.id.input_poll_interval);
        cbCommentWarmup = findViewById(R.id.cb_comment_warmup);
        cbTiktokDm = findViewById(R.id.cb_tiktok_dm);
        cbZaloIm = findViewById(R.id.cb_zalo_im);
        cbWaIm = findViewById(R.id.cb_wa_im);
        textStatus = findViewById(R.id.text_status);
        textCurrentTask = findViewById(R.id.text_current_task);
        textLog = findViewById(R.id.text_log);
    }

    private void bindActions() {
        Button btnSave = findViewById(R.id.btn_save_config);
        Button btnStart = findViewById(R.id.btn_start_agent);
        Button btnStop = findViewById(R.id.btn_stop_agent);
        Button btnPull = findViewById(R.id.btn_pull_once);
        Button btnOpenTarget = findViewById(R.id.btn_open_target);
        Button btnMarkSent = findViewById(R.id.btn_mark_sent);
        Button btnGoCommentAuto = findViewById(R.id.btn_go_comment_auto);
        Button btnSkip = findViewById(R.id.btn_mark_skip);
        Button btnFail = findViewById(R.id.btn_mark_fail);
        Button btnClearLog = findViewById(R.id.btn_clear_log);

        btnSave.setOnClickListener(v -> {
            AgentConfig config = collectConfigFromInputs();
            if (!config.isValid()) {
                toast(getString(R.string.error_invalid_config));
                return;
            }
            prefs.saveConfig(config);
            appendLog(getString(R.string.log_config_saved));
        });

        btnStart.setOnClickListener(v -> {
            AgentConfig config = collectConfigFromInputs();
            if (!config.isValid()) {
                toast(getString(R.string.error_invalid_config));
                return;
            }
            prefs.saveConfig(config);
            startAgentService(MobileAgentService.ACTION_START, null);
        });

        btnStop.setOnClickListener(v -> startAgentService(MobileAgentService.ACTION_STOP, null));
        btnPull.setOnClickListener(v -> startAgentService(MobileAgentService.ACTION_PULL_ONCE, null));
        btnOpenTarget.setOnClickListener(v -> startAgentService(MobileAgentService.ACTION_OPEN_TARGET, null));
        btnMarkSent.setOnClickListener(v -> startAgentService(MobileAgentService.ACTION_MARK_SENT, null));
        btnGoCommentAuto.setOnClickListener(v -> quickGoComment());
        btnSkip.setOnClickListener(v -> startAgentService(MobileAgentService.ACTION_MARK_SKIP, null));
        btnFail.setOnClickListener(v -> {
            Intent extra = new Intent();
            extra.putExtra(MobileAgentService.EXTRA_ERROR_MESSAGE, "manual_fail_from_app");
            startAgentService(MobileAgentService.ACTION_MARK_FAIL, extra);
        });
        btnClearLog.setOnClickListener(v -> {
            logBuffer.setLength(0);
            textLog.setText("");
        });
    }

    private void restoreConfig() {
        AgentConfig config = prefs.loadConfig();
        inputAdminBase.setText(config.getAdminBase());
        inputToken.setText(config.getToken());
        inputDeviceCode.setText(config.getDeviceCode());
        inputPollInterval.setText(String.valueOf(config.getPollIntervalSec()));
        setTaskTypesToInputs(config.getTaskTypes());
    }

    private AgentConfig collectConfigFromInputs() {
        AgentConfig config = AgentConfig.defaultConfig();
        config.setAdminBase(valueOf(inputAdminBase));
        config.setToken(valueOf(inputToken));
        config.setDeviceCode(valueOf(inputDeviceCode));

        int interval = AgentConfig.DEFAULT_POLL_INTERVAL_SEC;
        try {
            interval = Integer.parseInt(valueOf(inputPollInterval));
        } catch (Exception ignore) {
        }
        config.setPollIntervalSec(interval);
        config.setTaskTypes(readTaskTypesFromInputs());
        return config;
    }

    private List<String> readTaskTypesFromInputs() {
        List<String> taskTypes = new ArrayList<>();
        if (cbCommentWarmup.isChecked()) {
            taskTypes.add("comment_warmup");
        }
        if (cbTiktokDm.isChecked()) {
            taskTypes.add("tiktok_dm");
        }
        if (cbZaloIm.isChecked()) {
            taskTypes.add("zalo_im");
        }
        if (cbWaIm.isChecked()) {
            taskTypes.add("wa_im");
        }
        if (taskTypes.isEmpty()) {
            taskTypes.addAll(AgentConfig.defaultTaskTypes());
        }
        return taskTypes;
    }

    private void setTaskTypesToInputs(List<String> taskTypes) {
        cbCommentWarmup.setChecked(taskTypes.contains("comment_warmup"));
        cbTiktokDm.setChecked(taskTypes.contains("tiktok_dm"));
        cbZaloIm.setChecked(taskTypes.contains("zalo_im"));
        cbWaIm.setChecked(taskTypes.contains("wa_im"));
    }

    private void requestStateSync() {
        startAgentService(MobileAgentService.ACTION_SYNC_STATE, null);
    }

    private void startAgentService(String action, Intent extras) {
        Intent serviceIntent = new Intent(this, MobileAgentService.class);
        serviceIntent.setAction(action);
        if (extras != null && extras.getExtras() != null) {
            serviceIntent.putExtras(extras.getExtras());
        }
        if (MobileAgentService.ACTION_START.equals(action)) {
            ContextCompat.startForegroundService(this, serviceIntent);
        } else {
            startService(serviceIntent);
        }
    }

    private void applyRuntimeState(String status, String logLine, String taskJson) {
        if (TextUtils.isEmpty(status)) {
            status = prefs.isRunning() ? "Running" : "Stopped";
        }
        textStatus.setText(getString(R.string.label_status_value, status));

        if (!TextUtils.isEmpty(taskJson)) {
            try {
                AgentTask task = AgentTask.fromJson(new JSONObject(taskJson));
                String summary = "#" + task.getId()
                        + " | " + task.getTaskType()
                        + " | " + task.getDisplayName()
                        + " | channel=" + task.getTargetChannel();
                textCurrentTask.setText(summary);
            } catch (Exception e) {
                textCurrentTask.setText(getString(R.string.task_none));
            }
        } else {
            textCurrentTask.setText(getString(R.string.task_none));
        }

        if (!TextUtils.isEmpty(logLine)) {
            appendLog(logLine);
        }
    }

    private String taskJsonFromPrefs() {
        AgentTask task = prefs.loadCurrentTask();
        if (task == null) {
            return "";
        }
        return task.toJson().toString();
    }

    private void appendLog(String line) {
        if (line == null || line.trim().isEmpty()) {
            return;
        }
        if (logBuffer.length() > 0) {
            logBuffer.append('\n');
        }
        logBuffer.append(line);
        String all = logBuffer.toString();
        if (all.length() > 6000) {
            all = all.substring(all.length() - 6000);
            logBuffer.setLength(0);
            logBuffer.append(all);
        }
        textLog.setText(all);
    }

    private void quickGoComment()
    {
        AgentTask task = prefs.loadCurrentTask();
        if (task == null || task.getId() <= 0) {
            toast(getString(R.string.console_no_data));
            return;
        }
        String uid = sanitizeHandle(task.getTiktokId());
        if (uid.isEmpty()) {
            toast(getString(R.string.console_action_failed));
            return;
        }
        String text = task.getCommentText().trim();
        if (text.isEmpty()) {
            text = task.getBestText();
        }
        copyText(text);
        openUrl("snssdk1128://user/profile/" + uid, "https://www.tiktok.com/@" + uid);
        SessionApiClient sessionApi = new SessionApiClient(this);
        AgentConfig cfg = prefs.loadConfig();
        sessionApi.updateTaskStatus(cfg.getAdminBase(), task.getId(), "comment_prepared", "prepared", text,
                new SessionApiClient.JsonCallback()
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
                });
        if (!ensureOverlayPermission()) {
            return;
        }
        if (!CommentAutomationBridge.isAccessibilityEnabled(this)) {
            toast(getString(R.string.console_accessibility_required));
            CommentAutomationBridge.openAccessibilitySettings(this);
            return;
        }
        CommentAutomationBridge.savePending(this, task.getId(), cfg.getAdminBase(), text);
        CommentAutomationBridge.startFloatingBubble(this);
        toast(getString(R.string.console_comment_ready));
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

    private void copyText(String text)
    {
        if (TextUtils.isEmpty(text)) {
            return;
        }
        ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        if (clipboard == null) {
            return;
        }
        clipboard.setPrimaryClip(ClipData.newPlainText("reach_comment", text));
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

    private String valueOf(EditText editText) {
        if (editText == null || editText.getText() == null) {
            return "";
        }
        return editText.getText().toString().trim();
    }

    private void toast(String message) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}


