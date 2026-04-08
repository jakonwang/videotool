package com.videotool.automation;

import android.accessibilityservice.AccessibilityService;
import android.content.BroadcastReceiver;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.TextUtils;
import android.view.accessibility.AccessibilityEvent;
import android.view.accessibility.AccessibilityNodeInfo;
import android.widget.Toast;

import com.videotool.R;
import com.videotool.console.SessionApiClient;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class ReachAccessibilityService extends AccessibilityService
{
    private final Handler handler = new Handler(Looper.getMainLooper());
    private BroadcastReceiver triggerReceiver;
    private boolean actionRunning = false;

    @Override
    protected void onServiceConnected()
    {
        super.onServiceConnected();
        if (triggerReceiver != null) {
            return;
        }
        triggerReceiver = new BroadcastReceiver()
        {
            @Override
            public void onReceive(Context context, Intent intent)
            {
                if (intent == null) {
                    return;
                }
                if (!CommentAutomationBridge.ACTION_TRIGGER_AUTOMATION.equals(intent.getAction())) {
                    return;
                }
                runCommentAutomation();
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
    public void onAccessibilityEvent(AccessibilityEvent event)
    {
    }

    @Override
    public void onInterrupt()
    {
    }

    @Override
    public boolean onUnbind(Intent intent)
    {
        if (triggerReceiver != null) {
            try {
                unregisterReceiver(triggerReceiver);
            } catch (Exception ignore) {
            }
            triggerReceiver = null;
        }
        return super.onUnbind(intent);
    }

    private void runCommentAutomation()
    {
        if (actionRunning) {
            return;
        }
        final CommentAutomationBridge.PendingData pending = CommentAutomationBridge.loadPending(this);
        if (pending == null || pending.taskId <= 0) {
            toast(getString(R.string.auto_no_pending_task));
            return;
        }

        actionRunning = true;
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            actionRunning = false;
            toast(getString(R.string.auto_comment_ui_not_ready));
            return;
        }

        boolean clickedComment = clickNodeByHints(
                root,
                new String[]{"comment", "评论", "bình luận"},
                true
        );
        if (!clickedComment) {
            actionRunning = false;
            toast(getString(R.string.auto_comment_button_not_found));
            return;
        }

        handler.postDelayed(() -> stepPasteAndSend(pending), 900);
    }

    private void stepPasteAndSend(CommentAutomationBridge.PendingData pending)
    {
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
                args.putInt(AccessibilityNodeInfo.ACTION_ARGUMENT_MOVEMENT_GRANULARITY_INT, AccessibilityNodeInfo.MOVEMENT_GRANULARITY_CHARACTER);
                input.performAction(AccessibilityNodeInfo.ACTION_PASTE, args);
            }
        }

        handler.postDelayed(() -> stepSend(pending), 700);
    }

    private void stepSend(CommentAutomationBridge.PendingData pending)
    {
        AccessibilityNodeInfo root = getRootInActiveWindow();
        if (root == null) {
            finishAction(false, "send_root_missing");
            return;
        }

        boolean sent = clickNodeByHints(root, new String[]{"send", "发送", "đăng", "gửi"}, true);
        if (!sent) {
            finishAction(false, "send_not_found");
            return;
        }
        reportStatusAndClose(pending);
    }

    private void reportStatusAndClose(CommentAutomationBridge.PendingData pending)
    {
        SessionApiClient client = new SessionApiClient(this);
        client.updateTaskStatus(
                pending.adminBase,
                pending.taskId,
                "comment_prepared",
                "warmed",
                pending.text,
                new SessionApiClient.JsonCallback()
                {
                    @Override
                    public void onSuccess(JSONObject data)
                    {
                        handler.post(() -> finishAction(true, ""));
                    }

                    @Override
                    public void onUnauthorized()
                    {
                        handler.post(() -> finishAction(true, ""));
                    }

                    @Override
                    public void onError(String errorMessage)
                    {
                        handler.post(() -> finishAction(true, ""));
                    }
                }
        );
    }

    private void finishAction(boolean success, String reason)
    {
        actionRunning = false;
        if (success) {
            toast(getString(R.string.auto_comment_sent_success));
        } else if (!TextUtils.isEmpty(reason)) {
            toast(getString(R.string.auto_comment_send_failed, reason));
        }
        CommentAutomationBridge.stopFloatingBubble(this);
        CommentAutomationBridge.clearPending(this);
    }

    private void copyClipboard(String text)
    {
        ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        if (clipboard == null || TextUtils.isEmpty(text)) {
            return;
        }
        clipboard.setPrimaryClip(ClipData.newPlainText("reach_comment", text));
    }

    private boolean setNodeText(AccessibilityNodeInfo node, String text)
    {
        if (node == null || TextUtils.isEmpty(text)) {
            return false;
        }
        Bundle args = new Bundle();
        args.putCharSequence(AccessibilityNodeInfo.ACTION_ARGUMENT_SET_TEXT_CHARSEQUENCE, text);
        return node.performAction(AccessibilityNodeInfo.ACTION_SET_TEXT, args);
    }

    private AccessibilityNodeInfo findBestInput(AccessibilityNodeInfo root)
    {
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
            if (containsHint(hint, "comment", "评论", "bình luận") || containsHint(desc, "comment", "评论", "bình luận")) {
                return node;
            }
        }
        return null;
    }

    private boolean clickNodeByHints(AccessibilityNodeInfo root, String[] hints, boolean allowParent)
    {
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

    private boolean matchNode(AccessibilityNodeInfo node, String[] hints)
    {
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

    private boolean containsHint(CharSequence value, String... hints)
    {
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

    private boolean clickNode(AccessibilityNodeInfo node, boolean allowParent)
    {
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

    private void collectNodes(AccessibilityNodeInfo node, List<AccessibilityNodeInfo> out)
    {
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

    private void toast(String message)
    {
        if (TextUtils.isEmpty(message)) {
            return;
        }
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}
