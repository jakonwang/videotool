package com.videotool.agent;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONException;
import org.json.JSONObject;

public class AgentPrefs {
    private static final String PREF_NAME = "mobile_agent_prefs";
    private static final String KEY_CONFIG = "agent_config_json";
    private static final String KEY_CURRENT_TASK = "agent_current_task_json";
    private static final String KEY_RUNNING = "agent_running";
    private static final String KEY_LAST_LOG = "agent_last_log";
    private static final String KEY_LAST_STATUS = "agent_last_status";

    private final SharedPreferences prefs;

    public AgentPrefs(Context context) {
        this.prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public void saveConfig(AgentConfig config) {
        if (config == null) {
            return;
        }
        try {
            prefs.edit().putString(KEY_CONFIG, config.toJson().toString()).apply();
        } catch (JSONException ignore) {
        }
    }

    public AgentConfig loadConfig() {
        String raw = prefs.getString(KEY_CONFIG, "");
        if (raw == null || raw.trim().isEmpty()) {
            return AgentConfig.defaultConfig();
        }
        try {
            JSONObject obj = new JSONObject(raw);
            return AgentConfig.fromJson(obj);
        } catch (JSONException e) {
            return AgentConfig.defaultConfig();
        }
    }

    public void saveCurrentTask(AgentTask task) {
        if (task == null) {
            prefs.edit().remove(KEY_CURRENT_TASK).apply();
            return;
        }
        prefs.edit().putString(KEY_CURRENT_TASK, task.toJson().toString()).apply();
    }

    public AgentTask loadCurrentTask() {
        String raw = prefs.getString(KEY_CURRENT_TASK, "");
        if (raw == null || raw.trim().isEmpty()) {
            return null;
        }
        try {
            return AgentTask.fromJson(new JSONObject(raw));
        } catch (JSONException e) {
            return null;
        }
    }

    public void clearCurrentTask() {
        prefs.edit().remove(KEY_CURRENT_TASK).apply();
    }

    public void setRunning(boolean running) {
        prefs.edit().putBoolean(KEY_RUNNING, running).apply();
    }

    public boolean isRunning() {
        return prefs.getBoolean(KEY_RUNNING, false);
    }

    public void setLastLog(String log) {
        prefs.edit().putString(KEY_LAST_LOG, log == null ? "" : log).apply();
    }

    public String getLastLog() {
        return prefs.getString(KEY_LAST_LOG, "");
    }

    public void setLastStatus(String status) {
        prefs.edit().putString(KEY_LAST_STATUS, status == null ? "" : status).apply();
    }

    public String getLastStatus() {
        return prefs.getString(KEY_LAST_STATUS, "");
    }
}
