package com.videotool.automation;

import android.accessibilityservice.AccessibilityService;
import android.content.BroadcastReceiver;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.graphics.Rect;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.TextUtils;
import android.view.accessibility.AccessibilityEvent;
import android.view.accessibility.AccessibilityNodeInfo;
import android.widget.Toast;

import com.videotool.R;
import com.videotool.agent.MobileAgentService;
import com.videotool.console.SessionApiClient;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class ReachAccessibilityService extends AccessibilityService {
    private static final int IM_RETRY_MAX = 5;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private BroadcastReceiver triggerReceiver;
    private boolean actionRunning = false;

    @Override
    protected void onServiceConnected() {
        super.onServiceConnected();
        if (triggerReceiver != null) {
            return;
        }
        triggerReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {
                if (intent == null) {
                    return;
                }
                if (!CommentAutomationBridge.ACTION_TRIGGER_AUTOMATION.equals(intent.getAction())) {
                    return;
                }
                runAutomation();
            }
        };
        IntentFilter filter = new IntentFilter(CommentAutomationBridge.ACTION_TRIGGER_AUTOMATION);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(triggerReceiver, filter, RECEIVER_NOT_EXPORTED);
        } else {
            registerReceiver(triggerReceiver, filter);
        }
    }

    @Override
    public void onAccessibilityEvent(AccessibilityEvent event) {
    }

    @Override
    public void onInterrupt() {
    }

    @Override
    public boolean onUnbind(Intent intent) {
        if (triggerReceiver != null) {
            try {
                unregisterReceiver(triggerReceiver);
            } catch (Exception ignore) {
            }
            triggerReceiver = null;
        }
        return super.onUnbind(intent);
    }

    private void runAutomation() {
        if (actionRunning) {
            return;
        }
        CommentAutomationBridge.PendingData pending = CommentAutomationBridge.loadPending(this);
        if (pending == null || pending.taskId <= 0) {
            toast(getString(R.string.auto_no_pending_task));
            return;
        }
        if (CommentAutomationBridge.MODE_IM_AUTO_SEND.equalsIgnoreCase(pending.mode)) {
            runImAutoSendAutomation(pending);
            return;
        }
        runCommentAutomation(pending);
    }

    private void runCommentAutomation(CommentAutomationBridge.PendingData pending) {
        actionRunning = true;
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            actionRunning = false;
            toast(getString(R.string.auto_comment_ui_not_ready));
            return;
        }

        boolean clickedComment = clickNodeByHints(
                root,
                new String[]{"comment", "binh luan"},
                true
        );
        if (!clickedComment) {
            actionRunning = false;
            toast(getString(R.string.auto_comment_button_not_found));
            return;
        }

        handler.postDelayed(() -> stepCommentPasteAndSend(pending), 900);
    }

    private void runImAutoSendAutomation(CommentAutomationBridge.PendingData pending) {
        actionRunning = true;
        handler.postDelayed(() -> stepImPasteAndSend(pending, 0), 700);
    }

    private void stepCommentPasteAndSend(CommentAutomationBridge.PendingData pending) {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            finishAction(false, "comment_root_missing");
            return;
        }

        AccessibilityNodeInfo input = findBestInput(root);
        boolean textOk = false;
        if (input != null) {
            textOk = setNodeText(input, pending.text);
        }
        if (!textOk) {
            copyClipboard(pending.text);
            if (input != null) {
                input.performAction(AccessibilityNodeInfo.ACTION_FOCUS);
                Bundle args = new Bundle();
                args.putInt(
                        AccessibilityNodeInfo.ACTION_ARGUMENT_MOVEMENT_GRANULARITY_INT,
                        AccessibilityNodeInfo.MOVEMENT_GRANULARITY_CHARACTER
                );
                input.performAction(AccessibilityNodeInfo.ACTION_PASTE, args);
            }
        }

        handler.postDelayed(() -> stepCommentSend(pending), 700);
    }

    private void stepCommentSend(CommentAutomationBridge.PendingData pending) {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            finishAction(false, "send_root_missing");
            return;
        }

        boolean sent = clickNodeByHints(root, new String[]{"send", "gui"}, true);
        if (!sent) {
            finishAction(false, "send_not_found");
            return;
        }
        reportCommentStatusAndClose(pending);
    }

    private void stepImPasteAndSend(CommentAutomationBridge.PendingData pending, int retry) {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            retryImOrFail(pending, retry, "im_root_missing");
            return;
        }

        AccessibilityNodeInfo input = findBestInput(root);
        if (input == null) {
            // WA/Zalo chat input placeholders can differ by locale.
            clickNodeByHints(root, new String[]{"message", "tin nhan", "chat", "nhap"}, true);
            retryImOrFail(pending, retry, "im_input_not_found");
            return;
        }

        boolean textOk = setNodeText(input, pending.text);
        if (!textOk) {
            copyClipboard(pending.text);
            input.performAction(AccessibilityNodeInfo.ACTION_FOCUS);
            Bundle args = new Bundle();
            args.putInt(
                    AccessibilityNodeInfo.ACTION_ARGUMENT_MOVEMENT_GRANULARITY_INT,
                    AccessibilityNodeInfo.MOVEMENT_GRANULARITY_CHARACTER
            );
            input.performAction(AccessibilityNodeInfo.ACTION_PASTE, args);
        }

        handler.postDelayed(() -> stepImSend(pending, retry), 600);
    }

    private void stepImSend(CommentAutomationBridge.PendingData pending, int retry) {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            retryImOrFail(pending, retry, "im_send_root_missing");
            return;
        }

        boolean sent = clickNodeByHints(root, new String[]{"send", "gui", "goi"}, true);
        if (!sent) {
            sent = clickLikelySendNode(root);
        }
        if (!sent) {
            retryImOrFail(pending, retry, "im_send_not_found");
            return;
        }

        reportImAutoResult(true, "");
    }

    private void retryImOrFail(CommentAutomationBridge.PendingData pending, int retry, String reason) {
        if (retry >= IM_RETRY_MAX) {
            reportImAutoResult(false, reason);
            return;
        }
        handler.postDelayed(() -> stepImPasteAndSend(pending, retry + 1), 900);
    }

    private void reportCommentStatusAndClose(CommentAutomationBridge.PendingData pending) {
        SessionApiClient client = new SessionApiClient(this);
        client.updateTaskStatus(
                pending.adminBase,
                pending.taskId,
                "comment_prepared",
                "warmed",
                pending.text,
                new SessionApiClient.JsonCallback() {
                    @Override
                    public void onSuccess(JSONObject data) {
                        handler.post(() -> finishAction(true, ""));
                    }

                    @Override
                    public void onUnauthorized() {
                        handler.post(() -> finishAction(true, ""));
                    }

                    @Override
                    public void onError(String errorMessage) {
                        handler.post(() -> finishAction(true, ""));
                    }
                }
        );
    }

    private void reportImAutoResult(boolean success, String reason) {
        try {
            Intent intent = new Intent(this, MobileAgentService.class);
            if (success) {
                intent.setAction(MobileAgentService.ACTION_MARK_SENT);
            } else {
                intent.setAction(MobileAgentService.ACTION_MARK_FAIL);
                intent.putExtra(
                        MobileAgentService.EXTRA_ERROR_MESSAGE,
                        TextUtils.isEmpty(reason) ? "im_auto_send_failed" : reason
                );
            }
            startService(intent);
        } catch (Exception ignore) {
        }
        finishAction(success, reason);
    }

    private void finishAction(boolean success, String reason) {
        actionRunning = false;
        if (success) {
            toast(getString(R.string.auto_comment_synced));
        } else if (!TextUtils.isEmpty(reason)) {
            toast(getString(R.string.auto_comment_send_failed, reason));
        }
        CommentAutomationBridge.stopFloatingBubble(this);
        CommentAutomationBridge.clearPending(this);
    }

    private void copyClipboard(String text) {
        ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        if (clipboard == null || TextUtils.isEmpty(text)) {
            return;
        }
        clipboard.setPrimaryClip(ClipData.newPlainText("reach_message", text));
    }

    private boolean setNodeText(AccessibilityNodeInfo node, String text) {
        if (node == null || TextUtils.isEmpty(text)) {
            return false;
        }
        Bundle args = new Bundle();
        args.putCharSequence(AccessibilityNodeInfo.ACTION_ARGUMENT_SET_TEXT_CHARSEQUENCE, text);
        return node.performAction(AccessibilityNodeInfo.ACTION_SET_TEXT, args);
    }

    private AccessibilityNodeInfo findBestInput(AccessibilityNodeInfo root) {
        if (root == null) {
            return null;
        }
        List<AccessibilityNodeInfo> nodes = new ArrayList<>();
        collectNodes(root, nodes);
        for (AccessibilityNodeInfo node : nodes) {
            if (node == null) {
                continue;
            }
            CharSequence className = node.getClassName();
            if (className != null && className.toString().toLowerCase(Locale.ROOT).contains("edittext")) {
                return node;
            }
        }
        for (AccessibilityNodeInfo node : nodes) {
            if (node == null) {
                continue;
            }
            CharSequence hint = null;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                hint = node.getHintText();
            }
            CharSequence desc = node.getContentDescription();
            if (containsHint(hint, "message", "tin nhan", "chat", "comment")
                    || containsHint(desc, "message", "tin nhan", "chat", "comment")) {
                return node;
            }
        }
        return null;
    }

    private boolean clickNodeByHints(AccessibilityNodeInfo root, String[] hints, boolean allowParent) {
        if (root == null || hints == null) {
            return false;
        }
        List<AccessibilityNodeInfo> nodes = new ArrayList<>();
        collectNodes(root, nodes);
        for (AccessibilityNodeInfo node : nodes) {
            if (node == null) {
                continue;
            }
            if (!matchNode(node, hints)) {
                continue;
            }
            if (clickNode(node, allowParent)) {
                return true;
            }
        }
        return false;
    }

    private boolean clickLikelySendNode(AccessibilityNodeInfo root) {
        if (root == null) {
            return false;
        }
        Rect screen = new Rect();
        root.getBoundsInScreen(screen);
        if (screen.width() <= 0 || screen.height() <= 0) {
            return false;
        }

        List<AccessibilityNodeInfo> nodes = new ArrayList<>();
        collectNodes(root, nodes);
        AccessibilityNodeInfo best = null;
        double bestScore = Double.NEGATIVE_INFINITY;
        final double minX = screen.left + screen.width() * 0.58d;
        final double minY = screen.top + screen.height() * 0.55d;
        final double maxArea = screen.width() * screen.height() * 0.18d;

        for (AccessibilityNodeInfo node : nodes) {
            if (node == null || !node.isClickable()) {
                continue;
            }
            Rect b = new Rect();
            node.getBoundsInScreen(b);
            if (b.width() <= 0 || b.height() <= 0) {
                continue;
            }
            if (b.centerX() < minX || b.centerY() < minY) {
                continue;
            }
            double area = (double) b.width() * (double) b.height();
            if (area > maxArea) {
                continue;
            }

            CharSequence className = node.getClassName();
            String classNameText = className == null ? "" : className.toString().toLowerCase(Locale.ROOT);
            double score = b.centerX() + b.centerY() - area * 0.02d;
            if (classNameText.contains("image") || classNameText.contains("button")) {
                score += 2200d;
            }
            CharSequence desc = node.getContentDescription();
            CharSequence text = node.getText();
            if (containsHint(desc, "send", "gui", "goi") || containsHint(text, "send", "gui", "goi")) {
                score += 3200d;
            }

            if (score > bestScore) {
                bestScore = score;
                best = node;
            }
        }
        return clickNode(best, true);
    }

    private boolean matchNode(AccessibilityNodeInfo node, String[] hints) {
        String text = node.getText() == null ? "" : node.getText().toString().toLowerCase(Locale.ROOT);
        String desc = node.getContentDescription() == null ? "" : node.getContentDescription().toString().toLowerCase(Locale.ROOT);
        for (String hint : hints) {
            String key = hint == null ? "" : hint.toLowerCase(Locale.ROOT);
            if (key.isEmpty()) {
                continue;
            }
            if (text.contains(key) || desc.contains(key)) {
                return true;
            }
        }
        return false;
    }

    private boolean containsHint(CharSequence value, String... hints) {
        if (value == null) {
            return false;
        }
        String text = value.toString().toLowerCase(Locale.ROOT);
        for (String hint : hints) {
            if (text.contains(hint.toLowerCase(Locale.ROOT))) {
                return true;
            }
        }
        return false;
    }

    private boolean clickNode(AccessibilityNodeInfo node, boolean allowParent) {
        if (node == null) {
            return false;
        }
        if (node.isClickable()) {
            return node.performAction(AccessibilityNodeInfo.ACTION_CLICK);
        }
        if (!allowParent) {
            return false;
        }
        AccessibilityNodeInfo parent = node.getParent();
        int guard = 0;
        while (parent != null && guard < 6) {
            if (parent.isClickable()) {
                return parent.performAction(AccessibilityNodeInfo.ACTION_CLICK);
            }
            parent = parent.getParent();
            guard++;
        }
        return false;
    }

    private void collectNodes(AccessibilityNodeInfo node, List<AccessibilityNodeInfo> out) {
        if (node == null || out == null) {
            return;
        }
        out.add(node);
        for (int i = 0; i < node.getChildCount(); i++) {
            AccessibilityNodeInfo child = node.getChild(i);
            if (child != null) {
                collectNodes(child, out);
            }
        }
    }

    private void toast(String message) {
        if (TextUtils.isEmpty(message)) {
            return;
        }
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}
