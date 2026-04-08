package com.videotool.agent;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class AgentConfig {
    public static final int DEFAULT_POLL_INTERVAL_SEC = 3;

    private String adminBase;
    private String token;
    private String deviceCode;
    private int pollIntervalSec;
    private List<String> taskTypes;

    public AgentConfig() {
        this.adminBase = "";
        this.token = "";
        this.deviceCode = "";
        this.pollIntervalSec = DEFAULT_POLL_INTERVAL_SEC;
        this.taskTypes = defaultTaskTypes();
    }

    public static AgentConfig defaultConfig() {
        return new AgentConfig();
    }

    public static List<String> defaultTaskTypes() {
        return new ArrayList<>(Arrays.asList("comment_warmup", "tiktok_dm", "zalo_im", "wa_im"));
    }

    public String getAdminBase() {
        return adminBase;
    }

    public void setAdminBase(String adminBase) {
        this.adminBase = normalizeAdminBase(adminBase);
    }

    public String getToken() {
        return token;
    }

    public void setToken(String token) {
        this.token = token == null ? "" : token.trim();
    }

    public String getDeviceCode() {
        return deviceCode;
    }

    public void setDeviceCode(String deviceCode) {
        this.deviceCode = deviceCode == null ? "" : deviceCode.trim();
    }

    public int getPollIntervalSec() {
        return pollIntervalSec;
    }

    public void setPollIntervalSec(int pollIntervalSec) {
        this.pollIntervalSec = Math.max(2, Math.min(60, pollIntervalSec));
    }

    public List<String> getTaskTypes() {
        return new ArrayList<>(taskTypes);
    }

    public void setTaskTypes(List<String> taskTypes) {
        List<String> sanitized = new ArrayList<>();
        if (taskTypes != null) {
            for (String type : taskTypes) {
                String normalized = normalizeTaskType(type);
                if (!normalized.isEmpty() && !sanitized.contains(normalized)) {
                    sanitized.add(normalized);
                }
            }
        }
        if (sanitized.isEmpty()) {
            sanitized = defaultTaskTypes();
        }
        this.taskTypes = sanitized;
    }

    public boolean isValid() {
        return !getAdminBase().isEmpty() && !getToken().isEmpty() && !getDeviceCode().isEmpty();
    }

    public JSONObject toJson() throws JSONException {
        JSONObject obj = new JSONObject();
        obj.put("admin_base", getAdminBase());
        obj.put("token", getToken());
        obj.put("device_code", getDeviceCode());
        obj.put("poll_interval_sec", getPollIntervalSec());
        JSONArray arr = new JSONArray();
        for (String type : getTaskTypes()) {
            arr.put(type);
        }
        obj.put("task_types", arr);
        return obj;
    }

    public static AgentConfig fromJson(JSONObject obj) {
        AgentConfig config = defaultConfig();
        if (obj == null) {
            return config;
        }
        config.setAdminBase(obj.optString("admin_base", ""));
        config.setToken(obj.optString("token", ""));
        config.setDeviceCode(obj.optString("device_code", ""));
        config.setPollIntervalSec(obj.optInt("poll_interval_sec", DEFAULT_POLL_INTERVAL_SEC));

        List<String> types = new ArrayList<>();
        JSONArray arr = obj.optJSONArray("task_types");
        if (arr != null) {
            for (int i = 0; i < arr.length(); i++) {
                types.add(arr.optString(i, ""));
            }
        }
        config.setTaskTypes(types);
        return config;
    }

    public static String normalizeAdminBase(String input) {
        String base = input == null ? "" : input.trim();
        if (base.isEmpty()) {
            return "";
        }
        while (base.endsWith("/")) {
            base = base.substring(0, base.length() - 1);
        }
        if (!base.contains("/admin.php")) {
            base = base + "/admin.php";
        }
        while (base.endsWith("/")) {
            base = base.substring(0, base.length() - 1);
        }
        return base;
    }

    private static String normalizeTaskType(String raw) {
        String value = raw == null ? "" : raw.trim().toLowerCase();
        if (value.isEmpty()) {
            return "";
        }
        switch (value) {
            case "comment":
            case "warmup":
            case "comment_warmup":
                return "comment_warmup";
            case "dm":
            case "tiktok_dm":
                return "tiktok_dm";
            case "zalo":
            case "zalo_im":
                return "zalo_im";
            case "wa":
            case "whatsapp":
            case "wa_im":
                return "wa_im";
            default:
                return "";
        }
    }
}
