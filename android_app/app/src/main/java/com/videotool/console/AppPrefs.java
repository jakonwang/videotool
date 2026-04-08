package com.videotool.console;

import android.content.Context;
import android.content.SharedPreferences;

public class AppPrefs
{
    private static final String PREF_NAME = "mobile_console_prefs";
    private static final String KEY_LANG = "lang";
    private static final String KEY_ADMIN_BASE = "admin_base";
    private static final String KEY_LOGGED_IN = "logged_in";
    private static final String KEY_USER_ID = "user_id";
    private static final String KEY_USERNAME = "username";
    private static final String KEY_ROLE = "role";
    private static final String KEY_TENANT_ID = "tenant_id";

    private final SharedPreferences prefs;

    public AppPrefs(Context context)
    {
        this.prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public String getLanguage()
    {
        return normalizeLanguage(prefs.getString(KEY_LANG, ""));
    }

    public void setLanguage(String lang)
    {
        prefs.edit().putString(KEY_LANG, normalizeLanguage(lang)).apply();
    }

    public String getAdminBase()
    {
        return normalizeAdminBase(prefs.getString(KEY_ADMIN_BASE, ""));
    }

    public void setAdminBase(String adminBase)
    {
        prefs.edit().putString(KEY_ADMIN_BASE, normalizeAdminBase(adminBase)).apply();
    }

    public boolean isLoggedIn()
    {
        return prefs.getBoolean(KEY_LOGGED_IN, false);
    }

    public void saveSession(int userId, String username, String role, int tenantId)
    {
        prefs.edit()
                .putBoolean(KEY_LOGGED_IN, userId > 0)
                .putInt(KEY_USER_ID, userId)
                .putString(KEY_USERNAME, username == null ? "" : username.trim())
                .putString(KEY_ROLE, role == null ? "" : role.trim())
                .putInt(KEY_TENANT_ID, Math.max(1, tenantId))
                .apply();
    }

    public void clearSession()
    {
        prefs.edit()
                .putBoolean(KEY_LOGGED_IN, false)
                .remove(KEY_USER_ID)
                .remove(KEY_USERNAME)
                .remove(KEY_ROLE)
                .remove(KEY_TENANT_ID)
                .apply();
    }

    public int getUserId()
    {
        return prefs.getInt(KEY_USER_ID, 0);
    }

    public String getUsername()
    {
        return prefs.getString(KEY_USERNAME, "");
    }

    public String getRole()
    {
        return prefs.getString(KEY_ROLE, "");
    }

    public int getTenantId()
    {
        return Math.max(1, prefs.getInt(KEY_TENANT_ID, 1));
    }

    public String getPortal()
    {
        return "viewer".equalsIgnoreCase(getRole()) ? "influencer" : "merchant";
    }

    public static String normalizeLanguage(String lang)
    {
        String value = lang == null ? "" : lang.trim().toLowerCase();
        if ("en".equals(value) || value.startsWith("en-")) {
            return "en";
        }
        if ("vi".equals(value) || value.startsWith("vi-")) {
            return "vi";
        }
        if ("zh".equals(value) || value.startsWith("zh-")) {
            return "zh";
        }
        return "";
    }

    public static String normalizeAdminBase(String value)
    {
        String base = value == null ? "" : value.trim();
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

    public static String baseOrigin(String adminBase)
    {
        String base = normalizeAdminBase(adminBase);
        int idx = base.indexOf("/admin.php");
        if (idx <= 0) {
            return "";
        }
        return base.substring(0, idx);
    }
}

