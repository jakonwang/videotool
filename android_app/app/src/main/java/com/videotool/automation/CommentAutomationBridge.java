package com.videotool.automation;

import android.accessibilityservice.AccessibilityServiceInfo;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.provider.Settings;
import android.text.TextUtils;
import android.view.accessibility.AccessibilityManager;

import java.util.List;

public class CommentAutomationBridge
{
    public static final String PREF_NAME = "reach_automation_prefs";
    public static final String KEY_TASK_ID = "task_id";
    public static final String KEY_ADMIN_BASE = "admin_base";
    public static final String KEY_TEXT = "text";

    public static final String ACTION_TRIGGER_AUTOMATION = "com.videotool.action.TRIGGER_AUTOMATION";

    public static class PendingData
    {
        public final int taskId;
        public final String adminBase;
        public final String text;

        public PendingData(int taskId, String adminBase, String text)
        {
            this.taskId = taskId;
            this.adminBase = adminBase == null ? "" : adminBase;
            this.text = text == null ? "" : text;
        }
    }

    public static void savePending(Context context, int taskId, String adminBase, String text)
    {
        if (context == null) {
            return;
        }
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
                .edit()
                .putInt(KEY_TASK_ID, Math.max(0, taskId))
                .putString(KEY_ADMIN_BASE, adminBase == null ? "" : adminBase)
                .putString(KEY_TEXT, text == null ? "" : text)
                .apply();
    }

    public static PendingData loadPending(Context context)
    {
        if (context == null) {
            return null;
        }
        int taskId = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE).getInt(KEY_TASK_ID, 0);
        String adminBase = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE).getString(KEY_ADMIN_BASE, "");
        String text = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE).getString(KEY_TEXT, "");
        if (taskId <= 0) {
            return null;
        }
        return new PendingData(taskId, adminBase == null ? "" : adminBase, text == null ? "" : text);
    }

    public static void clearPending(Context context)
    {
        if (context == null) {
            return;
        }
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
                .edit()
                .remove(KEY_TASK_ID)
                .remove(KEY_ADMIN_BASE)
                .remove(KEY_TEXT)
                .apply();
    }

    public static void startFloatingBubble(Context context)
    {
        if (context == null) {
            return;
        }
        Intent intent = new Intent(context, FloatingBubbleService.class);
        context.startService(intent);
    }

    public static void stopFloatingBubble(Context context)
    {
        if (context == null) {
            return;
        }
        Intent intent = new Intent(context, FloatingBubbleService.class);
        context.stopService(intent);
    }

    public static void triggerAutomation(Context context)
    {
        if (context == null) {
            return;
        }
        Intent intent = new Intent(ACTION_TRIGGER_AUTOMATION);
        intent.setPackage(context.getPackageName());
        context.sendBroadcast(intent);
    }

    public static boolean isAccessibilityEnabled(Context context)
    {
        if (context == null) {
            return false;
        }
        AccessibilityManager manager = (AccessibilityManager) context.getSystemService(Context.ACCESSIBILITY_SERVICE);
        if (manager == null) {
            return false;
        }
        List<AccessibilityServiceInfo> enabled = manager.getEnabledAccessibilityServiceList(
                AccessibilityServiceInfo.FEEDBACK_ALL_MASK
        );
        if (enabled == null || enabled.isEmpty()) {
            return false;
        }
        String expected = new ComponentName(context, ReachAccessibilityService.class).flattenToString();
        for (AccessibilityServiceInfo info : enabled) {
            if (info == null || info.getResolveInfo() == null || info.getResolveInfo().serviceInfo == null) {
                continue;
            }
            String id = info.getId();
            if (expected.equalsIgnoreCase(id)) {
                return true;
            }
        }
        return false;
    }

    public static void openAccessibilitySettings(Context context)
    {
        if (context == null) {
            return;
        }
        Intent intent = new Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        context.startActivity(intent);
    }
}
