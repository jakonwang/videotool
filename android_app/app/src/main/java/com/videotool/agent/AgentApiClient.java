package com.videotool.agent;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class AgentApiClient {
    public static final String AGENT_VERSION = "android-agent/1.0.0";
    private static final MediaType JSON = MediaType.parse("application/json; charset=utf-8");

    private final OkHttpClient client;

    public AgentApiClient() {
        this.client = new OkHttpClient.Builder().build();
    }

    public interface ApiCallback<T> {
        void onSuccess(T result);

        void onError(String errorMessage);
    }

    public static class PullResult {
        public final AgentTask task;
        public final String reason;
        public final String serverTime;

        public PullResult(AgentTask task, String reason, String serverTime) {
            this.task = task;
            this.reason = reason == null ? "" : reason;
            this.serverTime = serverTime == null ? "" : serverTime;
        }
    }

    public static class ReportResult {
        public final int taskStatus;
        public final String event;

        public ReportResult(int taskStatus, String event) {
            this.taskStatus = taskStatus;
            this.event = event == null ? "" : event;
        }
    }

    public void pullTask(final AgentConfig config, final ApiCallback<PullResult> callback) {
        try {
            String url = config.getAdminBase() + "/mobile_agent/pull";
            JSONObject payload = new JSONObject();
            payload.put("token", config.getToken());
            payload.put("device_code", config.getDeviceCode());
            payload.put("agent_version", AGENT_VERSION);
            JSONArray taskTypes = new JSONArray();
            for (String type : config.getTaskTypes()) {
                taskTypes.put(type);
            }
            payload.put("task_types", taskTypes);

            Request request = buildPostRequest(url, config, payload);
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    callback.onError("pull_request_failed: " + safeMessage(e));
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String body = response.body() == null ? "" : response.body().string();
                    if (!response.isSuccessful()) {
                        callback.onError("pull_http_" + response.code() + ": " + truncate(body));
                        return;
                    }
                    try {
                        JSONObject root = new JSONObject(body);
                        int code = root.optInt("code", 1);
                        if (code != 0) {
                            callback.onError("pull_failed: " + root.optString("msg", "unknown"));
                            return;
                        }
                        JSONObject data = root.optJSONObject("data");
                        if (data == null) {
                            callback.onSuccess(new PullResult(null, "", ""));
                            return;
                        }
                        JSONObject taskObj = data.optJSONObject("task");
                        AgentTask task = null;
                        if (taskObj != null) {
                            task = AgentTask.fromJson(taskObj);
                        }
                        callback.onSuccess(new PullResult(
                                task,
                                data.optString("reason", ""),
                                data.optString("server_time", "")
                        ));
                    } catch (JSONException e) {
                        callback.onError("pull_non_json: " + truncate(body));
                    }
                }
            });
        } catch (Exception e) {
            callback.onError("pull_build_error: " + safeMessage(e));
        }
    }

    public void report(
            final AgentConfig config,
            final int taskId,
            final String event,
            final String renderedText,
            final String errorCode,
            final String errorMessage,
            final String screenshotPath,
            final ApiCallback<ReportResult> callback
    ) {
        try {
            String url = config.getAdminBase() + "/mobile_agent/report";
            JSONObject payload = new JSONObject();
            payload.put("token", config.getToken());
            payload.put("device_code", config.getDeviceCode());
            payload.put("task_id", taskId);
            payload.put("event", event == null ? "" : event);
            payload.put("rendered_text", renderedText == null ? "" : renderedText);
            payload.put("error_code", errorCode == null ? "" : errorCode);
            payload.put("error_message", errorMessage == null ? "" : errorMessage);
            payload.put("screenshot_path", screenshotPath == null ? "" : screenshotPath);
            payload.put("duration_ms", 0);
            payload.put("agent_version", AGENT_VERSION);

            Request request = buildPostRequest(url, config, payload);
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    callback.onError("report_request_failed: " + safeMessage(e));
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String body = response.body() == null ? "" : response.body().string();
                    if (!response.isSuccessful()) {
                        callback.onError("report_http_" + response.code() + ": " + truncate(body));
                        return;
                    }
                    try {
                        JSONObject root = new JSONObject(body);
                        int code = root.optInt("code", 1);
                        if (code != 0) {
                            callback.onError("report_failed: " + root.optString("msg", "unknown"));
                            return;
                        }
                        JSONObject data = root.optJSONObject("data");
                        if (data == null) {
                            callback.onSuccess(new ReportResult(0, ""));
                            return;
                        }
                        callback.onSuccess(new ReportResult(
                                data.optInt("task_status", 0),
                                data.optString("event", "")
                        ));
                    } catch (JSONException e) {
                        callback.onError("report_non_json: " + truncate(body));
                    }
                }
            });
        } catch (Exception e) {
            callback.onError("report_build_error: " + safeMessage(e));
        }
    }

    private Request buildPostRequest(String url, AgentConfig config, JSONObject payload) {
        RequestBody body = RequestBody.create(payload.toString(), JSON);
        return new Request.Builder()
                .url(url)
                .header("Accept", "application/json")
                .header("Content-Type", "application/json; charset=utf-8")
                .header("X-Mobile-Agent-Token", config.getToken())
                .header("X-Device-Code", config.getDeviceCode())
                .header("User-Agent", AGENT_VERSION)
                .post(body)
                .build();
    }

    private static String safeMessage(Throwable e) {
        if (e == null || e.getMessage() == null || e.getMessage().trim().isEmpty()) {
            return "unknown_error";
        }
        return e.getMessage().trim();
    }

    private static String truncate(String text) {
        if (text == null) {
            return "";
        }
        String value = text.trim();
        if (value.length() <= 280) {
            return value;
        }
        return value.substring(0, 280);
    }
}
