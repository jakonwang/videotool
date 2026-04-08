package com.videotool.agent;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.text.TextUtils;

public class MobileTaskExecutor {
    private final Context appContext;

    public MobileTaskExecutor(Context context) {
        this.appContext = context.getApplicationContext();
    }

    public ExecutionResult prepareTask(AgentTask task) {
        if (task == null) {
            return ExecutionResult.failed("task_null");
        }
        String text = task.getBestText();
        boolean copied = copyToClipboard(text);
        String targetUrl = task.resolveTargetUrl(text);
        if (TextUtils.isEmpty(targetUrl)) {
            return ExecutionResult.failed("target_url_empty");
        }
        boolean opened = openTarget(task.getTargetPackageName(), targetUrl);
        if (!opened) {
            return ExecutionResult.failed("open_target_failed");
        }
        return ExecutionResult.success(copied, targetUrl);
    }

    public boolean reopenTask(AgentTask task) {
        if (task == null) {
            return false;
        }
        String targetUrl = task.resolveTargetUrl(task.getBestText());
        if (TextUtils.isEmpty(targetUrl)) {
            return false;
        }
        return openTarget(task.getTargetPackageName(), targetUrl);
    }

    private boolean copyToClipboard(String text) {
        if (text == null || text.trim().isEmpty()) {
            return false;
        }
        try {
            ClipboardManager clipboard = (ClipboardManager) appContext.getSystemService(Context.CLIPBOARD_SERVICE);
            if (clipboard == null) {
                return false;
            }
            ClipData clip = ClipData.newPlainText("mobile_task_text", text);
            clipboard.setPrimaryClip(clip);
            return true;
        } catch (Exception ignore) {
            return false;
        }
    }

    private boolean openTarget(String packageName, String targetUrl) {
        Intent appIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(targetUrl));
        appIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        if (packageName != null && !packageName.trim().isEmpty()) {
            appIntent.setPackage(packageName);
        }
        try {
            appContext.startActivity(appIntent);
            return true;
        } catch (Exception ignore) {
        }

        Intent genericIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(targetUrl));
        genericIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        try {
            appContext.startActivity(genericIntent);
            return true;
        } catch (Exception ignore) {
            return false;
        }
    }

    public static class ExecutionResult {
        public final boolean ok;
        public final boolean copied;
        public final String targetUrl;
        public final String error;

        private ExecutionResult(boolean ok, boolean copied, String targetUrl, String error) {
            this.ok = ok;
            this.copied = copied;
            this.targetUrl = targetUrl == null ? "" : targetUrl;
            this.error = error == null ? "" : error;
        }

        public static ExecutionResult success(boolean copied, String targetUrl) {
            return new ExecutionResult(true, copied, targetUrl, "");
        }

        public static ExecutionResult failed(String error) {
            return new ExecutionResult(false, false, "", error);
        }
    }
}
