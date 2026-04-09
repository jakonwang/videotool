package com.videotool.agent;

import org.json.JSONException;
import org.json.JSONObject;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class AgentTask {
    private final int id;
    private final String taskType;
    private final String targetChannel;
    private final int priority;
    private final String renderedText;
    private final String commentText;
    private final String tiktokId;
    private final String nickname;
    private final String waNumber;
    private final String zaloId;
    private final String waUrl;
    private final String zaloUrl;
    private final JSONObject rawPayload;

    public AgentTask(
            int id,
            String taskType,
            String targetChannel,
            int priority,
            String renderedText,
            String commentText,
            String tiktokId,
            String nickname,
            String waNumber,
            String zaloId,
            String waUrl,
            String zaloUrl,
            JSONObject rawPayload
    ) {
        this.id = id;
        this.taskType = safe(taskType);
        this.targetChannel = safe(targetChannel);
        this.priority = priority;
        this.renderedText = safe(renderedText);
        this.commentText = safe(commentText);
        this.tiktokId = safe(tiktokId);
        this.nickname = safe(nickname);
        this.waNumber = safe(waNumber);
        this.zaloId = safe(zaloId);
        this.waUrl = safe(waUrl);
        this.zaloUrl = safe(zaloUrl);
        this.rawPayload = rawPayload == null ? new JSONObject() : rawPayload;
    }

    public static AgentTask fromJson(JSONObject taskObj) throws JSONException {
        if (taskObj == null) {
            throw new JSONException("task is null");
        }
        JSONObject payload = taskObj.optJSONObject("payload");
        if (payload == null) {
            payload = new JSONObject();
        }
        JSONObject influencer = taskObj.optJSONObject("influencer");
        if (influencer == null) {
            influencer = new JSONObject();
        }
        JSONObject channels = payload.optJSONObject("channels");
        if (channels == null) {
            channels = new JSONObject();
        }

        String tiktokId = influencer.optString("tiktok_id", "");
        if (tiktokId.isEmpty()) {
            tiktokId = payload.optString("tiktok_id", "");
        }

        String waUrl = taskObj.optString("wa_url", "");
        if (waUrl.isEmpty()) {
            waUrl = payload.optString("wa_url", channels.optString("wa_me", ""));
        }
        String zaloUrl = taskObj.optString("zalo_url", "");
        if (zaloUrl.isEmpty()) {
            zaloUrl = payload.optString("zalo_url", channels.optString("zalo_open", ""));
        }

        return new AgentTask(
                taskObj.optInt("id", 0),
                taskObj.optString("task_type", payload.optString("task_type", "")),
                taskObj.optString("target_channel", payload.optString("target_channel", "auto")),
                taskObj.optInt("priority", 0),
                taskObj.optString("rendered_text", ""),
                payload.optString("comment_text", ""),
                tiktokId,
                influencer.optString("nickname", payload.optString("nickname", "")),
                channels.optString("whatsapp", ""),
                channels.optString("zalo", ""),
                waUrl,
                zaloUrl,
                payload
        );
    }

    public JSONObject toJson() {
        JSONObject obj = new JSONObject();
        try {
            obj.put("id", id);
            obj.put("task_type", taskType);
            obj.put("target_channel", targetChannel);
            obj.put("priority", priority);
            obj.put("rendered_text", renderedText);
            obj.put("comment_text", commentText);
            obj.put("tiktok_id", tiktokId);
            obj.put("nickname", nickname);
            obj.put("wa_number", waNumber);
            obj.put("zalo_id", zaloId);
            obj.put("wa_url", waUrl);
            obj.put("zalo_url", zaloUrl);
            obj.put("payload", rawPayload);
        } catch (JSONException ignore) {
        }
        return obj;
    }

    public int getId() {
        return id;
    }

    public String getTaskType() {
        return taskType;
    }

    public String getTargetChannel() {
        return targetChannel;
    }

    public int getPriority() {
        return priority;
    }

    public String getRenderedText() {
        return renderedText;
    }

    public String getCommentText() {
        return commentText;
    }

    public String getTiktokId() {
        return tiktokId;
    }

    public String getNickname() {
        return nickname;
    }

    public String getWaNumber() {
        return waNumber;
    }

    public String getZaloId() {
        return zaloId;
    }

    public String getWaUrl() {
        return waUrl;
    }

    public String getZaloUrl() {
        return zaloUrl;
    }

    public JSONObject getRawPayload() {
        return rawPayload;
    }

    public String getDisplayName() {
        String nick = nickname.trim();
        if (!nick.isEmpty()) {
            return nick;
        }
        return tiktokId.trim();
    }

    public boolean isCommentTask() {
        return "comment_warmup".equalsIgnoreCase(taskType);
    }

    public boolean isAutoDmTask() {
        String value = taskType == null ? "" : taskType.trim().toLowerCase();
        return "zalo_auto_dm".equals(value) || "wa_auto_dm".equals(value);
    }

    public String getPreparedEvent() {
        if (isAutoDmTask()) {
            return "sending";
        }
        if (isCommentTask()) {
            return "comment_prepared";
        }
        if ("tiktok_dm".equalsIgnoreCase(taskType)) {
            return "dm_prepared";
        }
        return "im_prepared";
    }

    public String getDoneEvent() {
        if (isAutoDmTask()) {
            return "sent";
        }
        if (isCommentTask()) {
            return "comment_sent";
        }
        return "done";
    }

    public String getAutoSendingEvent() {
        return "sending";
    }

    public String getAutoDoneEvent() {
        return "sent";
    }

    public String getBestText() {
        if (!renderedText.trim().isEmpty()) {
            return renderedText.trim();
        }
        return commentText.trim();
    }

    public String resolveTiktokUrl() {
        String handle = safe(tiktokId).trim();
        if (handle.isEmpty()) {
            return "";
        }
        if (handle.startsWith("@")) {
            handle = handle.substring(1);
        }
        if (handle.isEmpty()) {
            return "";
        }
        return "https://www.tiktok.com/@" + handle;
    }

    public String resolveZaloUrl() {
        if (!zaloUrl.trim().isEmpty()) {
            return zaloUrl.trim();
        }
        if (!zaloId.trim().isEmpty()) {
            return "https://zalo.me/" + zaloId.trim();
        }
        return "";
    }

    public String resolveWaUrl(String text) {
        if (!waUrl.trim().isEmpty()) {
            if (!text.trim().isEmpty() && !waUrl.contains("?text=")) {
                return waUrl + (waUrl.contains("?") ? "&" : "?") + "text=" + encode(text.trim());
            }
            return waUrl.trim();
        }
        String digits = waNumber.replaceAll("[^0-9]", "");
        if (digits.isEmpty()) {
            return "";
        }
        if (text == null || text.trim().isEmpty()) {
            return "https://wa.me/" + digits;
        }
        return "https://wa.me/" + digits + "?text=" + encode(text.trim());
    }

    public String resolveTargetUrl(String text) {
        String channel = targetChannel.trim().toLowerCase();
        if ("wa".equals(channel) || "whatsapp".equals(channel)) {
            String wa = resolveWaUrl(text);
            if (!wa.isEmpty()) {
                return wa;
            }
        }
        if ("zalo".equals(channel)) {
            String zalo = resolveZaloUrl();
            if (!zalo.isEmpty()) {
                return zalo;
            }
        }
        String tiktok = resolveTiktokUrl();
        if (!tiktok.isEmpty()) {
            return tiktok;
        }
        if ("zalo".equals(channel)) {
            return resolveZaloUrl();
        }
        if ("wa".equals(channel) || "whatsapp".equals(channel)) {
            return resolveWaUrl(text);
        }
        return "";
    }

    public String getTargetPackageName() {
        String channel = targetChannel.trim().toLowerCase();
        if ("wa".equals(channel) || "whatsapp".equals(channel)) {
            return "com.whatsapp";
        }
        if ("zalo".equals(channel)) {
            return "com.zing.zalo";
        }
        return "com.zhiliaoapp.musically";
    }

    private static String safe(String value) {
        return value == null ? "" : value;
    }

    private static String encode(String value) {
        try {
            return URLEncoder.encode(value, StandardCharsets.UTF_8.name());
        } catch (Exception e) {
            return "";
        }
    }
}
